"""Verify that unsupported hosts cannot inspect or mutate Docker or runtime files."""
import contextlib
import importlib.util
import io
from pathlib import Path
import runpy
import sys
import unittest
from unittest.mock import patch
# Load platform-specific stdlib dependencies before simulating another host.
import urllib.request

RUNNER = Path(__file__).resolve().with_name('environment.py')


class PlatformGuard(unittest.TestCase):
    def test_cli_rejects_every_action_before_subprocess(self):
        for action in ('up', 'test', 'stop'):
            with self.subTest(action=action), patch.object(sys, 'platform', 'win32'), \
                    patch.object(sys, 'argv', [str(RUNNER), action]), \
                    patch('subprocess.run') as command, \
                    patch('pathlib.Path.mkdir') as mkdir, \
                    contextlib.redirect_stderr(io.StringIO()) as error:
                with self.assertRaises(SystemExit) as result:
                    runpy.run_path(str(RUNNER), run_name='__main__')
                self.assertEqual(2, result.exception.code)
                self.assertIn('WSL2', error.getvalue())
                command.assert_not_called()
                mkdir.assert_not_called()

    def test_imported_helpers_cannot_bypass_guard(self):
        spec = importlib.util.spec_from_file_location('guarded_runtime', RUNNER)
        runtime = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(runtime)
        with patch.object(sys, 'platform', 'win32'), patch('subprocess.run') as command:
            with self.assertRaisesRegex(RuntimeError, 'Linux'):
                runtime.docker('info')
            with self.assertRaisesRegex(RuntimeError, 'Linux'):
                runtime.exists('synthetic-container')
            command.assert_not_called()


if __name__ == '__main__':
    unittest.main(verbosity=2)
