"""Exercise the protected prelaunch workflow over the isolated installer's HTTPS socket."""
import base64
import hmac
import json
import os
import re
import struct
import subprocess
import sys
import tempfile
import time
from pathlib import Path
from urllib.parse import urlencode


assert os.environ.get('GITHUB_ACTIONS') == 'true'
assert os.environ.get('RUNNER_ENVIRONMENT') == 'github-hosted'
runner_temp = Path(os.environ['RUNNER_TEMP']).resolve()
credentials_path, ca = map(Path, sys.argv[1:])
assert credentials_path.parent.resolve() == runner_temp and ca.parent.resolve() == runner_temp
assert credentials_path.stat().st_mode & 0o077 == 0
credentials = json.loads(credentials_path.read_text())
credentials_path.unlink()

compose = ['sudo', '/srv/gunindex/compose.sh', 'exec', '-T']
domain = 'installer.gunindex-ci.internal'
panel = '/admin/setup.php'


def request(path, cookie_jar, fields=None):
    command = ['curl', '--silent', '--show-error', '--max-time', '10', '--noproxy', '*', '--cacert', str(ca),
               '--resolve', f'{domain}:443:127.0.0.1', '--cookie', cookie_jar, '--cookie-jar', cookie_jar,
               '--write-out', '\n%{http_code}', f'https://{domain}{path}']
    if fields is not None:
        command += ['--header', 'Content-Type: application/x-www-form-urlencoded', '--data-binary', '@-']
    result = subprocess.run(command, check=True, capture_output=True, text=True, encoding='utf-8',
                            input=None if fields is None else urlencode(fields))
    body, status = result.stdout.rsplit('\n', 1)
    return int(status), body


def form_token(body):
    return re.search(r'name="csrf_token" value="([a-f0-9]{64})"', body)[1]


def one_time_code():
    counter = max(int(time.time()) // 30, credentials['totp_counter'] + 1)
    digest = hmac.digest(base64.b32decode(credentials['totp_key']), struct.pack('>Q', counter), 'sha1')
    offset = digest[-1] & 15
    return f'{(int.from_bytes(digest[offset:offset + 4], "big") & 0x7fffffff) % 1000000:06d}'


with tempfile.TemporaryDirectory(dir=runner_temp) as temp:
    anonymous, staff = [str(Path(temp) / name) for name in ['anonymous.cookies', 'staff.cookies']]
    assert request('/', anonymous)[0] == 503
    assert request('/sitemap.xml', anonymous)[0] == 503
    assert request('/auth/register.php', anonymous)[0] == 503
    assert request(panel, staff)[0] == 303
    status, body = request('/auth/login.php', staff)
    assert status == 200
    assert request('/auth/login.php', staff, {'csrf_token': form_token(body), 'login': 'installer_test_admin',
                                             'password': credentials['password']})[0] == 303
    assert request(panel, staff)[0] == 303
    status, body = request('/auth/mfa.php', staff)
    assert status == 200
    assert request('/auth/mfa.php', staff, {'csrf_token': form_token(body), 'code': one_time_code()})[0] == 303

    status, body = request(panel, staff)
    assert status == 200 and 'data-installation-state="pending"' in body
    token = form_token(body)
    assert request(panel, staff, {'action': 'activate', 'confirm': '1'})[0] == 403
    assert request(panel, staff, {'action': 'activate', 'confirm': '1', 'csrf_token': token})[0] == 409
    assert request(panel, staff, {'action': 'run_checks', 'csrf_token': token})[0] == 303
    status, body = request(panel, staff)
    check_results = dict(re.findall(r'data-installation-check="([a-z_]+)" data-status="(passed|failed)"', body))
    assert status == 200 and len(check_results) == 7 and set(check_results.values()) == {'passed'}, check_results
    assert 'value="activate"' in body and request('/', anonymous)[0] == 503
    assert request(panel, staff, {'action': 'activate', 'csrf_token': token})[0] == 422
    assert request(panel, staff, {'action': 'activate', 'confirm': '1', 'csrf_token': token})[0] == 303
    assert request('/', anonymous)[0] == 200
    status, body = request(panel, staff)
    assert status == 200 and 'data-installation-state="active"' in body
    assert request(panel, staff, {'action': 'activate', 'confirm': '1', 'csrf_token': token})[0] == 409

invariants = r'''require "config/bootstrap.php";
if ((int)$pdo->query("SELECT COUNT(*) FROM admin_logs WHERE action_type='installation.activated'")->fetchColumn() !== 1) exit(1);
if (payments_config()['enabled'] || bank_config()['enabled'] || bitcoin_config()['enabled']) exit(1);
if (site_setting($pdo,'operator_vat_id')!=='' || site_setting($pdo,'operator_tax_number')!=='' || site_setting($pdo,'legal_publication_enabled')!=='0') exit(1);
foreach (['manual_paypal_enabled','finance_enabled'] as $key) if (site_setting($pdo,$key) !== '0') exit(1);'''
subprocess.run(compose + ['php', 'php', '-r', invariants], check=True)
print('Installation gate, password and TOTP, CSRF, technical checks, one-time audited launch and disabled payments verified over HTTPS.')
