<?php
declare(strict_types=1);

/** Proposed fees are calculations, never proof of an issued invoice/payment. */
function fees_progressive(int $saleCents, array $rates, int $capCents, int $minimumCents = 0): int
{
    if ($saleCents < 0 || $saleCents > 9999999999) {
        throw new InvalidArgumentException('Ungültiger Verkaufspreis.');
    }
    $remaining = $saleCents;
    $weighted = 0;
    foreach ([[10000, $rates[0]], [40000, $rates[1]], [150000, $rates[2]], [9999999999, $rates[3]]] as [$width, $basisPoints]) {
        $part = min($remaining, $width);
        $weighted += $part * $basisPoints;
        $remaining -= $part;
    }
    return max($minimumCents, min($capCents, intdiv($weighted + 5000, 10000)));
}

function fees_quote(string $service, ?int $saleCents = null, ?PDO $pdo = null): array
{
    $fixed = [
        'private_fixed' => ['Privates Festpreisinserat', null, 0],
        'wanted' => ['Gesuch', null, 0],
        'search_alert' => ['Suchalarm', null, 0],
        'auction_listing' => ['Auktion einstellen', 'fee_auction_listing_cents', 50],
        'bump_single' => ['Einzelner Bump', 'fee_bump_single_cents', 199],
        'bump_pack' => ['Drei Bumps', 'fee_bump_pack_cents', 499],
        'category_top' => ['Kategorie-Top · sieben Tage', 'fee_category_top_cents', 990],
        'home_top' => ['Startseiten-Top · sieben Tage', 'fee_home_top_cents', 1990],
        'banner_monthly' => ['Händlerbanner · ein Monat', 'fee_banner_monthly_cents', 12900],
    ];
    if ($service === 'auction_success' || $service === 'escrow_preview') {
        if ($saleCents === null) {
            throw new InvalidArgumentException('Verkaufspreis fehlt.');
        }
        $escrow = $service === 'escrow_preview';
        $amount = fees_progressive($saleCents, $escrow ? [290, 190, 120, 60] : [300, 200, 100, 50], $escrow ? 3990 : 2990, $escrow ? 249 : 0);
        return ['service' => $service, 'label' => $escrow ? 'Treuhandvorschau · nicht verfügbar' : 'Erfolgreiche Auktion', 'gross_cents' => $amount, 'currency' => 'EUR', 'version' => 'progressive-1.0', 'available' => false];
    }
    if (!isset($fixed[$service])) {
        throw new InvalidArgumentException('Unbekannte Leistung.');
    }
    [$label, $setting, $amount] = $fixed[$service];
    if ($pdo !== null && $setting !== null) {
        $raw = site_setting($pdo, $setting, (string)$amount);
        if (!preg_match('/\A[0-9]{1,9}\z/', $raw)) {
            throw new RuntimeException('Gebührenkonfiguration ungültig.');
        }
        $amount = (int)$raw;
    }
    return ['service' => $service, 'label' => $label, 'gross_cents' => $amount, 'currency' => 'EUR', 'version' => 'fixed-1.0-' . substr(hash('sha256', $service . ':' . $amount), 0, 12), 'available' => $amount === 0];
}

function fees_tax_split(int $grossCents, int $basisPoints, bool $reviewed): array
{
    if (!$reviewed || $grossCents < 0 || $grossCents > 9999999999 || $basisPoints < 0 || $basisPoints > 10000) {
        throw new InvalidArgumentException('Geprüfte Steuerkonfiguration erforderlich.');
    }
    $denominator = 10000 + $basisPoints;
    $net = intdiv($grossCents * 10000 + intdiv($denominator, 2), $denominator);
    return ['net_cents' => $net, 'tax_cents' => $grossCents - $net, 'gross_cents' => $grossCents];
}
