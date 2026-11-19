<?php
declare(strict_types=1);

require_once __DIR__ . '/mail.php';

/**
 * Check TXT publication at the configured sender domain. This does not evaluate
 * SPF authorization, DKIM signatures, DMARC alignment or delivery.
 *
 * @param (callable(string): (list<string>|false))|null $lookup CLI-only test resolver
 * @return array{domain: string, spf: string, dkim: string, dmarc: string}
 */
function mail_dns_status(string $from, string $selector, ?callable $lookup = null): array
{
    if ($lookup !== null && PHP_SAPI !== 'cli') {
        throw new InvalidArgumentException('DNS resolver injection is restricted to CLI tests.');
    }

    $status = ['domain' => '', 'spf' => 'invalid', 'dkim' => 'invalid', 'dmarc' => 'invalid'];
    if (!mail_address_valid($from)) {
        return $status;
    }
    $domain = strtolower(substr($from, strrpos($from, '@') + 1));
    if (!str_contains($domain, '.') || !mail_dns_labels_valid($domain) || filter_var($domain, FILTER_VALIDATE_IP)) {
        return $status;
    }

    $lookup ??= mail_dns_txt(...);
    $status['domain'] = $domain;
    $status['spf'] = mail_dns_check($domain, $lookup, mail_dns_spf_status(...));
    $selector = strtolower($selector);
    $dkimName = $selector . '._domainkey.' . $domain;
    if ($selector === '') {
        $status['dkim'] = 'missing';
    } elseif (strlen($dkimName) <= 253 && mail_dns_labels_valid($selector)) {
        $status['dkim'] = mail_dns_check($dkimName, $lookup, mail_dns_dkim_status(...));
    }
    $status['dmarc'] = mail_dns_check('_dmarc.' . $domain, $lookup, mail_dns_dmarc_status(...));
    return $status;
}

function mail_dns_labels_valid(string $name): bool
{
    return strlen($name) <= 253
        && preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/', $name) === 1;
}

/** @return list<string>|false */
function mail_dns_txt(string $name): array|false
{
    $records = @dns_get_record($name . '.', DNS_TXT);
    if ($records === false) {
        return false;
    }

    $txt = [];
    foreach ($records as $record) {
        if (($record['type'] ?? '') === 'TXT') {
            // The TXT strings of one resource record have no implicit separator.
            $txt[] = isset($record['entries']) ? implode('', $record['entries']) : $record['txt'];
        }
    }
    return $txt;
}

function mail_dns_check(string $name, callable $lookup, callable $validate): string
{
    try {
        $records = $lookup($name);
        if (!is_array($records) || !array_is_list($records) || count($records) > 128) {
            return 'error';
        }
        foreach ($records as $record) {
            if (!is_string($record) || strlen($record) > 8192) {
                return 'error';
            }
        }
        return $validate($records);
    } catch (Throwable) {
        return 'error';
    }
}

function mail_dns_spf_status(array $records): string
{
    // RFC 7208 §4.5. Other TXT records and SPF versions are ignored.
    $spf = preg_grep('/\Av=spf1(?: |\z)/i', $records);
    if ($spf === []) {
        return 'missing';
    }
    return count($spf) === 1 && preg_match('/\Av=spf1(?: [\x20-\x7e]*)?\z/i', reset($spf)) === 1
        ? 'ok' : 'invalid';
}

/** @return array<string, string>|null */
function mail_dns_tags(string $record, string $namePattern, bool $caseInsensitive = false): ?array
{
    $tags = [];
    foreach (explode(';', preg_replace('/;[ \t]*\z/', '', $record)) as $part) {
        if (!preg_match('/\A[ \t]*(' . $namePattern . ')[ \t]*=[ \t]*([\x09\x20-\x7e]*)\z/', $part, $tag)) {
            return null;
        }
        $name = $caseInsensitive ? strtolower($tag[1]) : $tag[1];
        if (isset($tags[$name])) {
            return null;
        }
        $tags[$name] = rtrim($tag[2], " \t");
    }
    return $tags;
}

function mail_dns_dkim_status(array $records): string
{
    // RFC 6376 §§3.2 and 3.6, RFC 8301 (RSA) and RFC 8463 (Ed25519).
    $records = preg_replace('/\r\n[ \t]+/', ' ', $records);
    $dkim = preg_grep('/(?:\A|;)[ \t]*(?:v[ \t]*=[ \t]*DKIM1(?:[ \t]*;|[ \t]*\z)|[kp][ \t]*=)/', $records);
    if ($dkim === []) {
        return 'missing';
    }
    if (count($dkim) !== 1) {
        return 'invalid';
    }
    $tags = mail_dns_tags(reset($dkim), '[A-Za-z][A-Za-z0-9_]*');
    if ($tags === null || (isset($tags['v']) && (array_key_first($tags) !== 'v' || $tags['v'] !== 'DKIM1'))) {
        return 'invalid';
    }

    $hashes = preg_split('/[ \t]*:[ \t]*/', $tags['h'] ?? 'sha256');
    $services = preg_split('/[ \t]*:[ \t]*/', $tags['s'] ?? '*');
    $key = base64_decode(str_replace([' ', "\t"], '', $tags['p'] ?? ''), true);
    if ($key === false || $key === '' || !in_array('sha256', $hashes, true)
        || (!in_array('*', $services, true) && !in_array('email', $services, true))) {
        return 'invalid';
    }

    return match ($tags['k'] ?? 'rsa') {
        'ed25519' => strlen($key) === 32 ? 'ok' : 'invalid',
        'rsa' => mail_dns_rsa_status($key),
        default => 'invalid',
    };
}

function mail_dns_rsa_status(string $key): string
{
    if (!extension_loaded('openssl')) {
        return 'error';
    }
    // Accept PKCS#1 and the SubjectPublicKeyInfo encoding used by DNS providers.
    $pem = chunk_split(base64_encode($key), 64, "\n");
    $public = @openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n{$pem}-----END PUBLIC KEY-----\n");
    $public = $public ?: @openssl_pkey_get_public("-----BEGIN RSA PUBLIC KEY-----\n{$pem}-----END RSA PUBLIC KEY-----\n");
    $details = $public !== false ? openssl_pkey_get_details($public) : false;
    return $details !== false && $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 1024
        ? 'ok' : 'invalid';
}

function mail_dns_dmarc_status(array $records): string
{
    // RFC 9989 §§4.7–4.10.1. Check only direct publication, without a DNS tree walk.
    $dmarc = preg_grep('/(?:\A|;)[ \t]*[vV][ \t]*=[ \t]*DMARC1(?:[ \t]*;|[ \t]*\z)/', $records);
    if ($dmarc === []) {
        return 'missing';
    }
    if (count($dmarc) !== 1) {
        return 'invalid';
    }
    $tags = mail_dns_tags(reset($dmarc), '[A-Za-z]+', true);
    if ($tags === null || array_key_first($tags) !== 'v' || in_array('', $tags, true)) {
        return 'invalid';
    }
    $policies = ['none', 'quarantine', 'reject'];
    foreach (['p', 'sp', 'np'] as $name) {
        if (isset($tags[$name]) && !in_array(strtolower($tags[$name]), $policies, true)) {
            return 'invalid';
        }
    }
    return 'ok';
}
