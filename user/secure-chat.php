<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
$user=require_login($pdo);$uid=(int)$user['id'];$listing=0;$peer=0;$context=null;
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');abort_request(405,t('account.method'));}
if(isset($_GET['listing_id'])||isset($_GET['user_id'])){
    $listing=is_string($_GET['listing_id']??null)?(filter_var($_GET['listing_id'],FILTER_VALIDATE_INT)?:0):0;
    $peer=is_string($_GET['user_id']??null)?(filter_var($_GET['user_id'],FILTER_VALIDATE_INT)?:0):0;
    try{$context=chat_context($pdo,$uid,$listing,$peer);}
    catch(MarketNotFoundException){abort_request(404,t('e2ee.error_input'));}
    catch(MarketAccessException){abort_request(403,t('e2ee.error_input'));}
}
$pageTitle=t('e2ee.title');$enabled=e2ee_enabled();if(!$enabled)http_response_code(503);
$labels=array_filter(i18n_dictionary(),static fn($key)=>str_starts_with($key,'e2ee.'),ARRAY_FILTER_USE_KEY);
include __DIR__.'/../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/e2ee.css">
<main class="container e2ee" id="secure-chat" data-user="<?=$uid?>" data-listing="<?=$listing?>" data-peer="<?=$peer?>" data-csrf="<?=h(csrf_token())?>" data-labels="<?=h(json_encode($labels,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))?>">
<h1><?=h(t('e2ee.title'))?></h1><p><?=h(t('e2ee.intro'))?></p>
<?php if($context): ?><p><?=h($context['peer']['username'])?> · <?=h($context['item']['title'])?></p><p><a href="/user/chat.php?listing_id=<?=$listing?>&amp;user_id=<?=$peer?>"><?=h(t('e2ee.legacy'))?></a></p><?php endif; ?>
<?php if(!$enabled): ?><p class="notice" role="status"><?=h(t('e2ee.disabled'))?></p>
<?php else: ?>
<noscript><p class="notice"><?=h(t('e2ee.unsupported'))?></p></noscript>
<p id="e2ee-status" class="notice" role="status" aria-live="polite"><?=h(t('e2ee.locked'))?></p>
<section class="panel"><h2><?=h(t('e2ee.devices'))?></h2>
<p><?=h(t('e2ee.own_fingerprint'))?> <code id="e2ee-own-fingerprint" class="e2ee-fingerprint"></code></p>
<ul id="e2ee-devices"></ul>
<!-- No native forms: secrets and plaintext can never fall back to an HTTP form submission. -->
<div id="e2ee-credentials">
<label class="field" for="e2ee-device-name"><?=h(t('e2ee.device_name'))?><input id="e2ee-device-name" maxlength="80" autocomplete="off" disabled></label>
<label class="field" for="e2ee-passphrase"><?=h(t('e2ee.passphrase'))?><input id="e2ee-passphrase" type="password" minlength="12" maxlength="256" autocomplete="off" disabled></label>
<p><?=h(t('e2ee.passphrase_help'))?></p>
<div class="button-row"><button class="button" type="button" id="e2ee-create" disabled><?=h(t('e2ee.create'))?></button><button class="button" type="button" id="e2ee-unlock" disabled><?=h(t('e2ee.unlock'))?></button><button class="button secondary" type="button" id="e2ee-lock" disabled><?=h(t('e2ee.lock'))?></button></div>
</div>
<details id="e2ee-recovery"><summary><?=h(t('e2ee.recover'))?></summary>
<p><?=h(t('e2ee.recovery_help'))?></p>
<label class="field" for="e2ee-recovery-input"><?=h(t('e2ee.recovery_code'))?><input id="e2ee-recovery-input" type="password" autocomplete="off" spellcheck="false" maxlength="255" disabled></label>
<label class="field" for="e2ee-revoke"><?=h(t('e2ee.revoke'))?><select id="e2ee-revoke" disabled><option value=""><?=h(t('e2ee.no_revoke'))?></option></select></label>
<button class="button secondary" type="button" id="e2ee-prepare-recovery" disabled><?=h(t('e2ee.prepare_recovery'))?></button>
</details>
<section id="e2ee-pending" class="panel" hidden><h3><?=h(t('e2ee.save_code'))?></h3><p><?=h(t('e2ee.confirm_help'))?></p>
<label class="field" for="e2ee-new-code"><?=h(t('e2ee.recovery_code'))?><textarea id="e2ee-new-code" readonly rows="5" spellcheck="false" autocomplete="off"></textarea></label>
<button type="button" class="button secondary" id="e2ee-download-code"><?=h(t('e2ee.download'))?></button>
<label class="check"><input type="checkbox" id="e2ee-code-saved"> <?=h(t('e2ee.code_saved'))?></label>
<div class="button-row"><button type="button" class="button" id="e2ee-commit" disabled><?=h(t('e2ee.commit'))?></button><button type="button" class="button secondary" id="e2ee-cancel"><?=h(t('e2ee.cancel'))?></button></div>
</section></section>
<details class="panel"><summary><?=h(t('e2ee.pending_uploads'))?></summary><p><?=h(t('e2ee.pending_uploads_help'))?></p><ul id="e2ee-uploads"></ul></details>
<?php if($context): ?>
<section class="panel"><h2><?=h(t('e2ee.peer_fingerprint'))?></h2><code id="e2ee-peer-fingerprint" class="e2ee-fingerprint"></code><p><?=h(t('e2ee.verify_help'))?></p>
<label class="field" for="e2ee-fingerprint-input"><?=h(t('e2ee.fingerprint_input'))?><input id="e2ee-fingerprint-input" maxlength="128" autocomplete="off" spellcheck="false" disabled></label><button type="button" class="button secondary" id="e2ee-verify" disabled><?=h(t('e2ee.verify'))?></button>
</section>
<section class="panel"><h2><?=h(t('e2ee.body'))?></h2><label class="field" for="e2ee-body"><?=h(t('e2ee.body'))?><textarea id="e2ee-body" maxlength="10000" rows="5" disabled></textarea></label>
<label class="field" for="e2ee-files"><?=h(t('e2ee.attachments'))?><input id="e2ee-files" type="file" multiple accept=".txt,.pdf,.png,.jpg,.jpeg,.webp" disabled></label><p><?=h(t('e2ee.attachment_help'))?></p>
<button type="button" class="button" id="e2ee-send" disabled><?=h(t('e2ee.send'))?></button>
<div id="e2ee-outbox" hidden><p><?=h(t('e2ee.outbox_help'))?></p><button type="button" class="button secondary" id="e2ee-discard-send" disabled><?=h(t('e2ee.discard_send'))?></button></div></section>
<section class="panel"><button class="button secondary" type="button" id="e2ee-load" disabled><?=h(t('e2ee.load'))?></button><button class="button secondary" type="button" id="e2ee-mark-read" disabled><?=h(t('e2ee.mark_read'))?></button><div id="e2ee-messages" aria-live="polite"></div></section>
<?php endif; ?>
<script type="module" src="/assets/js/e2ee-ui.mjs"></script>
<?php endif; ?>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
