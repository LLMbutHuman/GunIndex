/* Native WebAuthn, with the server responsible for validating each ceremony. */
(() => {
  'use strict';
  function decode(value) {
    return Uint8Array.from(atob(value.replace(/-/g,'+').replace(/_/g,'/')), character => character.charCodeAt(0));
  }
  function encode(value) {
    return btoa(String.fromCharCode(...new Uint8Array(value))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/g,'');
  }
  async function request(fields) {
    const response=await fetch('/auth/passkey.php',{method:'POST',body:fields});
    const result=await response.json().catch(()=>({}));
    if (!response.ok) {
      const error=new Error();
      error.safeText=typeof result.error==='string'?result.error:'';
      throw error;
    }
    return result;
  }
  for (const form of document.querySelectorAll('[data-passkey]')) {
    if (!window.isSecureContext || typeof PublicKeyCredential==='undefined' || location.origin!==form.dataset.origin) continue;
    const button=form.querySelector('[data-passkey-start]');
    const status=form.querySelector('[data-passkey-error]');
    const add=form.dataset.passkey==='add';
    button.disabled=false;
    form.addEventListener('submit',event=>event.preventDefault());
    button.addEventListener('click',async()=>{
      if (!form.reportValidity()) return;
      button.disabled=true;
      status.textContent='';
      try {
        const begin=new FormData(form);
        const csrf=begin.get('csrf_token');
        begin.set('action',add?'start_add':'start_login');
        let result;
        try { result=await request(begin); }
        finally {
          const password=form.elements.namedItem('password');
          const factor=form.elements.namedItem('factor');
          if(password) password.value='';
          if(factor) factor.value='';
        }
        const publicKey=result.publicKey;
        publicKey.challenge=decode(publicKey.challenge);
        if (add) publicKey.user.id=decode(publicKey.user.id);
        for (const item of [...publicKey.excludeCredentials??[],...publicKey.allowCredentials??[]]) item.id=decode(item.id);
        const credential=add?await navigator.credentials.create({publicKey}):await navigator.credentials.get({publicKey});
        if (!credential || credential.type!=='public-key') throw new Error();
        const formData=new FormData();
        formData.set('csrf_token',csrf);
        formData.set('action',add?'finish_add':'finish_login');
        formData.set('rawId',encode(credential.rawId));
        formData.set('clientData',encode(credential.response.clientDataJSON));
        if (add) formData.set('attestation',encode(credential.response.attestationObject));
        else {
          formData.set('authenticator',encode(credential.response.authenticatorData));
          formData.set('signature',encode(credential.response.signature));
          if (credential.response.userHandle) formData.set('userHandle',encode(credential.response.userHandle));
        }
        const done=await request(formData);
        if (!['/user/passkeys.php','/user/dashboard.php','/auth/user-factor.php'].includes(done.next)) throw new Error();
        location.assign(done.next);
      } catch (error) {
        status.textContent=error.safeText||form.dataset.error;
        button.disabled=false;
      }
    });
  }
})();
