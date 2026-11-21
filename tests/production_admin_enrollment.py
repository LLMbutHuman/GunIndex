"""Exercise the administrator prompt on a hosted installer-test terminal."""
import base64
import hmac
import os
import pty
import re
import secrets
import select
import signal
import struct
import sys
import time


assert os.environ.get('GITHUB_ACTIONS') == 'true'
assert os.environ.get('RUNNER_ENVIRONMENT') == 'github-hosted'
compose = sys.argv[1]
assert compose == '/srv/gunindex/compose.sh'
password = secrets.token_urlsafe(28).encode()
child, terminal = pty.fork()
if child == 0:
    os.execvp('sudo', ['sudo', compose, 'exec', 'php', 'php', 'ops/create-admin.php'])

pending = b''
finished = False


def expect(prompt):
    global pending
    deadline = time.monotonic() + 20
    while True:
        match = re.search(prompt, pending)
        if match:
            pending = pending[match.end():]
            return match.groups()
        remaining = deadline - time.monotonic()
        if remaining <= 0 or not select.select([terminal], [], [], remaining)[0]:
            raise RuntimeError('The installer did not reach the expected administrator prompt.')
        pending += os.read(terminal, 4096)
        if password in pending:
            raise RuntimeError('The terminal echoed the administrator password.')


def answer(value):
    os.write(terminal, value + b'\n')


try:
    expect(b'Benutzername: ')
    answer(b'installer_test_admin')
    expect(b'E-Mail: ')
    answer(b'installer@example.invalid')
    expect(rb'Passwort \(mindestens [^\r\n]*\): ')
    answer(password)
    expect(b'Passwort wiederholen: ')
    answer(password)
    (key,) = expect(rb'TOTP-Schl[^\r\n]*: ([A-Z2-7]{32})')
    expect(b'Aktueller Code aus der App: ')
    counter = int(time.time()) // 30
    mac = hmac.digest(base64.b32decode(key), struct.pack('>Q', counter), 'sha1')
    offset = mac[-1] & 15
    code = (int.from_bytes(mac[offset:offset + 4], 'big') & 0x7fffffff) % 1000000
    answer(f'{code:06d}'.encode())
    expect(b'Administrator angelegt.')
    _, status = os.waitpid(child, 0)
    finished = True
    assert os.waitstatus_to_exitcode(status) == 0
finally:
    if not finished:
        os.kill(child, signal.SIGTERM)
        os.waitpid(child, 0)
    os.close(terminal)

print('First administrator enrollment required a confirmed TOTP code and hid the password.')
