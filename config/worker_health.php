<?php
declare(strict_types=1);

function worker_heartbeat_limits(): array
{
    return ['auction'=>120,'mail'=>180,'discovery'=>240,'business'=>180,'privacy'=>900,'paypal-refund'=>180,'bitcoin'=>180];
}

/** Read only a small regular heartbeat file, owned by the worker runtime. */
function worker_read_heartbeat(string $path): ?string
{
    if (is_link($path)) return '';
    if (!is_file($path)) return null;
    $handle=@fopen($path,'rb');
    if ($handle===false) return '';
    try { $text=fread($handle,32); return is_string($text)&&feof($handle)?$text:''; }
    finally { fclose($handle); }
}

function worker_heartbeat_status(?string $success, ?string $failure, int $now, int $maximumAge): array
{
    $parse=static function (?string $value) use ($now): ?int {
        if ($value===null||!preg_match('/\A[0-9]{1,12}\s*\z/',$value)) return null;
        $time=(int)trim($value);
        return $time>0&&$time<=$now?$time:null;
    };
    $last=$parse($success);$failed=$parse($failure);
    $age=$last===null?null:$now-$last;
    $problem=$failure!==null&&($failed===null||$last===null||$failed>=$last);
    return ['last_success_age_seconds'=>$age,'recent_failure'=>$problem,'ready'=>$age!==null&&$age<=$maximumAge&&!$problem];
}
