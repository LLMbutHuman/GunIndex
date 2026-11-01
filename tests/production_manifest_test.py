#!/usr/bin/env python3
"""Real isolated PHP/HKDF/HMAC checks; no database, network or customer data."""
import hashlib,hmac,importlib.util,json,os,subprocess,tempfile,unittest
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
DOCKER=['docker','--host=unix:///var/run/docker.sock']
IMAGE=os.environ.get('GUNINDEX_PRODUCTION_TEST_IMAGE','gunindex-rebuild-php:local')
spec=importlib.util.spec_from_file_location('production_backup',ROOT/'ops/production/backup.py')
backup=importlib.util.module_from_spec(spec);spec.loader.exec_module(backup)

class ProductionManifest(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  result=subprocess.run(DOCKER+['image','inspect',IMAGE],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
  if result.returncode:raise RuntimeError('Prepare the local PHP test image, or set GUNINDEX_PRODUCTION_TEST_IMAGE to a reviewed production image.')
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory(prefix='gunindex-manifest-');self.base=Path(self.temp.name)
  self.key=self.base/'backup.key';self.key.write_bytes(bytes(range(32)));self.key.chmod(0o600)
  wrapper=self.base/'compose.sh';wrapper.write_text('#!/bin/sh\nexit 99\n');wrapper.chmod(0o700)
 def tearDown(self):self.temp.cleanup()
 def sign(self,body):
  return subprocess.run(DOCKER+['run','--rm','-i','--network','none','--read-only','--user',str(os.getuid())+':'+str(os.getgid()),'--cap-drop','ALL','--security-opt','no-new-privileges','--mount','type=bind,source='+str(self.key)+',target=/run/backup.key,readonly','--mount','type=bind,source='+str(ROOT/'ops/production/manifest-auth.php')+',target=/app/ops/production/manifest-auth.php,readonly',IMAGE,'php','/app/ops/production/manifest-auth.php'],input=body,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 def signature(self,metadata):
  body=json.dumps(metadata,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()
  result=self.sign(body);self.assertEqual(0,result.returncode);return result.stdout.decode().strip()
 def guarded_operations(self):
  ops=backup.Operations(self.base);ops.manifest_signature=self.signature
  def forbidden(*args,**kwargs):raise AssertionError('An invalid manifest reached a database operation.')
  ops.mysql=forbidden
  return ops
 def test_01_real_php_matches_independent_standard_hkdf_hmac(self):
  body=b'{"format":"gunindex-snapshot-1","fixture":"synthetic-only"}'
  extracted=hmac.new(bytes(32),self.key.read_bytes(),hashlib.sha256).digest()
  derived=hmac.new(extracted,b'GunIndex snapshot manifest v1\x01',hashlib.sha256).digest()
  expected=hmac.new(derived,body,hashlib.sha256).hexdigest().encode()
  result=self.sign(body);self.assertEqual(0,result.returncode);self.assertTrue(hmac.compare_digest(expected,result.stdout))
 def test_02_changed_manifest_changes_signature(self):
  self.assertNotEqual(self.signature({'amount':100}),self.signature({'amount':101}))
 def test_03_changed_key_changes_signature(self):
  first=self.signature({'fixture':'synthetic'});self.key.write_bytes(bytes(reversed(range(32))))
  self.assertNotEqual(first,self.signature({'fixture':'synthetic'}))
 def test_04_publicly_readable_key_is_rejected_without_secret_output(self):
  self.key.chmod(0o644);result=self.sign(b'{}');self.assertNotEqual(0,result.returncode);self.assertEqual(b'',result.stdout);self.assertIn(b'authentication failed',result.stderr)
 def test_05_short_key_is_rejected(self):
  self.key.write_bytes(b'too-short');self.assertNotEqual(0,self.sign(b'{}').returncode)
 def test_06_oversized_manifest_is_rejected(self):
  self.assertNotEqual(0,self.sign(b'x'*(20971520+1)).returncode)
 def test_07_modified_signed_manifest_never_reaches_database(self):
  metadata={'format':'gunindex-snapshot-1','created_at':'synthetic-fixture'}
  metadata['manifest_hmac']=self.signature(metadata);metadata['created_at']='modified-fixture'
  manifest=self.base/'snapshot.json';manifest.write_text(json.dumps(metadata))
  with self.assertRaisesRegex(RuntimeError,'authentication failed'):self.guarded_operations().restore(manifest,'gunindex_restore_testonly',self.base/'restored')
 def test_08_authenticated_path_traversal_is_still_rejected(self):
  metadata={'format':'gunindex-snapshot-1','database_archive':'../outside.gib'}
  metadata['manifest_hmac']=self.signature(metadata);manifest=self.base/'snapshot.json';manifest.write_text(json.dumps(metadata))
  with self.assertRaisesRegex(RuntimeError,'Unsafe archive'):self.guarded_operations().restore(manifest,'gunindex_restore_testonly',self.base/'restored')

if __name__=='__main__':unittest.main(verbosity=2)
