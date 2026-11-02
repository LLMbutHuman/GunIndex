<?php
declare(strict_types=1);

/** Short-lived session lists, scoped to one authenticated account, never product content. */
function personal_state(array &$session, int $userId, ?int $now = null): array
{
    $now ??= time();
    if ($userId<1) throw new InvalidArgumentException('Authenticated account required.');
    $state=$session['personal_lists']??null;
    if (!is_array($state)||($state['user_id']??null)!==$userId||!is_int($state['created']??null)
        ||$state['created']>$now||$now-$state['created']>=43200) {
        $state=['user_id'=>$userId,'created'=>$now,'recent_enabled'=>false,'recent'=>[],'compare'=>[]];
    }
    $state['recent_enabled']=($state['recent_enabled']??false)===true;
    foreach (['recent'=>20,'compare'=>4] as $bucket=>$limit) {
        $entries=[];
        foreach (array_slice(is_array($state[$bucket]??null)?$state[$bucket]:[],0,$limit,true) as $id=>$seen) {
            if (filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false
                ||!is_int($seen)||$seen>$now||$now-$seen>=21600) continue;
            $entries[(int)$id]=$seen;
        }
        $state[$bucket]=$entries;
    }
    if (!$state['recent_enabled']) $state['recent']=[];
    // Rebuild the allowlisted shape so arbitrary session fields cannot persist here.
    $session['personal_lists']=array_intersect_key($state,array_flip(['user_id','created','recent_enabled','recent','compare']));
    return $session['personal_lists'];
}

function personal_current(array $listing, ?int $now=null): bool
{
    if (($listing['status']??'')!=='active') return false;
    if (($listing['type']??'')==='fixed') return true;
    if (($listing['type']??'')!=='auction'||!is_string($listing['ends_at']??null)) return false;
    $end=strtotime($listing['ends_at'].' UTC');
    return $end!==false&&$end>($now??time());
}

function personal_record_view(array &$session, int $userId, array $publicListing, ?int $now=null): void
{
    $now??=time();$state=personal_state($session,$userId,$now);
    $id=filter_var($publicListing['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$state['recent_enabled']||$id===false||!personal_current($publicListing,$now)) return;
    $state['recent']=[$id=>$now]+$state['recent'];
    $state['recent']=array_slice($state['recent'],0,20,true);
    $session['personal_lists']=$state;
}

/** HTTP callers enforce POST/CSRF and current listing visibility before adding. */
function personal_change(array &$session,int $userId,string $action,?int $listingId=null,?int $now=null): void
{
    $now??=time();$state=personal_state($session,$userId,$now);
    switch ($action) {
        case 'enable': $state['recent_enabled']=true; break;
        case 'disable': $state['recent_enabled']=false;$state['recent']=[]; break;
        case 'clear_recent': $state['recent']=[]; break;
        case 'clear_compare': $state['compare']=[]; break;
        case 'add':
            if ($listingId===null||$listingId<1) throw new InvalidArgumentException(t('personal.unavailable'));
            if (!isset($state['compare'][$listingId])&&count($state['compare'])>=4) throw new InvalidArgumentException(t('personal.limit'));
            $state['compare'][$listingId]=$now; break;
        case 'remove':
            if ($listingId===null||$listingId<1) throw new InvalidArgumentException(t('personal.unavailable'));
            unset($state['compare'][$listingId]); break;
        default: throw new InvalidArgumentException(t('personal.invalid'));
    }
    $session['personal_lists']=$state;
}

function personal_items(PDO $pdo,array &$session,int $userId,string $bucket): array
{
    if (!in_array($bucket,['recent','compare'],true)) throw new InvalidArgumentException('Unknown list.');
    $state=personal_state($session,$userId);$items=[];
    foreach ($state[$bucket] as $id=>$seen) {
        $listing=market_find($pdo,(int)$id,false);
        if ($listing!==null&&personal_current($listing)) $items[]=$listing;
        else unset($state[$bucket][$id]);
    }
    $session['personal_lists']=$state;
    return $items;
}
