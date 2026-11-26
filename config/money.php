<?php
declare(strict_types=1);
require_once __DIR__.'/i18n.php';
function money_parse(string $value):int{if(!preg_match('/\A(0|[1-9][0-9]{0,7})(?:[.,]([0-9]{1,2}))?\z/',$value,$m))throw new InvalidArgumentException(function_exists('t')?t('finance_customer.amount_invalid'):'Bitte einen gültigen Geldbetrag eingeben.');return ((int)$m[1])*100+(int)str_pad($m[2]??'',2,'0');}

function money_eur(int $cents, ?string $locale = null): string
{
    $sign = $cents < 0 ? '−' : '';
    $whole = abs(intdiv($cents, 100));
    $fraction = str_pad((string)abs($cents % 100), 2, '0', STR_PAD_LEFT);
    [$decimal, $group] = i18n_number_separators($locale ?? 'de', $whole);
    $amount = number_format($whole, 0, '', $group).$decimal.$fraction;
    return match ($locale) {
        null => $sign.$amount.' €',
        'en' => $sign.'€'.$amount,
        'nl' => "€\u{00A0}".$sign.$amount,
        default => $sign.$amount."\u{00A0}€",
    };
}
