<?php
declare(strict_types=1);

require_once __DIR__.'/money.php';

const BANK_STATEMENT_NAMESPACE='urn:iso:std:iso:20022:tech:xsd:camt.053.001.08';
const BANK_STATEMENT_MAX_BYTES=5*1024*1024;
const BANK_STATEMENT_MAX_STATEMENTS=100;
const BANK_STATEMENT_MAX_ENTRIES=2000;

/**
 * Read the booked, single-transaction EUR subset of camt.053.001.08.
 * This checks structure and extracts evidence. Provenance must be verified by the importer.
 */
function bank_statement_parse(string $xml): array
{
    if ($xml===''||strlen($xml)>BANK_STATEMENT_MAX_BYTES||str_contains($xml,"\0")
        ||preg_match('//u',$xml)!==1||preg_match('/<!DOCTYPE\b|<!ENTITY\b/',$xml)) {
        bank_statement_invalid();
    }
    $document=new DOMDocument();
    $options=LIBXML_NONET|LIBXML_COMPACT;
    if (defined('LIBXML_NO_XXE')) $options|=LIBXML_NO_XXE;
    $previous=libxml_use_internal_errors(true);
    try {
        $loaded=$document->loadXML($xml,$options);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    if (!$loaded||$document->doctype!==null||strtoupper($document->encoding??'UTF-8')!=='UTF-8') {
        bank_statement_invalid();
    }

    $xpath=new DOMXPath($document);
    $xpath->registerNamespace('c',BANK_STATEMENT_NAMESPACE);
    $body=bank_statement_element($xpath,'/c:Document/c:BkToCstmrStmt');
    if ($body===null||$xpath->query('/c:Document/*')->length!==1) bank_statement_invalid();
    $statements=$xpath->query('./c:Stmt',$body);
    if ($statements->length<1||$statements->length>BANK_STATEMENT_MAX_STATEMENTS
        ||$xpath->query('./c:Stmt/c:Ntry',$body)->length>BANK_STATEMENT_MAX_ENTRIES) {
        bank_statement_invalid();
    }

    $accounts=[];
    $entries=[];
    $skipped=0;
    foreach ($statements as $statement) {
        $iban=bank_statement_iban(bank_statement_text($xpath,'./c:Acct/c:Id/c:IBAN',$statement)??'');
        if ($iban===null) bank_statement_invalid();
        $accounts[$iban]=true;
        $accountCurrency=bank_statement_text($xpath,'./c:Acct/c:Ccy',$statement);
        foreach ($xpath->query('./c:Ntry',$statement) as $entry) {
            $booking=($accountCurrency===null||$accountCurrency==='EUR')
                ? bank_statement_booking($xpath,$entry,$iban)
                : null;
            if ($booking===null) {
                ++$skipped;
                continue;
            }
            $key=$iban."\0".$booking['direction']."\0".$booking['source_reference'];
            if (isset($entries[$key])) {
                if ($entries[$key]!==$booking) bank_statement_invalid();
                ++$skipped;
                continue;
            }
            $entries[$key]=$booking;
        }
    }
    return [
        'statement_count'=>$statements->length,
        'skipped_count'=>$skipped,
        'accounts'=>array_keys($accounts),
        'entries'=>array_values($entries),
    ];
}

function bank_statement_invalid(): never
{
    throw new InvalidArgumentException(function_exists('t')?t('bank.invalid_statement'):'Invalid camt.053.001.08 statement.');
}

function bank_statement_element(DOMXPath $xpath,string $path,?DOMElement $context=null): ?DOMElement
{
    $nodes=$xpath->query($path,$context);
    if ($nodes->length>1) bank_statement_invalid();
    return $nodes->item(0);
}

function bank_statement_scalar(DOMElement $element): string
{
    if ($element->childElementCount!==0) bank_statement_invalid();
    return trim($element->textContent," \t\r\n");
}

function bank_statement_text(DOMXPath $xpath,string $path,DOMElement $context): ?string
{
    $element=bank_statement_element($xpath,$path,$context);
    return $element===null?null:bank_statement_scalar($element);
}

/** Normalize and validate the ISO 13616 checksum without large integer arithmetic. */
function bank_statement_iban(string $value): ?string
{
    $iban=strtoupper(str_replace([" ","\t","\r","\n"],'',$value));
    if (!preg_match('/\A[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}\z/',$iban)) return null;
    $rearranged=substr($iban,4).substr($iban,0,4);
    $remainder=0;
    foreach (str_split($rearranged) as $character) {
        $digit=ord($character);
        $remainder=$digit<=57
            ? ($remainder*10+$digit-48)%97
            : ($remainder*100+$digit-55)%97;
    }
    return $remainder===1?$iban:null;
}

function bank_statement_booking(DOMXPath $xpath,DOMElement $entry,string $iban): ?array
{
    $status=bank_statement_text($xpath,'./c:Sts/c:Cd',$entry);
    $indicator=bank_statement_text($xpath,'./c:CdtDbtInd',$entry);
    $reversal=bank_statement_text($xpath,'./c:RvslInd',$entry);
    $amountElement=bank_statement_element($xpath,'./c:Amt',$entry);
    if ($amountElement===null||$indicator===null
        ||bank_statement_element($xpath,'./c:Sts',$entry)===null) bank_statement_invalid();
    if ($status!=='BOOK'||!in_array($indicator,['CRDT','DBIT'],true)
        ||!in_array($reversal,[null,'false','0','true','1'],true)) return null;

    $details=$xpath->query('./c:NtryDtls',$entry);
    $transactions=$xpath->query('./c:NtryDtls/c:TxDtls',$entry);
    if ($details->length!==1||$transactions->length!==1
        ||bank_statement_element($xpath,'./c:AddtlInfInd',$entry)!==null) return null;
    $transaction=$transactions->item(0);
    $batchSize=bank_statement_text($xpath,'./c:NtryDtls/c:Btch/c:NbOfTxs',$entry);
    if ($batchSize!==null&&!preg_match('/\A0*1\z/',$batchSize)) return null;

    $transactionIndicator=bank_statement_text($xpath,'./c:CdtDbtInd',$transaction);
    if ($transactionIndicator!==null&&$transactionIndicator!==$indicator) return null;
    $returnInformation=bank_statement_element($xpath,'./c:RtrInf',$transaction);
    $isReversal=in_array($reversal,['true','1'],true)||$returnInformation!==null;
    if (($indicator==='CRDT'&&$isReversal)||($indicator==='DBIT'&&!$isReversal)) return null;

    $amount=bank_statement_cents($amountElement);
    $transactionAmount=bank_statement_element($xpath,'./c:Amt',$transaction);
    if ($amount===null||($transactionAmount!==null&&bank_statement_cents($transactionAmount)!==$amount)) {
        return null;
    }

    $sourceReference=bank_statement_text($xpath,'./c:AcctSvcrRef',$entry)
        ??bank_statement_text($xpath,'./c:Refs/c:AcctSvcrRef',$transaction);
    if ($sourceReference===null||$sourceReference===''||strlen($sourceReference)>128
        ||in_array(strtoupper($sourceReference),['NOTPROVIDED','NONREF'],true)
        ||preg_match('/\p{C}/u',$sourceReference)) return null;

    $bookingDate=bank_statement_booking_date($xpath,$entry);
    if ($bookingDate===null) return null;
    return [
        'iban'=>$iban,
        'source_reference'=>$sourceReference,
        'booking_date'=>$bookingDate,
        'amount_cents'=>$amount,
        'payment_reference'=>bank_statement_payment_reference($xpath,$transaction),
        'direction'=>$isReversal?'reversal':'credit',
    ];
}

function bank_statement_cents(DOMElement $element): ?int
{
    if ($element->getAttribute('Ccy')!=='EUR'
        ||!preg_match('/\A(0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/',bank_statement_scalar($element),$match)) {
        return null;
    }
    $cents=money_parse($match[0]);
    return $cents>0?$cents:null;
}

function bank_statement_booking_date(DOMXPath $xpath,DOMElement $entry): ?string
{
    $date=bank_statement_text($xpath,'./c:BookgDt/c:Dt',$entry);
    $dateTime=bank_statement_text($xpath,'./c:BookgDt/c:DtTm',$entry);
    if ($date!==null&&$dateTime!==null) bank_statement_invalid();
    if ($dateTime!==null) {
        if (!preg_match('/\A([0-9]{4}-[0-9]{2}-[0-9]{2})T(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9](?:\.[0-9]{1,9})?(?:Z|[+-](?:(?:0[0-9]|1[0-3]):[0-5][0-9]|14:00))?\z/',$dateTime,$match)) return null;
        $date=$match[1];
    }
    if ($date===null||!preg_match('/\A([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})\z/',$date,$match)
        ||!checkdate((int)$match[2],(int)$match[3],(int)$match[1])) return null;
    return $date;
}

function bank_statement_payment_reference(DOMXPath $xpath,DOMElement $transaction): ?string
{
    $references=[];
    $fields=$xpath->query('./c:Refs/c:EndToEndId|./c:RmtInf/c:Ustrd|./c:RmtInf/c:Strd/c:CdtrRefInf/c:Ref',$transaction);
    foreach ($fields as $field) {
        preg_match_all('/(?<![A-Z0-9-])GI-B-[A-F0-9]{24}(?![A-Z0-9-])/i',bank_statement_scalar($field),$matches);
        foreach ($matches[0] as $candidate) $references[strtoupper($candidate)]=true;
        if (count($references)>1) return null;
    }
    return array_key_first($references);
}
