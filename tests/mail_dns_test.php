<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require_once __DIR__ . '/../config/mail_dns.php';

function mail_dns_test_expect(bool $condition, string $scenario): void
{
    if (!$condition) {
        throw new RuntimeException('Mail DNS test failed: ' . $scenario);
    }
}

$from = 'sender@News.Example.TEST';
$domain = 'news.example.test';
$selector = 'Mail.Sep';
$dkimName = 'mail.sep._domainkey.' . $domain;
$dmarcName = '_dmarc.' . $domain;
// Public keys from the published RFC 8463 Appendix A.2 examples.
$ed25519 = '11qYAYKxCrfVS/7TyWQHOg7hcvPapiMlrwIaaPcHURo=';
$rsa = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDkHlOQoBTzWRiGs5V6NpP3idY6Wk08a5qhdR6wy5bdOKb2jLQiY/J16JYi0Qvx/byYzCNb3W91y3FutACDfzwQ/BC/e/8uBsCR+yz1Lxj+PL6lHvqMKrM3rG4hstT5QjvHO9PzoxZyVYLzBfO2EeC3Ip3G+2kryOTIKT+l/K4w3QIDAQAB';
$ed25519Record = 'v=DKIM1; k=ed25519; p=' . $ed25519;

$queries = [];
$lookup = static function (string $name) use ($domain, $dkimName, $dmarcName, $ed25519Record, &$queries): array {
    $queries[] = $name;
    return match ($name) {
        $domain => ['site-verification=irrelevant', 'v=spf10 -all', 'v=spf1 include:_spf.example.test -all'],
        $dkimName => [$ed25519Record],
        $dmarcName => ['v=DMARC1; p=quarantine; t=y; rua=mailto:reports@example.test'],
        default => [],
    };
};
mail_dns_test_expect(
    mail_dns_status($from, $selector, $lookup) === ['domain' => $domain, 'spf' => 'ok', 'dkim' => 'ok', 'dmarc' => 'ok'],
    'positive results expose only statuses and the normalized domain'
);
mail_dns_test_expect($queries === [$domain, $dkimName, $dmarcName], 'precise TXT query scope');

foreach (['not-an-email', 'admin@[127.0.0.1]', 'admin@-bad.example.test', "admin@example.test\n"] as $invalid) {
    $queries = [];
    mail_dns_test_expect(
        mail_dns_status($invalid, $selector, $lookup) === ['domain' => '', 'spf' => 'invalid', 'dkim' => 'invalid', 'dmarc' => 'invalid']
        && $queries === [],
        'invalid sender must never reach the resolver'
    );
}
foreach (['' => 'missing', '/bad' => 'invalid', 'mail._bad' => 'invalid', str_repeat('s', 64) => 'invalid', str_repeat('s.', 120) . 's' => 'invalid'] as $invalid => $expected) {
    $queries = [];
    mail_dns_test_expect(
        mail_dns_status($from, $invalid, $lookup) === ['domain' => $domain, 'spf' => 'ok', 'dkim' => $expected, 'dmarc' => 'ok']
        && $queries === [$domain, $dmarcName],
        'invalid or omitted selector prevents only the DKIM lookup'
    );
}

$rsaStatus = extension_loaded('openssl') ? 'ok' : 'error';
$cases = [
    ['spf', $domain, [], 'missing', 'no TXT records'],
    ['spf', $domain, ['v=spf10 -all', 'v=spf1' . "\t" . '-all'], 'missing', 'SPF version boundary'],
    ['spf', $domain, ['V=SPF1'], 'ok', 'SPF case-insensitive version'],
    ['spf', $domain, ['v=spf1 -all', 'v=spf1 ~all'], 'invalid', 'duplicate SPF records'],
    ['spf', $domain, ["v=spf1 -all\x00"], 'invalid', 'invalid SPF record character'],
    ['dkim', $dkimName, ['site-verification=irrelevant'], 'missing', 'unrelated DKIM TXT'],
    ['dkim', $dkimName, [$ed25519Record, $ed25519Record], 'invalid', 'duplicate DKIM records'],
    ['dkim', $dkimName, ['v=DKIM1; k=ed25519; p='], 'invalid', 'revoked public key'],
    ['dkim', $dkimName, [$ed25519Record . '; p=' . $ed25519], 'invalid', 'duplicate DKIM key tag'],
    ['dkim', $dkimName, ['k=ed25519; v=DKIM1; p=' . $ed25519], 'invalid', 'DKIM version is not first'],
    ['dkim', $dkimName, ['k=ed25519; p=' . substr($ed25519, 0, 12) . "\r\n " . substr($ed25519, 12) . ';'], 'ok', 'optional DKIM version and folded key'],
    ['dkim', $dkimName, ['k=ed25519; p=YQ=='], 'invalid', 'wrong Ed25519 key length'],
    ['dkim', $dkimName, ['k=ed25519; p=' . $ed25519 . '!'], 'invalid', 'malformed key encoding'],
    ['dkim', $dkimName, [$ed25519Record . '; h=sha1'], 'invalid', 'obsolete hash is not usable'],
    ['dkim', $dkimName, [$ed25519Record . '; s=other'], 'invalid', 'DKIM key restricted to a different service'],
    ['dkim', $dkimName, ['p=' . $rsa], $rsaStatus, 'DKIM RSA SPKI and implicit key type'],
    ['dkim', $dkimName, ['k=rsa; p=' . base64_encode(substr(base64_decode($rsa, true), 22))], $rsaStatus, 'DKIM RSA PKCS#1'],
    ['dkim', $dkimName, ['k=rsa; p=YQ=='], extension_loaded('openssl') ? 'invalid' : 'error', 'malformed RSA key'],
    ['dmarc', $dmarcName, ['v=dmarc1; p=reject', 'v=DMARC2; p=reject'], 'missing', 'DMARC version is case-sensitive'],
    ['dmarc', $dmarcName, ['p=reject; v=DMARC1'], 'invalid', 'DMARC version is not first'],
    ['dmarc', $dmarcName, ['v=DMARC1; rua=mailto:reports@example.test'], 'ok', 'RFC 9989 implicit none policy'],
    ['dmarc', $dmarcName, ['V=DMARC1; P = Reject; sp=quarantine; unknown=kept;'], 'ok', 'recognized DMARC policy with extension tag'],
    ['dmarc', $dmarcName, ['v=DMARC1; p=none; P=reject'], 'invalid', 'duplicate DMARC tags'],
    ['dmarc', $dmarcName, ['v=DMARC1; p=block'], 'invalid', 'invalid DMARC policy'],
    ['dmarc', $dmarcName, ['v=DMARC1; p=reject; sp=block'], 'invalid', 'invalid subdomain policy'],
    ['dmarc', $dmarcName, ['v=DMARC1; p=reject;;'], 'invalid', 'invalid DMARC tag separator'],
    ['dmarc', $dmarcName, ['v=DMARC1; p=reject', 'v=DMARC1; p=none'], 'invalid', 'multiple DMARC records'],
    ['spf', $domain, false, 'error', 'resolver returned false'],
    ['dkim', $dkimName, [null], 'error', 'resolver returned malformed data'],
    ['dmarc', $dmarcName, array_fill(0, 129, 'unrelated'), 'error', 'large TXT answer count'],
    ['dmarc', $dmarcName, [str_repeat('x', 8193)], 'error', 'large TXT answer record'],
];
foreach ($cases as [$key, $query, $records, $expected, $scenario]) {
    $status = mail_dns_status($from, $selector, static fn(string $name): array|false => $name === $query ? $records : []);
    mail_dns_test_expect($status[$key] === $expected, $scenario);
}

$status = mail_dns_status($from, $selector, static function (): never {
    throw new RuntimeException('Synthetic resolver exception');
});
mail_dns_test_expect($status === ['domain' => $domain, 'spf' => 'error', 'dkim' => 'error', 'dmarc' => 'error'], 'resolver exceptions remain private');
fwrite(STDOUT, "Mail DNS tests passed.\n");
