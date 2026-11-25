// Real cryptographic tests against the same vendored bundle used by browsers.
// Synthetic accounts only. No network, database, files, keys, or plaintext logs.
import assert from 'node:assert/strict';
import { webcrypto, createHash } from 'node:crypto';
import { TextDecoder, TextEncoder } from 'node:util';
import { ReadableStream, TransformStream, WritableStream } from 'node:stream/web';

globalThis.crypto ??= webcrypto;
globalThis.TextEncoder ??= TextEncoder;
globalThis.TextDecoder ??= TextDecoder;
globalThis.ReadableStream ??= ReadableStream;
globalThis.TransformStream ??= TransformStream;
globalThis.WritableStream ??= WritableStream;
const pgp = await import('../assets/vendor/openpgp/openpgp.min.mjs');
const crypto = await import('../assets/js/e2ee-crypto.mjs');
const encode = value => new TextEncoder().encode(value);
const base64 = value => Buffer.from(value).toString('base64');
const decode64 = value => new Uint8Array(Buffer.from(value, 'base64'));
const hash = value => createHash('sha256').update(value).digest('hex');
const PASSWORD = 'Synthetic-device-only-passphrase-47';
const MESSAGE = 'Synthetic private message canary: 7cb32ef0 📨';
const FILE = 'Synthetic private attachment canary: d3526289';
const config = { v6Keys: true, aeadProtect: true, preferredAEADAlgorithm: pgp.enums.aead.gcm, preferredCompressionAlgorithm: pgp.enums.compression.uncompressed };
const checks = [];
function test(name, fn) { checks.push([name, fn]); }
test('ed25519_capability_check_verifies_known_signature_without_generating_keys', async () => {
  const subtle=globalThis.crypto.subtle;
  const generateKey=subtle.generateKey;
  subtle.generateKey=() => { throw new DOMException('Synthetic transient key generation failure','OperationError'); };
  try { assert.equal(await crypto.supportsIdentityVerification(),true); }
  finally { subtle.generateKey=generateKey; }
});
function account(result) {
  const { manifest, signature, recovery_cipher } = result.request;
  return { manifest, signature, hash: hash(manifest), recovery_cipher };
}
async function trust(bundle, user) {
  const inspected = await crypto.verifyBundle(bundle, user);
  // The test knows the synthetic identity; production requires explicit comparison.
  return crypto.verifyBundle(bundle, user, inspected.pin);
}
function record(request, self, peer, attachments = []) {
  return {
    id: 1, listing_id: request.listing_id, sender_id: JSON.parse(self.manifest).user_id,
    receiver_id: request.peer_id, device_id: request.device_id, client_id: request.client_id,
    sender_version: request.sender_version, receiver_version: request.receiver_version,
    ciphertext: request.ciphertext, created_at: '2026-11-11 12:00:00',
    sender_manifest: self, receiver_manifest: peer,
    attachments: attachments.map(({ id, sha256, bytes }) => ({ id, sha256, bytes })),
  };
}
function routing() { return { listing_id: 73, client_id: crypto.randomId() }; }
function pins(a, b) { return { 101: a.pin, 202: b.pin }; }
async function unlockVault(local, password = PASSWORD) {
  const result = await pgp.decrypt({ message: await pgp.readMessage({ binaryMessage: decode64(local.vault_cipher) }), passwords: [password], format: 'utf8' });
  return JSON.parse(result.data);
}
async function crafted(state, verifiedSelf, verifiedPeer, payload, { unsigned = false, compress = false } = {}) {
  const keys = [];
  for (const bundle of [verifiedSelf, verifiedPeer]) {
    for (const device of bundle.manifest.devices) if (!device.revoked) keys.push(await pgp.readKey({ armoredKey: device.public_key }));
    keys.push(await pgp.readKey({ armoredKey: bundle.manifest.recovery_public }));
  }
  return base64(await pgp.encrypt({
    message: await pgp.createMessage({ binary: encode(JSON.stringify(payload)) }),
    encryptionKeys: keys, ...(unsigned ? {} : { signingKeys: state.deviceKey }), format: 'binary',
    config: { ...config, preferredCompressionAlgorithm: compress ? pgp.enums.compression.zlib : pgp.enums.compression.uncompressed },
  }));
}
function noLeak(dto, values) {
  const serialized = JSON.stringify(dto);
  for (const value of values) {
    assert.equal(serialized.includes(value), false, 'plaintext must not occur in server DTO');
    assert.equal(serialized.includes(base64(encode(value))), false, 'base64 is not encryption');
  }
  assert.equal(serialized.includes('PRIVATE KEY'), false);
  assert.equal(serialized.includes('identity_private'), false);
}
let a1, b1, a2, b2, a3, ba1, bb1, ba2, bb2, ba3;
let sa1, sb1, sa2, sb2, sa3, va1, vb1, va2, vb2, va3;
let oldRecord, allRecord, fileResult, fileRecord, plaintextFileRecord;

test('initialization validates identities, names and separate device passphrases', async () => {
  for (const args of [[0, 'Device', PASSWORD], [101, '', PASSWORD], [101, 'Device', 'short'], [101, 'Bad\nname', PASSWORD]]) {
    await assert.rejects(crypto.initialize(...args));
  }
  a1 = await crypto.initialize(101, 'Alice laptop', PASSWORD);
  b1 = await crypto.initialize(202, 'Bob laptop', PASSWORD);
  ba1 = account(a1); bb1 = account(b1);
  assert.equal(a1.request.expected_version, 0);
  assert.equal(a1.request.expected_hash, '0'.repeat(64));
  assert.equal(a1.recoveryCode.split('.').length, 6);
  assert.notEqual(a1.local.device_id, b1.local.device_id);
  sa1 = await crypto.unlock(a1.local, PASSWORD); sb1 = await crypto.unlock(b1.local, PASSWORD);
});

test('manifest signatures, identity pins, account binding and exact schemas fail closed', async () => {
  const inspected = await crypto.verifyBundle(ba1, 101);
  assert.equal(inspected.trusted, false); assert.match(inspected.fingerprint, /^[a-f0-9]{64}$/);
  va1 = await trust(ba1, 101); vb1 = await trust(bb1, 202);
  await assert.rejects(crypto.encryptMessage(sa1, va1, await crypto.verifyBundle(bb1, 202), routing(), MESSAGE), /identity_untrusted/);
  await assert.rejects(crypto.verifyBundle(ba1, 202));
  await assert.rejects(crypto.verifyBundle(ba1, 101, vb1.pin), /identity_changed/);
  await assert.rejects(crypto.verifyBundle({ ...ba1, hash: 'f'.repeat(64) }, 101), /manifest_hash/);
  const signature = decode64(ba1.signature); signature[0] ^= 1;
  await assert.rejects(crypto.verifyBundle({ ...ba1, signature: base64(signature) }, 101), /manifest_signature/);
  const changed = JSON.stringify({ ...JSON.parse(ba1.manifest), injected: true });
  await assert.rejects(crypto.verifyBundle({ ...ba1, manifest: changed, hash: hash(changed) }, 101));
  const changedName = ba1.manifest.replace('Alice laptop', 'Other laptop');
  await assert.rejects(crypto.verifyBundle({ ...ba1, manifest: changedName, hash: hash(changedName) }, 101), /manifest_signature/);
});

test('local vault contains only device private key and historical recovery keys', async () => {
  const vault = await unlockVault(a1.local);
  assert.deepEqual(Object.keys(vault).sort(), ['device_id', 'device_private', 'protocol', 'recovery_keys', 'user_id']);
  assert.equal(vault.recovery_keys.length, 0);
  assert.equal(sa1.recoveryKeys.length, 0);
  assert.notEqual(sa1.deviceKey.getFingerprint(), va1.recovery.getFingerprint());
  noLeak(a1.request, [PASSWORD, a1.recoveryCode, vault.device_private]);
  await assert.rejects(crypto.unlock(a1.local, 'Incorrect-password-47'));
  await assert.rejects(crypto.unlock({ ...a1.local, user_id: 202 }, PASSWORD), /vault_binding/);
  await assert.rejects(crypto.unlock({ ...a1.local, device_id: crypto.randomId() }, PASSWORD), /vault_binding/);
});

test('signed encrypted messages reach both participants without server plaintext', async () => {
  const request = await crypto.encryptMessage(sa1, va1, vb1, routing(), MESSAGE);
  oldRecord = record(request, ba1, bb1);
  noLeak(request, [MESSAGE, PASSWORD, a1.recoveryCode]);
  const message = await pgp.readMessage({ binaryMessage: decode64(request.ciphertext) });
  assert.equal(message.packets.some(packet => packet.constructor.tag === pgp.enums.packet.symEncryptedIntegrityProtectedData && packet.version === 2), true, 'RFC9580 AEAD encrypted-data packet');
  assert.equal((await crypto.decryptMessage(sb1, oldRecord, pins(va1, vb1))).body, MESSAGE);
  assert.equal((await crypto.decryptMessage(sa1, oldRecord, pins(va1, vb1))).body, MESSAGE);
  await assert.rejects(crypto.decryptMessage(sb1, oldRecord, {}), /identity_untrusted/);
});

test('recovery rejects wrong secret, altered backup, stale code and invalid revocations', async () => {
  const fields = a1.recoveryCode.split('.'); fields[5] = Buffer.alloc(32, 7).toString('base64url');
  await assert.rejects(crypto.recover(ba1, fields.join('.'), 'New device', PASSWORD));
  const altered = decode64(ba1.recovery_cipher); altered[altered.length - 3] ^= 1;
  await assert.rejects(crypto.recover({ ...ba1, recovery_cipher: base64(altered) }, a1.recoveryCode, 'New device', PASSWORD), /recovery_hash/);
  await assert.rejects(crypto.recover(ba1, a1.recoveryCode, 'New device', PASSWORD, [crypto.randomId()]), /invalid_revocation/);
  await assert.rejects(crypto.recover(ba1, a1.recoveryCode, 'New device', PASSWORD, [a1.local.device_id, a1.local.device_id]));
});

test('two users with two independent devices preserve old history and rotate recovery', async () => {
  a2 = await crypto.recover(ba1, a1.recoveryCode, 'Alice phone', PASSWORD);
  b2 = await crypto.recover(bb1, b1.recoveryCode, 'Bob phone', PASSWORD);
  ba2 = account(a2); bb2 = account(b2);
  va2 = await crypto.verifyBundle(ba2, 101, va1.pin); vb2 = await crypto.verifyBundle(bb2, 202, vb1.pin);
  sa2 = await crypto.unlock(a2.local, PASSWORD); sb2 = await crypto.unlock(b2.local, PASSWORD);
  assert.equal(a2.request.expected_version, 1); assert.equal(a2.request.expected_hash, ba1.hash);
  assert.equal(va2.manifest.devices.length, 2);
  assert.equal(va2.manifest.previous_hash, ba1.hash);
  assert.equal(va2.manifest.identity_key, va1.manifest.identity_key);
  assert.notEqual(va2.manifest.recovery_public, va1.manifest.recovery_public);
  assert.notEqual(a2.recoveryCode.split('.').at(-1), a1.recoveryCode.split('.').at(-1));
  assert.notEqual(sa1.deviceKey.getFingerprint(), sa2.deviceKey.getFingerprint());
  assert.equal(sa2.recoveryKeys.length, 1);
  assert.equal(sa2.recoveryKeys[0].getFingerprint(), va1.recovery.getFingerprint());
  assert.equal(sa2.recoveryKeys.some(key => key.getFingerprint() === va2.recovery.getFingerprint()), false);
  const vault = await unlockVault(a2.local);
  assert.deepEqual(Object.keys(vault).sort(), ['device_id', 'device_private', 'protocol', 'recovery_keys', 'user_id']);
  assert.equal((await crypto.decryptMessage(sa2, oldRecord, pins(va2, vb2))).body, MESSAGE);
  assert.equal((await crypto.decryptMessage(sb2, oldRecord, pins(va2, vb2))).body, MESSAGE);
  await assert.rejects(crypto.recover(ba2, a1.recoveryCode, 'Old code', PASSWORD), /recovery_version/);
  await assert.rejects(crypto.recover(ba1, a2.recoveryCode, 'Rollback', PASSWORD), /manifest_rollback/);
  await assert.rejects(crypto.verifyBundle(ba1, 101, va2.pin), /manifest_rollback/);
  const historical = await crypto.verifyBundle(ba1, 101, va2.pin, { historical: true });
  await assert.rejects(crypto.encryptMessage(sa1, historical, vb2, routing(), MESSAGE), /identity_untrusted/);
  await assert.rejects(crypto.verifyBundle(ba2, 101, { ...va2.pin, hash: 'e'.repeat(64) }), /manifest_conflict/);
  const request = await crypto.encryptMessage(sa2, va2, vb2, routing(), MESSAGE);
  allRecord = record(request, ba2, bb2);
  for (const state of [sa1, sa2, sb1, sb2]) {
    assert.equal((await crypto.decryptMessage(state, allRecord, pins(va2, vb2))).body, MESSAGE);
  }
});

test('context substitution, wrong device, unsigned and wrongly signed messages fail', async () => {
  for (const change of [{ listing_id: 74 }, { client_id: crypto.randomId() }, { device_id: sa1.device_id }, { sender_version: 1 }]) {
    await assert.rejects(crypto.decryptMessage(sb2, { ...allRecord, ...change }, pins(va2, vb2)));
  }
  const plain = await crypto.decryptMessage(sb2, allRecord, pins(va2, vb2));
  const unsigned = await crafted(sa2, va2, vb2, plain, { unsigned: true });
  await assert.rejects(crypto.decryptMessage(sb2, { ...allRecord, ciphertext: unsigned }, pins(va2, vb2)));
  const wrongSigner = await crafted(sb2, va2, vb2, plain);
  await assert.rejects(crypto.decryptMessage(sb2, { ...allRecord, ciphertext: wrongSigner }, pins(va2, vb2)));
  const wrongContext = await crafted(sa2, va2, vb2, { ...plain, listing_id: 74 });
  await assert.rejects(crypto.decryptMessage(sb2, { ...allRecord, ciphertext: wrongContext }, pins(va2, vb2)), /message_context/);
  const tamper = decode64(allRecord.ciphertext); tamper[tamper.length - 5] ^= 1;
  await assert.rejects(crypto.decryptMessage(sb2, { ...allRecord, ciphertext: base64(tamper) }, pins(va2, vb2)));
  await assert.rejects(crypto.decryptMessage(sb2, { ...allRecord, ciphertext: allRecord.ciphertext.slice(0, -8) }, pins(va2, vb2)));
});

test('attachments keep names, MIME and bytes encrypted and require verified messages', async () => {
  const route = routing();
  fileResult = await crypto.encryptAttachment(sa2, va2, vb2, route, { id: crypto.randomId(), bytes: encode(FILE), name: 'private-synthetic-note.txt', mime: 'text/plain' });
  noLeak(fileResult.request, [FILE, fileResult.descriptor.name, 'text/plain']);
  assert.equal(fileResult.descriptor.bytes, decode64(fileResult.request.ciphertext).length);
  assert.equal(fileResult.descriptor.sha256, hash(decode64(fileResult.request.ciphertext)));
  const request = await crypto.encryptMessage(sa2, va2, vb2, route, '', [fileResult.descriptor]);
  fileRecord = record(request, ba2, bb2, [fileResult.descriptor]);
  await assert.rejects(crypto.decryptAttachment(sb2, fileRecord, fileResult.descriptor, decode64(fileResult.request.ciphertext)), /message_not_verified/);
  plaintextFileRecord = await crypto.decryptMessage(sb2, fileRecord, pins(va2, vb2));
  for (const message of [fileRecord, plaintextFileRecord]) {
    const file = await crypto.decryptAttachment(sb2, message, plaintextFileRecord.attachments[0], decode64(fileResult.request.ciphertext));
    assert.deepEqual(file.bytes, encode(FILE)); assert.equal(file.name, 'private-synthetic-note.txt');
  }
  assert.throws(() => { plaintextFileRecord.attachments[0].name = 'changed.txt'; }, TypeError);
  assert.equal(Object.isFrozen(plaintextFileRecord), true);
});

test('attachment tampering, substitution and outer metadata mismatch fail closed', async () => {
  const encrypted = decode64(fileResult.request.ciphertext); encrypted[encrypted.length - 5] ^= 1;
  await assert.rejects(crypto.decryptAttachment(sb2, plaintextFileRecord, fileResult.descriptor, encrypted), /attachment_hash/);
  await assert.rejects(crypto.decryptAttachment(sb2, plaintextFileRecord, { ...fileResult.descriptor, name: 'changed.txt' }, decode64(fileResult.request.ciphertext)), /attachment_binding/);
  await assert.rejects(crypto.decryptAttachment(sb2, structuredClone(plaintextFileRecord), fileResult.descriptor, decode64(fileResult.request.ciphertext)), /message_not_verified/);
  await assert.rejects(crypto.decryptMessage(sb2, { ...fileRecord, attachments: [] }, pins(va2, vb2)), /attachment_binding/);
  await assert.rejects(crypto.decryptMessage(sb2, { ...fileRecord, attachments: [{ ...fileRecord.attachments[0], sha256: 'f'.repeat(64) }] }, pins(va2, vb2)), /attachment_binding/);
  const wrongFile = await crypto.encryptAttachment(sa2, va2, vb2, { listing_id: 74, client_id: fileRecord.client_id }, { id: crypto.randomId(), bytes: encode(FILE), name: 'wrong-route.txt', mime: 'text/plain' });
  const request = await crypto.encryptMessage(sa2, va2, vb2, { listing_id: 73, client_id: fileRecord.client_id }, '', [wrongFile.descriptor]);
  const wrongRecord = record(request, ba2, bb2, [wrongFile.descriptor]);
  const wrongPlain = await crypto.decryptMessage(sb2, wrongRecord, pins(va2, vb2));
  await assert.rejects(crypto.decryptAttachment(sb2, wrongPlain, wrongFile.descriptor, decode64(wrongFile.request.ciphertext)), /message_context/);
});

test('plaintext, ciphertext, Unicode, attachment and decompression limits are enforced', async () => {
  const route = routing();
  await assert.rejects(crypto.encryptMessage(sa2, va2, vb2, route, 'a'.repeat(5001)), /message_length/);
  await assert.rejects(crypto.encryptMessage(sa2, va2, vb2, route, '   '), /message_length/);
  await assert.rejects(crypto.encryptMessage(sa2, va2, vb2, { ...route, sender_id: 202 }, 'body'), /routing_mismatch/);
  const request = await crypto.encryptMessage(sa2, va2, vb2, route, '📨'.repeat(5000));
  assert.equal(Array.from((await crypto.decryptMessage(sb2, record(request, ba2, bb2), pins(va2, vb2))).body).length, 5000);
  await assert.rejects(crypto.encryptMessage(sa2, va2, vb2, route, '', Array(4).fill(fileResult.descriptor)), /attachment_limit/);
  await assert.rejects(crypto.encryptMessage(sa2, va2, vb2, route, '', [fileResult.descriptor, fileResult.descriptor]));
  for (const file of [
    { bytes: encode('<svg/>'), name: 'unsafe.svg', mime: 'image/svg+xml' },
    { bytes: encode('<html/>'), name: 'unsafe.html', mime: 'text/html' },
    { bytes: encode('<html/>'), name: 'disguised.html', mime: 'text/plain' },
    { bytes: encode('MZ'), name: 'disguised.exe', mime: 'application/pdf' },
    { bytes: encode('text'), name: 'hidden\u202efile.txt', mime: 'text/plain' },
    { bytes: encode('file'), name: '../secret.txt', mime: 'text/plain' },
    { bytes: new Uint8Array(3 * 1024 * 1024 + 1), name: 'too-large.txt', mime: 'text/plain' },
  ]) await assert.rejects(crypto.encryptAttachment(sa2, va2, vb2, route, { ...file, id: crypto.randomId() }));
  const large = await crypto.encryptAttachment(sa2, va2, vb2, route, { id: crypto.randomId(), bytes: new Uint8Array(3 * 1024 * 1024).fill(35), name: 'boundary.txt', mime: 'text/plain' });
  const withFile = record(await crypto.encryptMessage(sa2, va2, vb2, route, '', [large.descriptor]), ba2, bb2, [large.descriptor]);
  const plain = await crypto.decryptMessage(sb2, withFile, pins(va2, vb2));
  assert.equal((await crypto.decryptAttachment(sb2, plain, large.descriptor, decode64(large.request.ciphertext))).bytes.length, 3 * 1024 * 1024);
  const original = await crypto.decryptMessage(sb2, allRecord, pins(va2, vb2));
  const bomb = await crafted(sa2, va2, vb2, { ...original, body: 'x'.repeat(192 * 1024) }, { compress: true });
  await assert.rejects(crypto.decryptMessage(sb2, { ...allRecord, ciphertext: bomb }, pins(va2, vb2)));
  await assert.rejects(crypto.decryptMessage(sb2, { ...allRecord, ciphertext: base64(new Uint8Array(128 * 1024 + 1)) }, pins(va2, vb2)));
});

test('device revocation rotates recovery and excludes the revoked device from future traffic', async () => {
  a3 = await crypto.recover(ba2, a2.recoveryCode, 'Alice replacement phone', PASSWORD, [a2.local.device_id]);
  ba3 = account(a3); va3 = await crypto.verifyBundle(ba3, 101, va2.pin); sa3 = await crypto.unlock(a3.local, PASSWORD);
  assert.equal(va3.manifest.devices.find(device => device.id === a2.local.device_id).revoked, true);
  assert.equal(sa2.recoveryKeys.some(key => key.getFingerprint() === va3.recovery.getFingerprint()), false);
  assert.equal(sa3.recoveryKeys.length, 2);
  assert.equal(sa3.recoveryKeys.some(key => key.getFingerprint() === va3.recovery.getFingerprint()), false);
  await assert.rejects(crypto.encryptMessage(sa2, va3, vb2, routing(), MESSAGE), /device_revoked/);
  const route = routing();
  const futureFile = await crypto.encryptAttachment(sb2, vb2, va3, route, { id: crypto.randomId(), bytes: encode(FILE), name: 'after-revocation.txt', mime: 'text/plain' });
  const request = await crypto.encryptMessage(sb2, vb2, va3, route, MESSAGE, [futureFile.descriptor]);
  const future = record(request, bb2, ba3, [futureFile.descriptor]);
  const currentPins = pins(va3, vb2);
  await assert.rejects(crypto.decryptMessage(sa2, future, currentPins));
  for (const state of [sa1, sa3, sb1, sb2]) {
    const plain = await crypto.decryptMessage(state, future, currentPins);
    assert.equal(plain.body, MESSAGE);
    assert.deepEqual((await crypto.decryptAttachment(state, plain, futureFile.descriptor, decode64(futureFile.request.ciphertext))).bytes, encode(FILE));
  }
  // Bypass application checks: possession of the revoked device's entire vault
  // still must not decrypt new message or attachment packets using OpenPGP itself.
  for (const ciphertext of [request.ciphertext, futureFile.request.ciphertext]) {
    await assert.rejects(pgp.decrypt({
      message: await pgp.readMessage({ binaryMessage: decode64(ciphertext) }),
      decryptionKeys: [sa2.deviceKey, ...sa2.recoveryKeys], format: 'binary',
    }));
  }
  await assert.rejects(pgp.decrypt({
    message: await pgp.readMessage({ binaryMessage: decode64(ba3.recovery_cipher) }),
    passwords: [a2.recoveryCode.split('.').at(-1)], format: 'binary',
  }));
  // Revocation cannot erase a device's previously received messages.
  assert.equal((await crypto.decryptMessage(sa2, allRecord, currentPins)).body, MESSAGE);
  assert.equal((await crypto.decryptMessage(sa3, allRecord, currentPins)).body, MESSAGE);
  await assert.rejects(crypto.recover(ba3, a2.recoveryCode, 'Old authority backup', PASSWORD), /recovery_version/);
  await assert.rejects(crypto.recover(ba3, a3.recoveryCode, 'Reactivate', PASSWORD, [a2.local.device_id]), /invalid_revocation/);
});

test('a replaced authority has independent recovery and explicitly anchored history', async () => {
  const replacement = await crypto.prepareIdentityReset(ba3, 101, 'Replacement authority', PASSWORD);
  assert.equal(replacement.request.action, 'reset_identity');
  assert.equal(replacement.request.expected_version, 3);
  assert.equal(replacement.request.expected_hash, ba3.hash);
  const replaced = account(replacement);
  const inspected = await crypto.verifyBundle(replaced, 101);
  assert.notEqual(inspected.manifest.identity_key, va3.manifest.identity_key);
  assert.equal(inspected.manifest.version, 4);
  assert.equal(inspected.manifest.devices.length, 1);
  assert.equal(inspected.trusted, false);
  await assert.rejects(crypto.verifyBundle(replaced, 101, va3.pin), /identity_changed/);
  const verified = await trust(replaced, 101);
  const recovered = await crypto.recover(replaced, replacement.recoveryCode, 'Recovered new epoch', PASSWORD);
  const recoveredState = await crypto.unlock(recovered.local, PASSWORD);
  assert.equal(recoveredState.recoveryKeys.length, 1);
  assert.equal(recoveredState.recoveryKeys[0].getFingerprint(), verified.recovery.getFingerprint());
  await assert.rejects(crypto.recover(replaced, a3.recoveryCode, 'Retired recovery', PASSWORD), /identity_changed/);

  const newState = await crypto.unlock(replacement.local, PASSWORD);
  const history = { retiredPins: { 101: [va2.pin] }, loadChain: (id, from, through) => {
    assert.deepEqual([id, from, through], [101, 2, 2]); return [ba2];
  } };
  const currentPins = pins(verified, vb2);
  await assert.rejects(crypto.decryptMessage(sb1, oldRecord, currentPins, { retiredPins: { 101: [] } }), /history_unanchored/);
  await assert.rejects(crypto.decryptMessage(sb1, oldRecord, currentPins, { ...history, loadChain: () => [ba3] }), /history_unanchored/);
  assert.equal((await crypto.decryptMessage(sb1, oldRecord, currentPins, history)).body, MESSAGE);
  await assert.rejects(crypto.decryptMessage(newState, oldRecord, currentPins, history));
  const restoredArchive = crypto.includeHistoricalVault(newState, sa1);
  assert.equal((await crypto.decryptMessage(restoredArchive, oldRecord, currentPins, history)).body, MESSAGE);
  assert.throws(() => crypto.includeHistoricalVault(newState, sb1), /vault_binding/);
  const next = await crypto.encryptMessage(restoredArchive, verified, vb2, routing(), MESSAGE);
  const nextRecord = record(next, replaced, bb2);
  assert.equal((await crypto.decryptMessage(sb2, nextRecord, currentPins)).body, MESSAGE);
  await assert.rejects(crypto.decryptMessage(sa1, nextRecord, currentPins));
});

test('foreign account, fabricated verified objects and mutated pins cannot bypass trust', async () => {
  await assert.rejects(crypto.encryptMessage(sa1, { ...va3 }, vb2, routing(), MESSAGE), /identity_untrusted/);
  await assert.rejects(crypto.encryptMessage({ ...sa1 }, va3, vb2, routing(), MESSAGE), /vault_locked/);
  await assert.rejects(crypto.decryptMessage(sa1, { ...allRecord, sender_id: 303 }, pins(va3, vb2)), /recipient_mismatch/);
  await assert.rejects(crypto.decryptMessage(sb1, allRecord, { 101: vb2.pin, 202: vb2.pin }), /identity_changed/);
  assert.throws(() => { va3.pin.version = 1; }, TypeError);
  assert.throws(() => { va3.manifest.devices[0].revoked = false; }, TypeError);
});

let passed = 0;
try {
  for (const [name, run] of checks) { await run(); passed++; console.log(`PASS ${name}`); }
  console.log(`E2EE crypto: ${passed}/${checks.length} groups passed (real OpenPGP.js 6.3.2; no DB/browser/provider test implied).`);
} catch (error) {
  // Do not dump assertion payloads, keys, decrypted messages or the minified module.
  console.error(`FAIL group ${passed + 1}: ${checks[passed][0]} (${error.name}: ${error.code || 'cryptographic assertion failed'})`);
  const frames = String(error.stack).split('\n').filter(line => /^\s+at /.test(line) && /(?:e2ee_crypto_test|e2ee-crypto)\.mjs:\d+:\d+\)?$/.test(line));
  for (const frame of frames.slice(0, 3)) console.error(frame);
  process.exitCode = 1;
}
