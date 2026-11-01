#!/usr/bin/env python3
"""Dealer/profile/banner/CSV HTTP checks on synthetic isolated identities only."""
import csv,io,json,re,secrets,subprocess,unittest,urllib.parse
import http_test as cases
from http_test import Browser,fixture,png,title

READ=r'''
if(PHP_SAPI!=='cli'||getenv('DB_DATABASE')!=='gunindex_rebuild_test')exit(2);
require '/app/config/bootstrap.php';require_once '/app/config/business.php';
$r=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);$name=$r['username']??'';
if(!is_string($name)||!preg_match('/\Atest_[a-f0-9]{16}\z/',$name))exit(2);
$q=$pdo->prepare("SELECT id FROM users WHERE username=? AND email=CONCAT(username,'@example.invalid') AND full_name='Synthetic test fixture'");$q->execute([$name]);$id=$q->fetchColumn();if(!$id)exit(2);
if($r['operation']==='category'){$slug='business-http-'.bin2hex(random_bytes(8));$q=$pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)');$q->execute(['Synthetic business HTTP category',$slug]);$out=['id'=>(int)$pdo->lastInsertId(),'slug'=>$slug];}
elseif($r['operation']==='profile'){$p=business_dealer_profile($pdo,(int)$id,false);$out=$p?['status'=>$p['status'],'revision'=>business_profile_revision($p)]:null;}
elseif($r['operation']==='campaigns'){$q=$pdo->prepare('SELECT id,status FROM market_campaigns WHERE user_id=? ORDER BY id DESC LIMIT 100');$q->execute([$id]);$out=$q->fetchAll();}
elseif($r['operation']==='bookings'){$q=$pdo->prepare('SELECT b.id,b.invoice_id,o.reference,i.gross_cents FROM market_premium_bookings b JOIN market_manual_orders o ON o.invoice_id=b.invoice_id JOIN market_invoices i ON i.id=b.invoice_id WHERE b.user_id=? ORDER BY b.id DESC LIMIT 100');$q->execute([$id]);$out=$q->fetchAll();}
elseif($r['operation']==='stats'){$q=$pdo->prepare('SELECT COALESCE(SUM(s.impressions),0) AS impressions,COALESCE(SUM(s.clicks),0) AS clicks FROM market_ad_daily_stats s JOIN market_campaigns c ON c.id=s.campaign_id WHERE c.user_id=?');$q->execute([$id]);$out=$q->fetch();}
elseif($r['operation']==='listings'){$q=$pdo->prepare('SELECT COUNT(*) FROM market_listings WHERE seller_id=?');$q->execute([$id]);$out=['count'=>(int)$q->fetchColumn()];}
else exit(2);
echo json_encode($out,JSON_THROW_ON_ERROR);
'''
def read(user,operation):
 run=subprocess.run(cases.DOCKER+['exec','-i',cases.CONTAINER,'php','-r',READ],input=json.dumps({'username':user['username'],'operation':operation}).encode(),stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 if run.returncode:raise RuntimeError('Synthetic business probe failed; inputs withheld.')
 return json.loads(run.stdout)
def upload(fields,field,name,mime,data):
 boundary='DealerBoundary'+secrets.token_hex(16);parts=[]
 for key,value in fields.items():parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"\r\n\r\n'+str(value)+'\r\n').encode())
 parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+field+'"; filename="'+name+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode()+data+b'\r\n');parts.append(('--'+boundary+'--\r\n').encode());return b''.join(parts),'multipart/form-data; boundary='+boundary
def csv_bytes(rows):
 stream=io.StringIO();writer=csv.writer(stream);writer.writerow(['title','description','category_slug','type','price','buyout_price','duration_days','country_code','zip','city','condition_label','pickup','shipping']);writer.writerows(rows);return stream.getvalue().encode()

class BusinessHTTP(unittest.TestCase):
 user=cases.MarketplaceHTTP.user
 login=cases.MarketplaceHTTP.login
 mfa_login=cases.MarketplaceHTTP.mfa_login
 seed_listing=cases.MarketplaceHTTP.seed_listing
 settings=cases.MarketplaceHTTP.settings
 restore_settings=cases.MarketplaceHTTP.restore_settings
 fields=cases.MarketplaceHTTP.fields
 bid=cases.MarketplaceHTTP.bid
 @classmethod
 def setUpClass(cls):
  fixture('reset_limits');name=cases.identity();fixture('seed',username=name,password='Synthetic category '+secrets.token_hex(16));category=read({'username':name},'category');cls.category_id=int(category['id']);cls.category_slug=category['slug']
 def profile(self,user):
  browser=user['browser'];fields=dict(csrf_token=browser.token('/user/dealer.php'),action='profile',company_name='Synthetic Outdoor Company',address_text='Teststraße 1, 10115 Testort',country_code='DE',contact_email='synthetic@example.invalid',website='https://shop.example.org',legal_notice='Synthetic legal company details for isolated regression testing only.')
  self.assertEqual(200,browser.request('/user/dealer.php',fields)[0]);return fields
 def published(self):
  user=self.user();admin=self.user(admin=True);self.profile(user);record=read(user,'profile');self.assertEqual('pending',record['status'])
  fields=dict(csrf_token=admin['browser'].token('/admin/settings.php'),action='profile',target_id=user['id'],status='published',reason='Synthetic content review',revision=record['revision'])
  self.assertEqual(200,admin['browser'].request('/admin/campaigns.php?kind=profiles',fields)[0]);self.assertEqual('published',read(user,'profile')['status']);return user,admin
 def campaign(self,user,data=None,name='banner.png',mime='image/png'):
  browser=user['browser'];fields=dict(csrf_token=browser.token('/user/dealer.php'),action='campaign',title='Synthetic outdoor equipment',target_url='https://shop.example.org/outdoor',country_code='DE',category_id=self.category_id)
  body,kind=upload(fields,'banner[]',name,mime,png() if data is None else data);return browser.request('/user/dealer.php',raw=body,content_type=kind)
 def row(self,label=None,**change):
  data=dict(title=label or title(),description='Ordinary synthetic product for isolated CSV import tests.',category_slug=self.category_slug,type='fixed',price='45.00',buyout_price='',duration_days='7',country_code='DE',zip='10115',city='Testort',condition_label='used',pickup='1',shipping='1');data.update(change);return list(data.values())
 def import_csv(self,user,rows):
  browser=user['browser'];body,kind=upload(dict(csrf_token=browser.token('/user/dealer.php'),action='import',accept_rules='1'),'csv','ordinary.csv','text/csv',csv_bytes(rows));return browser.request('/user/dealer.php',raw=body,content_type=kind)
 def test_profile_is_explicit_pending_and_csrf_protected(self):
  user=self.user();browser=user['browser'];body=browser.request('/user/dealer.php')[2]
  self.assertNotIn('value="'+user['username']+'@example.invalid"',body);self.assertEqual(403,browser.request('/user/dealer.php',{'action':'profile'})[0]);self.profile(user);self.assertEqual('pending',read(user,'profile')['status'])
 def test_stale_profile_review_does_not_publish_changed_details(self):
  user=self.user();admin=self.user(admin=True);fields=self.profile(user);old=read(user,'profile')['revision'];fields['company_name']='Changed Synthetic Company';fields['csrf_token']=user['browser'].token('/user/dealer.php');self.assertEqual(200,user['browser'].request('/user/dealer.php',fields)[0])
  result=admin['browser'].request('/admin/campaigns.php',dict(csrf_token=admin['browser'].token('/admin/settings.php'),action='profile',target_id=user['id'],status='published',reason='Synthetic review',revision=old));self.assertEqual(422,result[0]);self.assertEqual('pending',read(user,'profile')['status'])
 def test_pending_banner_is_private_and_reencoded(self):
  user,admin=self.published();self.assertEqual(200,self.campaign(user)[0]);campaign=read(user,'campaigns')[0];self.assertEqual('pending',campaign['status'])
  status,headers,data=user['browser'].request('/user/dealer.php?banner_image='+str(campaign['id']),binary=True);self.assertEqual(200,status);self.assertEqual('image/webp',headers['Content-Type']);self.assertEqual(b'RIFF',data[:4]);self.assertEqual(b'WEBP',data[8:12]);other=self.user();self.assertEqual(404,other['browser'].request('/user/dealer.php?banner_image='+str(campaign['id']))[0]);self.assertEqual(404,Browser().request('/marketplace/banner.php?id='+str(campaign['id'])+'&country=DE')[0])
 def test_approved_paid_banner_is_country_scoped_and_counted_once(self):
  user,admin=self.published();original=self.settings(admin);values=dict(original,business_promotions_enabled='1',manual_paypal_enabled='1',manual_paypal_approved='1',manual_paypal_recipient='merchant@example.invalid',manual_tax_reviewed='1',manual_tax_bps='1900',finance_enabled='0')
  self.assertEqual(200,admin['browser'].request('/admin/settings.php',values)[0]);self.addCleanup(self.restore_settings,admin,original)
  self.assertEqual(200,self.campaign(user)[0]);campaign=read(user,'campaigns')[0]
  moderation=dict(csrf_token=admin['browser'].token('/admin/settings.php'),action='campaign',target_id=campaign['id'],status='approved',reason='Synthetic banner content review')
  self.assertEqual(200,admin['browser'].request('/admin/campaigns.php',moderation)[0]);listing=self.seed_listing(user,type='fixed');browser=user['browser'];body=browser.request('/user/promotions.php')[2];key=re.search(r'name="order_key" value="([a-f0-9]{64})"',body).group(1)
  result=browser.request('/user/promotions.php',dict(csrf_token=browser.token('/user/promotions.php'),action='order',order_key=key,service='banner_monthly',listing_id=listing,campaign_id=campaign['id'],accept_terms='1'));self.assertEqual(303,result[0]);booking=read(user,'bookings')[0]
  self.assertEqual(404,Browser().request('/marketplace/banner.php?id='+str(campaign['id'])+'&country=DE')[0])
  cents=int(booking['gross_cents']);approval=dict(csrf_token=admin['browser'].token('/admin/settings.php'),invoice_id=booking['invoice_id'],transaction_id='SYNTHETIC'+secrets.token_hex(8).upper(),reference=booking['reference'],received_amount=f'{cents//100}.{cents%100:02}',decision='approve',reason='Synthetic direct account verification',review_confirmed='1')
  self.assertEqual(200,admin['browser'].request('/admin/payments.php',approval)[0]);self.assertEqual(200,browser.request('/user/promotions.php',dict(csrf_token=browser.token('/user/promotions.php'),action='start',booking_id=booking['id'],immediate_start='1'))[0])
  url='/marketplace/banner.php?id='+str(campaign['id'])+'&country=DE';visitor=Browser();self.assertEqual(200,visitor.request(url,binary=True)[0]);self.assertEqual(200,visitor.request(url,binary=True)[0]);self.assertEqual(404,visitor.request('/marketplace/banner.php?id='+str(campaign['id'])+'&country=AT')[0]);click=visitor.request('/marketplace/banner-click.php?id='+str(campaign['id'])+'&country=DE');self.assertEqual(303,click[0]);self.assertEqual('https://shop.example.org/outdoor',click[1]['Location']);counts=read(user,'stats');self.assertEqual(1,int(counts['impressions']));self.assertEqual(1,int(counts['clicks']))
  moderation['csrf_token']=admin['browser'].token('/admin/settings.php');moderation['status']='disabled';self.assertEqual(200,admin['browser'].request('/admin/campaigns.php',moderation)[0]);self.assertEqual(404,visitor.request(url)[0])
 def test_disguised_script_is_not_a_banner(self):
  user,_=self.published();self.assertEqual(422,self.campaign(user,b'<?php echo "bad"; ?>','banner.png','image/png')[0]);self.assertEqual([],read(user,'campaigns'))
 def test_csv_has_real_partial_success_duplicate_and_error_report(self):
  user=self.user();label=title();good=self.row(label);bad=self.row(category_slug='nonexistent-'+secrets.token_hex(5));result=self.import_csv(user,[good,good,bad]);self.assertEqual(200,result[0]);self.assertIn('1 Inserate angelegt, 1 exakte Dubletten',result[2]);self.assertEqual(1,read(user,'listings')['count']);self.assertIsNotNone(fixture('find_listing',username=user['username'],title=label)['id'])
 def test_csv_row_limit_rejects_before_any_write(self):
  user=self.user();self.assertEqual(422,self.import_csv(user,[self.row() for _ in range(101)])[0]);self.assertEqual(0,read(user,'listings')['count'])
 def test_csv_export_is_owner_scoped_and_formula_safe(self):
  user=self.user();other=self.user();browser=user['browser'];label=title();fields=self.fields(user,title=label,description='=HYPERLINK("https://example.invalid") ordinary synthetic text')
  self.assertEqual(303,browser.request('/user/create-listing.php',fields)[0]);foreign=self.seed_listing(other,type='fixed')
  status,headers,body=browser.request('/user/dealer.php',dict(csrf_token=browser.token('/user/dealer.php'),action='export',after_id='0'));self.assertEqual(200,status);self.assertIn('text/csv',headers['Content-Type']);rows=list(csv.reader(io.StringIO(body.lstrip('\ufeff'))));self.assertEqual(2,len(rows));self.assertTrue(rows[1][2].startswith("'="));self.assertNotEqual(str(foreign),rows[1][0])
 def test_bulk_rejects_foreign_and_bound_auctions_atomically(self):
  owner=self.user();other=self.user();fixed=self.seed_listing(owner,type='fixed');foreign=self.seed_listing(other,type='fixed');browser=owner['browser']
  def bulk(ids):
   payload=[('csrf_token',browser.token('/user/dealer.php')),('action','bulk'),('transition','pause')]+[('listing_ids[]',str(i)) for i in ids];return browser.request('/user/dealer.php',raw=urllib.parse.urlencode(payload).encode(),content_type='application/x-www-form-urlencoded')
  self.assertEqual(422,bulk([fixed,foreign])[0]);self.assertEqual('active',fixture('state',listing_id=fixed)['status']);auction=self.seed_listing(owner);self.assertEqual(303,self.bid(other,auction,'20.00')[0]);self.assertEqual(422,bulk([fixed,auction])[0]);self.assertEqual('active',fixture('state',listing_id=fixed)['status']);self.assertEqual(200,bulk([fixed])[0]);self.assertEqual('paused',fixture('state',listing_id=fixed)['status'])

if __name__=='__main__':unittest.main(verbosity=2)
