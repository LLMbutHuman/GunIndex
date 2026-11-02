<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(2);}
require_once __DIR__.'/../config/consent.php';
require_once __DIR__.'/../config/functions.php';
require_once __DIR__.'/../config/settings.php';
$passed=0;
function consent_test(string $name,callable $run):void {global $passed;try{$run();$passed++;echo 'PASS '.$name."\n";}catch(Throwable $e){fwrite(STDERR,'FAIL '.$name.' '.get_class($e).' at '.$e->getLine()."\n");exit(1);}}
function consent_assert(bool $ok):void {if(!$ok)throw new RuntimeException('Consent assertion failed.');}
function consent_reject(callable $run):void {try{$run();}catch(InvalidArgumentException|DomainException){return;}throw new RuntimeException('Invalid consent was accepted.');}
$body=str_repeat('Synthetic document, never an actual legal approval. ',5);
$doc=['id'=>42,'title'=>'Synthetic <script> fixture','version'=>'1.0','language'=>'de','document_code'=>'synthetic','body_text'=>$body,'content_hash'=>hash('sha256',$body)];
$input=['legal_confirm'=>'1','legal_language'=>'de','legal_documents'=>[42=>$doc['content_hash']]];
consent_test('configured_codes_are_bounded_and_canonical',static function():void{consent_assert(consent_codes('terms,privacy')===['privacy','terms']);consent_assert(consent_codes('')===[]);consent_assert(site_setting_validate('legal_registration_documents','terms,privacy')==='privacy,terms');});
consent_test('invalid_and_duplicate_codes_rejected',static function():void{foreach(['../secrets','terms,terms','terms, privacy',',terms','terms,','TERMS',str_repeat('a',81),implode(',',array_map(fn($i)=>'code'.$i,range(1,11)))] as $raw)consent_reject(fn()=>consent_codes($raw));});
consent_test('no_requirements_create_no_claimed_consent',static fn()=>consent_validate_submission([],[],'de'));
consent_test('exact_displayed_version_accepted',static fn()=>consent_validate_submission([$doc],$input,'de'));
consent_test('unchecked_consent_rejected',static function()use($doc,$input):void{$input['legal_confirm']='0';consent_reject(fn()=>consent_validate_submission([$doc],$input,'de'));});
consent_test('missing_version_rejected',static fn()=>consent_reject(fn()=>consent_validate_submission([$doc],['legal_confirm'=>'1','legal_language'=>'de'],'de')));
consent_test('changed_version_rejected',static function()use($doc,$input):void{$doc['id']=43;consent_reject(fn()=>consent_validate_submission([$doc],$input,'de'));});
consent_test('changed_hash_rejected',static function()use($doc,$input):void{$input['legal_documents'][42]=str_repeat('0',64);consent_reject(fn()=>consent_validate_submission([$doc],$input,'de'));});
consent_test('language_change_requires_confirmation',static fn()=>consent_reject(fn()=>consent_validate_submission([$doc],$input,'en')));
consent_test('nested_payload_and_extra_documents_rejected',static function()use($doc,$input):void{$input['legal_documents'][42]=['hash'=>$doc['content_hash']];consent_reject(fn()=>consent_validate_submission([$doc],$input,'de'));$input['legal_documents']=[42=>$doc['content_hash'],99=>$doc['content_hash']];consent_reject(fn()=>consent_validate_submission([$doc],$input,'de'));});
consent_test('changed_requirement_set_rejected',static fn()=>consent_reject(fn()=>consent_validate_submission([],$input,'de')));
consent_test('tampered_document_body_rejected',static function()use($doc):void{$doc['body_text'].='tampered';consent_reject(fn()=>consent_integrity($doc));});
consent_test('rendered_document_is_exact_escaped_and_unchecked',static function()use($doc):void{ob_start();consent_form([$doc],'de');$html=ob_get_clean();consent_assert(str_contains($html,'version_id=42')&&str_contains($html,'&lt;script&gt;')&&!str_contains($html,' checked')&&str_contains($html,'name="legal_confirm"'));});
consent_test('all_eight_interfaces_include_localized_consent',static function()use($doc):void{foreach(i18n_locales() as $locale=>$_){$_SESSION['locale']=$locale;ob_start();consent_form([$doc],$locale);$html=ob_get_clean();consent_assert(!str_contains($html,'consent.')&&str_contains($html,'value="'.$locale.'"'));}unset($_SESSION['locale']);});
echo 'Consent checks passed: '.$passed."\n";
