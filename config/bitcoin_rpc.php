<?php
declare(strict_types=1);

require_once __DIR__.'/payments.php';

/** Bitcoin Core 30 receive-only adapter. The configured wallet endpoint never comes from a request. */
final class MarketBitcoin
{
    private const MAX_RESPONSE=2*1024*1024;
    private const MAX_MONEY=21000000;
    private const MAX_TXIDS=10;
    private const BECH32_CHARSET='qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    private const BECH32_GENERATORS=[0x3b6a57b2,0x26508e6d,0x1ea119fa,0x3d4233dd,0x2a1462b3];

    private string $origin;
    private string $scheme;
    private string $wallet;
    private string $chain;
    private string $authorization;
    private ?Closure $transport;

    /** An injected transport receives (URL, headers, JSON body) and returns ['status'=>int, 'body'=>string]. */
    public function __construct(array $config,?callable $transport=null)
    {
        $wallet=$config['wallet']??null;
        $chain=$config['chain']??null;
        $user=$config['rpc_user']??null;
        $password=$config['rpc_password']??null;
        if (!is_string($wallet)||!self::label($wallet)||!in_array($chain,['main','regtest'],true)
            ||!self::credential($user)||str_contains($user,':')||!self::credential($password)) {
            throw new PaymentUnavailable('Die Bitcoin-Node-Konfiguration ist unvollständig oder ungültig.');
        }
        [$this->origin,$this->scheme]=self::origin($config['rpc_url']??null);
        $this->wallet=$wallet;
        $this->chain=$chain;
        $this->authorization='Authorization: Basic '.base64_encode($user.':'.$password);
        $this->transport=$transport===null?null:Closure::fromCallable($transport);
    }

    public function assertReady(): void
    {
        $this->readyTip();
    }

    /** Keep address allocation for one reference under the caller's invoice lock. */
    public function address(string $reference): string
    {
        if (!self::label($reference)) throw new PaymentConflict('Ungültige Bitcoin-Zahlungsreferenz.');
        $this->assertReady();
        $result=$this->rpc('getaddressesbylabel',[$reference],true,-11);
        if ($result===null) {
            $address=$this->rpc('getnewaddress',[$reference,'bech32'],true);
            $result=$this->rpc('getaddressesbylabel',[$reference],true);
            if (!is_string($address)||$this->labelAddress($result)!==$address) throw self::invalidResponse();
        } else {
            $address=$this->labelAddress($result);
        }
        $this->addressLabel($address,$reference);
        return $address;
    }

    /** Return observed outputs at one consistent wallet and active-chain snapshot. */
    public function receipts(string $address,?string $knownTxid=null): array
    {
        if ($knownTxid!==null&&!self::hash($knownTxid)) throw new PaymentConflict('Ungültige Bitcoin-Transaktionskennung.');
        $tip=$this->readyTip();
        $label=$this->addressLabel($address);
        if ($knownTxid!==null) {
            $receipts=$this->transactionReceipts($address,$knownTxid,$tip,null);
            if ($this->readyTip()!==$tip) throw self::invalidResponse();
            return $receipts??[];
        }
        $tally=$this->addressTally($address,$label);
        $receipts=[];
        $accounted=0;
        $excluded=false;
        foreach ($tally['txids'] as $txid=>$expectedOutputs) {
            $observed=$this->transactionReceipts($address,$txid,$tip,$expectedOutputs);
            if ($observed===null) {
                $excluded=true;
                continue;
            }
            foreach ($observed as $receipt) {
                $accounted+=$receipt['satoshis'];
                $receipts[]=$receipt;
            }
        }
        if ((!$excluded&&$accounted!==$tally['satoshis'])||$accounted>$tally['satoshis']
            ||$this->addressTally($address,$label)!==$tally||$this->readyTip()!==$tip) {
            throw self::invalidResponse();
        }
        return $receipts;
    }

    private function transactionReceipts(string $address,string $txid,array $tip,?int $expectedOutputs): ?array
    {
        $transaction=$this->rpc('gettransaction',[$txid,true],true,$expectedOutputs===null?-5:null);
        if ($transaction===null) return null;
        if (!$transaction instanceof stdClass||($transaction->txid??null)!==$txid
            ||!is_int($transaction->confirmations??null)||!is_int($transaction->timereceived??null)
            ||$transaction->timereceived<1||!self::matchesTip($transaction->lastprocessedblock??null,$tip)) {
            throw self::invalidResponse();
        }
        if (self::transactionExcluded($transaction)) return null;
        $outputs=self::matchingOutputs($transaction,$address);
        if ($outputs===null) return null;
        if ($expectedOutputs!==null&&count($outputs)!==$expectedOutputs) throw self::invalidResponse();
        $blockHash=$this->activeBlock($transaction,$tip);
        $receipts=[];
        foreach ($outputs as $position=>$satoshis) {
            if ($satoshis===0) continue;
            $receipts[]=[
                'txid'=>$txid,
                'vout'=>$position,
                'satoshis'=>$satoshis,
                'confirmations'=>$transaction->confirmations,
                'block_hash'=>$blockHash,
                'received_at'=>$transaction->timereceived,
            ];
        }
        return $receipts;
    }

    private function readyTip(): array
    {
        $node=$this->rpc('getblockchaininfo',[],false);
        if (!$node instanceof stdClass||($node->chain??null)!==$this->chain
            ||($node->initialblockdownload??null)!==false||!is_int($node->blocks??null)||$node->blocks<0
            ||($node->headers??null)!==$node->blocks||!self::hash($node->bestblockhash??null)) {
            throw new PaymentUnavailable('Die Bitcoin-Node ist nicht für das konfigurierte Netz synchronisiert.');
        }
        $tip=['hash'=>$node->bestblockhash,'height'=>$node->blocks];
        $wallet=$this->rpc('getwalletinfo',[],true);
        if (!$wallet instanceof stdClass||($wallet->walletname??null)!==$this->wallet
            ||($wallet->descriptors??null)!==true||($wallet->private_keys_enabled??null)!==false
            ||($wallet->external_signer??null)!==false||($wallet->scanning??null)!==false
            ||!self::matchesTip($wallet->lastprocessedblock??null,$tip)) {
            throw new PaymentUnavailable('Die Bitcoin-Empfangswallet ist nicht synchronisiert oder ungeeignet.');
        }
        return $tip;
    }

    private function labelAddress(mixed $result): string
    {
        if (!$result instanceof stdClass) throw self::invalidResponse();
        $addresses=get_object_vars($result);
        if (count($addresses)!==1) throw new PaymentConflict('Der Bitcoin-Zahlungsreferenz sind nicht genau eine Empfangsadresse zugeordnet.');
        $address=array_key_first($addresses);
        if (!is_string($address)||!is_object($addresses[$address])||($addresses[$address]->purpose??null)!=='receive') {
            throw self::invalidResponse();
        }
        return $address;
    }

    private function addressLabel(string $address,?string $expected=null): string
    {
        $script=$this->witnessScript($address);
        $info=$this->rpc('getaddressinfo',[$address],true);
        if (!$info instanceof stdClass||($info->address??null)!==$address||($info->scriptPubKey??null)!==$script
            ||($info->ismine??null)!==true||($info->ischange??null)!==false
            ||($info->iswitness??null)!==true||($info->witness_version??null)!==0
            ||!is_array($info->labels??null)||count($info->labels)!==1||!self::label($info->labels[0]??null)
            ||($expected!==null&&$info->labels[0]!==$expected)) {
            throw self::invalidResponse();
        }
        return $info->labels[0];
    }

    private function addressTally(string $address,string $label): array
    {
        $rows=$this->rpc('listreceivedbyaddress',[0,true,true,$address,false],true);
        if (!is_array($rows)||count($rows)!==1||!$rows[0] instanceof stdClass) throw self::invalidResponse();
        $row=$rows[0];
        if (($row->address??null)!==$address||($row->label??null)!==$label
            ||!is_int($row->confirmations??null)||$row->confirmations<0||!is_array($row->txids??null)) {
            throw self::invalidResponse();
        }
        if (count($row->txids)>self::MAX_TXIDS) throw new PaymentConflict('Zu viele Bitcoin-Eingänge für diese Empfangsadresse.');
        $txids=[];
        foreach ($row->txids as $txid) {
            if (!self::hash($txid)) throw self::invalidResponse();
            // Core lists the transaction identifier once for each output assigned to the address.
            $txids[$txid]=($txids[$txid]??0)+1;
        }
        ksort($txids,SORT_STRING);
        return ['txids'=>$txids,'satoshis'=>self::satoshis($row->amount??null),'confirmations'=>$row->confirmations];
    }

    private static function transactionExcluded(stdClass $transaction): bool
    {
        foreach (['walletconflicts','mempoolconflicts'] as $field) {
            if (!is_array($transaction->$field??null)) throw self::invalidResponse();
            foreach ($transaction->$field as $conflict) if (!self::hash($conflict)) throw self::invalidResponse();
        }
        if (isset($transaction->generated)&&!is_bool($transaction->generated)) throw self::invalidResponse();
        if (isset($transaction->abandoned)&&!is_bool($transaction->abandoned)) throw self::invalidResponse();
        return $transaction->confirmations<0||($transaction->generated??false)||($transaction->abandoned??false)
            ||$transaction->walletconflicts!==[]||$transaction->mempoolconflicts!==[]||isset($transaction->replaced_by_txid);
    }

    private static function matchingOutputs(stdClass $transaction,string $address): ?array
    {
        if (!is_array($transaction->details??null)) throw self::invalidResponse();
        $outputs=[];
        foreach ($transaction->details as $detail) {
            if (!$detail instanceof stdClass||!is_string($detail->category??null)) throw self::invalidResponse();
            if (($detail->address??null)!==$address||$detail->category!=='receive') continue;
            $position=$detail->vout??null;
            if (!is_int($position)||$position<0||$position>0xffffffff||isset($outputs[$position])
                ||!is_bool($detail->abandoned??null)) throw self::invalidResponse();
            // A transaction can appear in the tally after its wallet entry was abandoned.
            if ($detail->abandoned) return null;
            $outputs[$position]=self::satoshis($detail->amount??null);
        }
        ksort($outputs,SORT_NUMERIC);
        return $outputs;
    }

    private function activeBlock(stdClass $transaction,array $tip): ?string
    {
        if ($transaction->confirmations===0) {
            if (isset($transaction->blockhash)||isset($transaction->blockheight)) throw self::invalidResponse();
            return null;
        }
        $hash=$transaction->blockhash??null;
        $height=$transaction->blockheight??null;
        if (!self::hash($hash)||!is_int($height)||$height<0
            ||$tip['height']-$height+1!==$transaction->confirmations) {
            throw self::invalidResponse();
        }
        $header=$this->rpc('getblockheader',[$hash,true],false);
        if (!$header instanceof stdClass||($header->hash??null)!==$hash||($header->height??null)!==$height
            ||($header->confirmations??null)!==$transaction->confirmations
            ||($height===$tip['height']&&$hash!==$tip['hash'])) {
            throw self::invalidResponse();
        }
        return $hash;
    }

    private function witnessScript(string $address): string
    {
        $human=$this->chain==='main'?'bc':'bcrt';
        $prefix=$human.'1';
        if (!str_starts_with($address,$prefix)||!in_array(strlen($address)-strlen($prefix),[39,59],true)) {
            throw new PaymentConflict('Ungültige Bitcoin-Empfangsadresse für das konfigurierte Netz.');
        }
        $values=[];
        foreach (str_split(substr($address,strlen($prefix))) as $character) {
            $value=strpos(self::BECH32_CHARSET,$character);
            if ($value===false) throw self::invalidResponse();
            $values[]=$value;
        }
        $expanded=array_values(unpack('C*',$human));
        $high=array_map(static fn(int $byte):int=>$byte>>5,$expanded);
        $low=array_map(static fn(int $byte):int=>$byte&31,$expanded);
        $checksum=1;
        foreach (array_merge($high,[0],$low,$values) as $value) {
            $top=$checksum>>25;
            $checksum=(($checksum&0x1ffffff)<<5)^$value;
            foreach (self::BECH32_GENERATORS as $index=>$generator) {
                if (($top>>$index)&1) $checksum^=$generator;
            }
        }
        if ($checksum!==1||$values[0]!==0) throw self::invalidResponse();

        $program='';
        $buffer=0;
        $bits=0;
        foreach (array_slice($values,1,-6) as $value) {
            $buffer=($buffer<<5)|$value;
            $bits+=5;
            if ($bits>=8) {
                $bits-=8;
                $program.=chr($buffer>>$bits);
                $buffer&=(1<<$bits)-1;
            }
        }
        if ($buffer!==0||$bits>=5||!in_array(strlen($program),[20,32],true)) throw self::invalidResponse();
        return bin2hex("\x00".chr(strlen($program)).$program);
    }

    private function rpc(string $method,array $params,bool $wallet,?int $absentError=null): mixed
    {
        $id=bin2hex(random_bytes(12));
        $url=$this->origin.($wallet?'/wallet/'.rawurlencode($this->wallet):'/');
        $headers=['Content-Type: application/json','Accept: application/json',$this->authorization];
        $body=json_encode(['jsonrpc'=>'2.0','id'=>$id,'method'=>$method,'params'=>$params],JSON_THROW_ON_ERROR);
        try {
            $response=$this->transport===null?$this->request($url,$headers,$body):($this->transport)($url,$headers,$body);
        } catch (Throwable) {
            throw new PaymentUnavailable('Die Bitcoin-Node ist vorübergehend nicht erreichbar.');
        }
        if (!is_array($response)||($response['status']??null)!==200||!is_string($response['body']??null)
            ||strlen($response['body'])>self::MAX_RESPONSE) throw self::invalidResponse();
        try { $reply=json_decode($response['body'],false,64,JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw self::invalidResponse(); }
        if (!$reply instanceof stdClass||($reply->jsonrpc??null)!=='2.0'||($reply->id??null)!==$id) {
            throw self::invalidResponse();
        }
        if (property_exists($reply,'error')) {
            if (!property_exists($reply,'result')&&$reply->error instanceof stdClass
                &&$absentError!==null&&($reply->error->code??null)===$absentError) return null;
            throw new PaymentUnavailable('Die Bitcoin-Node hat die Anfrage nicht bestätigt.');
        }
        if (!isset($reply->result)) throw self::invalidResponse();
        return $reply->result;
    }

    private function request(string $url,array $headers,string $request): array
    {
        if (!function_exists('curl_init')) throw new PaymentUnavailable('Die sichere Bitcoin-Anbindung ist nicht verfügbar.');
        $handle=curl_init($url);
        if ($handle===false) throw self::invalidResponse();
        $body='';
        curl_setopt_array($handle,[
            CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$request,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROTOCOLS=>$this->scheme==='https'?CURLPROTO_HTTPS:CURLPROTO_HTTP,
            CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int {
                if (strlen($body)+strlen($chunk)>self::MAX_RESPONSE) return 0;
                $body.=$chunk;
                return strlen($chunk);
            },
        ]);
        $bundle=getenv('CURL_CA_BUNDLE')?:getenv('SSL_CERT_FILE');
        if (is_string($bundle)&&$bundle!=='') curl_setopt($handle,CURLOPT_CAINFO,$bundle);
        try {
            if (curl_exec($handle)!==true) throw new PaymentUnavailable('Die Bitcoin-Node ist vorübergehend nicht erreichbar.');
            return ['status'=>(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE),'body'=>$body];
        } finally {
            curl_close($handle);
        }
    }

    private static function origin(mixed $value): array
    {
        if (!is_string($value)||strlen($value)>512||preg_match('/[\x00-\x20\x7f\\\\]/',$value)) throw self::invalidOrigin();
        $parts=parse_url($value);
        if ($parts===false||!isset($parts['scheme'],$parts['host'])||isset($parts['user'])||isset($parts['pass'])
            ||isset($parts['query'])||isset($parts['fragment'])||!in_array($parts['path']??'',['','/'],true)
            ||(isset($parts['port'])&&$parts['port']<1)) throw self::invalidOrigin();
        $scheme=$parts['scheme'];
        $host=$parts['host'];
        if ($scheme==='http') {
            if (!in_array($host,['127.0.0.1','[::1]'],true)) throw self::invalidOrigin();
        } elseif ($scheme==='https') {
            $address=str_starts_with($host,'[')&&str_ends_with($host,']')?substr($host,1,-1):$host;
            if (!filter_var($address,FILTER_VALIDATE_IP)&&!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)) {
                throw self::invalidOrigin();
            }
        } else {
            throw self::invalidOrigin();
        }
        $origin=$scheme.'://'.$host.(isset($parts['port'])?':'.$parts['port']:'');
        if ($value!==$origin&&$value!==$origin.'/') throw self::invalidOrigin();
        return [$origin,$scheme];
    }

    private static function satoshis(mixed $btc): int
    {
        if ((!is_int($btc)&&!is_float($btc))||!is_finite((float)$btc)||$btc<0||$btc>self::MAX_MONEY) {
            throw self::invalidResponse();
        }
        // At 21 million BTC, binary64 decoding plus scaling error stays below half a satoshi.
        $satoshis=(int)round($btc*100000000);
        if ((float)($satoshis/100000000)!==(float)$btc) throw self::invalidResponse();
        return $satoshis;
    }

    private static function label(mixed $value): bool
    {
        return is_string($value)&&preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/',$value)===1;
    }

    private static function hash(mixed $value): bool
    {
        return is_string($value)&&preg_match('/\A[a-f0-9]{64}\z/',$value)===1;
    }

    private static function credential(mixed $value): bool
    {
        return is_string($value)&&$value!==''&&strlen($value)<=512&&!preg_match('/[\x00-\x1f\x7f]/',$value);
    }

    private static function matchesTip(mixed $block,array $tip): bool
    {
        return $block instanceof stdClass&&($block->hash??null)===$tip['hash']&&($block->height??null)===$tip['height'];
    }

    private static function invalidOrigin(): PaymentUnavailable
    {
        return new PaymentUnavailable('Die Bitcoin-Node benötigt eine feste HTTPS-Adresse oder eine lokale Loopback-Adresse.');
    }

    private static function invalidResponse(): PaymentUnavailable
    {
        return new PaymentUnavailable('Die Antwort der Bitcoin-Node ist ungültig oder nicht konsistent.');
    }
}
