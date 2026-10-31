<?php
declare(strict_types=1);
function money_parse(string $value):int{if(!preg_match('/\A(0|[1-9][0-9]{0,7})(?:[.,]([0-9]{1,2}))?\z/',$value,$m))throw new InvalidArgumentException('Bitte einen gültigen Geldbetrag eingeben.');return ((int)$m[1])*100+(int)str_pad($m[2]??'',2,'0');}
function money_eur(int $cents):string{if($cents<0)return '−'.money_eur(-$cents);return number_format(intdiv($cents,100),0,',','.').','.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT).' €';}
