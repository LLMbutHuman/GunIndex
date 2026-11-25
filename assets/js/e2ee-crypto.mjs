import * as pgp from '../vendor/openpgp/openpgp.min.mjs';

// Application trust/binding rules live here; all encryption uses OpenPGP.js.
// This protocol does not provide forward secrecy or post-compromise security.
const utf8 = new TextEncoder();
const decoder = new TextDecoder('utf-8', { fatal: true });
const DOMAIN = utf8.encode('GunIndex device manifest v1\0');
const ZERO = '0'.repeat(64);
const MAX_FILE = 3 * 1024 * 1024;
const MAX_CIPHER = 5 * 1024 * 1024;
const MAX_MESSAGE = 128 * 1024;
const MAX_BACKUP = 1024 * 1024;
const MAX_MANIFEST = 256 * 1024;
const MIME = new Set(['text/plain', 'application/pdf', 'image/png', 'image/jpeg', 'image/webp']);
const CONFIG = Object.freeze({
  // Key instance methods expect a complete config; the top-level API alone
  // merges partial overrides with defaults.
  ...pgp.config,
  v6Keys: true, aeadProtect: true,
  preferredAEADAlgorithm: pgp.enums.aead.gcm,
  preferredSymmetricAlgorithm: pgp.enums.symmetric.aes256,
  preferredCompressionAlgorithm: pgp.enums.compression.uncompressed,
  maxDecompressedMessageSize: MAX_CIPHER,
  allowUnauthenticatedMessages: false,
  allowUnauthenticatedStream: false,
});
const verifiedBundles = new WeakSet();
const unlockedStates = new WeakSet();

function requireValue(ok, code = 'invalid_input') {
  if (!ok) throw new Error(code);
}
function object(value, fields) {
  requireValue(value !== null && typeof value === 'object' && !Array.isArray(value));
  requireValue(Object.keys(value).length === fields.length && fields.every(key => Object.hasOwn(value, key)));
  return value;
}
function integer(value, min = 1, max = 2147483647) {
  requireValue(Number.isSafeInteger(value) && value >= min && value <= max);
  return value;
}
function hex(value, size) {
  requireValue(typeof value === 'string' && new RegExp(`^[a-f0-9]{${size}}$`).test(value));
  return value;
}
function bytes(value, max = MAX_CIPHER) {
  requireValue(value instanceof Uint8Array && value.byteLength <= max);
  return value;
}
function b64(value) {
  let text = '';
  for (let offset = 0; offset < value.length; offset += 8192) {
    text += String.fromCharCode(...value.subarray(offset, offset + 8192));
  }
  return btoa(text);
}
function unb64(value, max = MAX_CIPHER) {
  requireValue(typeof value === 'string' && value.length <= Math.ceil(max / 3) * 4);
  // Avoid a repeated-group regexp: multi-megabyte attachments must not exhaust
  // the regexp engine's stack. Re-encoding below checks canonical padding/bits.
  requireValue(value.length % 4 === 0 && !/[^A-Za-z0-9+/=]/.test(value) && !/=/.test(value.slice(0, -2)));
  const result = Uint8Array.from(atob(value), char => char.charCodeAt(0));
  requireValue(result.length <= max && b64(result) === value);
  return result;
}
function url64(value) { return b64(value).replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_'); }
function unurl64(value, length) {
  requireValue(typeof value === 'string' && /^[A-Za-z0-9_-]+$/.test(value));
  const result = unb64(value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4), length);
  requireValue(result.length === length && url64(result) === value);
  return result;
}
function random(size) { return crypto.getRandomValues(new Uint8Array(size)); }

export async function supportsIdentityVerification() {
  // Public RFC 8032 section 7.1 test vector, with an empty message.
  const bytes = value => Uint8Array.from(value.match(/../g), pair => parseInt(pair, 16));
  const publicKey = bytes('d75a980182b10ab7d54bfed3c964073a0ee172f3daa62325af021a68f707511a');
  const signature = bytes('e5564300c360ac729086e2cc806e828a84877f1eb8e5d974d873e065224901555fb8821590a33bacc61e39701cf9b46bd25bf5f0595bbe24655141438e7a100b');
  try {
    const key = await crypto.subtle.importKey('raw',publicKey,{name:'Ed25519'},false,['verify']);
    return await crypto.subtle.verify('Ed25519',key,signature,new Uint8Array());
  } catch (error) {
    if (error.name === 'NotSupportedError') return false;
    throw error;
  }
}
export function randomId() { return Array.from(random(16), byte => byte.toString(16).padStart(2, '0')).join(''); }
async function hash(value) {
  return Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', value)), byte => byte.toString(16).padStart(2, '0')).join('');
}
export async function identityFingerprint(identityKey) {
  const raw = unb64(identityKey, 32); requireValue(raw.length === 32);
  return hash(raw);
}
function passphrase(value) {
  requireValue(typeof value === 'string' && Array.from(value).length >= 12 && utf8.encode(value).length <= 1024, 'weak_passphrase');
  return value;
}
function deviceName(value) {
  requireValue(typeof value === 'string' && value.trim() === value && Array.from(value).length >= 1 && Array.from(value).length <= 80 && !/[\u0000-\u001f\u007f]/u.test(value));
  return value;
}
function join(a, b) { const result = new Uint8Array(a.length + b.length); result.set(a); result.set(b, a.length); return result; }
function json(value, max) {
  requireValue(typeof value === 'string' && utf8.encode(value).length <= max);
  return JSON.parse(value);
}
async function publicKey(armored) {
  requireValue(typeof armored === 'string' && armored.length <= 16384 && armored.startsWith('-----BEGIN PGP PUBLIC KEY BLOCK-----'));
  const key = await pgp.readKey({ armoredKey: armored, config: CONFIG });
  requireValue(!key.isPrivate() && key.keyPacket.version === 6, 'invalid_public_key');
  await key.verifyPrimaryKey(undefined, undefined, CONFIG);
  await key.getSigningKey(undefined, undefined, undefined, CONFIG);
  await key.getEncryptionKey(undefined, undefined, undefined, CONFIG);
  return key;
}
async function privateKey(armored) {
  requireValue(typeof armored === 'string' && armored.length <= 32768 && armored.startsWith('-----BEGIN PGP PRIVATE KEY BLOCK-----'));
  const key = await pgp.readPrivateKey({ armoredKey: armored, config: CONFIG });
  requireValue(key.keyPacket.version === 6 && key.isDecrypted(), 'invalid_private_key');
  await key.validate(CONFIG);
  return key;
}
async function newKey() {
  return pgp.generateKey({ type: 'curve25519', format: 'object', config: CONFIG });
}
async function passwordEncrypt(payload, password, max) {
  const plain = utf8.encode(JSON.stringify(payload)); requireValue(plain.length <= max);
  const result = await pgp.encrypt({ message: await pgp.createMessage({ binary: plain }), passwords: [password], format: 'binary', config: CONFIG });
  requireValue(result.length <= max);
  return b64(result);
}
async function passwordDecrypt(ciphertext, password, max) {
  const encrypted = unb64(ciphertext, max); requireValue(encrypted.length > 0);
  const result = await pgp.decrypt({ message: await pgp.readMessage({ binaryMessage: encrypted, config: CONFIG }), passwords: [password], format: 'binary', config: { ...CONFIG, maxDecompressedMessageSize: max } });
  requireValue(result.data.length <= max);
  return json(decoder.decode(result.data), max);
}
function manifestShape(manifest, expectedUser) {
  object(manifest, ['protocol', 'user_id', 'version', 'previous_hash', 'identity_key', 'recovery_public', 'recovery_hash', 'devices']);
  requireValue(manifest.protocol === 'gunindex-devices-1' && manifest.user_id === integer(expectedUser));
  integer(manifest.version); hex(manifest.previous_hash, 64); hex(manifest.recovery_hash, 64);
  requireValue(manifest.version !== 1 || manifest.previous_hash === ZERO);
  requireValue(unb64(manifest.identity_key, 32).length === 32);
  requireValue(Array.isArray(manifest.devices) && manifest.devices.length >= 1 && manifest.devices.length <= 64);
  let active = 0; const ids = new Set(); const fingerprints = new Set();
  for (const device of manifest.devices) {
    object(device, ['id', 'name', 'public_key', 'fingerprint', 'revoked']);
    hex(device.id, 32); deviceName(device.name); hex(device.fingerprint, 64);
    requireValue(typeof device.revoked === 'boolean' && !ids.has(device.id) && !fingerprints.has(device.fingerprint));
    ids.add(device.id); fingerprints.add(device.fingerprint); if (!device.revoked) active++;
  }
  requireValue(active >= 1 && active <= 10, 'device_limit');
}

async function signedManifest(bundle, expectedUser) {
  requireValue(bundle !== null && typeof bundle === 'object');
  const manifest = json(bundle.manifest, MAX_MANIFEST);
  manifestShape(manifest, expectedUser);
  hex(bundle.hash, 64); requireValue(await hash(utf8.encode(bundle.manifest)) === bundle.hash, 'manifest_hash');
  const signature = unb64(bundle.signature, 64); requireValue(signature.length === 64);
  const identity = await crypto.subtle.importKey('raw', unb64(manifest.identity_key, 32), { name: 'Ed25519' }, false, ['verify']);
  requireValue(await crypto.subtle.verify('Ed25519', identity, signature, join(DOMAIN, utf8.encode(bundle.manifest))), 'manifest_signature');
  return manifest;
}

/** Null pin permits inspection only. Explicitly compared identity must be pinned before encryption. */
export async function verifyBundle(bundle, expectedUser, pin = null, { historical = false } = {}) {
  const manifest = await signedManifest(bundle, expectedUser);
  if (pin !== null) {
    object(pin, ['identity_key', 'version', 'hash']); integer(pin.version); hex(pin.hash, 64);
    requireValue(pin.identity_key === manifest.identity_key, 'identity_changed');
    requireValue(historical ? manifest.version <= pin.version : manifest.version >= pin.version, 'manifest_rollback');
    requireValue(manifest.version !== pin.version || bundle.hash === pin.hash, 'manifest_conflict');
    if (!historical && manifest.version === pin.version + 1) requireValue(manifest.previous_hash === pin.hash, 'manifest_chain');
  }
  const recovery = await publicKey(manifest.recovery_public);
  const keys = new Map();
  for (const device of manifest.devices) {
    const key = await publicKey(device.public_key);
    requireValue(key.getFingerprint() === device.fingerprint, 'device_fingerprint');
    requireValue(key.getFingerprint() !== recovery.getFingerprint(), 'duplicate_key');
    keys.set(device.id, key);
    Object.freeze(device);
  }
  Object.freeze(manifest.devices); Object.freeze(manifest);
  const result = Object.freeze({
    manifest, hash: bundle.hash, signature: bundle.signature,
    pin: Object.freeze({ identity_key: manifest.identity_key, version: manifest.version, hash: bundle.hash }),
    fingerprint: await identityFingerprint(manifest.identity_key), trusted: pin !== null, historical,
    keys, recovery,
  });
  verifiedBundles.add(result);
  return result;
}

async function accountResult(userId, name, password, previous, identityPrivate, identityPublic, oldRecovery, revokeIds, { reset = false, epochVersion = 1 } = {}) {
  integer(userId); deviceName(name); passphrase(password);
  requireValue(Array.isArray(revokeIds) && new Set(revokeIds).size === revokeIds.length);
  const deviceKeys = await newKey(); const recoveryKeys = await newKey();
  const deviceId = randomId();
  const devices = previous && !reset ? previous.manifest.devices.map(device => ({ ...device })) : [];
  for (const id of revokeIds) {
    hex(id, 32); const device = devices.find(entry => entry.id === id);
    requireValue(device && !device.revoked, 'invalid_revocation'); device.revoked = true;
  }
  devices.push({ id: deviceId, name, public_key: deviceKeys.publicKey.armor(), fingerprint: deviceKeys.publicKey.getFingerprint(), revoked: false });
  const version = previous ? previous.manifest.version + 1 : 1;
  if (reset) epochVersion = version;
  const secret = random(32);
  const recoveryCipher = await passwordEncrypt({
    protocol: epochVersion === 1 ? 'gunindex-recovery-1' : 'gunindex-recovery-2', user_id: userId,
    ...(epochVersion === 1 ? {} : { epoch_version: epochVersion }),
    identity_private: b64(new Uint8Array(await crypto.subtle.exportKey('pkcs8', identityPrivate))),
    recovery_keys: [...oldRecovery, recoveryKeys.privateKey.armor()],
  }, url64(secret), MAX_BACKUP);
  const manifestObject = {
    protocol: 'gunindex-devices-1', user_id: userId, version,
    previous_hash: previous ? previous.hash : ZERO, identity_key: identityPublic,
    recovery_public: recoveryKeys.publicKey.armor(), recovery_hash: await hash(unb64(recoveryCipher, MAX_BACKUP)), devices,
  };
  manifestShape(manifestObject, userId);
  const manifest = JSON.stringify(manifestObject);
  requireValue(utf8.encode(manifest).length <= MAX_MANIFEST, 'manifest_limit');
  const manifestHash = await hash(utf8.encode(manifest));
  const signature = b64(new Uint8Array(await crypto.subtle.sign('Ed25519', identityPrivate, join(DOMAIN, utf8.encode(manifest)))));
  const vaultCipher = await passwordEncrypt({
    protocol: 'gunindex-vault-1', user_id: userId, device_id: deviceId,
    device_private: deviceKeys.privateKey.armor(), recovery_keys: oldRecovery,
  }, password, MAX_BACKUP);
  return {
    request: { action: reset ? 'reset_identity' : 'account', expected_version: previous ? previous.manifest.version : 0, expected_hash: previous ? previous.hash : ZERO, manifest, signature, recovery_cipher: recoveryCipher },
    local: { user_id: userId, device_id: deviceId, vault_cipher: vaultCipher },
    recoveryCode: `GX1.${userId}.${version}.${manifestHash}.${url64(unb64(identityPublic, 32))}.${url64(secret)}`,
  };
}

async function freshIdentity(userId, name, password, previous = null) {
  integer(userId); deviceName(name); passphrase(password);
  const identity = await crypto.subtle.generateKey({ name: 'Ed25519' }, true, ['sign', 'verify']);
  const identityPublic = b64(new Uint8Array(await crypto.subtle.exportKey('raw', identity.publicKey)));
  return accountResult(userId, name, password, previous, identity.privateKey, identityPublic, [], [], { reset: previous !== null });
}

export async function initialize(userId, name, password) {
  return freshIdentity(userId, name, password);
}

export async function prepareIdentityReset(bundle, userId, name, password) {
  const previous = await verifyBundle(bundle, userId);
  return freshIdentity(userId, name, password, previous);
}

export async function recover(bundle, code, name, password, revokeIds = []) {
  requireValue(typeof code === 'string' && code.length < 256, 'invalid_recovery_code');
  const parts = code.split('.');
  requireValue(parts.length === 6 && parts[0] === 'GX1' && /^[1-9]\d*$/.test(parts[1]) && /^[1-9]\d*$/.test(parts[2]), 'invalid_recovery_code');
  const userId = integer(Number(parts[1])); const version = integer(Number(parts[2]));
  hex(parts[3], 64);
  const identityPublic = b64(unurl64(parts[4], 32)); const secret = url64(unurl64(parts[5], 32));
  const current = await verifyBundle(bundle, userId, { identity_key: identityPublic, version, hash: parts[3] });
  requireValue(current.manifest.version === version && current.hash === parts[3], 'recovery_version');
  requireValue(await hash(unb64(bundle.recovery_cipher, MAX_BACKUP)) === current.manifest.recovery_hash, 'recovery_hash');
  const backup = await passwordDecrypt(bundle.recovery_cipher, secret, MAX_BACKUP);
  const backupFields = ['protocol', 'user_id', 'identity_private', 'recovery_keys'];
  const rotated = backup?.protocol === 'gunindex-recovery-2';
  object(backup, rotated ? [...backupFields, 'epoch_version'] : backupFields);
  requireValue((rotated || backup.protocol === 'gunindex-recovery-1') && backup.user_id === userId);
  const epochVersion = rotated ? integer(backup.epoch_version, 2, version) : 1;
  const identityPrivate = await crypto.subtle.importKey('pkcs8', unb64(backup.identity_private, 256), { name: 'Ed25519' }, true, ['sign']);
  const identityKey = await crypto.subtle.importKey('raw', unb64(identityPublic, 32), { name: 'Ed25519' }, false, ['verify']);
  const proof = random(32);
  requireValue(await crypto.subtle.verify('Ed25519', identityKey, await crypto.subtle.sign('Ed25519', identityPrivate, proof), proof), 'recovery_identity');
  const historicalKeys = await recoveryKeyList(backup.recovery_keys);
  requireValue(historicalKeys.length === current.manifest.version - epochVersion + 1, 'recovery_key_count');
  requireValue(historicalKeys.at(-1).getFingerprint() === current.recovery.getFingerprint(), 'recovery_recipient');
  return accountResult(userId, name, password, current, identityPrivate, identityPublic, backup.recovery_keys, revokeIds, { epochVersion });
}

async function recoveryKeyList(armoredKeys) {
  requireValue(Array.isArray(armoredKeys) && armoredKeys.length <= 64);
  const result = []; const seen = new Set();
  for (const armored of armoredKeys) {
    const key = await privateKey(armored);
    requireValue(!seen.has(key.getFingerprint()), 'duplicate_key');
    seen.add(key.getFingerprint()); result.push(key);
  }
  return result;
}

export async function unlock(local, password) {
  object(local, ['user_id', 'device_id', 'vault_cipher']); integer(local.user_id); hex(local.device_id, 32); passphrase(password);
  const vault = await passwordDecrypt(local.vault_cipher, password, MAX_BACKUP);
  object(vault, ['protocol', 'user_id', 'device_id', 'device_private', 'recovery_keys']);
  requireValue(vault.protocol === 'gunindex-vault-1' && vault.user_id === local.user_id && vault.device_id === local.device_id, 'vault_binding');
  const deviceKey = await privateKey(vault.device_private);
  const recoveryKeys = await recoveryKeyList(vault.recovery_keys);
  const state = Object.freeze({ user_id: local.user_id, device_id: local.device_id, deviceKey, recoveryKeys: Object.freeze(recoveryKeys) });
  unlockedStates.add(state);
  return state;
}

/** Add keys for reading the old vault while retaining the current device for sending. */
export function includeHistoricalVault(current, old) {
  requireValue(unlockedStates.has(current) && unlockedStates.has(old) && current.user_id === old.user_id, 'vault_binding');
  const keys = new Map([current.deviceKey, ...current.recoveryKeys].map(key => [key.getFingerprint(), key]));
  const archiveKeys = new Map();
  for (const key of [...(current.archiveKeys ?? []), old.deviceKey, ...old.recoveryKeys]) {
    const fingerprint = key.getFingerprint();
    if (!keys.has(fingerprint)) archiveKeys.set(fingerprint, key);
  }
  const state = Object.freeze({ ...current, archiveKeys: Object.freeze([...archiveKeys.values()]) });
  unlockedStates.add(state);
  return state;
}

function trusted(bundle) { requireValue(verifiedBundles.has(bundle) && bundle.trusted && !bundle.historical, 'identity_untrusted'); }
function route(state, self, peer, routing) {
  requireValue(unlockedStates.has(state), 'vault_locked'); trusted(self); trusted(peer);
  requireValue(self.manifest.user_id === state.user_id && peer.manifest.user_id !== state.user_id);
  const device = self.manifest.devices.find(item => item.id === state.device_id);
  requireValue(device && !device.revoked && device.fingerprint === state.deviceKey.getFingerprint(), 'device_revoked');
  requireValue(!state.recoveryKeys.some(key => key.getFingerprint() === self.recovery.getFingerprint()), 'recovery_key_exposed');
  const result = {
    listing_id: integer(routing.listing_id), sender_id: state.user_id, receiver_id: peer.manifest.user_id,
    sender_device: state.device_id, client_id: hex(routing.client_id, 32),
    sender_version: self.manifest.version, receiver_version: peer.manifest.version,
  };
  for (const key of Object.keys(routing)) {
    requireValue(Object.hasOwn(result, key) && routing[key] === result[key], 'routing_mismatch');
  }
  return result;
}
function recipients(self, peer) {
  const list = [];
  for (const bundle of [self, peer]) {
    for (const device of bundle.manifest.devices) if (!device.revoked) list.push(bundle.keys.get(device.id));
    list.push(bundle.recovery);
  }
  return list;
}
async function encryptPayload(state, self, peer, payload, max) {
  const plain = utf8.encode(JSON.stringify(payload)); requireValue(plain.length <= max);
  const ciphertext = await pgp.encrypt({
    message: await pgp.createMessage({ binary: plain }), encryptionKeys: recipients(self, peer),
    signingKeys: state.deviceKey, format: 'binary', config: CONFIG,
  });
  requireValue(ciphertext.length <= max, 'ciphertext_limit');
  return ciphertext;
}
function descriptor(value) {
  object(value, ['id', 'sha256', 'bytes', 'name', 'mime', 'size']);
  hex(value.id, 32); hex(value.sha256, 64); integer(value.bytes, 1, MAX_CIPHER); fileMetadata(value);
  return value;
}
function fileMetadata(value) {
  requireValue(typeof value.name === 'string' && Array.from(value.name).length >= 1 && Array.from(value.name).length <= 180 && value.name.trim() === value.name && !/[\/\\\u0000-\u001f\u007f\u202a-\u202e\u2066-\u2069]/u.test(value.name));
  requireValue(value.name !== '.' && value.name !== '..' && MIME.has(value.mime), 'file_type');
  const endings = {'text/plain':/\.txt$/i,'application/pdf':/\.pdf$/i,'image/png':/\.png$/i,'image/jpeg':/\.jpe?g$/i,'image/webp':/\.webp$/i};
  requireValue(endings[value.mime].test(value.name), 'file_type');
  integer(value.size, 0, MAX_FILE);
}
function descriptors(values) {
  requireValue(Array.isArray(values) && values.length <= 3, 'attachment_limit');
  const ids = new Set();
  for (const entry of values) { descriptor(entry); requireValue(!ids.has(entry.id)); ids.add(entry.id); }
}

export async function encryptMessage(state, self, peer, routing, body, attachments = []) {
  const binding = route(state, self, peer, routing); descriptors(attachments);
  requireValue(typeof body === 'string' && Array.from(body).length <= 5000 && (body.trim().length > 0 || attachments.length > 0), 'message_length');
  const ciphertext = await encryptPayload(state, self, peer, { protocol: 'gunindex-message-1', ...binding, body, attachments }, MAX_MESSAGE);
  return {
    action: 'send', listing_id: binding.listing_id, peer_id: binding.receiver_id, device_id: binding.sender_device,
    client_id: binding.client_id, sender_version: binding.sender_version, receiver_version: binding.receiver_version,
    ciphertext: b64(ciphertext), attachments: attachments.map(entry => entry.id),
  };
}

export async function encryptAttachment(state, self, peer, routing, file) {
  const binding = route(state, self, peer, routing);
  object(file, ['bytes', 'name', 'mime', 'id']); bytes(file.bytes, MAX_FILE); hex(file.id, 32);
  const metadata = { name: file.name, mime: file.mime, size: file.bytes.length }; fileMetadata(metadata);
  const ciphertext = await encryptPayload(state, self, peer, {
    protocol: 'gunindex-attachment-2', ...binding, file_id: file.id, ...metadata, data: b64(file.bytes),
  }, MAX_CIPHER);
  return {
    request: { action: 'upload', listing_id: binding.listing_id, peer_id: binding.receiver_id, device_id: binding.sender_device, client_id: binding.client_id,
      sender_version: binding.sender_version, receiver_version: binding.receiver_version, id: file.id, ciphertext: b64(ciphertext) },
    descriptor: { id: file.id, sha256: await hash(ciphertext), bytes: ciphertext.length, ...metadata },
  };
}

async function historicalBundle(bundle, userId, pin, { retiredPins, loadChain }) {
  const { identity_key: identity } = json(bundle.manifest, MAX_MANIFEST);
  if (pin.identity_key === identity) return verifyBundle(bundle, userId, pin, { historical: true });
  requireValue(Array.isArray(retiredPins?.[userId]), 'identity_changed');
  const anchor = retiredPins?.[userId]?.find(candidate => candidate.identity_key === identity);
  requireValue(anchor, 'history_unanchored');
  const candidate = await verifyBundle(bundle, userId, anchor, { historical: true });
  const since = candidate.manifest.version + 1;
  const length = anchor.version - candidate.manifest.version;
  if (!length) return candidate;
  requireValue(length <= 128 && typeof loadChain === 'function', 'history_unanchored');
  const chain = await loadChain(userId, since, anchor.version);
  requireValue(Array.isArray(chain) && chain.length === length, 'history_unanchored');
  let previousHash = candidate.hash;
  for (const [index, proof] of chain.entries()) {
    const manifest = await signedManifest(proof, userId);
    requireValue(manifest.identity_key === identity && manifest.version === since + index && manifest.previous_hash === previousHash, 'history_unanchored');
    previousHash = proof.hash;
  }
  requireValue(previousHash === anchor.hash, 'history_unanchored');
  return candidate;
}

async function recordContext(state, record, pins, history) {
  requireValue(unlockedStates.has(state), 'vault_locked');
  integer(record.listing_id); integer(record.sender_id); integer(record.receiver_id);
  requireValue(record.sender_id !== record.receiver_id && [record.sender_id, record.receiver_id].includes(state.user_id), 'recipient_mismatch');
  hex(record.device_id, 32); hex(record.client_id, 32); integer(record.sender_version); integer(record.receiver_version);
  const getPin = id => pins instanceof Map ? pins.get(id) : pins?.[id];
  requireValue(getPin(record.sender_id) && getPin(record.receiver_id), 'identity_untrusted');
  const sender = await historicalBundle(record.sender_manifest, record.sender_id, getPin(record.sender_id), history);
  const receiver = await historicalBundle(record.receiver_manifest, record.receiver_id, getPin(record.receiver_id), history);
  requireValue(sender.manifest.version === record.sender_version && receiver.manifest.version === record.receiver_version, 'manifest_version');
  const device = sender.manifest.devices.find(entry => entry.id === record.device_id);
  requireValue(device && !device.revoked, 'sender_revoked');
  return { sender, receiver, verificationKey: sender.keys.get(record.device_id) };
}
async function decryptPayload(state, ciphertext, verificationKey, max) {
  bytes(ciphertext, max); requireValue(ciphertext.length > 0);
  const result = await pgp.decrypt({
    message: await pgp.readMessage({ binaryMessage: ciphertext, config: CONFIG }),
    decryptionKeys: [state.deviceKey, ...state.recoveryKeys, ...(state.archiveKeys ?? [])], verificationKeys: verificationKey,
    expectSigned: true, format: 'binary', config: { ...CONFIG, maxDecompressedMessageSize: max },
  });
  requireValue(result.signatures.length === 1, 'signature_count');
  await result.signatures[0].verified;
  requireValue(result.data.length <= max);
  return json(decoder.decode(result.data), max);
}
function sameRoute(payload, record, versions = true) {
  requireValue(payload.listing_id === record.listing_id && payload.sender_id === record.sender_id && payload.receiver_id === record.receiver_id && payload.sender_device === record.device_id && payload.client_id === record.client_id, 'message_context');
  if (versions) requireValue(payload.sender_version === record.sender_version && payload.receiver_version === record.receiver_version, 'message_version');
}

export async function decryptMessage(state, record, pins, history = {}) {
  const context = await recordContext(state, record, pins, history);
  const payload = await decryptPayload(state, unb64(record.ciphertext, MAX_MESSAGE), context.verificationKey, MAX_MESSAGE);
  object(payload, ['protocol', 'listing_id', 'sender_id', 'receiver_id', 'sender_device', 'client_id', 'sender_version', 'receiver_version', 'body', 'attachments']);
  requireValue(payload.protocol === 'gunindex-message-1'); sameRoute(payload, record); descriptors(payload.attachments);
  requireValue(typeof payload.body === 'string' && Array.from(payload.body).length <= 5000 && (payload.body.trim().length > 0 || payload.attachments.length > 0), 'message_length');
  requireValue(Array.isArray(record.attachments) && record.attachments.length === payload.attachments.length, 'attachment_binding');
  for (const attachment of payload.attachments) {
    const stored = record.attachments.find(entry => entry.id === attachment.id);
    requireValue(stored && stored.sha256 === attachment.sha256 && stored.bytes === attachment.bytes, 'attachment_binding');
  }
  // Carry verified context privately for attachment decryption; no caller-supplied signing key.
  for (const attachment of payload.attachments) Object.freeze(attachment);
  Object.freeze(payload.attachments); Object.freeze(payload);
  const verified = { state, record: structuredClone(record), context, attachments: payload.attachments };
  verifiedMessages.set(payload, verified);
  verifiedMessages.set(record, verified);
  return payload;
}
const verifiedMessages = new WeakMap();

/** Accepts a previously verified server record or decryptMessage's result. */
export async function decryptAttachment(state, record, entry, ciphertext) {
  const verified = verifiedMessages.get(record);
  requireValue(verified && verified.state === state, 'message_not_verified');
  descriptor(entry);
  const expected = verified.attachments.find(item => item.id === entry.id);
  requireValue(expected && Object.keys(expected).every(key => expected[key] === entry[key]), 'attachment_binding');
  bytes(ciphertext, MAX_CIPHER);
  requireValue(ciphertext.length === entry.bytes && await hash(ciphertext) === entry.sha256, 'attachment_hash');
  const payload = await decryptPayload(state, ciphertext, verified.context.verificationKey, MAX_CIPHER);
  const versioned = payload?.protocol === 'gunindex-attachment-2';
  const fields = ['protocol', 'listing_id', 'sender_id', 'receiver_id', 'sender_device', 'client_id', 'file_id', 'name', 'mime', 'size', 'data'];
  object(payload, versioned ? [...fields, 'sender_version', 'receiver_version'] : fields);
  requireValue(versioned || payload.protocol === 'gunindex-attachment-1'); sameRoute(payload, verified.record, versioned);
  requireValue(payload.file_id === entry.id && payload.name === entry.name && payload.mime === entry.mime && payload.size === entry.size, 'attachment_context');
  fileMetadata(payload); const data = unb64(payload.data, MAX_FILE); requireValue(data.length === payload.size, 'attachment_size');
  return { bytes: data, name: payload.name, mime: payload.mime, size: payload.size };
}
