<?php
declare(strict_types=1);
require_once __DIR__.'/../config/i18n.php';
require_once __DIR__.'/../config/image_order.php';
function order_check(bool $ok):void {if(!$ok)throw new RuntimeException('Image order assertion failed.');}
function order_reject(array $rows,mixed $positions,mixed $revision):void {
    try {image_order_validate($rows,$positions,$revision);}catch(InvalidArgumentException){return;}
    throw new RuntimeException('Invalid order accepted.');
}
$rows=[['id'=>11,'position'=>0],['id'=>22,'position'=>1],['id'=>33,'position'=>2]];
$revision=image_order_revision($rows);
order_check(image_order_validate($rows,[11=>'3',22=>'1',33=>'2'],$revision)===['22','33','11']);
order_reject($rows,[11=>'1',22=>'1',33=>'3'],$revision);
order_reject($rows,[11=>'1',22=>'2',99=>'3'],$revision);
order_reject($rows,[11=>'1',22=>'2'],$revision);
order_reject($rows,[11=>'1',22=>'2',33=>['3']],$revision);
order_reject($rows,[11=>'1',22=>'2',33=>'4'],$revision);
order_reject($rows,[11=>'1',22=>'2',33=>'3'],'stale');
order_reject(array_reverse($rows),[11=>'1',22=>'2',33=>'3'],$revision);
order_reject($rows,'1,2,3',$revision);
order_check(image_order_validate([],[],image_order_revision([]))===[]);
$broken=[['id'=>11,'position'=>-1]];
order_reject($broken,[11=>'1'],image_order_revision($broken));
echo "Image order checks passed: 11\n";
