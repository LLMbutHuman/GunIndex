<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once __DIR__ . '/../config/catalog.php';
require_once __DIR__ . '/../config/seo.php';
function seo_assert(bool $result, string $message): void { if (!$result) throw new RuntimeException($message); }
function seo_invalid(callable $operation): void {
    try { $operation(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('Invalid sitemap input was accepted.');
}
$fixed = ['id'=>42,'type'=>'fixed','status'=>'active','title'=>'Camping table','description'=>'A folding table in good condition.',
    'price_cents'=>1001,'condition_label'=>'used','image_count'=>2,'updated_at'=>'2026-10-30 12:13:14'];
$auction = array_merge($fixed, ['id'=>43,'type'=>'auction','ends_at'=>gmdate('Y-m-d H:i:s',time()+3600)]);
$enabled = ['seo_indexing_enabled'=>'1','maintenance_enabled'=>'0'];
$origin = 'https://market.example.org';
$tests = [
    'trusted_origin_rejects_poisoning_and_non_public_targets' => static function () use ($origin): void {
        seo_assert(seo_origin('https://MARKET.EXAMPLE.ORG:443/') === $origin, 'Origin normalization failed.');
        foreach (['http://market.example.org','https://localhost','https://127.0.0.1','https://market.test','https://market.example.org/admin',
            'https://market.example.org?host=other','https://market.example.org#fragment','https://user@market.example.org',
            'https://user:secret@market.example.org','https://market.example.org:8443',"https://market.example.org\r\nHost: other",'https://market.example.org\\evil'] as $value)
            seo_assert(seo_origin($value) === null, 'Untrusted origin accepted.');
    },
    'request_indexing_rejects_ip_hosts_test_environments_and_forged_proxy_headers' => static function () use ($origin): void {
        $server = ['HTTPS'=>'on', 'HTTP_HOST'=>'market.example.org'];
        seo_assert(seo_request_origin($server, $origin, 'production', 'gunindex') === $origin, 'Canonical production HTTPS host rejected.');
        seo_assert(seo_request_origin(['HTTP_HOST'=>'MARKET.EXAMPLE.ORG:443'] + $server, $origin, 'production', 'gunindex') === $origin, 'Canonical explicit HTTPS port rejected.');
        foreach (['127.0.0.1', '[2001:db8::1]', 'foreign.example.org', 'market.example.org:444', "market.example.org\r\n"] as $host) {
            $untrusted = ['HTTP_HOST'=>$host, 'HTTP_X_FORWARDED_HOST'=>'market.example.org'] + $server;
            seo_assert(seo_request_origin($untrusted, $origin, 'production', 'gunindex') === null, 'A foreign host was allowed to publish metadata.');
        }
        seo_assert(seo_request_origin(['HTTPS'=>'off', 'HTTP_X_FORWARDED_PROTO'=>'https'] + $server, $origin, 'production', 'gunindex') === null, 'A proxy header substituted for HTTPS.');
        foreach ([['development','gunindex'], ['test','gunindex'], ['production','gunindex_test'], ['production','']] as [$environment, $database]) {
            seo_assert(seo_request_origin($server, $origin, $environment, $database) === null, 'A test or unconfigured instance remained indexable.');
        }
        foreach (['https://staging.example.org', 'https://demo.example.org', 'https://server.example.internal'] as $reserved) {
            seo_assert(seo_origin($reserved) === null, 'A staging or internal origin was accepted.');
        }
    },
    'indexing_requires_explicit_gate_and_nonmaintenance_state' => static function () use ($enabled,$origin): void {
        seo_assert(!seo_gate([], $origin), 'Default gate must be closed.');
        seo_assert(seo_gate($enabled,$origin), 'Explicit gate rejected.');
        seo_assert(!seo_gate(array_merge($enabled,['maintenance_enabled'=>'1']),$origin), 'Maintenance did not close indexing.');
        seo_assert(!seo_gate(array_merge($enabled,['maintenance_enabled'=>'unexpected']),$origin), 'Invalid maintenance configuration opened indexing.');
        seo_assert(!seo_gate($enabled,null), 'Missing origin accepted.');
        seo_assert(!seo_gate(['seo_indexing_enabled'=>true],$origin), 'Noncanonical gate value accepted.');
    },
    'only_home_and_validated_current_listing_pages_are_indexable' => static function () use ($fixed,$enabled,$origin): void {
        $page=seo_page($enabled,'/marketplace/view-item.php',$fixed,$origin);
        seo_assert($page['index'] && $page['canonical']===$origin.'/de/marketplace/view-item.php?id=42','Listing canonical incorrect.');
        seo_assert(seo_page($enabled,'/marketplace/index.php',null,$origin)['canonical']===$origin.'/de/','Homepage alias not canonicalized.');
        foreach (['/auth/login.php','/user/invoices.php','/admin/index.php','/marketplace/search.php','/marketplace/view-auction.php'] as $path)
            seo_assert(!seo_page($enabled,$path,$fixed,$origin)['index'], 'Private or mismatched route indexable.');
        seo_assert(!seo_page($enabled,'/marketplace/view-item.php',null,$origin)['index'],'Unvalidated listing route indexable.');
    },
    'all_locales_get_a_self_canonical_and_reciprocal_alternates' => static function () use ($fixed,$enabled,$origin): void {
        $alternates = seo_alternates($origin, seo_listing_path($fixed));
        seo_assert(count($alternates)===9 && $alternates['x-default']===$alternates['de'],'Invalid default locale variant.');
        foreach (i18n_locales() as $locale=>$label) {
            $route = '/'.$locale.'/marketplace/view-item.php';
            $page = seo_page($enabled, $route, $fixed, $origin, 'en');
            seo_assert($page['index'] && $page['canonical']===$origin.$route.'?id=42','Locale path did not control canonical.');
            seo_assert($page['alternates']===$alternates && $page['structured']['url']===$page['canonical'],'Metadata versions do not match.');
            seo_assert($page['structured']['description']===$fixed['description'],'Seller content was changed.');
        }
        $disabled = seo_page([], '/en/', null, $origin, 'en');
        seo_assert(!$disabled['index'] && $disabled['alternates']===[] && $disabled['canonical']===null,'Gate leaks language metadata.');
    },
    'locale_routes_and_return_paths_remain_public_and_bounded' => static function (): void {
        seo_assert(seo_public_route('/it/')===['locale'=>'it','path'=>'/'],'Localized home not resolved.');
        foreach (['/xx/','/en/admin/index.php','/fr//','/pl/marketplace/search.php','/de/marketplace/view-item.php/','/EN/','/en/../admin/index.php'] as $path) {
            seo_assert(seo_public_route($path)===null,'An unsupported path accepted a prefix.');
        }
        seo_assert(seo_switch_path('/fr/marketplace/view-item.php?id=42&origin_zip=10115#a','it')==='/it/marketplace/view-item.php?id=42&origin_zip=10115#a','Choice lost return context.');
        seo_assert(seo_switch_path('/marketplace/index.php','it') === '/it/', 'The homepage alias did not switch to a stable language URL.');
        seo_assert(seo_switch_path('/marketplace/view-item.php?id=42','it') === '/it/marketplace/view-item.php?id=42', 'The item alias did not switch to a stable language URL.');
        seo_assert(seo_switch_path('/auth/login.php','it')==='/auth/login.php','A private path was rewritten.');
        seo_invalid(static fn()=>seo_localized_path('/admin/index.php','en'));
        seo_invalid(static fn()=>seo_localized_path('/','xx'));
    },
    'ended_hidden_and_restricted_listings_are_excluded' => static function () use ($fixed,$auction): void {
        foreach (['draft','paused','sold','ended','cancelled'] as $status)
            seo_assert(!seo_listing_current(array_merge($fixed,['status'=>$status])),'Nonpublic status accepted.');
        seo_assert(!seo_listing_current(array_merge($auction,['ends_at'=>'2020-01-01 00:00:00'])),'Expired auction accepted.');
        seo_assert(!seo_listing_current(array_merge($auction,['ends_at'=>null])),'Auction without deadline accepted.');
        seo_assert(!seo_listing_current(array_merge($fixed,['title'=>'Waffen'])),'Restricted catalog text accepted.');
        foreach ([-1,'42&buyer=1',0,[],null] as $id)
            seo_assert(seo_listing_path(array_merge($fixed,['id'=>$id]))===null,'Invalid listing ID accepted.');
    },
    'product_metadata_is_exact_and_omits_private_fields' => static function () use ($fixed,$origin): void {
        $product=seo_product_data(array_merge($fixed,['highest_bidder_id'=>99,'max_cents'=>999999,'seller_email'=>'private@example.org']),$origin);
        seo_assert($product['offers']['price']==='10.01' && $product['offers']['priceCurrency']==='EUR','Price is not exact.');
        $soldOut = seo_product_data($fixed + ['stock_available'=>false], $origin);
        seo_assert($soldOut['offers']['availability'] === 'https://schema.org/OutOfStock', 'Empty dealer stock was advertised as available.');
        seo_assert(count($product['image'])===2,'Actual image count ignored.');
        $json=seo_json($product);
        foreach (['highest_bidder_id','max_cents','seller_email','private@example.org'] as $private)
            seo_assert(!str_contains($json,$private),'Private listing field exposed.');
    },
    'auction_bid_is_not_advertised_as_sale_price' => static function () use ($auction,$origin): void {
        $product=seo_product_data($auction,$origin);
        seo_assert($product!==null && !isset($product['offers']),'Auction invents fixed sale offer.');
    },
    'breadcrumb_links_and_descriptions_match_localized_public_documents' => static function () use ($fixed, $enabled, $origin): void {
        $page = seo_page($enabled, '/fr/marketplace/view-item.php', $fixed + ['category_id'=>3, 'seo_category_name'=>'Jardin'], $origin);
        $crumbs = $page['breadcrumbs'];
        seo_assert($crumbs['@type'] === 'BreadcrumbList' && count($crumbs['itemListElement']) === 3, 'The listing hierarchy is incomplete.');
        [$home, $category, $item] = $crumbs['itemListElement'];
        seo_assert($home['position'] === 1 && $home['item'] === $origin . '/fr/', 'Home breadcrumb does not use the current language.');
        seo_assert($category['position'] === 2 && $category['name'] === 'Jardin' && $category['item'] === $origin . '/marketplace/search.php?category_id=3', 'Category breadcrumb is incorrect.');
        seo_assert($item['position'] === 3 && $item['name'] === $fixed['title'] && $item['item'] === $page['canonical'], 'Listing breadcrumb differs from its canonical URL.');
        seo_assert(seo_page([], '/fr/marketplace/view-item.php', $fixed, $origin)['breadcrumbs'] === null, 'Breadcrumb metadata survives a closed gate.');
        $phrase = "\nUn   été\t" . str_repeat('é', 170);
        $description = seo_description($phrase);
        seo_assert(preg_match_all('/./u', $description) === 160 && str_starts_with($description, 'Un été '), 'Description trimming damaged multibyte text.');
    },
    'json_ld_cannot_escape_its_script_element' => static function (): void {
        $text='</script><script>alert("x")</script>&';
        $json=seo_json(['name'=>$text]);
        seo_assert(!str_contains($json,'<') && !str_contains($json,'&'),'Active HTML escaped the JSON block.');
        seo_assert(json_decode($json,true,512,JSON_THROW_ON_ERROR)['name']===$text,'Structured text was altered.');
    },
    'robots_gate_and_origin_are_consistent' => static function () use ($origin): void {
        seo_assert(seo_robots(false,$origin)==="User-agent: *\nDisallow: /\n",'Disabled robots must disallow all.');
        seo_assert(str_contains(seo_robots(true,$origin),'Sitemap: '.$origin.'/sitemap.xml'),'Enabled sitemap not advertised.');
        seo_assert(!str_contains(seo_robots(true,"https://x\r\nAllow: /"),'Sitemap:'),'Robots origin injection.');
    },
    'pagination_is_bounded_and_does_not_accept_arrays_or_injection' => static function (): void {
        seo_assert(seo_sitemap_page(null)===null && seo_sitemap_page('1')===1 && seo_sitemap_page('50000')===50000,'Page boundary rejected.');
        foreach (['0','01','50001','1 OR 1','-1',[],1,'1.0'] as $value) seo_invalid(static fn()=>seo_sitemap_page($value));
    },
    'sitemap_emits_only_public_canonical_urls_and_real_timestamps' => static function () use ($fixed,$auction,$origin): void {
        $xml=seo_sitemap_document($origin,1,['items'=>[$fixed,$auction,array_merge($fixed,['id'=>44,'status'=>'draft'])]]);
        seo_assert(str_contains($xml,'<loc>'.$origin.'/de/</loc>'),'Homepage missing.');
        seo_assert(str_contains($xml,'view-item.php?id=42') && str_contains($xml,'view-auction.php?id=43'),'Current public listing missing.');
        seo_assert(!str_contains($xml,'id=44'),'Draft exposed in sitemap.');
        seo_assert(str_contains($xml,'2026-10-30T12:13:14Z'),'Real modification time absent.');
        seo_assert(!str_contains($xml,'price_cents') && !str_contains($xml,'description'),'Internal fields in XML.');
        $document = new DOMDocument();
        seo_assert($document->loadXML($xml, LIBXML_NONET),'Sitemap XML is invalid.');
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s','http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('x','http://www.w3.org/1999/xhtml');
        $entries = $xpath->query('/s:urlset/s:url');
        seo_assert($entries->length===24,'Eight versions per public entity must be emitted.');
        foreach ($entries as $entry) {
            $location = $xpath->evaluate('string(s:loc)', $entry);
            $links = $xpath->query('x:link[@rel="alternate"]', $entry);
            seo_assert($links->length===9,'Sitemap entry has missing language alternatives.');
            $hrefs = [];
            foreach ($links as $link) $hrefs[$link->getAttribute('hreflang')] = $link->getAttribute('href');
            seo_assert(count($hrefs)===9 && in_array($location,$hrefs,true) && $hrefs['x-default']===$hrefs['de'],'Nonreciprocal XML languages.');
        }
    },
    'sitemap_index_and_rows_have_hard_limits' => static function () use ($origin): void {
        $xml=seo_sitemap_document($origin,null,['pages'=>2]);
        seo_assert(substr_count($xml,'<sitemap>')===2 && str_contains($xml,'sitemap.xml?page=2'),'Sitemap index missing page.');
        seo_invalid(static fn()=>seo_sitemap_document($origin,null,['pages'=>50001]));
        seo_invalid(static fn()=>seo_sitemap_document($origin,1,['items'=>array_fill(0,1001,[])]));
    },
];
foreach ($tests as $name=>$operation) { $operation(); fwrite(STDOUT,"PASS $name\n"); }
fwrite(STDOUT,'SEO checks passed: '.count($tests)."\n");

if (in_array('--database',$argv,true)) {
    if (getenv('DB_DATABASE') !== 'gunindex_rebuild_test') { fwrite(STDERR,"Only the isolated synthetic schema is allowed.\n"); exit(2); }
    require_once __DIR__ . '/../config/bootstrap.php';
    if ($pdo->inTransaction()) throw new RuntimeException('Unexpected existing transaction.');
    $pdo->beginTransaction();
    try {
        $suffix=bin2hex(random_bytes(8));
        $pdo->prepare("INSERT INTO users(username,email,password_hash) VALUES (?,?,?)")
            ->execute(['test_seo_'.$suffix,'test_seo_'.$suffix.'@example.invalid','synthetic-disabled-password']);
        $seller=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO market_categories(name,slug,is_active) VALUES (?, ?, 1)")
            ->execute(['Synthetic SEO parent','seo-parent-'.$suffix]);
        $parent=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO market_categories(parent_id,name,slug,is_active) VALUES (?, ?, ?, 1)")
            ->execute([$parent,'Synthetic SEO child','seo-child-'.$suffix]);
        $category=(int)$pdo->lastInsertId();
        $pdo->exec("UPDATE market_countries SET is_active=1 WHERE code='DE'");
        $insert=$pdo->prepare("INSERT INTO market_listings(seller_id,category_id,title,description,type,price_cents,status,zip,city,country_code,ends_at) VALUES (?,?,'Synthetic ordinary table','Synthetic item used only in isolated SEO tests.',?,1001,?,'00001','Synthetic town','DE',?)");
        $publicIds=[];
        for($i=0;$i<1001;$i++) { $insert->execute([$seller,$category,'fixed','active',null]); $publicIds[]=(int)$pdo->lastInsertId(); }
        $insert->execute([$seller,$category,'auction','active',gmdate('Y-m-d H:i:s',time()+3600)]);$publicIds[]=(int)$pdo->lastInsertId();
        $insert->execute([$seller,$category,'auction','active','2020-01-01 00:00:00']);$expired=(int)$pdo->lastInsertId();
        $insert->execute([$seller,$category,'fixed','draft',null]);$draft=(int)$pdo->lastInsertId();
        $collect=static function()use($pdo):array {
            $pages=seo_sitemap_rows($pdo,null)['pages'];
            if($pages>100)throw new RuntimeException('Synthetic test schema is unexpectedly large.');
            $ids=[];
            for($page=1;$page<=$pages;$page++) {
                $data=seo_sitemap_rows($pdo,$page);
                seo_assert(count($data['items'])<=1000,'Sitemap batch limit exceeded.');
                foreach($data['items'] as $row) { seo_assert(!isset($ids[$row['id']]),'A listing appeared on multiple pages.'); $ids[$row['id']]=true; }
            }
            return $ids;
        };
        $ids=$collect();
        foreach($publicIds as $id)seo_assert(isset($ids[$id]),'Public listing omitted across sitemap pages.');
        seo_assert(!isset($ids[$expired])&&!isset($ids[$draft]),'Expired auction or draft exposed.');
        $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$seller]);
        $ids=$collect();foreach($publicIds as $id)seo_assert(!isset($ids[$id]),'Inactive seller leaked.');
        $pdo->prepare('UPDATE users SET is_active=1 WHERE id=?')->execute([$seller]);
        $pdo->exec("UPDATE market_countries SET is_active=0 WHERE code='DE'");
        $ids=$collect();foreach($publicIds as $id)seo_assert(!isset($ids[$id]),'Disabled country leaked.');
        $pdo->exec("UPDATE market_countries SET is_active=1 WHERE code='DE'");
        $pdo->prepare('UPDATE market_categories SET is_active=0 WHERE id=?')->execute([$parent]);
        $ids=$collect();foreach($publicIds as $id)seo_assert(!isset($ids[$id]),'Inactive category ancestor leaked.');
        fwrite(STDOUT,"PASS database_public_visibility_and_multipage_boundaries\n");
    } finally { if($pdo->inTransaction())$pdo->rollBack(); }
}
