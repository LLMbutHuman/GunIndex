<?php
declare(strict_types=1);
require_once __DIR__.'/i18n.php';

/** Configured document codes, never executable paths or translated guesses. */
function consent_codes(string $value): array
{
    if ($value==='') return [];
    if (strlen($value)>809) throw new InvalidArgumentException(t('consent.invalid_codes'));
    $codes=explode(',',$value);
    if(count($codes)>10||count(array_unique($codes))!==count($codes))throw new InvalidArgumentException(t('consent.invalid_codes'));
    foreach($codes as $code)if(!preg_match('/\A[a-z0-9-]{2,80}\z/',$code))throw new InvalidArgumentException(t('consent.invalid_codes'));
    sort($codes,SORT_STRING);return $codes;
}

function consent_integrity(array $document): void
{
    if(!is_string($document['body_text']??null)||!is_string($document['content_hash']??null)
        ||!hash_equals(hash('sha256',$document['body_text']),$document['content_hash']))throw new DomainException(t('consent.unavailable'));
}

/** Lock current requirements and document rows during the enclosing business transaction. */
function consent_documents(PDO $pdo,string $scope,string $locale,bool $lock=false): array
{
    if(!in_array($scope,['registration','checkout'],true)||!isset(i18n_locales()[$locale]))throw new InvalidArgumentException(t('consent.changed'));
    if($lock&&!$pdo->inTransaction())throw new LogicException('Consent locking requires a transaction.');
    $key='legal_'.$scope.'_documents';
    // Read raw values: a corrupt requirement must not silently fall back to no requirements.
    $q=$pdo->prepare('SELECT setting_key,setting_value FROM system_settings WHERE setting_key IN (?,?) ORDER BY setting_key'.($lock?' FOR SHARE':''));
    $q->execute([$key,'legal_publication_enabled']);$settings=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    $codes=consent_codes($settings[$key]??'');if(!$codes)return [];
    if(($settings['legal_publication_enabled']??'0')!=='1')throw new DomainException(t('consent.unavailable'));
    $documents=[];
    foreach($codes as $code){
        $q=$pdo->prepare("SELECT * FROM market_legal_documents WHERE document_code=? AND language=? AND status='approved' AND effective_at<=UTC_TIMESTAMP() ORDER BY effective_at DESC,id DESC LIMIT 1".($lock?' FOR SHARE':''));
        $q->execute([$code,$locale]);$document=$q->fetch();
        if(!$document)throw new DomainException(t('consent.unavailable'));
        consent_integrity($document);$documents[]=$document;
    }
    return $documents;
}

function consent_validate_submission(array $documents,array $input,string $locale): void
{
    $submitted=$input['legal_documents']??[];
    if(!is_array($submitted)||count($submitted)!==count($documents))throw new InvalidArgumentException(t('consent.changed'));
    if($documents&&(($input['legal_confirm']??null)!=='1'||($input['legal_language']??null)!==$locale))throw new InvalidArgumentException(t('consent.confirm'));
    foreach($documents as $doc){
        consent_integrity($doc);$hash=$submitted[(int)$doc['id']]??null;
        if(!is_string($hash)||!hash_equals($doc['content_hash'],$hash))throw new InvalidArgumentException(t('consent.changed'));
    }
}

function consent_check(PDO $pdo,string $scope,array $input,string $locale): array
{
    $documents=consent_documents($pdo,$scope,$locale,true);
    consent_validate_submission($documents,$input,$locale);return $documents;
}

/** The enclosing transaction commits the receipt together with account/order creation. */
function consent_record(PDO $pdo,int $userId,string $scope,?int $invoiceId,array $documents): void
{
    if(!$pdo->inTransaction()||$userId<1||!in_array($scope,['registration','checkout'],true)
        ||($scope==='registration'&&$invoiceId!==null)||($scope==='checkout'&&($invoiceId===null||$invoiceId<1)))throw new LogicException('Invalid consent context.');
    if($invoiceId!==null){$q=$pdo->prepare('SELECT user_id FROM market_invoices WHERE id=? FOR SHARE');$q->execute([$invoiceId]);if((int)$q->fetchColumn()!==$userId)throw new DomainException(t('consent.changed'));}
    $q=$pdo->prepare('INSERT INTO market_contract_receipts(user_id,scope,context_key,invoice_id,document_id,content_hash) VALUES (?,?,?,?,?,?)');
    foreach($documents as $doc){
        legal_accept($pdo,$userId,(int)$doc['id'],$scope);
        $q->execute([$userId,$scope,$invoiceId===null?'account':'invoice:'.$invoiceId,$invoiceId,$doc['id'],$doc['content_hash']]);
    }
}

/** Render only validated, explicitly approved versions; no preselected consent. */
function consent_form(array $documents,string $locale,bool $required=true): void
{
    if(!$documents)return;
    echo '<fieldset class="panel span-2 contract-documents"><legend>'.h(t('consent.title')).'</legend><p>'.h(t('consent.help')).'</p>';
    foreach($documents as $doc){
        $url='/marketplace/legal.php?document='.rawurlencode($doc['document_code']).'&language='.rawurlencode($doc['language']).'&version_id='.(int)$doc['id'];
        echo '<p><a href="'.h($url).'" target="_blank" rel="noopener">'.h($doc['title'].' · '.$doc['version'].' / '.$doc['language']).'</a></p>';
        echo '<input type="hidden" name="legal_documents['.(int)$doc['id'].']" value="'.h($doc['content_hash']).'">';
    }
    echo '<input type="hidden" name="legal_language" value="'.h($locale).'"><label class="check"><input type="checkbox" name="legal_confirm" value="1"'.($required?' required':'').'> '.h(t('consent.accept')).'</label></fieldset>';
}

function consent_order_links(PDO $pdo,int $userId,int $invoiceId): void
{
    $q=$pdo->prepare("SELECT d.*,r.content_hash AS accepted_hash FROM market_contract_receipts r JOIN market_legal_documents d ON d.id=r.document_id JOIN market_invoices i ON i.id=r.invoice_id WHERE r.user_id=? AND i.user_id=? AND r.invoice_id=? AND r.scope='checkout' ORDER BY r.id");
    $q->execute([$userId,$userId,$invoiceId]);$documents=$q->fetchAll();if(!$documents)return;
    foreach($documents as $doc){consent_integrity($doc);if(!hash_equals($doc['accepted_hash'],$doc['content_hash']))throw new DomainException(t('consent.unavailable'));}
    echo '<section class="panel contract-documents"><h2>'.h(t('consent.title')).'</h2>';
    foreach($documents as $doc){$url='/marketplace/legal.php?document='.rawurlencode($doc['document_code']).'&language='.rawurlencode($doc['language']).'&version_id='.(int)$doc['id'];echo '<p><a href="'.h($url).'">'.h($doc['title'].' · '.$doc['version'].' / '.$doc['language']).'</a></p>';}
    echo '</section>';
}
