<?php
declare(strict_types=1);

if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
require_once __DIR__.'/../config/bank_statement.php';

const BANK_TEST_IBAN='DE89370400440532013000';
const BANK_TEST_REFERENCE='GI-B-1234567890ABCDEF12345678';

function bank_test_require(bool $condition): void
{
    if (!$condition) throw new RuntimeException('Unexpected bank statement result.');
}

function bank_test_invalid(string $xml): void
{
    try { bank_statement_parse($xml); }
    catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Invalid bank statement accepted.');
}

function bank_test_statement(string $entries,string $iban=BANK_TEST_IBAN,?string $currency='EUR'): string
{
    return '<Stmt><Id>SYNTHETIC-STATEMENT</Id><CreDtTm>2026-11-20T00:00:00Z</CreDtTm>'
        .'<Acct><Id><IBAN>'.$iban.'</IBAN></Id>'.($currency===null?'':'<Ccy>'.$currency.'</Ccy>').'</Acct>'
        .'<Bal><Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp><Amt Ccy="EUR">100.00</Amt>'
        .'<CdtDbtInd>CRDT</CdtDbtInd><Dt><Dt>2026-11-19</Dt></Dt></Bal>'.$entries.'</Stmt>';
}

function bank_test_xml(string $statements): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><Document xmlns="'.BANK_STATEMENT_NAMESPACE.'">'
        .'<BkToCstmrStmt><GrpHdr><MsgId>SYNTHETIC-EXPORT</MsgId><CreDtTm>2026-11-20T00:00:00Z</CreDtTm>'
        .'</GrpHdr>'.$statements.'</BkToCstmrStmt></Document>';
}

function bank_test_entry(array $fields=[]): string
{
    return strtr(
        '<Ntry>{amount}<CdtDbtInd>{indicator}</CdtDbtInd>{reversal}{status}{booked}{source}'
        .'<BkTxCd><Domn><Cd>PMNT</Cd><Fmly><Cd>RCDT</Cd><SubFmlyCd>ESCT</SubFmlyCd></Fmly></Domn></BkTxCd>'
        .'{additional}<NtryDtls>{batch}{transactions}</NtryDtls></Ntry>',
        array_replace([
            '{amount}'=>'<Amt Ccy="EUR">12.30</Amt>',
            '{indicator}'=>'CRDT',
            '{reversal}'=>'',
            '{status}'=>'<Sts><Cd>BOOK</Cd></Sts>',
            '{booked}'=>'<BookgDt><Dt>2026-11-19</Dt></BookgDt>',
            '{source}'=>'<AcctSvcrRef>SYNTHETIC-ENTRY</AcctSvcrRef>',
            '{additional}'=>'',
            '{batch}'=>'',
            '{transactions}'=>'<TxDtls><Refs><EndToEndId>NOTPROVIDED</EndToEndId></Refs>'
                .'<RmtInf><Ustrd>Order '.BANK_TEST_REFERENCE.'</Ustrd></RmtInf></TxDtls>',
        ],$fields)
    );
}

$tests=[
    'exact booked EUR cents and case-normalized references'=>static function(): void {
        $tx='<TxDtls><Refs><EndToEndId>'.strtolower(BANK_TEST_REFERENCE).'</EndToEndId>'
            .'<AcctSvcrRef>TRANSACTION-LEVEL</AcctSvcrRef></Refs><Amt Ccy="EUR">12.3</Amt>'
            .'<RmtInf><Ustrd>Order '.BANK_TEST_REFERENCE.'</Ustrd></RmtInf></TxDtls>';
        $parsed=bank_statement_parse(bank_test_xml(bank_test_statement(
            bank_test_entry(['{transactions}'=>$tx]),'de89 3704 0044 0532 0130 00'
        )));
        bank_test_require($parsed===[
            'statement_count'=>1,'skipped_count'=>0,'accounts'=>[BANK_TEST_IBAN],
            'entries'=>[[
                'iban'=>BANK_TEST_IBAN,'source_reference'=>'SYNTHETIC-ENTRY',
                'booking_date'=>'2026-11-19','amount_cents'=>1230,
                'payment_reference'=>BANK_TEST_REFERENCE,'direction'=>'credit',
            ]],
        ]);
    },
    'empty statements preserve normalized account scope'=>static function(): void {
        $parsed=bank_statement_parse(bank_test_xml(bank_test_statement('',BANK_TEST_IBAN,null)));
        bank_test_require($parsed===['statement_count'=>1,'skipped_count'=>0,'accounts'=>[BANK_TEST_IBAN],'entries'=>[]]);
        $separate=bank_statement_parse(bank_test_xml(bank_test_statement('').bank_test_statement('','FR1420041010050500013M02606')));
        bank_test_require($separate['accounts']===[BANK_TEST_IBAN,'FR1420041010050500013M02606']);
    },
    'structured remittance and transaction bank reference'=>static function(): void {
        $tx='<TxDtls><Refs><AcctSvcrRef>CASE-Sensitive/Tx</AcctSvcrRef></Refs><RmtInf><Strd><CdtrRefInf>'
            .'<Ref>'.BANK_TEST_REFERENCE.'</Ref></CdtrRefInf></Strd></RmtInf></TxDtls>';
        $parsed=bank_statement_parse(bank_test_xml(bank_test_statement(bank_test_entry([
            '{source}'=>'','{transactions}'=>$tx,'{booked}'=>'<BookgDt><DtTm>2026-11-19T23:59:59-11:00</DtTm></BookgDt>',
        ]))));
        bank_test_require($parsed['entries'][0]['source_reference']==='CASE-Sensitive/Tx');
        bank_test_require($parsed['entries'][0]['payment_reference']===BANK_TEST_REFERENCE);
        bank_test_require($parsed['entries'][0]['booking_date']==='2026-11-19');
    },
    'ambiguous and non-standalone references remain unmatched'=>static function(): void {
        $other='GI-B-000000000000000000000000';
        $ambiguous='<TxDtls><Refs><EndToEndId>'.$other.'</EndToEndId></Refs><RmtInf><Ustrd>'
            .BANK_TEST_REFERENCE.'</Ustrd></RmtInf></TxDtls>';
        $unmatched='<TxDtls><RmtInf><Ustrd>X'.BANK_TEST_REFERENCE.' A'.BANK_TEST_REFERENCE.'B</Ustrd></RmtInf></TxDtls>';
        foreach ([$ambiguous,$unmatched] as $tx) {
            $result=bank_statement_parse(bank_test_xml(bank_test_statement(bank_test_entry(['{transactions}'=>$tx]))));
            bank_test_require(count($result['entries'])===1&&$result['entries'][0]['payment_reference']===null);
        }
    },
    'debit reversals are retained separately from payments'=>static function(): void {
        $base=bank_test_entry();
        $reversal=bank_test_entry(['{indicator}'=>'DBIT','{reversal}'=>'<RvslInd>true</RvslInd>']);
        $normalDebit=bank_test_entry(['{indicator}'=>'DBIT']);
        $creditReversal=bank_test_entry(['{reversal}'=>'<RvslInd>1</RvslInd>']);
        $returned=bank_test_entry(['{indicator}'=>'DBIT','{source}'=>'<AcctSvcrRef>RETURNED</AcctSvcrRef>',
            '{transactions}'=>'<TxDtls><RtrInf><Rsn><Cd>AC04</Cd></Rsn></RtrInf><RmtInf><Ustrd>'
                .BANK_TEST_REFERENCE.'</Ustrd></RmtInf></TxDtls>']);
        $parsed=bank_statement_parse(bank_test_xml(bank_test_statement($base.$reversal.$normalDebit.$creditReversal.$returned)));
        bank_test_require(array_column($parsed['entries'],'direction')===['credit','reversal','reversal']);
        bank_test_require($parsed['entries'][1]['payment_reference']===BANK_TEST_REFERENCE&&$parsed['skipped_count']===2);
    },
    'unsupported pending batch amount and account rows are skipped'=>static function(): void {
        $options=[
            ['{status}'=>'<Sts><Cd>PDNG</Cd></Sts>'],
            ['{status}'=>'<Sts><Prtry>BOOK</Prtry></Sts>'],
            ['{amount}'=>'<Amt Ccy="GBP">12.30</Amt>'],
            ['{amount}'=>'<Amt Ccy="EUR">12.301</Amt>'],
            ['{amount}'=>'<Amt Ccy="EUR">0.00</Amt>'],
            ['{amount}'=>'<Amt Ccy="EUR">12,30</Amt>'],
            ['{batch}'=>'<Btch><NbOfTxs>2</NbOfTxs></Btch>'],
            ['{transactions}'=>'<TxDtls/><TxDtls/>'],
            ['{transactions}'=>'<TxDtls><Amt Ccy="EUR">10.00</Amt></TxDtls>'],
            ['{transactions}'=>'<TxDtls><CdtDbtInd>DBIT</CdtDbtInd></TxDtls>'],
            ['{additional}'=>'<AddtlInfInd><MsgId>AGGREGATE-DETAILS</MsgId></AddtlInfInd>'],
            ['{source}'=>'<NtryRef>ENTRY-NUMBER-ONLY</NtryRef>'],
            ['{source}'=>'<AcctSvcrRef>'.str_repeat('A',129).'</AcctSvcrRef>'],
            ['{source}'=>'<AcctSvcrRef>NOTPROVIDED</AcctSvcrRef>'],
            ['{booked}'=>'<BookgDt><Dt>2026-02-29</Dt></BookgDt>'],
        ];
        $entries='';
        foreach ($options as $fields) $entries.=bank_test_entry($fields);
        $parsed=bank_statement_parse(bank_test_xml(bank_test_statement($entries).bank_test_statement(bank_test_entry(),BANK_TEST_IBAN,'USD')));
        bank_test_require($parsed['entries']===[]&&$parsed['skipped_count']===count($options)+1);
    },
    'same bank identity deduplicates but a conflicting duplicate fails'=>static function(): void {
        $first=bank_test_statement(bank_test_entry());
        $parsed=bank_statement_parse(bank_test_xml($first.$first));
        bank_test_require(count($parsed['entries'])===1&&$parsed['skipped_count']===1&&$parsed['statement_count']===2);
        $changed=bank_test_statement(bank_test_entry(['{amount}'=>'<Amt Ccy="EUR">12.31</Amt>']));
        bank_test_invalid(bank_test_xml($first.$changed));
    },
    'unsafe or malformed files and oversized inputs fail'=>static function(): void {
        $valid=bank_test_xml(bank_test_statement(bank_test_entry()));
        $inputs=[
            '',substr($valid,0,-1),str_replace(BANK_STATEMENT_NAMESPACE,'urn:other',$valid),
            str_replace('encoding="UTF-8"','encoding="ISO-8859-1"',$valid),
            str_replace('?><Document','?><!DOCTYPE Document><Document',$valid),
            str_replace(BANK_TEST_IBAN,'DE00370400440532013000',$valid),
            bank_test_xml(''),
            bank_test_xml(bank_test_statement(bank_test_entry(['{amount}'=>'']))),
            bank_test_xml(bank_test_statement(bank_test_entry(['{status}'=>'']))),
            bank_test_xml(bank_test_statement(bank_test_entry(['{amount}'=>'<Amt Ccy="EUR">1.00</Amt><Amt Ccy="EUR">2.00</Amt>']))),
            bank_test_xml(bank_test_statement(bank_test_entry(['{booked}'=>'<BookgDt><Dt>2026-11-19</Dt><DtTm>2026-11-19T00:00:00Z</DtTm></BookgDt>']))),
            bank_test_xml(bank_test_statement(str_repeat(bank_test_entry(),BANK_STATEMENT_MAX_ENTRIES+1))),
            str_repeat('x',BANK_STATEMENT_MAX_BYTES+1),
        ];
        foreach ($inputs as $xml) bank_test_invalid($xml);
    },
];

foreach ($tests as $name=>$test) {
    $test();
    echo 'PASS '.$name.PHP_EOL;
}
echo 'Bank statement checks passed: '.count($tests).PHP_EOL;
