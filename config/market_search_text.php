<?php
declare(strict_types=1);

function market_like_literal(string $text): string
{
    return str_replace(['!','%','_'],['!!','!%','!_'],$text);
}

/** Match each word and give title matches more weight than description matches. */
function market_text_search(string $query): array
{
    if (strlen($query)>200 || !preg_match('//u',$query) || preg_match('/[\x00-\x1f\x7f]/',$query)) {
        throw new InvalidArgumentException(t('classification.search_invalid'));
    }
    $query=trim($query);
    if ($query==='') return [[],[],'0',[]];
    $terms=array_values(array_unique(preg_split('/\s+/u',$query,-1,PREG_SPLIT_NO_EMPTY)));
    if (!$terms || count($terms)>12) throw new InvalidArgumentException(t('classification.search_invalid'));
    $phrase=market_like_literal(implode(' ',$terms));
    $conditions=[];
    $parameters=[];
    $ranking=["CASE WHEN l.title=? THEN 100 WHEN l.title LIKE ? ESCAPE '!' THEN 55 WHEN l.title LIKE ? ESCAPE '!' THEN 30 WHEN l.description LIKE ? ESCAPE '!' THEN 10 ELSE 0 END"];
    $rankingParameters=[implode(' ',$terms),$phrase.'%','%'.$phrase.'%','%'.$phrase.'%'];
    foreach ($terms as $term) {
        $match='%'.market_like_literal($term).'%';
        $conditions[]="(l.title LIKE ? ESCAPE '!' OR l.description LIKE ? ESCAPE '!')";
        array_push($parameters,$match,$match);
        $ranking[]="CASE WHEN l.title LIKE ? ESCAPE '!' THEN 4 ELSE 0 END";
        $rankingParameters[]=$match;
    }
    return [$conditions,$parameters,implode(' + ',$ranking),$rankingParameters];
}

/** Use the same seller, catalogue, deadline and country gates as the results page. */
function market_public_where(array $categories): array
{
    if (!$categories) return ['1=0'];
    return ["l.status='active'","(l.ends_at IS NULL OR l.ends_at>UTC_TIMESTAMP())",
        'EXISTS(SELECT 1 FROM users seller WHERE seller.id=l.seller_id AND seller.is_active=1)',
        'EXISTS(SELECT 1 FROM market_countries mc WHERE mc.code=l.country_code AND mc.is_active=1)',
        'l.category_id IN ('.implode(',',array_fill(0,count($categories),'?')).')'];
}

function market_suggestions(PDO $pdo, string $query, array $filters): array
{
    $query=trim($query);
    if (strlen($query)>200 || !preg_match('/\A.{2,80}\z/u',$query) || !catalog_text_allowed($query)) return [];
    $scope=$filters['scope']??'country';
    if (!in_array($scope,['country','international'],true)) throw new InvalidArgumentException(t('classification.search_invalid'));
    $country=geo_country(($filters['country_code']??'')?:null);
    $category=$filters['category_id']??'';
    if ($category !== '' && (!is_string($category) || !ctype_digit($category))) return [];
    $categories = market_category_ids($pdo, $category === '' ? null : (int)$category);
    if (!$categories) return [];
    $where=market_public_where($categories);
    $parameters=$categories;
    if ($scope==='country') {
        $where[]='l.country_code=?';
        $parameters[]=$country;
    }
    $type=$filters['type']??'';
    if ($type!=='') {
        if (!in_array($type,['fixed','auction','negotiated'],true)) throw new InvalidArgumentException(t('classification.search_invalid'));
        $where[]='l.type=?';
        $parameters[]=$type;
    }
    $where[]="l.title LIKE ? ESCAPE '!'";
    $parameters[]=market_like_literal($query).'%';
    $q=$pdo->prepare('SELECT DISTINCT l.title FROM market_listings l WHERE '.implode(' AND ',$where).' ORDER BY l.title LIMIT 8');
    $q->execute($parameters);
    return array_values(array_filter($q->fetchAll(PDO::FETCH_COLUMN),'catalog_text_allowed'));
}
