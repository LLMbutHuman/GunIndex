/* Store private progress independently of the form that publishes an item. */
(() => {
  'use strict';
  for (const form of document.querySelectorAll('[data-draft-autosave]')) {
    const status = form.querySelector('[data-draft-status]');
    const gallery = form.querySelector('[data-draft-images]');
    const clear = form.querySelector('[name=clear_draft_images]');
    const fileInput = form.elements.namedItem('images[]');
    const revision = form.elements.namedItem('draft_revision');
    const base = form.elements.namedItem('draft_base');
    const allowed = new Set(['title','description','category_id','condition_label','type','price','buyout_price',
      'shipping_price','country_code','zip','city','duration_days','listing_duration_days','status','pickup','shipping',
      'trade_checkout_enabled','image_revision','csrf_token','editor','draft_revision','draft_base','images[]']);
    let timer;
    let pending;
    let dirty = false;
    let stopped = false;
    let submitting = false;
    let fileVersion = 0;

    function refreshImages(total, version) {
      if (gallery.children.length === total) return;
      gallery.replaceChildren();
      clear.hidden = total === 0;
      for (let position = 0; position < total; position++) {
        const image = document.createElement('img');
        image.width = 96;
        image.height = 72;
        image.alt = gallery.dataset.imageAlt;
        image.src = '/user/listing-draft-image.php?' + new URLSearchParams({
          editor: form.elements.namedItem('editor').value, position, revision: version, size: 'thumb',
        });
        gallery.append(image);
      }
    }

    function schedule() {
      clearTimeout(timer);
      if (!stopped && !pending && !submitting && dirty) timer = setTimeout(save, 900);
    }

    function save() {
      if (stopped || submitting || pending || !dirty) return;
      if (form.querySelector('[data-classification-mode][aria-busy=true]')) { schedule(); return; }
      dirty = false;
      const uploadedVersion = fileVersion;
      const body = new FormData();
      for (const [name, value] of new FormData(form)) {
        if ((allowed.has(name) || /^(attributes|image_positions)\[[1-9]\d{0,18}\]$/.test(name)) && (typeof value === 'string' || value.size > 0)) {
          body.append(name,value);
        }
      }
      body.set('autosave','1');
      status.textContent = status.dataset.saving;
      pending = (async () => {
        try {
          const response = await fetch('/user/listing-draft.php',{ method:'POST',body,signal:AbortSignal.timeout(90000) });
          const result = await response.json();
          if (!response.ok) {
            if (response.status === 409) stopped = true;
            status.textContent = typeof result.error === 'string' ? result.error : status.dataset.failed;
            return;
          }
          if (!/^[a-f0-9]{32}$/.test(result.revision) || !/^[a-f0-9]{64}$/.test(result.base)
              || !Number.isInteger(result.images) || result.images < 0 || result.images > 5) throw new Error();
          revision.value = result.revision;
          base.value = result.base;
          if (fileVersion === uploadedVersion) fileInput.value = '';
          refreshImages(result.images,result.revision);
          status.textContent = status.dataset.saved;
        } catch {
          status.textContent = status.dataset.failed;
        } finally {
          pending = null;
          schedule();
        }
      })();
    }

    form.addEventListener('input',event => {
      if (event.target === fileInput) fileVersion++;
      if (event.target.name !== 'accept_rules' && event.target.name !== 'accept_auction_fees' && event.target.name !== 'legal_confirm') {
        dirty = true;
        schedule();
      }
    });
    form.addEventListener('submit',event => {
      clearTimeout(timer);
      if (!pending) return;
      event.preventDefault();
      if (submitting) return;
      submitting = true;
      const submitter = event.submitter;
      pending.finally(() => {
        submitting = false;
        if (!stopped) form.requestSubmit(submitter ?? undefined);
      });
    });
  }
})();
