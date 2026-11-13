<?php
declare(strict_types=1);
/** CLI-only, synthetic browser fixtures. Never prints keys, ciphertext or private paths. */
if (PHP_SAPI!=='cli'||PHP_OS_FAMILY==='Windows'||getenv('DB_DATABASE')!=='gunindex_rebuild_test') exit(2);
require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/../config/e2ee.php';
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='gunindex_rebuild_test') exit(2);

function ebf_assert(bool $condition): void
{
    if (!$condition) throw new RuntimeException('Synthetic E2EE browser fixture invariant failed.');
}
function ebf_user(PDO $pdo,string $fixture,string $role): array
{
    $username='e2eweb_'.$fixture.'_'.$role; $password='Synthetic E2EE '.bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO users(username,email,password_hash,full_name,zip,city,country_code,preferred_locale) VALUES (?,?,?,'Synthetic E2EE browser fixture','10115','Synthetic town','DE','de')")
        ->execute([$username,$username.'@example.invalid',auth_hash($password)]);
    return ['id'=>(int)$pdo->lastInsertId(),'username'=>$username,'email'=>$username.'@example.invalid','password'=>$password];
}
function ebf_owners(PDO $pdo,mixed $fixture): array
{
    if (!is_string($fixture)||!preg_match('/\A[a-f0-9]{32}\z/',$fixture)) throw new InvalidArgumentException('Synthetic fixture identifier required.');
    $owners=[];
    foreach (['seller','buyer','outsider'] as $role) {
        $q=$pdo->prepare("SELECT id,username FROM users WHERE username=? AND email=CONCAT(username,'@example.invalid') AND full_name='Synthetic E2EE browser fixture' AND is_admin=0");
        $q->execute(['e2eweb_'.$fixture.'_'.$role]); $row=$q->fetch(); ebf_assert(is_array($row));
        $owners[$role]=['id'=>(int)$row['id'],'username'=>$row['username']];
    }
    return $owners;
}
function ebf_scan(string $bytes,string $probe,array &$findings): void
{
    if ($probe!==''&&str_contains($bytes,$probe)) $findings['plaintext_found']=true;
    foreach (['-----BEGIN PGP PRIVATE KEY BLOCK-----','-----BEGIN PRIVATE KEY-----','-----BEGIN ENCRYPTED PRIVATE KEY-----'] as $marker)
        if (str_contains($bytes,$marker)) $findings['private_key_marker_found']=true;
}

try {
    $raw=stream_get_contents(STDIN,16385);
    if (strlen($raw)>16384) throw new InvalidArgumentException('Fixture request too large.');
    $input=json_decode($raw,true,12,JSON_THROW_ON_ERROR); ebf_assert(is_array($input));
    $operation=$input['operation']??'';
    if ($operation==='setup') {
        ebf_assert(e2ee_enabled()&&geo_country_enabled($pdo,'DE'));
        $fixture=bin2hex(random_bytes(16)); $users=[];
        $pdo->beginTransaction();
        try {
            maintenance_assert_market_available($pdo);
            foreach (['seller','buyer','outsider'] as $role) $users[$role]=ebf_user($pdo,$fixture,$role);
            $pdo->prepare('INSERT INTO market_categories(name,slug) VALUES (?,?)')->execute(['Synthetic E2EE desks','e2eweb-'.$fixture]);
            $category=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code) VALUES (?,?,'Synthetic E2EE folding desk','Ordinary synthetic folding desk for isolated encrypted-chat browser tests.','fixed',4500,'active','10115','Synthetic town','DE')")
                ->execute([$users['seller']['id'],$category]);
            $listing=(int)$pdo->lastInsertId(); $pdo->commit();
        } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
        $out=['fixture'=>$fixture,'users'=>$users,'listing_id'=>$listing];
    } elseif (in_array($operation,['status','restore'],true)) {
        $owners=ebf_owners($pdo,$input['fixture']??null);
        if ($operation==='restore') {
            // Setup changes no global settings. Retain synthetic rows for diagnostic evidence.
            $out=['restored'=>true,'global_changes'=>false];
        } else {
            $probe=$input['plaintext_probe']??'';
            if (!is_string($probe)||strlen($probe)>5000) throw new InvalidArgumentException('Invalid synthetic plaintext probe.');
            $q=$pdo->prepare('SELECT l.id FROM market_listings l JOIN market_categories c ON c.id=l.category_id WHERE l.seller_id=? AND c.slug=?');
            $q->execute([$owners['seller']['id'],'e2eweb-'.$input['fixture']]); $listing=(int)$q->fetchColumn(); ebf_assert($listing>0);
            $findings=['plaintext_found'=>false,'private_key_marker_found'=>false,'cipher2_empty_legacy_text'=>true];
            $q=$pdo->prepare('SELECT id,sender_id,receiver_id,cipher_version,message_text FROM market_messages WHERE listing_id=? ORDER BY id LIMIT 501');
            $q->execute([$listing]); $rows=$q->fetchAll(); ebf_assert(count($rows)<=500); $messages=[];
            foreach ($rows as $row) {
                if ((int)$row['cipher_version']===2&&$row['message_text']!=='') $findings['cipher2_empty_legacy_text']=false;
                ebf_scan($row['message_text'],$probe,$findings);
                $messages[]=['id'=>(int)$row['id'],'sender_id'=>(int)$row['sender_id'],'receiver_id'=>(int)$row['receiver_id'],
                    'cipher_version'=>(int)$row['cipher_version'],'legacy_text_bytes'=>strlen($row['message_text'])];
            }
            $q=$pdo->prepare('SELECT e.ciphertext,e.message_id,e.device_id,e.client_id,e.sender_version,e.receiver_version FROM market_e2ee_messages e JOIN market_messages m ON m.id=e.message_id WHERE m.listing_id=? ORDER BY e.message_id LIMIT 501');
            $q->execute([$listing]); $encrypted=$q->fetchAll(); ebf_assert(count($encrypted)<=500); $envelopes=[];
            foreach ($encrypted as $row) {
                ebf_scan($row['ciphertext'],$probe,$findings);
                $decoded=base64_decode($row['ciphertext'],true); if (is_string($decoded)) ebf_scan($decoded,$probe,$findings);
                $envelopes[]=['message_id'=>(int)$row['message_id'],'device_id'=>$row['device_id'],'client_id'=>$row['client_id'],
                    'sender_version'=>(int)$row['sender_version'],'receiver_version'=>(int)$row['receiver_version'],'stored_ciphertext_bytes'=>strlen($row['ciphertext'])];
            }
            $accounts=[];
            foreach ($owners as $role=>$owner) {
                $q=$pdo->prepare('SELECT current_version,current_hash,identity_key FROM market_e2ee_accounts WHERE user_id=?'); $q->execute([$owner['id']]); $account=$q->fetch();
                $accounts[$role]=$account?['version'=>(int)$account['current_version'],'manifest_hash'=>$account['current_hash']]:null;
                $q=$pdo->prepare('SELECT manifest,recovery_cipher FROM market_e2ee_manifests WHERE user_id=? ORDER BY version'); $q->execute([$owner['id']]);
                foreach ($q->fetchAll() as $manifest) {
                    ebf_scan($manifest['manifest'],$probe,$findings); ebf_scan($manifest['recovery_cipher'],$probe,$findings);
                    $decoded=base64_decode($manifest['recovery_cipher'],true); if (is_string($decoded)) ebf_scan($decoded,$probe,$findings);
                }
            }
            $q=$pdo->prepare('SELECT id,owner_id,cipher_hash,cipher_bytes,message_id FROM market_e2ee_attachments WHERE listing_id=? ORDER BY id LIMIT 101');
            $q->execute([$listing]); $attachments=[];
            foreach ($q->fetchAll() as $row) {
                $download=e2ee_download($pdo,(int)$row['owner_id'],$row['id']);
                ebf_assert(is_resource($download['stream']??null));
                try { $bytes=stream_get_contents($download['stream']); }
                finally { fclose($download['stream']); }
                ebf_assert(is_string($bytes)); ebf_scan($bytes,$probe,$findings);
                $attachments[]=['id'=>$row['id'],'owner_id'=>(int)$row['owner_id'],'bytes'=>(int)$row['cipher_bytes'],
                    'sha256'=>$row['cipher_hash'],'message_id'=>$row['message_id']===null?null:(int)$row['message_id'],'private_file_present'=>true];
            }
            $out=['listing_id'=>$listing,'messages'=>$messages,'envelopes'=>$envelopes,'accounts'=>$accounts,'attachments'=>$attachments]+$findings;
        }
    } else throw new InvalidArgumentException('Unsupported E2EE fixture operation.');
    echo json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,'Synthetic E2EE fixture failed: '.get_class($error).' at '.basename($error->getFile()).':'.$error->getLine().PHP_EOL);
    exit(1);
}
