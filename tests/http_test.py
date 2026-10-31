#!/usr/bin/env python3
"""Actual HTTP/security regressions using only synthetic isolated data.

Run via tests/environment.py test so the background worker is paused. No old SQL
exports, identities or uploaded documents are read. Test credentials never log.
"""
import base64
import concurrent.futures
import datetime
import html.parser
import http.cookiejar
import json
import os
import re
import secrets
import struct
import subprocess
import time
import unittest
import urllib.error
import urllib.parse
import urllib.request
import zlib

BASE = 'http://127.0.0.1:8081'
CONTAINER = 'gunindex-rebuild-app'
DOCKER = ['docker', '--host=unix:///var/run/docker.sock']


def fixture(operation, **fields):
    completed = subprocess.run(DOCKER + ['exec', '-i', CONTAINER, 'php', '/app/tests/fixture.php'],
                               input=json.dumps(dict(operation=operation, **fields)).encode(),
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
    if completed.returncode:
        raise RuntimeError('Synthetic CLI fixture failed; submitted values withheld.')
    try:
        return json.loads(completed.stdout)
    except (ValueError, UnicodeDecodeError):
        raise RuntimeError('Fixture did not return valid JSON.') from None


def identity():
    return 'test_' + secrets.token_hex(8)


def title(extra=''):
    return 'Fixture ' + secrets.token_hex(8) + (' ' + extra if extra else '')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, url):
        return None


class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}),
                                                urllib.request.HTTPCookieProcessor(self.cookies), NoRedirect())

    def request(self, path, fields=None, *, raw=None, content_type=None, method=None, binary=False):
        if fields is not None:
            raw = urllib.parse.urlencode(fields).encode()
            content_type = 'application/x-www-form-urlencoded'
        headers = {'User-Agent': 'GunIndex-Isolated-Regression'}
        if content_type:
            headers['Content-Type'] = content_type
        request = urllib.request.Request(BASE + path, data=raw, headers=headers, method=method)
        try:
            response = self.opener.open(request, timeout=20)
        except urllib.error.HTTPError as response:
            data = response.read()
            return response.code, response.headers, data if binary else data.decode('utf-8', 'replace')
        with response:
            data = response.read()
            return response.status, response.headers, data if binary else data.decode('utf-8', 'replace')

    def token(self, path='/user/profile.php'):
        status, _, body = self.request(path)
        if status != 200:
            raise RuntimeError('CSRF form unavailable: HTTP ' + str(status))
        match = re.search(r'name="csrf_token"\s+value="([a-f0-9]{64})"', body)
        if not match:
            raise RuntimeError('Expected CSRF field missing.')
        return match.group(1)


class SettingsForm(html.parser.HTMLParser):
    """Read the actual settings form rather than mirroring its definitions."""
    def __init__(self, body):
        super().__init__(convert_charrefs=True)
        self.active = False
        self.values = {}
        self.select = None
        self.textarea = None
        self.feed(body)

    def handle_starttag(self, tag, attributes):
        attrs = dict(attributes)
        if tag == 'form':
            self.active = attrs.get('action') == '/admin/settings.php'
        if not self.active:
            return
        if tag == 'input' and 'name' in attrs:
            if attrs.get('type') != 'checkbox' or 'checked' in attrs:
                self.values[attrs['name']] = attrs.get('value', '')
        elif tag == 'textarea':
            self.textarea = attrs.get('name')
            self.values[self.textarea] = ''
        elif tag == 'select':
            self.select = attrs.get('name')
        elif tag == 'option' and self.select:
            if self.select not in self.values or 'selected' in attrs:
                self.values[self.select] = attrs.get('value', '')

    def handle_endtag(self, tag):
        if tag == 'form':
            self.active = False
        elif tag == 'select':
            self.select = None
        elif tag == 'textarea':
            self.textarea = None

    def handle_data(self, data):
        if self.active and self.textarea:
            self.values[self.textarea] += data


def png(width=80, height=60):
    def chunk(kind, data):
        return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data) & 0xffffffff)
    scanlines = (b'\0' + b'\x36\x99\x80' * width) * height
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(scanlines)) + chunk(b'IEND', b'')


def multipart(fields, files):
    boundary = 'GunIndexBoundary' + secrets.token_hex(16)
    pieces = []
    for key, value in fields.items():
        pieces.append(('--' + boundary + '\r\nContent-Disposition: form-data; name="' + key + '"\r\n\r\n' + str(value) + '\r\n').encode())
    for name, mime, data in files:
        pieces.append(('--' + boundary + '\r\nContent-Disposition: form-data; name="images[]"; filename="' + name + '"\r\nContent-Type: ' + mime + '\r\n\r\n').encode() + data + b'\r\n')
    pieces.append(('--' + boundary + '--\r\n').encode())
    return b''.join(pieces), 'multipart/form-data; boundary=' + boundary


class MarketplaceHTTP(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        fixture('reset_limits')
        cls.category_id = int(fixture('categories')['categories'][0]['id'])

    def user(self, *, admin=False, mfa=True, login=True, password=None):
        name = identity()
        secret = password or ('Synthetic ' + secrets.token_hex(20))
        row = fixture('seed', username=name, password=secret, admin=admin, mfa=mfa)
        user = dict(username=name, password=secret, id=int(row['id']), browser=Browser())
        if login:
            status, headers, _ = self.login(user)
            self.assertEqual(303, status)
            if admin:
                self.assertEqual('/auth/mfa.php', headers['Location'])
                self.mfa_login(user)
            else:
                self.assertEqual('/user/dashboard.php', headers['Location'])
        return user

    def login(self, user, browser=None):
        browser = browser or user['browser']
        token = browser.token('/auth/login.php')
        return browser.request('/auth/login.php', {'csrf_token': token, 'login': user['username'], 'password': user['password']})

    def mfa_login(self, user):
        code = fixture('code', username=user['username'])['code']
        browser = user['browser']
        result = browser.request('/auth/mfa.php', {'csrf_token': browser.token('/auth/mfa.php'), 'code': code})
        self.assertEqual(303, result[0])
        self.assertEqual('/admin/index.php', result[1]['Location'])
        return code

    def fields(self, user, **changes):
        values = dict(csrf_token=user['browser'].token('/user/create-listing.php'), title=title(),
                      description='An ordinary synthetic household product for isolated regression checks.',
                      category_id=str(self.category_id), price='129.99', buyout_price='', type='fixed',
                      status='active', duration_days='3', zip='10115', city='Teststadt', condition_label='used',
                      pickup='1', shipping='1', accept_rules='1')
        values.update(changes)
        return values

    def seed_listing(self, user, **changes):
        data = dict(username=user['username'], title=title(), category_id=self.category_id)
        data.update(changes)
        return fixture('seed_listing', **data)['id']

    def bid(self, user, listing, maximum):
        browser = user['browser']
        return browser.request('/actions/place-bid.php', {'csrf_token': browser.token(), 'listing_id': str(listing), 'max_bid': maximum})

    def worker(self, expected=0):
        result = subprocess.run(DOCKER + ['exec', CONTAINER, 'php', '/app/ops/auction-worker.php', '--once'],
                                stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
        self.assertEqual(expected, result.returncode, 'Independent auction worker returned an unexpected status.')

    def settings(self, user):
        response = user['browser'].request('/admin/settings.php')
        self.assertEqual(200, response[0])
        values = SettingsForm(response[2]).values
        self.assertIn('_revision', values)
        self.assertIn('brand_name', values)
        return values

    def restore_settings(self, user, original):
        latest = self.settings(user)
        restored = dict(original, csrf_token=latest['csrf_token'], _revision=latest['_revision'])
        self.assertEqual(200, user['browser'].request('/admin/settings.php', restored)[0])

    def category(self, admin, parent=0, **changes):
        slug = 'test-' + secrets.token_hex(8)
        values = dict(csrf_token=admin['browser'].token('/admin/categories.php'), id='0', name='Testcategory ' + secrets.token_hex(8), slug=slug, parent_id=str(parent), sort_order='0', is_active='1')
        values.update(changes)
        response = admin['browser'].request('/admin/categories.php', values)
        self.assertEqual(303, response[0])
        return fixture('category', slug=slug)

    def test_category_seed_preserves_utf8_umlauts(self):
        names={row['slug']:row['name'] for row in fixture('categories')['categories']}
        self.assertEqual('Bücher & Medien',names['buecher-medien'])
        self.assertEqual('Sammlerzubehör',names['sammlerzubehoer'])

    def test_public_home_is_general_and_resources_are_local(self):
        status, _, body = Browser().request('/')
        self.assertEqual(200, status)
        self.assertNotIn('cdn.tailwindcss.com', body)
        self.assertNotIn('fonts.googleapis.com', body)
        self.assertNotIn('EWB-pflichtig', body)
        self.assertNotIn('GunDirect.de', body)
        for path in ('/assets/css/app.css', '/assets/js/app.js'):
            self.assertEqual(200, Browser().request(path)[0])

    def test_private_paths_and_http_fixture_are_not_public(self):
        for path in ('/config/db.php', '/.env', '/.git/HEAD', '/sql/schema.sql', '/datenbank/thegunsite_db.sql',
                     '/tests/fixture.php', '/tests/environment.py', '/assets/uploads/verification/2_front.jpg',
                     '/assets/uploads/listings/2_1.jpg', '/config/%2e%2e/datenbank/thegunsite_db.sql'):
            with self.subTest(path=path):
                self.assertEqual(404, Browser().request(path)[0])

    def test_anonymous_private_routes_redirect_and_member_is_not_admin(self):
        for path in ('/user/profile.php', '/user/dashboard.php', '/user/create-listing.php', '/user/payments.php', '/admin/settings.php'):
            self.assertEqual(303, Browser().request(path)[0])
        member = self.user()
        for path in ('/admin/index.php', '/admin/settings.php', '/admin/categories.php'):
            self.assertEqual(403, member['browser'].request(path)[0])

    def test_registration_requires_csrf_and_rejects_array_values(self):
        browser = Browser()
        self.assertEqual(403, browser.request('/auth/register.php', {'username': identity()})[0])
        fields = dict(csrf_token=browser.token('/auth/register.php'), username='bad', email='bad@example.invalid', zip='10115', city='Teststadt', password='Synthetic long password', password_confirm='Synthetic long password')
        fields.pop('username')
        fields['username[]'] = 'array'
        self.assertEqual(422, browser.request('/auth/register.php', fields)[0])

    def test_registration_and_special_password_login_roundtrip(self):
        browser = Browser(); name = identity(); secret = '  <>&" Special ' + secrets.token_hex(12) + '  '
        values = dict(csrf_token=browser.token('/auth/register.php'), username=name, email=name + '@example.invalid', zip='10115', city='Teststadt', password=secret, password_confirm=secret)
        status, headers, _ = browser.request('/auth/register.php', values)
        self.assertEqual(303, status); self.assertEqual('/user/dashboard.php', headers['Location'])
        self.assertIn(name, browser.request('/user/dashboard.php')[2])
        self.assertEqual(303, browser.request('/auth/logout.php', {'csrf_token': browser.token()})[0])
        user = dict(username=name, password=secret, browser=browser)
        self.assertEqual(303, self.login(user)[0])
        user['password'] = secret.strip()
        other = Browser()
        self.assertEqual(422, self.login(user, other)[0])

    def test_login_csrf_and_uniform_failure(self):
        known = self.user(login=False)
        browser = Browser()
        self.assertEqual(403, browser.request('/auth/login.php', {'login': known['username'], 'password': known['password']})[0])
        token = browser.token('/auth/login.php')
        first = browser.request('/auth/login.php', {'csrf_token': token, 'login': known['username'], 'password': 'Synthetic wrong password'})
        second = browser.request('/auth/login.php', {'csrf_token': token, 'login': identity(), 'password': 'Synthetic wrong password'})
        self.assertEqual(422, first[0]); self.assertEqual(422, second[0])
        message = 'Anmeldung fehlgeschlagen. Bitte prüfe deine Zugangsdaten.'
        self.assertIn(message, first[2]); self.assertIn(message, second[2])

    def test_session_rotation_cookie_flags_and_logout_protection(self):
        user = self.user(login=False); browser = user['browser']
        browser.token('/auth/login.php')
        before = next(cookie.value for cookie in browser.cookies if cookie.name == 'gunindex_session')
        self.assertEqual(303, self.login(user)[0])
        after = next(cookie.value for cookie in browser.cookies if cookie.name == 'gunindex_session')
        self.assertNotEqual(before, after)
        cookie = next(cookie for cookie in browser.cookies if cookie.name == 'gunindex_session')
        self.assertTrue(cookie.has_nonstandard_attr('HttpOnly'))
        self.assertEqual('Lax', cookie.get_nonstandard_attr('SameSite'))
        self.assertEqual(200, browser.request('/auth/logout.php')[0])
        self.assertEqual(403, browser.request('/auth/logout.php', {})[0])
        self.assertEqual(200, browser.request('/user/profile.php')[0])
        self.assertEqual(303, browser.request('/auth/logout.php', {'csrf_token': browser.token()})[0])
        self.assertEqual(303, browser.request('/user/profile.php')[0])

    def test_password_change_rejects_wrong_current_password_atomically(self):
        user = self.user(); browser = user['browser']
        values = dict(csrf_token=browser.token(), action='save', email=user['username'] + '@example.invalid', full_name='Synthetic test fixture', zip='10115', city='Changed city', current_password='Wrong synthetic password', new_password='New synthetic password 123', password_confirm='New synthetic password 123')
        self.assertEqual(422, browser.request('/user/profile.php', values)[0])
        self.assertTrue(fixture('account', username=user['username'], password=user['password'])['password_matches'])

    def test_password_change_updates_hash_and_revokes_other_session(self):
        user = self.user(); other = Browser()
        self.assertEqual(303, self.login(user, other)[0])
        secret = 'New <>& special ' + secrets.token_hex(12)
        values = dict(csrf_token=user['browser'].token(), action='save', email=user['username'] + '@example.invalid', full_name='Synthetic test fixture', zip='10115', city='Teststadt', current_password=user['password'], new_password=secret, password_confirm=secret)
        self.assertEqual(200, user['browser'].request('/user/profile.php', values)[0])
        self.assertFalse(fixture('account', username=user['username'], password=user['password'])['password_matches'])
        self.assertTrue(fixture('account', username=user['username'], password=secret)['password_matches'])
        self.assertEqual(303, other.request('/user/profile.php')[0])
        self.assertEqual(200, user['browser'].request('/user/profile.php')[0])

    def test_admin_requires_mfa_and_rejects_bad_code(self):
        admin = self.user(admin=True, login=False); browser = admin['browser']
        self.assertEqual(303, self.login(admin)[0])
        self.assertEqual(303, browser.request('/admin/settings.php')[0])
        self.assertEqual(403, browser.request('/auth/mfa.php', {'code': 'not-a-code'})[0])
        self.assertEqual(422, browser.request('/auth/mfa.php', {'csrf_token': browser.token('/auth/mfa.php'), 'code': 'abcdef'})[0])
        self.mfa_login(admin)
        self.assertEqual(200, browser.request('/admin/settings.php')[0])

    def test_admin_without_enrolled_mfa_cannot_login(self):
        admin = self.user(admin=True, mfa=False, login=False)
        self.assertEqual(503, self.login(admin)[0])
        self.assertEqual(303, admin['browser'].request('/admin/index.php')[0])

    def test_totp_success_cannot_be_replayed_in_another_session(self):
        admin = self.user(admin=True, login=False)
        self.assertEqual(303, self.login(admin)[0])
        code = self.mfa_login(admin)
        second = Browser()
        self.assertEqual(303, self.login(admin, second)[0])
        self.assertEqual(422, second.request('/auth/mfa.php', {'csrf_token': second.token('/auth/mfa.php'), 'code': code})[0])
        self.assertEqual(303, second.request('/admin/settings.php')[0])

    def test_settings_require_csrf_and_public_brand_changes(self):
        admin = self.user(admin=True); original = self.settings(admin)
        changed = dict(original, brand_name='TestMarket ' + secrets.token_hex(6), accent='#123abc', secondary='#de9012', animation_mode='reduced')
        no_token = dict(changed); no_token.pop('csrf_token')
        self.assertEqual(403, admin['browser'].request('/admin/settings.php', no_token)[0])
        try:
            self.assertEqual(200, admin['browser'].request('/admin/settings.php', changed)[0])
            body = Browser().request('/')[2]
            self.assertIn(changed['brand_name'], body); self.assertIn('--accent:#123abc', body)
            self.assertIn('data-motion="reduced"', body)
        finally:
            self.restore_settings(admin, original)

    def test_settings_invalid_css_cannot_be_saved(self):
        admin = self.user(admin=True); original = self.settings(admin)
        changed = dict(original, accent='red;}</style><script>alert(1)</script>')
        self.assertEqual(422, admin['browser'].request('/admin/settings.php', changed)[0])
        current = self.settings(admin)
        self.assertEqual(original['accent'], current['accent'])
        self.assertEqual(original['_revision'], current['_revision'])

    def test_stale_settings_revision_preserves_newer_changes(self):
        admin = self.user(admin=True); original = self.settings(admin)
        first = dict(original, brand_name='Fresh market ' + secrets.token_hex(5))
        stale = dict(original, secondary='#aa2211')
        try:
            self.assertEqual(200, admin['browser'].request('/admin/settings.php', first)[0])
            self.assertEqual(409, admin['browser'].request('/admin/settings.php', stale)[0])
            current = self.settings(admin)
            self.assertEqual(first['brand_name'], current['brand_name'])
            self.assertEqual(original['secondary'], current['secondary'])
        finally:
            self.restore_settings(admin, original)

    def test_category_creation_cycle_and_regulated_category_rejection(self):
        admin = self.user(admin=True)
        parent = self.category(admin); child = self.category(admin, int(parent['id']))
        fields = dict(csrf_token=admin['browser'].token('/admin/categories.php'), id=str(parent['id']), name=parent['name'], slug=parent['slug'], parent_id=str(child['id']), sort_order='0', is_active='1')
        self.assertEqual(422, admin['browser'].request('/admin/categories.php', fields)[0])
        self.assertIsNone(fixture('category', slug=parent['slug'])['parent_id'])
        fields.update(id='0', name='Waffen', slug='test-' + secrets.token_hex(8), parent_id='0')
        self.assertEqual(422, admin['browser'].request('/admin/categories.php', fields)[0])

    def test_category_parent_deactivation_hides_child_and_blocks_bid(self):
        admin = self.user(admin=True); seller = self.user(); buyer = self.user()
        parent = self.category(admin); child = self.category(admin, int(parent['id']))
        listing = self.seed_listing(seller, category_id=int(child['id']))
        fields = dict(csrf_token=admin['browser'].token('/admin/categories.php'), id=str(parent['id']), name=parent['name'], slug=parent['slug'], parent_id='0', sort_order='0', is_active='0')
        self.assertEqual(303, admin['browser'].request('/admin/categories.php', fields)[0])
        self.assertFalse(fixture('category', slug=child['slug'])['available'])
        self.assertEqual(404, Browser().request('/marketplace/view-auction.php?id=' + str(listing))[0])
        self.assertEqual(403, self.bid(buyer, listing, '20.00')[0])
        # Keep this synthetic inactive auction from contaminating later worker tests.
        fields['is_active'] = '1'
        self.assertEqual(303, admin['browser'].request('/admin/categories.php', fields)[0])

    def test_fixed_listing_create_persists_exact_money_and_search(self):
        seller = self.user(); values = self.fields(seller)
        self.assertEqual(303, seller['browser'].request('/user/create-listing.php', values)[0])
        listing = int(fixture('find_listing', username=seller['username'], title=values['title'])['id'])
        state = fixture('state', listing_id=listing)
        self.assertEqual(12999, state['price_cents']); self.assertEqual('active', state['status'])
        self.assertIn(values['title'], Browser().request('/marketplace/search.php?q=' + urllib.parse.quote(values['title']))[2])
        self.assertEqual(200, Browser().request('/marketplace/view-item.php?id=' + str(listing))[0])

    def test_listing_requires_csrf_rules_and_scalar_price_without_data_loss(self):
        seller = self.user(); values = self.fields(seller)
        missing = dict(values); missing.pop('csrf_token')
        self.assertEqual(403, seller['browser'].request('/user/create-listing.php', missing)[0])
        self.assertEqual(422, seller['browser'].request('/user/create-listing.php', dict(values, accept_rules='0'))[0])
        invalid = dict(values, price='12.345')
        response = seller['browser'].request('/user/create-listing.php', invalid)
        self.assertEqual(422, response[0]); self.assertIn(values['title'], response[2])
        self.assertIsNone(fixture('find_listing', username=seller['username'], title=values['title'])['id'])

    def test_draft_is_private_and_other_seller_cannot_edit(self):
        seller = self.user(); other = self.user(); values = self.fields(seller, status='draft')
        self.assertEqual(303, seller['browser'].request('/user/create-listing.php', values)[0])
        listing = int(fixture('find_listing', username=seller['username'], title=values['title'])['id'])
        self.assertEqual('draft', fixture('state', listing_id=listing)['status'])
        self.assertEqual(404, Browser().request('/marketplace/view-item.php?id=' + str(listing))[0])
        self.assertEqual(404, other['browser'].request('/user/edit-listing.php?id=' + str(listing))[0])
        self.assertEqual(200, seller['browser'].request('/user/edit-listing.php?id=' + str(listing))[0])

    def test_regulated_listing_text_is_rejected_without_creating_record(self):
        seller = self.user(); values = self.fields(seller, description='Diese synthetische Anzeige bietet Munition und Waffen an.')
        self.assertEqual(422, seller['browser'].request('/user/create-listing.php', values)[0])
        self.assertIsNone(fixture('find_listing', username=seller['username'], title=values['title'])['id'])

    def test_actual_image_upload_reencodes_and_head_has_no_body(self):
        seller = self.user(); values = self.fields(seller)
        raw, mime = multipart(values, [('ordinary.png', 'image/png', png())])
        self.assertEqual(303, seller['browser'].request('/user/create-listing.php', raw=raw, content_type=mime)[0])
        listing = int(fixture('find_listing', username=seller['username'], title=values['title'])['id'])
        self.assertEqual(1, fixture('state', listing_id=listing)['images'])
        url = '/marketplace/image.php?id=' + str(listing) + '&size=large'
        status, headers, body = Browser().request(url, binary=True)
        self.assertEqual(200, status); self.assertEqual('image/webp', headers.get_content_type())
        self.assertEqual(b'RIFF', body[:4]); self.assertEqual(b'WEBP', body[8:12])
        self.assertEqual(b'', Browser().request(url, method='HEAD', binary=True)[2])

    def test_disguised_script_upload_rejected_and_listing_not_saved(self):
        seller = self.user(); values = self.fields(seller)
        raw, mime = multipart(values, [('photo.jpg', 'image/jpeg', b'<?php echo "synthetic"; ?>')])
        self.assertEqual(422, seller['browser'].request('/user/create-listing.php', raw=raw, content_type=mime)[0])
        self.assertIsNone(fixture('find_listing', username=seller['username'], title=values['title'])['id'])

    def test_image_count_and_resolution_limits_are_enforced(self):
        seller = self.user()
        for files in ([('ordinary.png', 'image/png', png())] * 6, [('wide.png', 'image/png', png(8001, 1))]):
            values = self.fields(seller); raw, mime = multipart(values, files)
            self.assertEqual(422, seller['browser'].request('/user/create-listing.php', raw=raw, content_type=mime)[0])
            self.assertIsNone(fixture('find_listing', username=seller['username'], title=values['title'])['id'])

    def test_auction_creation_sets_start_buyout_and_duration(self):
        seller = self.user(); values = self.fields(seller, type='auction', price='10.00', buyout_price='30.00', duration_days='1')
        started = time.time()
        self.assertEqual(303, seller['browser'].request('/user/create-listing.php', values)[0])
        listing = int(fixture('find_listing', username=seller['username'], title=values['title'])['id'])
        state = fixture('state', listing_id=listing)
        end = datetime.datetime.strptime(state['ends_at'], '%Y-%m-%d %H:%M:%S').replace(tzinfo=datetime.timezone.utc).timestamp()
        self.assertEqual('auction', state['type']); self.assertEqual(1000, state['price_cents'])
        self.assertGreaterEqual(end, started + 86400 - 2)

    def test_bid_rejects_get_csrf_self_and_late_bid(self):
        seller = self.user(); buyer = self.user(); listing = self.seed_listing(seller)
        self.assertEqual(405, buyer['browser'].request('/actions/place-bid.php')[0])
        self.assertEqual(403, buyer['browser'].request('/actions/place-bid.php', {'listing_id': listing, 'max_bid': '20.00'})[0])
        self.assertEqual(403, self.bid(seller, listing, '20.00')[0])
        fixture('expire', listing_id=listing)
        self.assertEqual(409, self.bid(buyer, listing, '20.00')[0])
        self.assertEqual(0, fixture('state', listing_id=listing)['events'])
        self.worker()

    def test_proxy_bid_charges_only_competitive_price_and_hides_cap(self):
        seller = self.user(); first = self.user(); second = self.user(); listing = self.seed_listing(seller)
        self.assertEqual(303, self.bid(first, listing, '190.37')[0])
        self.assertEqual(1000, fixture('state', listing_id=listing)['current_bid_cents'])
        self.assertEqual(303, self.bid(second, listing, '30.00')[0])
        state = fixture('state', listing_id=listing)
        self.assertEqual(first['id'], state['highest_bidder_id']); self.assertEqual(3100, state['current_bid_cents'])
        body = Browser().request('/marketplace/view-auction.php?id=' + str(listing))[2]
        self.assertNotIn('190.37', body); self.assertNotIn('190,37', body)
        self.assertEqual(409, seller['browser'].request('/user/edit-listing.php?id=' + str(listing))[0])

    def test_equal_maxima_use_first_accepted_bid_and_extend_last_minutes(self):
        seller = self.user(); first = self.user(); second = self.user(); listing = self.seed_listing(seller, ends_in=30)
        started = time.time()
        self.assertEqual(303, self.bid(first, listing, '50.00')[0])
        self.assertEqual(303, self.bid(second, listing, '50.00')[0])
        state = fixture('state', listing_id=listing)
        end = datetime.datetime.strptime(state['ends_at'], '%Y-%m-%d %H:%M:%S').replace(tzinfo=datetime.timezone.utc).timestamp()
        self.assertEqual(first['id'], state['highest_bidder_id']); self.assertEqual(5000, state['current_bid_cents'])
        self.assertGreaterEqual(end, started + 118)

    def test_parallel_bid_processes_preserve_both_events_and_one_leader(self):
        seller = self.user(login=False); low = self.user(login=False); high = self.user(login=False)
        listing = self.seed_listing(seller); barrier = int(time.time() * 1000) + 1800
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            futures = [pool.submit(fixture, 'bid', listing_id=listing, username=user['username'], maximum=amount, start_at_ms=barrier)
                       for user, amount in ((low, 2000), (high, 3000))]
            results = [future.result() for future in futures]
        self.assertTrue(all(result['ok'] for result in results))
        state = fixture('state', listing_id=listing)
        self.assertEqual(high['id'], state['highest_bidder_id']); self.assertEqual(2100, state['current_bid_cents'])
        self.assertEqual(2, state['events'])

    def test_worker_closes_without_web_visit_and_is_idempotent(self):
        seller = self.user(login=False); buyer = self.user(login=False); listing = self.seed_listing(seller)
        self.assertTrue(fixture('bid', listing_id=listing, username=buyer['username'], maximum=2000)['ok'])
        fixture('expire', listing_id=listing); self.worker()
        first = fixture('state', listing_id=listing)
        self.assertEqual('ended', first['status']); self.assertEqual(buyer['id'], first['highest_bidder_id'])
        self.worker(); self.assertEqual(first, fixture('state', listing_id=listing))

    def test_worker_quarantines_one_conflict_and_finishes_other_due_auctions(self):
        seller = self.user(login=False); buyer = self.user(login=False)
        broken = self.seed_listing(seller); normal = self.seed_listing(seller)
        self.assertTrue(fixture('bid', listing_id=broken, username=buyer['username'], maximum=2000)['ok'])
        fixture('inconsistent', listing_id=broken)
        fixture('expire', listing_id=broken); fixture('expire', listing_id=normal)
        self.worker(expected=2)
        self.assertEqual('review', fixture('state', listing_id=broken)['status'])
        self.assertEqual('ended', fixture('state', listing_id=normal)['status'])
        self.assertEqual(2, fixture('state', listing_id=broken)['events'])
        self.worker()

    def test_buyout_requires_csrf_and_is_unavailable_after_bids(self):
        seller = self.user(); buyer = self.user(); listing = self.seed_listing(seller, buyout_cents=3000)
        browser = buyer['browser']
        self.assertEqual(405, browser.request('/marketplace/buyout-request.php?listing_id=' + str(listing))[0])
        self.assertEqual(403, browser.request('/marketplace/buyout-request.php', {'listing_id': listing})[0])
        self.assertEqual(303, self.bid(buyer, listing, '20.00')[0])
        self.assertEqual(409, browser.request('/marketplace/buyout-request.php', {'listing_id': listing, 'csrf_token': browser.token()})[0])
        fresh = self.seed_listing(seller, buyout_cents=3000)
        self.assertEqual(303, browser.request('/marketplace/buyout-request.php', {'listing_id': fresh, 'csrf_token': browser.token()})[0])
        state = fixture('state', listing_id=fresh)
        self.assertEqual('sold', state['status']); self.assertEqual(3000, state['current_bid_cents'])

    def test_invoice_owner_and_disabled_bank_transfer_cannot_be_bypassed(self):
        owner = self.user(); other = self.user(); invoice = fixture('invoice', username=owner['username'])['id']
        url = '/user/payments.php?invoice_id=' + str(invoice)
        self.assertEqual(404, other['browser'].request(url)[0])
        status, _, body = owner['browser'].request(url)
        self.assertEqual(200, status); self.assertIn('Banküberweisung', body)
        self.assertIn('aria-disabled="true"', body); self.assertNotIn('IBAN', body)
        fields = dict(csrf_token=owner['browser'].token(), invoice_id=invoice, provider='bank')
        self.assertEqual(503, owner['browser'].request('/actions/payment-checkout.php', fields)[0])
        self.assertEqual(200, owner['browser'].request(url)[0])


if __name__ == '__main__':
    unittest.main(verbosity=2)
