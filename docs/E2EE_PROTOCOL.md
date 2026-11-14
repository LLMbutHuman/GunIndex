# E2EE implementation contract (development, 2026-11-14)

Status: normal device/recovery/message/file workflows implemented and tested in
isolated Chromium/PHP/MySQL; not a production or independent security approval.
OpenPGP.js 6.3.2 / RFC9580 protects signed message and attachment packets. Separate
Ed25519 signatures authenticate account device manifests. This application trust
protocol needs independent review. No forward secrecy or post-compromise security
is claimed. Web-delivered JavaScript, endpoint compromise and metadata are outside
the ciphertext confidentiality guarantee.

## Account manifest and authority

Manifest is an exact UTF-8 JSON string, signed using WebCrypto Ed25519 with bytes
`GunIndex device manifest v1\0` followed by the exact manifest bytes. The server
verifies with sodium against the immutable account identity raw public key (32 bytes).
Signature/public key use canonical standard base64. No OpenPGP signature is treated
as a raw Ed25519 signature. Hashes are lowercase SHA256 hex over exact bytes.

Manifest fields (no unknown fields):
`protocol:"gunindex-devices-1",user_id:int,version:int,previous_hash:hex64,
identity_key:base64,recovery_public:armored OpenPGP public key,
recovery_hash:hex64,devices:[{id:hex32,name:string,public_key:armored public key,
fingerprint:lowerhex40or64,revoked:bool}]`.

Version starts at 1, previous_hash is 64 zeros. Later versions use current hash
and version+1 (atomic compare-and-swap). Identity cannot silently change. At most
10 active devices / 64 total device records; existing device public keys and IDs
cannot change or disappear, and revoked devices cannot reactivate. Historical
manifests remain available to authorized message participants for signature checks.
New devices need possession of the separate recovery authority; an ordinary device
vault never stores that authority. Each device uses its own OpenPGP signing and
encryption key, generated with v6Keys and RFC9580 AEAD enabled.

## Recovery and local storage

Random 32-byte recovery secret encrypts an OpenPGP password-encrypted backup holding
the authority private PKCS8 key and all historical recovery decryption keys. Its
binary ciphertext is base64 encoded as recovery_cipher; its decoded SHA256 is
recovery_hash. No application/login/server key can unlock it.

Recovery code: `GX1.user_id.version.manifest_hash.identity_base64url.secret_base64url`.
It anchors the exact latest manifest and identity, rejecting server rollback during
recovery. Every device enrollment/revocation creates a fresh recovery secret and
recovery encryption key, and requires saving the replacement recovery code before
commit. The old code does not unlock the new backup.

Devices keep an OpenPGP password-encrypted local vault in IndexedDB, protected by
a separate user-entered device passphrase (minimum 12 characters). It contains its
own device private key and, after recovery, historical recovery keys. It excludes
the account authority and the newly generated current recovery private key. That
current key exists only inside the independently encrypted recovery backup.
Revocation rotates the recovery recipient so revoked devices cannot use a historical
recovery key for future messages. Previously decrypted/received information cannot
be erased. Recovery-secret/authority compromise requires separate identity recovery;
automatic root identity replacement is not part of this initial implementation.

## Messages and files

Client encrypts and signs to all current active device public keys of both parties
and both current recovery public keys. Decryption must verify the expected sender
device signature before exposing plaintext. The signed encrypted JSON is:
`{protocol:"gunindex-message-1",listing_id,sender_id,receiver_id,sender_device,
client_id:hex32,sender_version,receiver_version,body:string,attachments:array}`.
Routing values must match the authenticated outer server record, including every ID.
Body maximum 5,000 Unicode characters; maximum 3 attachments.

Each file is separately signed/encrypted OpenPGP JSON:
`{protocol:"gunindex-attachment-1",listing_id,sender_id,receiver_id,sender_device,
client_id,file_id:hex32,name,mime,size,data:base64}`.
Encrypted message descriptor: `{id:hex32,sha256:hex64,bytes:int,name,mime,size}`.
Files: maximum 3 MiB plaintext / 5 MiB binary ciphertext each, at most 10 unattached
uploads and 50 MiB stored ciphertext per account. Only text/plain, application/pdf,
image/png, image/jpeg and image/webp are accepted by the client; all decrypted files
are downloads, never active HTML/iframe content. Filenames and MIME stay encrypted.
Server cannot scan plaintext; uploaded files remain private opaque ciphertext.

## PHP API contract

`user/e2ee-api.php`: authenticated same-origin JSON, no-store. POST JSON always
contains csrf_token; use normal session token verification and exact action schemas.
Feature gate is environment `E2EE_ENABLED=1` (default off); all mutations also use
the existing maintenance barrier. No real customer data in tests.

GET `action=account`: `{account:null|{manifest,signature,hash,recovery_cipher}}` own only.
GET `action=context&listing_id=N&peer_id=N`: authorized chat_context,
`{self:{manifest,signature,hash}|null,peer:{manifest,signature,hash}|null,can_send}`.
GET `action=history&listing_id=N&peer_id=N&after=N`: max25 encrypted messages ASC,
`{messages:[{id,listing_id,sender_id,receiver_id,device_id,client_id,sender_version,
receiver_version,ciphertext,created_at,sender_manifest:{manifest,signature,hash},
receiver_manifest:{manifest,signature,hash},attachments:[{id,sha256,bytes}]}]}`.
GET `action=download&id=hex32`: authenticated party of its bound message; opaque
application/octet-stream, attachment, nosniff, no-store. Unbound uploads are owner-only.

POST `action=account,expected_version,expected_hash,manifest,signature,recovery_cipher`:
verify signature and strict manifest, backup digest, CAS; `{account:{manifest,signature,hash,recovery_cipher}}`.
POST `action=upload,listing_id,peer_id,device_id,client_id,id,ciphertext`:
ciphertext base64 -> private exclusive random storage; `{attachment:{id,sha256,bytes}}`.
POST `action=send,listing_id,peer_id,device_id,client_id,sender_version,receiver_version,
ciphertext,attachments:[hex32]`: current manifests and active device required;
idempotent nonce per sender; attach only its matching conversation/device/client_id
uploads atomically. Existing market_messages has cipher_version=2, message_text='';
separate E2EE table holds ciphertext. Maintain conversation index and generic notices.
`{id:int}`. Same nonce with different content is a conflict, not silent success.

Once either account has an E2EE identity, legacy chat_send is permanently refused,
including if the feature gate is later disabled. Legacy v0/v1 history remains readable
and explicitly marked as server-readable history; it is never relabelled E2EE.
Current manifests are verified/pinned on client (identity + highest version/hash).
First peer identity requires explicit out-of-band full fingerprint comparison.

## Browser, delivery and storage operations

The browser never submits a native form containing a message, file plaintext,
device passphrase or recovery code. IndexedDB holds encrypted device vaults,
public trust pins and an encrypted outgoing request. A lost send response can
be retried using exactly the same nonce and ciphertext; the user may explicitly
discard that retry, with notice that delivery might already have occurred.
An account change or lock clears rendered plaintext. Idle locking runs after
five minutes. Downloaded user files always use application/octet-stream; allowed
extensions must match the encrypted MIME type. Files are not rendered inline.
No server plaintext malware scan or remote erasure of previously received data
is claimed. Browser-delivered code must remain trustworthy.

GET `action=uploads` lists only the user's unbound upload IDs, hashes, sizes and
timestamps. POST `action=discard,id` removes only the user's still-unbound file.
This allows recovery of quota after an interrupted browser upload. Files from
other devices should be removed only if their pending sends are no longer needed.
POST `action=read,listing_id,peer_id,through` explicitly marks received E2EE
messages read through an actual authorized message; merely reading GET history
has no read-status side effect.

The default `/var/lib/gunindex/e2ee` is inside the existing persistent private
volume and the encrypted production file snapshot. A custom E2EE_ATTACHMENT_DIR
needs its own reviewed persistent mount and inclusion in backups. Restore must
keep database manifests, messages and attachment blobs together. The operator's
backup key cannot decrypt E2EE plaintext. Users must retain their latest recovery
code separately; loss of all device vaults and that code is unrecoverable.
The privacy export contains encrypted message/file packets and public signed
manifests; it does not provide recovery secrets or claim plaintext export.

Every recovery code anchors the version at which it was created. An old code and
a matching old signed server snapshot cannot alone prove that a later version
exists. Current server CAS and highest locally pinned versions reject stale writes
and observed rollback. This is not global key transparency: a malicious delivery
server can withhold newer manifests from a device that has never seen them.
Revocation tests prove exclusion for messages encrypted against the updated
manifest, not revocation against compromised JavaScript or concealed updates.

## Dependency and verification evidence

- Pinned upstream: [OpenPGP.js 6.3.2](https://github.com/openpgpjs/openpgpjs/releases/tag/v6.3.2),
  [RFC 9580](https://www.rfc-editor.org/rfc/rfc9580.html). LGPL license and original
  files remain in `assets/vendor/openpgp`, with SHA256 provenance.
- `npm ci --ignore-scripts`, `node ops/vendor-e2ee.mjs --check` and `npm audit`
  validate dependency installation, byte reproduction and published advisories.
- `node tests/e2ee_crypto_test.mjs`: 12 genuine crypto groups passed on Node
  22.23.4. Node 18.2 cannot run the required WebCrypto Ed25519 operations.
- `tests/e2ee_integration_test.php`: synthetic HTTP/DB/authorization/concurrency
  tests. Opaque packet fixtures here are not proof of OpenPGP confidentiality.
- `tests/e2ee_browser.js`: real Chromium UI, two users/multiple browser devices,
  encrypted download, recovery, revocation, privacy checks and mobile locales.
  Current executed results are recorded in IMPLEMENTATION_PROGRESS.md.

Deployment requires HTTPS and browser WebCrypto Ed25519 plus IndexedDB; unavailable
capabilities fail closed. E2EE_ENABLED defaults to 0. Independent security review,
Firefox/Safari and supported mobile-browser validation, and an actual target-server
restore remain separate acceptance items. Until their completion this development
implementation must not be described as independently audited or production-ready.

## Acceptance at 2026-11-14

Code `7ba5dfd79315da10f5d6f3282a9a4e7c246b5656`,
[CI 43648359381](https://github.com/LLMbutHuman/GunIndex/actions/runs/43648359381),
Linux job 132611100770: complete pass, including existing regression suite.

| Class | Scope | Evidence or remaining work |
| --- | --- | --- |
| A | Signed encryption, independent devices, verification, recovery, revocation, encrypted files | 12 genuine crypto groups, 15 HTTP/DB groups, 19 Chromium UI checks; eight mobile locales |
| A | Interrupted delivery, locked plaintext lifecycle, cross-tab trust state | Real lost-response retry without duplicate row; manual lock aborts stalled HTTP; old tab cannot lower a newer persisted pin |
| A | Server/file confidentiality and permissions | Canary checks for plaintext/private material; private downloads, quotas, CAS, access failures, export and legacy preservation |
| B | Production assurance | Independent crypto/application review, additional supported browsers and actual target-server backup/restore |
| C | Compromised account authority replacement | Not implemented. Recovery-code rotation does not revoke already extracted authority material. Requires an explicit authenticated identity reset, peer re-verification and preserved historical verification |

The earlier failed browser run 43436883236 identified mobile overflow from a long
participant name; this was corrected and the entire subsequent run passed.
