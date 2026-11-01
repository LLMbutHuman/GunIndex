#!/usr/bin/env python3
"""Non-destructive installer/configuration checks. Never installs on this host."""
import importlib.util,json,os,subprocess,tempfile,unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('production_configure',ROOT/'ops/production/configure.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)

class Installation(unittest.TestCase):
 def command(self,*args):return subprocess.run(['bash',str(ROOT/'ops/install-ubuntu-22.04.sh'),*args],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 def test_help_needs_no_root_and_has_no_side_effects(self):
  result=self.command('--help');self.assertEqual(0,result.returncode);self.assertIn(b'No database',result.stdout)
 def test_dry_run_writes_no_target_or_secret(self):
  with tempfile.TemporaryDirectory() as temp:
   target=Path(temp)/'deployment';result=self.command('--domain','markt.gunindex.eu','--email','operator@gunindex.eu','--directory',str(target),'--dry-run')
   self.assertEqual(0,result.returncode);self.assertFalse(target.exists());self.assertIn(b'DRY RUN',result.stdout)
 def test_placeholder_and_injected_domains_are_rejected(self):
  for value in ('example.com','market.invalid','market.test','localhost','a.tld;touch/tmp/unsafe','https://gunindex.eu','gunindex.eu/other'):
   self.assertNotEqual(0,self.command('--domain',value,'--email','operator@gunindex.eu','--dry-run').returncode)
 def test_unsafe_target_paths_are_rejected(self):
  for value in ('/','/etc','/srv/../etc','/srv/a b','relative'):
   self.assertNotEqual(0,self.command('--domain','markt.gunindex.eu','--email','operator@gunindex.eu','--directory',value,'--dry-run').returncode)
 def test_source_checkout_cannot_contain_or_be_the_installation(self):
  for value in (str(ROOT),str(ROOT/'deployment'),str(ROOT.parent)):
   self.assertNotEqual(0,self.command('--domain','markt.gunindex.eu','--email','operator@gunindex.eu','--directory',value,'--dry-run').returncode)
 def test_private_configuration_is_created_once_and_preserved(self):
  with tempfile.TemporaryDirectory() as temp:
   base=Path(temp);module.configure(base,'markt.gunindex.eu','operator@gunindex.eu','synthetic-release',ROOT)
   paths=[base/'config/app.env',base/'config/database.env',base/'keys/backup.key'];original=[p.read_bytes() for p in paths]
   self.assertTrue(all((p.stat().st_mode&0o077)==0 for p in paths),'Secret permissions are too broad.')
   self.assertEqual(32,len(original[2]));module.configure(base,'markt.gunindex.eu','operator@gunindex.eu','synthetic-release2',ROOT)
   self.assertTrue(all(before==path.read_bytes() for before,path in zip(original,paths)),'A previously generated credential changed.')
 def test_missing_key_never_silently_regenerates(self):
  with tempfile.TemporaryDirectory() as temp:
   base=Path(temp);module.configure(base,'markt.gunindex.eu','operator@gunindex.eu','synthetic-release',ROOT);(base/'keys/backup.key').unlink()
   with self.assertRaises(RuntimeError):module.configure(base,'markt.gunindex.eu','operator@gunindex.eu','synthetic-release2',ROOT)
   self.assertFalse((base/'keys/backup.key').exists())
 def test_administrator_modified_configuration_is_not_overwritten(self):
  with tempfile.TemporaryDirectory() as temp:
   base=Path(temp);module.configure(base,'markt.gunindex.eu','operator@gunindex.eu','synthetic-release',ROOT)
   file=base/'config/compose.yml';file.write_text(file.read_text()+'\n# operator change\n')
   with self.assertRaises(RuntimeError):module.configure(base,'markt.gunindex.eu','operator@gunindex.eu','synthetic-release2',ROOT)
   self.assertIn('operator change',file.read_text())
 def test_domain_change_does_not_replace_keys(self):
  with tempfile.TemporaryDirectory() as temp:
   base=Path(temp);module.configure(base,'markt.gunindex.eu','operator@gunindex.eu','synthetic-release',ROOT);digest=(base/'keys/backup.key').read_bytes()
   with self.assertRaises(RuntimeError):module.configure(base,'other.gunindex.eu','operator@gunindex.eu','synthetic-release2',ROOT)
   self.assertTrue(digest==(base/'keys/backup.key').read_bytes(),'A domain conflict changed the backup key.')
 def test_shell_and_python_syntax(self):
  for file in [ROOT/'ops/install-ubuntu-22.04.sh',ROOT/'ops/offsite-backup.sh',*list((ROOT/'ops/production').glob('*.sh'))]:
   result=subprocess.run(['bash','-n',str(file)],stdout=subprocess.PIPE,stderr=subprocess.PIPE);self.assertEqual(0,result.returncode,'Shell syntax failed: '+file.name)
  for file in (ROOT/'ops/production').glob('*.py'):compile(file.read_text(),str(file),'exec')
if __name__=='__main__':unittest.main(verbosity=2)
