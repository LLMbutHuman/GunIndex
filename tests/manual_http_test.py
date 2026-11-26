#!/usr/bin/env python3
"""Manual payment and private communication regressions; no PayPal requests."""
import hashlib,re,secrets,unittest
import http_test as cases
from http_test import Browser,fixture,service_start_fields,service_terms_fields

class ManualHTTP(unittest.TestCase):
 user=cases.MarketplaceHTTP.user
 login=cases.MarketplaceHTTP.login
 mfa_login=cases.MarketplaceHTTP.mfa_login
 seed_listing=cases.MarketplaceHTTP.seed_listing
 settings=cases.MarketplaceHTTP.settings
 restore_settings=cases.MarketplaceHTTP.restore_settings
 bid=cases.MarketplaceHTTP.bid
 worker=cases.MarketplaceHTTP.worker
 @classmethod
 def setUpClass(cls):
  fixture('reset_limits');cls.category_id=int(fixture('categories')['categories'][0]['id'])
 def enabled(self):
  admin=self.user(admin=True);original=self.settings(admin);values=dict(original,manual_paypal_enabled='1',manual_paypal_approved='1',manual_paypal_recipient='merchant@example.invalid',manual_tax_reviewed='1',manual_tax_bps='1900')
  self.assertEqual(200,admin['browser'].request('/admin/settings.php',values)[0]);self.addCleanup(self.restore_settings,admin,original)
  return admin
 def order(self,user,service='bump_single',listing_type='fixed'):
  listing=self.seed_listing(user,type=listing_type);browser=user['browser'];body=browser.request('/user/billing.php')[2]
  key=re.search(r'name="order_key" value="([a-f0-9]{64})"',body).group(1)
  fields=dict(csrf_token=browser.token('/user/billing.php'),action='order',order_key=key,listing_id=listing,service=service,accept_terms='1')
  fields.update(service_terms_fields(body,service))
  status,headers,_=browser.request('/user/billing.php',fields);self.assertEqual(303,status)
  invoice=int(headers['Location'].split('order=')[1]);state=fixture('manual_state',username=user['username'],invoice_id=invoice)
  return invoice,listing,state
 def approve(self,admin,state,**changes):
  fields=dict(csrf_token=admin['browser'].token('/admin/payments.php'),invoice_id=state['id'],transaction_id='TEST'+secrets.token_hex(8).upper(),reference=state['reference'],received_amount=f"{int(state['gross_cents'])//100}.{int(state['gross_cents'])%100:02}",decision='approve',reason='Synthetic direct account verification',review_confirmed='1')
  fields.update(changes);return admin['browser'].request('/admin/payments.php',fields),fields
 def test_manual_checkout_is_disabled_by_default(self):
  user=self.user();body=user['browser'].request('/user/billing.php')[2]
  self.assertIn('noch nicht aktiviert',body);self.assertNotIn('action="https://www.paypal.com',body)
 def test_server_order_prefills_amount_reference_and_stays_unpaid(self):
  self.enabled();user=self.user();invoice,_,state=self.order(user)
  body=user['browser'].request('/user/billing.php?order='+str(invoice))[2]
  self.assertIn('name="amount" value="1.99"',body);self.assertIn('name="invoice" value="'+state['reference']+'"',body)
  self.assertIn('merchant@example.invalid',body);self.assertEqual('issued',state['status']);self.assertEqual(0,int(state['credits']))
 def test_claim_and_browser_parameters_never_confirm_payment(self):
  self.enabled();user=self.user();invoice,_,state=self.order(user);browser=user['browser']
  self.assertEqual(200,browser.request('/user/billing.php?order='+str(invoice)+'&paid=1&status=paid')[0])
  self.assertEqual(200,browser.request('/user/billing.php',dict(csrf_token=browser.token('/user/billing.php'),action='claim',invoice_id=invoice))[0])
  state=fixture('manual_state',username=user['username'],invoice_id=invoice)
  self.assertEqual('pending',state['status']);self.assertEqual(0,int(state['credits']));self.assertIsNotNone(state['customer_claimed_at'])
 def test_manual_review_requires_admin_csrf_amount_and_reference(self):
  admin=self.enabled();user=self.user();invoice,_,state=self.order(user)
  self.assertEqual(403,user['browser'].request('/admin/payments.php')[0])
  self.assertEqual(403,admin['browser'].request('/admin/payments.php',{'invoice_id':invoice})[0])
  self.assertEqual(422,self.approve(admin,state,received_amount='0.01')[0][0])
  self.assertEqual(422,self.approve(admin,state,reference='WRONG')[0][0])
  self.assertEqual('issued',fixture('manual_state',username=user['username'],invoice_id=invoice)['status'])
 def test_approval_is_idempotent_and_transaction_cannot_be_reused(self):
  admin=self.enabled();user=self.user();invoice,_,state=self.order(user)
  response,fields=self.approve(admin,state);self.assertEqual(200,response[0])
  self.assertEqual(200,admin['browser'].request('/admin/payments.php',fields)[0])
  confirmed=fixture('manual_state',username=user['username'],invoice_id=invoice)
  self.assertEqual('paid',confirmed['status']);self.assertEqual(1,int(confirmed['credits']));self.assertEqual(1,int(confirmed['approvals']))
  other_invoice,_,other_state=self.order(user)
  self.assertEqual(422,self.approve(admin,other_state,transaction_id=fields['transaction_id'])[0][0])
  self.assertEqual('issued',fixture('manual_state',username=user['username'],invoice_id=other_invoice)['status'])
 def test_fee_changes_do_not_change_existing_order(self):
  admin=self.enabled();user=self.user();invoice,_,state=self.order(user)
  values=self.settings(admin);values['fee_bump_single_cents']='299';self.assertEqual(200,admin['browser'].request('/admin/settings.php',values)[0])
  self.assertEqual(199,int(fixture('manual_state',username=user['username'],invoice_id=invoice)['gross_cents']))
  _,_,new=self.order(user);self.assertEqual(299,int(new['gross_cents']))
 def test_order_binds_price_service_and_original_language(self):
  admin=self.enabled();user=self.user();browser=user['browser'];listing=self.seed_listing(user);route='/user/billing.php'
  def language(locale):
   self.assertEqual(303,browser.request('/user/language.php',dict(csrf_token=browser.token(route),locale=locale,market_country='DE',return_path=route))[0])
  language('fr');body=browser.request(route)[2]
  self.assertIn('Crédits de mise en avant pour trois activations.',body)
  key=re.search(r'name="order_key" value="([a-f0-9]{64})"',body).group(1)
  post=dict(csrf_token=browser.token(route),action='order',order_key=key,listing_id=listing,service='bump_pack',accept_terms='1')
  quoted=service_terms_fields(body,'bump_pack')
  self.assertEqual(422,browser.request(route,post)[0])
  self.assertEqual(422,browser.request(route,{**post,**quoted,'accept_terms':'0'})[0])
  self.assertEqual(422,browser.request(route,{**post,**quoted,'service':'bump_single'})[0])
  language('en');self.assertEqual(422,browser.request(route,{**post,**quoted})[0]);language('fr')
  values=self.settings(admin);values['fee_bump_pack_cents']='599';self.assertEqual(200,admin['browser'].request('/admin/settings.php',values)[0])
  self.assertEqual(422,browser.request(route,{**post,**quoted})[0])
  latest=service_terms_fields(browser.request(route)[2],'bump_pack')
  self.assertNotEqual(quoted,latest)
  status,headers,_=browser.request(route,{**post,**latest});self.assertEqual(303,status)
  invoice=int(headers['Location'].split('order=')[1]);snapshot=fixture('manual_state',username=user['username'],invoice_id=invoice)
  self.assertEqual(599,int(snapshot['gross_cents']));self.assertEqual('fr',snapshot['terms_language'])
  self.assertIn('Crédits de mise en avant pour trois activations.',snapshot['terms_text'])
  self.assertEqual(hashlib.sha256(snapshot['terms_text'].encode()).hexdigest(),snapshot['terms_hash'])
  self.assertIn('5,99\xa0€',browser.request(headers['Location'])[2])
  language('en');saved=browser.request(headers['Location'])[2]
  self.assertIn('€5.99',saved)
  self.assertIn('name="amount" value="5.99"',saved)
  self.assertIn('id="saved-order-terms" lang="fr">Crédits de mise en avant pour trois activations.',saved)
 def test_bump_only_after_review_and_obeys_72_hours(self):
  admin=self.enabled();user=self.user();invoice,listing,state=self.order(user,'bump_pack',listing_type='auction');browser=user['browser']
  bidder=self.user();self.assertEqual(303,self.bid(bidder,listing,'20.00')[0])
  before=fixture('bump_state',listing_id=listing)
  fields=dict(csrf_token=browser.token('/user/billing.php'),action='bump',listing_id=listing,immediate_start='1')
  self.assertEqual(422,browser.request('/user/billing.php',fields)[0])
  self.assertEqual(200,self.approve(admin,state)[0][0]);missing=dict(fields);missing.pop('immediate_start');self.assertEqual(422,browser.request('/user/billing.php',missing)[0])
  self.assertEqual(422,browser.request('/user/billing.php',fields)[0])
  fields.update(service_start_fields(browser.request('/user/billing.php')[2]))
  self.assertEqual(422,browser.request('/user/billing.php',{**fields,'start_language':'fr'})[0])
  self.assertEqual(200,browser.request('/user/billing.php',fields)[0])
  self.assertEqual(422,browser.request('/user/billing.php',fields)[0])
  after=fixture('bump_state',listing_id=listing);self.assertEqual(1,after['bumps']);self.assertEqual(before['created_at'],after['created_at']);self.assertEqual(before['ends_at'],after['ends_at']);self.assertEqual(before['current_bid_cents'],after['current_bid_cents'])
  self.assertEqual('de',after['consent']['consent_language']);self.assertEqual(fields['start_hash'],after['consent']['consent_hash'])
  self.assertEqual(2,int(fixture('manual_state',username=user['username'],invoice_id=invoice)['credits']))
 def test_manual_order_cannot_enter_automatic_capture(self):
  self.enabled();owner=self.user();invoice,_,_=self.order(owner)
  self.assertFalse(fixture('automatic_guard',username=owner['username'],invoice_id=invoice)['allowed'])
 def test_checkout_validation_uses_selected_language(self):
  self.enabled();seller=self.user();browser=seller['browser'];url='/user/billing.php'
  listing=self.seed_listing(seller);foreign_listing=self.seed_listing(self.user())
  self.assertEqual(303,browser.request('/user/language.php',dict(csrf_token=browser.token(url),locale='fr',market_country='DE',return_path=url))[0])
  body=browser.request(url)[2]
  key=re.search(r'name="order_key" value="([a-f0-9]{64})"',body).group(1)
  fields=dict(csrf_token=browser.token(url),action='order',order_key=key,listing_id=foreign_listing,service='bump_single',accept_terms='1')
  status,_,body=browser.request(url,fields)
  self.assertEqual(422,status);self.assertIn('Vous ne pouvez promouvoir que vos propres annonces actives.',body)
  status,_,body=browser.request(url,dict(csrf_token=browser.token(url),action='bump',listing_id=listing,immediate_start='1'))
  self.assertEqual(422,status);self.assertIn('Aucun crédit payé de mise en avant n’est disponible.',body)
  self.assertEqual(0,int(fixture('bump_state',listing_id=listing)['bumps']))
 def test_admin_registration_switch_is_enforced(self):
  admin=self.user(admin=True);original=self.settings(admin);self.addCleanup(self.restore_settings,admin,original)
  values=dict(original,registration_enabled='0');self.assertEqual(200,admin['browser'].request('/admin/settings.php',values)[0])
  self.assertEqual(503,Browser().request('/auth/register.php')[0])
 def closed_auction(self):
  seller=self.user();winner=self.user();listing=self.seed_listing(seller);self.assertEqual(303,self.bid(winner,listing,'20.00')[0]);fixture('expire',listing_id=listing);self.worker();return seller,winner,listing
 def test_only_auction_parties_rate_once_with_escaped_public_profile(self):
  seller,winner,listing=self.closed_auction();other=self.user();url='/user/rate-user.php?listing_id='+str(listing)
  self.assertEqual(403,other['browser'].request(url)[0]);browser=winner['browser'];self.assertEqual(403,browser.request(url,{'stars':'5','comment':'Good'})[0])
  message='<script>rating_'+secrets.token_hex(8)+'</script>';fields=dict(csrf_token=browser.token(url),stars='5',comment=message)
  self.assertEqual(200,browser.request(url,fields)[0]);self.assertEqual(409,browser.request(url,fields)[0])
  body=Browser().request('/marketplace/profile.php?user_id='+str(seller['id']))[2];self.assertNotIn(message,body);self.assertIn('&lt;script&gt;rating_',body);self.assertNotIn(seller['username']+'@example.invalid',body)
 def test_rating_moderation_needs_csrf_and_preserves_record(self):
  seller,winner,listing=self.closed_auction();browser=winner['browser'];url='/user/rate-user.php?listing_id='+str(listing);message='Public feedback '+secrets.token_hex(8)
  self.assertEqual(303,browser.request('/user/language.php',dict(csrf_token=browser.token(url),locale='en',market_country='DE',return_path=url))[0])
  self.assertIn('<h1>Rate your auction</h1>',browser.request(url)[2])
  status,_,body=browser.request(url,dict(csrf_token=browser.token(url),stars='4',comment=message))
  self.assertEqual(200,status);self.assertIn('Your rating was saved.',body)
  record=fixture('rating_state',username=winner['username'],listing_id=listing);admin=self.user(admin=True)['browser']
  route='/admin/ratings.php'
  self.assertEqual(303,admin.request('/user/language.php',dict(csrf_token=admin.token(route),locale='en',market_country='DE',return_path=route))[0])
  self.assertIn('<h1>Moderate ratings</h1>',admin.request(route)[2])
  self.assertNotIn('Moderation decision saved.',admin.request(route+'?saved=1')[2])
  self.assertEqual(405,admin.request(route+'?delete_id='+str(record['id']))[0]);self.assertEqual(403,admin.request(route,{'id':record['id'],'status':'hidden'})[0])
  self.assertEqual(303,admin.request(route,dict(csrf_token=admin.token(route),id=record['id'],status='hidden',reason='Synthetic documented moderation'))[0])
  self.assertIn('Moderation decision saved.',admin.request(route)[2])
  self.assertNotIn('Moderation decision saved.',admin.request(route)[2])
  self.assertEqual('hidden',fixture('rating_state',username=winner['username'],listing_id=listing)['status']);self.assertNotIn(message,Browser().request('/marketplace/profile.php?user_id='+str(seller['id']))[2])
 def test_foreign_payment_order_is_not_readable(self):
  self.enabled();owner=self.user();invoice,_,_=self.order(owner);outsider=self.user()
  self.assertEqual(404,outsider['browser'].request('/user/billing.php?order='+str(invoice))[0])
 def test_chat_escapes_content_and_isolates_other_pairs(self):
  seller=self.user();buyer=self.user();other=self.user();listing=self.seed_listing(seller,type='fixed')
  url=f"/user/chat.php?listing_id={listing}&user_id={seller['id']}";browser=buyer['browser']
  message='<script>private_'+secrets.token_hex(8)+'</script>'
  self.assertEqual(403,browser.request(url,{'message':message})[0])
  result=browser.request(url,dict(csrf_token=browser.token(url),action='send',message=message));self.assertIn(result[0],(200,303))
  body=browser.request(url)[2];self.assertNotIn(message,body);self.assertIn('&lt;script&gt;private_',body)
  wrong=f"/user/chat.php?listing_id={listing}&user_id={buyer['id']}"
  self.assertEqual(403,other['browser'].request(wrong)[0]);self.assertNotIn('private_',other['browser'].request('/user/messages.php')[2])
 def test_new_chat_after_auction_end_is_restricted_to_winner(self):
  seller=self.user();buyer=self.user();other=self.user();listing=self.seed_listing(seller)
  self.assertEqual(303,self.bid(buyer,listing,'20.00')[0]);fixture('expire',listing_id=listing);self.worker()
  url=f"/user/chat.php?listing_id={listing}&user_id={seller['id']}"
  self.assertEqual(403,other['browser'].request(url)[0]);self.assertEqual(200,buyer['browser'].request(url)[0])

if __name__=='__main__':unittest.main(verbosity=2)
