<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/functions.php';
foreach(['settings','catalog','money','auth','images','market','fees','payments','manual_payments'] as $module){
 $path=__DIR__.'/'.$module.'.php';
 if(is_file($path)) require_once $path;
}
