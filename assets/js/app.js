/* Local progressive enhancement. Every core workflow also uses native forms. */
(() => {
  'use strict';
  const root = document.documentElement;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const adminMotion = root.dataset.motion;
  const motionToggle = document.querySelector('.motion-toggle');
  let visitorMotion;
  try { visitorMotion = localStorage.getItem('gunindex.motion'); } catch (_) {}
  if (visitorMotion === 'off') root.dataset.motion = 'off';
  if (motionToggle && adminMotion === 'full' && !reduced.matches) {
    motionToggle.hidden = false;
    const updateMotionLabel = () => { motionToggle.textContent = root.dataset.motion === 'off' ? (root.dataset.labelResumeMotion || 'Animationen fortsetzen') : (root.dataset.labelPauseMotion || 'Animationen pausieren'); motionToggle.setAttribute('aria-pressed', String(root.dataset.motion === 'off')); };
    updateMotionLabel();
    motionToggle.addEventListener('click', () => {
      root.dataset.motion = root.dataset.motion === 'off' ? adminMotion : 'off';
      try { localStorage.setItem('gunindex.motion', root.dataset.motion === 'off' ? 'off' : 'auto'); } catch (_) {}
      updateMotionLabel();
    });
  }
  const fullMotion = root.dataset.motion === 'full' && !reduced.matches;
  const toggle = document.querySelector('.theme-toggle');
  const setTheme = value => {
    root.dataset.theme = value === 'light' ? 'light' : 'dark';
    if (toggle) {
      toggle.setAttribute('aria-pressed', String(root.dataset.theme === 'light'));
      toggle.setAttribute('aria-label', root.dataset.theme === 'light' ? (root.dataset.labelThemeDark || 'Dunkles Farbschema aktivieren') : (root.dataset.labelThemeLight || 'Helles Farbschema aktivieren'));
    }
  };
  let savedTheme;
  try { savedTheme = window.localStorage.getItem('gunindex.theme'); } catch (_) { /* Storage may be disabled. */ }
  setTheme(savedTheme === 'light' || savedTheme === 'dark' ? savedTheme : 'dark');
  if (toggle) {
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
      const value = root.dataset.theme === 'light' ? 'dark' : 'light';
      setTheme(value);
      try { window.localStorage.setItem('gunindex.theme', value); } catch (_) { /* Theme still works for this page. */ }
    });
  }
  if (fullMotion && 'IntersectionObserver' in window) {
    root.classList.add('motion-ready');
    const observer = new IntersectionObserver(entries => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          entry.target.classList.remove('is-pending');
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      }
    }, {rootMargin: '0px 0px 20px 0px', threshold: 0.035});
    document.querySelectorAll('.reveal').forEach(element => {
      if (element.getBoundingClientRect().top > window.innerHeight - 20) element.classList.add('is-pending');
      else element.classList.add('is-visible');
      observer.observe(element);
    });
    const art = document.querySelector('.hero-art');
    if (art && window.matchMedia('(pointer:fine)').matches) {
      let pending = false;
      let point = {x: 0, y: 0};
      art.addEventListener('pointermove', event => {
        const box = art.getBoundingClientRect();
        point = {x: (event.clientX - box.left) / box.width - 0.5, y: (event.clientY - box.top) / box.height - 0.5};
        if (!pending) {
          pending = true;
          requestAnimationFrame(() => {
            art.style.setProperty('--pointer-x', (point.x * 12).toFixed(2) + 'deg');
            art.style.setProperty('--pointer-y', (-point.y * 12).toFixed(2) + 'deg');
            pending = false;
          });
        }
      });
      art.addEventListener('pointerleave', () => {
        art.style.setProperty('--pointer-x', '0deg');
        art.style.setProperty('--pointer-y', '0deg');
      });
    }
  }
  document.querySelectorAll('[data-gallery]').forEach(gallery => {
    const main = gallery.querySelector('[data-gallery-main]');
    if (!main) return;
    gallery.querySelectorAll('[data-gallery-src]').forEach(link => {
      link.addEventListener('click', event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        main.src = link.dataset.gallerySrc;
        main.alt = link.dataset.galleryAlt || 'Produktansicht';
        gallery.querySelectorAll('[data-gallery-src]').forEach(other => other.removeAttribute('aria-current'));
        link.setAttribute('aria-current', 'true');
      });
    });
  });
  const countdowns = Array.from(document.querySelectorAll('[data-countdown]'));
  const tick = () => {
    for (const element of countdowns) {
      const end = Date.parse(element.dataset.countdown);
      if (!Number.isFinite(end)) continue;
      const remaining = Math.max(0, Math.ceil((end - Date.now()) / 1000));
      if (remaining === 0) { element.textContent = root.dataset.labelCountdownExpired || 'Frist abgelaufen · Abschluss wird verarbeitet'; continue; }
      const days = Math.floor(remaining / 86400);
      const hours = Math.floor(remaining % 86400 / 3600);
      const minutes = Math.floor(remaining % 3600 / 60);
      const seconds = remaining % 60;
      element.textContent = (days ? days + ' ' + (root.dataset.labelDays || 'Tage') + ' · ' : '') + String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }
  };
  if (countdowns.length) { tick(); window.setInterval(tick, 1000); }
  document.querySelectorAll('input[type=file][data-image-preview]').forEach(input => {
    let urls = [];
    input.addEventListener('change', () => {
      const holder = document.getElementById(input.dataset.imagePreview);
      if (!holder) return;
      urls.forEach(URL.revokeObjectURL);
      urls = [];
      holder.replaceChildren();
      Array.from(input.files || []).slice(0, 5).forEach(file => {
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return;
        const src = URL.createObjectURL(file);
        urls.push(src);
        const image = document.createElement('img');
        image.src = src; image.alt = 'Bildvorschau'; image.width = 100; image.height = 100;
        holder.append(image);
      });
    });
    window.addEventListener('pagehide', () => urls.forEach(URL.revokeObjectURL), {once: true});
  });
})();
