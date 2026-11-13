import {initialize,recover,unlock,verifyBundle,encryptMessage,decryptMessage,encryptAttachment,decryptAttachment,randomId} from './e2ee-crypto.mjs';
const root=document.querySelector('#secure-chat');
if(root){
 const labels=JSON.parse(root.dataset.labels),text=key=>labels['e2ee.'+key]||labels['e2ee.error_default'];
 const user=Number(root.dataset.user),listing=Number(root.dataset.listing),peer=Number(root.dataset.peer),csrf=root.dataset.csrf;
 const el=id=>document.getElementById('e2ee-'+id),apiPath='/user/e2ee-api.php';
 let db,state=null,local=null,pins={},account=null,selfBundle=null,peerBundle=null,canSend=false,pending=null,outbox=null,busy=false,cursor=0,active=true,epoch=0;
 const outboxKey='outbox:'+listing+':'+peer;
 const shown=new Set(),urls=new Set(),requests=new Set();let lastActivity=Date.now();
 function status(key,error=false){if(key==='ready'&&!state)key='locked';el('status').textContent=text(key);el('status').classList.toggle('e2ee-error',error);}
 function key(name){return user+':'+name;}
 function get(name){return new Promise((resolve,reject)=>{const request=db.transaction('items').objectStore('items').get(key(name));request.onsuccess=()=>resolve(request.result);request.onerror=()=>reject(request.error);});}
 function persist(values){return new Promise((resolve,reject)=>{
  const tx=db.transaction('items','readwrite'),items=tx.objectStore('items');let merged=null,failure=null;
  for(const [name,value] of Object.entries(values))if(name!=='pins')items.put(value,key(name));
  if(values.pins){const read=items.get(key('pins'));read.onsuccess=()=>{try{
   merged={...(read.result||{})};
   for(const [id,next] of Object.entries(values.pins)){const previous=merged[id];
    if(previous&&(previous.identity_key!==next.identity_key||(previous.version===next.version&&previous.hash!==next.hash)))throw Error('identity_pin_conflict');
    if(!previous||next.version>previous.version)merged[id]=next;
   }items.put(merged,key('pins'));
  }catch(error){failure=error;tx.abort();}};}
  tx.oncomplete=()=>{if(merged)pins=merged;resolve();};tx.onerror=()=>reject(failure||tx.error);tx.onabort=()=>reject(failure||tx.error);
 });}
 async function request(url,options,consume){const controller=new AbortController();requests.add(controller);const timer=setTimeout(()=>controller.abort(new DOMException('Request timed out','TimeoutError')),30000);
  try{return await consume(await fetch(url,{...options,signal:controller.signal}));}finally{clearTimeout(timer);requests.delete(controller);}
 }
 async function api(action,data=null){
  const options={credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}};let url=apiPath;
  if(data===null){url+='?'+new URLSearchParams({action,...(['context','history'].includes(action)?{listing_id:listing,peer_id:peer,...(action==='history'?{after:cursor}:{})}:{})});}
  else{options.method='POST';options.headers['Content-Type']='application/json';options.body=JSON.stringify({...data,action,csrf_token:csrf});}
  return request(url,options,async response=>{
  if(!response.ok){const error=new Error(response.status===409?'state_changed':response.status===429?'quota':'error_default');error.status=response.status;throw error;}
  if(!response.headers.get('content-type')?.includes('application/json'))throw Error('error_default');
  return response.json();});
 }
 function errorKey(error){
  if(['state_changed','quota'].includes(error.message))return error.message;
  if(/recovery|password|passphrase/.test(error.message))return 'error_recovery';
  if(/revoked|device/.test(error.message))return 'error_device';
  if(/identity|manifest|rollback|pin|signature/.test(error.message))return 'error_trust';
  return 'error_default';
 }
 function controls(){
  const unlocked=!!state&&!busy&&!pending,deviceOk=unlocked&&selfBundle?.manifest.devices.some(d=>d.id===state.device_id&&!d.revoked);
  for(const id of ['device-name','passphrase','recovery-input','revoke'])el(id).disabled=busy||!!pending;
  el('create').hidden=!!account;el('create').disabled=busy||!!pending||!!account;
  el('unlock').hidden=!local;el('unlock').disabled=busy||!!pending||!local;
  el('prepare-recovery').disabled=busy||!!pending||!account;
  el('lock').disabled=!state&&!pending&&!busy;el('commit').disabled=busy||!pending||!el('code-saved').checked;
  el('recovery').hidden=!account;el('pending').hidden=!pending;
  for(const id of ['body','files','send'])if(el(id))el(id).disabled=!(deviceOk&&peerBundle?.trusted&&canSend);
  if(outbox){for(const id of ['body','files'])if(el(id))el(id).disabled=true;}
  if(el('send'))el('send').textContent=text(outbox?'retry_send':'send');
  if(el('outbox'))el('outbox').hidden=!outbox;
  if(el('discard-send'))el('discard-send').disabled=busy||!outbox;
  for(const id of ['fingerprint-input','verify'])if(el(id))el(id).disabled=busy||!peerBundle;
  if(el('load'))el('load').disabled=busy||!state||!selfBundle?.trusted||!peerBundle?.trusted;
  if(el('mark-read'))el('mark-read').disabled=busy||!state||cursor===0;
 }
 async function task(fn){if(busy)return;busy=true;controls();status('busy');try{await fn();}catch(error){if(error.name!=='AbortError')status(errorKey(error),true);}finally{busy=false;controls();}}
 async function refresh(){
  const selectedDevice=el('revoke').value;
  pins=await get('pins')||{};
  account=(await api('account')).account;
  selfBundle=account?await verifyBundle(account,user,pins[user]||null):null;
  if(selfBundle?.trusted){pins[user]=selfBundle.pin;await persist({pins});}
  el('own-fingerprint').textContent=selfBundle?.fingerprint||'';
  el('devices').replaceChildren();el('revoke').replaceChildren(new Option(text('no_revoke'),''));
  for(const device of selfBundle?.manifest.devices||[]){const li=document.createElement('li');li.textContent=device.name+' · '+device.id+(device.revoked?' · '+text('revoked'):'');el('devices').append(li);if(!device.revoked)el('revoke').append(new Option(device.name+' · '+device.id,device.id));}
  if([...el('revoke').options].some(option=>option.value===selectedDevice))el('revoke').value=selectedDevice;
  const uploads=(await api('uploads')).attachments;el('uploads').replaceChildren();
  for(const upload of uploads){const li=document.createElement('li'),button=document.createElement('button');li.textContent=upload.id+' · '+upload.bytes+' B · '+upload.created_at+' UTC ';button.type='button';button.className='button secondary';button.textContent=text('remove_upload');button.disabled=!!outbox?.attachments.includes(upload.id);button.addEventListener('click',()=>task(async()=>{await api('discard',{id:upload.id});await refresh();status('ready');}));li.append(button);el('uploads').append(li);}
  if(listing&&peer){
   const context=await api('context');canSend=context.can_send;
   peerBundle=context.peer?await verifyBundle(context.peer,peer,pins[peer]||null):null;
   if(context.self){selfBundle=await verifyBundle(context.self,user,pins[user]||null);if(selfBundle.trusted)pins[user]=selfBundle.pin;}
   if(peerBundle?.trusted){pins[peer]=peerBundle.pin;await persist({pins});}
   if(context.peer&&pins[peer])peerBundle=await verifyBundle(context.peer,peer,pins[peer]);
   el('peer-fingerprint').textContent=peerBundle?.fingerprint||text('awaiting_peer');
  }
  // A different tab may have advanced a pin while this response was in flight.
  // Revalidate against the transactionally merged highest versions.
  if(account&&pins[user])selfBundle=await verifyBundle(account,user,pins[user]);
 }
 function download(bytes,name,mime='application/octet-stream'){
  const url=URL.createObjectURL(new Blob([bytes],{type:mime}));urls.add(url);
  const anchor=document.createElement('a');anchor.href=url;anchor.download=name;anchor.rel='noopener';document.body.append(anchor);anchor.click();anchor.remove();
  setTimeout(()=>{URL.revokeObjectURL(url);urls.delete(url);},30000);
 }
 function clearPending(){pending=null;el('new-code').value='';el('code-saved').checked=false;}
 function lock(){epoch++;for(const controller of requests)controller.abort();state=null;clearPending();cursor=0;shown.clear();for(const id of ['passphrase','recovery-input','body','files'])if(el(id))el(id).value='';if(el('messages'))el('messages').replaceChildren();for(const url of urls)URL.revokeObjectURL(url);urls.clear();status('locked');controls();}
 async function stage(result){if(!active)return;lock();pending=result;el('new-code').value=result.recoveryCode;el('code-saved').checked=false;el('passphrase').value='';el('recovery-input').value='';status('save_code');}
 async function load(){
  if(!state||!peerBundle?.trusted||!selfBundle?.trusted)return;
  const generation=epoch,result=await api('history');
  for(const record of result.messages){
   if(!active||generation!==epoch||!state)return;
   if(!Number.isSafeInteger(record.id)||record.id<=cursor||record.listing_id!==listing||!([user,peer].includes(record.sender_id)&&[user,peer].includes(record.receiver_id)))throw Error('message_context');
   if(shown.has(record.id))continue;
   const article=document.createElement('article');article.className='panel';article.dataset.e2eeMessage=String(record.id);
   try{
    const message=await decryptMessage(state,record,pins);if(!active||generation!==epoch||!state)return;const body=document.createElement('p');body.textContent=message.body;article.append(body);
    const date=document.createElement('small');date.textContent=record.created_at+' UTC';article.append(date);
    for(const descriptor of message.attachments){const button=document.createElement('button');button.type='button';button.className='button secondary';button.textContent=text('download')+': '+descriptor.name;
     button.addEventListener('click',()=>task(async()=>{if(!state)throw Error('vault_locked');const generation=epoch;const cipher=await request(apiPath+'?'+new URLSearchParams({action:'download',id:descriptor.id}),{credentials:'same-origin',cache:'no-store'},async response=>{if(!response.ok)throw Error('error_default');return new Uint8Array(await response.arrayBuffer());});if(!active||generation!==epoch||!state)return;const file=await decryptAttachment(state,message,descriptor,cipher);if(active&&generation===epoch&&state)download(file.bytes,file.name);status('ready');}));article.append(button);}
   }catch{const warning=document.createElement('p');warning.textContent=text('failed_message');article.append(warning);}
   if(!active||generation!==epoch||!state)return;el('messages').append(article);shown.add(record.id);cursor=record.id;
  }
 }
 async function send(){
  await refresh();if(!canSend||!state||!peerBundle?.trusted)throw Error('identity_untrusted');
  // Keep the exact encrypted request across a lost response/reload. A retry can
  // never generate a second message or persist plaintext in browser storage.
  if(outbox){await api('send',outbox);await persist({[outboxKey]:null});outbox=null;el('body').value='';el('files').value='';await load();status('sent');return;}
  const routing={listing_id:listing,client_id:randomId()},descriptors=[],uploaded=[];
  const files=[...el('files').files];if(files.length>3)throw Error('attachment_limit');
  try{
   for(const file of files){if(file.size>3*1024*1024)throw Error('attachment_limit');const result=await encryptAttachment(state,selfBundle,peerBundle,routing,{bytes:new Uint8Array(await file.arrayBuffer()),name:file.name,mime:file.type,id:randomId()});
    const saved=(await api('upload',result.request)).attachment;uploaded.push(saved.id);
    if(saved.id!==result.descriptor.id||saved.sha256!==result.descriptor.sha256||saved.bytes!==result.descriptor.bytes)throw Error('attachment_binding');descriptors.push(result.descriptor);}
   const request=await encryptMessage(state,selfBundle,peerBundle,routing,el('body').value,descriptors);
   await persist({[outboxKey]:request});outbox=request;
   await api('send',request);await persist({[outboxKey]:null});outbox=null;el('body').value='';el('files').value='';await load();status('sent');
  }catch(error){if(!outbox)for(const id of uploaded){try{await api('discard',{id});}catch{/* Bound files are never discarded. */}}throw error;}
 }
 function bind(id,fn){if(el(id))el(id).addEventListener('click',()=>task(fn));}
 async function start(){
  if(!isSecureContext||!crypto.subtle||!indexedDB)throw Error('unsupported');
  try{await crypto.subtle.generateKey({name:'Ed25519'},false,['sign','verify']);}catch{throw Error('unsupported');}
  db=await new Promise((resolve,reject)=>{const request=indexedDB.open('gunindex-e2ee-v1',1);request.onupgradeneeded=()=>request.result.createObjectStore('items');request.onsuccess=()=>resolve(request.result);request.onerror=()=>reject(request.error);});
  local=await get('vault')||null;pins=await get('pins')||{};outbox=await get(outboxKey)||null;await refresh();
  bind('create',async()=>{const generation=epoch;const result=await initialize(user,el('device-name').value.trim(),el('passphrase').value);if(generation===epoch)await stage(result);});
  bind('prepare-recovery',async()=>{const generation=epoch,selected=el('revoke').value;await refresh();if(generation!==epoch)return;const revoke=new Set();if(local&&selfBundle?.manifest.devices.some(d=>d.id===local.device_id&&!d.revoked))revoke.add(local.device_id);if(selected){if(!selfBundle.manifest.devices.some(d=>d.id===selected&&!d.revoked))throw Error('device_revoked');revoke.add(selected);}
   const result=await recover(account,el('recovery-input').value.trim(),el('device-name').value.trim(),el('passphrase').value,[...revoke]);if(generation===epoch)await stage(result);});
  bind('commit',async()=>{if(!pending||!el('code-saved').checked)throw Error('invalid_input');const candidate=pending;
   const saved=(await api('account',candidate.request)).account;const verified=await verifyBundle(saved,user);
   if(saved.manifest!==candidate.request.manifest||saved.signature!==candidate.request.signature||verified.hash!==candidate.recoveryCode.split('.')[3])throw Error('manifest_hash');
   pins[user]=verified.pin;await persist({vault:candidate.local,pins});local=candidate.local;lock();await refresh();status('created');});
  el('code-saved').addEventListener('change',controls);
  el('download-code').addEventListener('click',()=>{if(pending)download(new TextEncoder().encode(pending.recoveryCode+'\n'),'GunIndex-recovery.txt','text/plain');});
  el('cancel').addEventListener('click',()=>{clearPending();status('locked');controls();});el('lock').addEventListener('click',lock);
  bind('unlock',async()=>{const passphrase=el('passphrase').value;lock();const generation=epoch;const unlocked=await unlock(local,passphrase);if(!active||generation!==epoch)return;state=unlocked;await refresh();if(!selfBundle?.trusted){lock();throw Error('identity_untrusted');}if(!selfBundle.manifest.devices.some(d=>d.id===state.device_id&&!d.revoked)){lock();throw Error('device_revoked');}await load();status('ready');});
  bind('verify',async()=>{await refresh();const fingerprint=el('fingerprint-input').value.replace(/\s/g,'').toLowerCase();if(!peerBundle||fingerprint!==peerBundle.fingerprint)throw Error('identity_untrusted');pins[peer]=peerBundle.pin;await persist({pins});el('fingerprint-input').value='';await refresh();await load();status('ready');});
  bind('send',send);bind('load',async()=>{await refresh();await load();status('ready');});
  bind('mark-read',async()=>{if(cursor<1||!state)return;await api('read',{listing_id:listing,peer_id:peer,through:cursor});status('read_done');});
  bind('discard-send',async()=>{if(!outbox)return;for(const id of outbox.attachments){try{await api('discard',{id});}catch{/* A bound file remains with its message. */}}await persist({[outboxKey]:null});outbox=null;status('ready');});
  status('locked');controls();
  setInterval(()=>{if(Date.now()-lastActivity>300000&&(state||pending||busy))lock();if(active&&!document.hidden&&!busy&&state&&peerBundle?.trusted)task(async()=>{await refresh();await load();status('ready');});},10000);
 }
 root.addEventListener('input',()=>lastActivity=Date.now());root.addEventListener('click',()=>lastActivity=Date.now());
 window.addEventListener('pagehide',()=>{active=false;lock();});
 window.addEventListener('pageshow',event=>{if(event.persisted)location.reload();});
 start().catch(error=>{status(error.message==='unsupported'?'unsupported':errorKey(error),true);});
}
