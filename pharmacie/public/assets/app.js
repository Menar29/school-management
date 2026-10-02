/* Comportements communs à toutes les pages */
(function () {
  'use strict';
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

  // Afficher / masquer le mot de passe
  $$('[data-toggle-pw]').forEach((b) => b.addEventListener('click', () => {
    const i = document.getElementById(b.dataset.togglePw);
    const show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    b.textContent = show ? 'Masquer' : 'Afficher';
    b.setAttribute('aria-pressed', show ? 'true' : 'false');
  }));

  // Majuscules activées
  $$('[data-caps]').forEach((i) => i.addEventListener('keyup', (e) => {
    const m = document.querySelector('[data-caps-msg]');
    if (m && e.getModifierState) m.hidden = !e.getModifierState('CapsLock');
  }));

  // Règles du nouveau mot de passe, vérifiées pendant la saisie
  $$('form[data-pw-rules]').forEach((f) => {
    const min = parseInt(f.dataset.pwRules, 10);
    const common = ['12345678', '123456789', 'password', 'motdepasse', 'azerty123', 'pharmacie', 'pharma2026', 'remede123'];
    const n1 = f.querySelector('[name=n1]');
    const lis = $$('.rules li', f);
    n1.addEventListener('input', () => {
      const v = n1.value;
      const ok = [v.length >= min, v.length > 0, v.length > 0 && !common.includes(v.toLowerCase())];
      lis.forEach((li, i) => { li.className = v ? (ok[i] ? 'ok' : 'ko') : ''; });
    });
  });

  // Pavé numérique du verrou de caisse
  $$('form[data-pinpad]').forEach((f) => {
    const input = f.querySelector('[name=pin]');
    const dots = $$('.dots span', f);
    const draw = () => dots.forEach((d, i) => d.classList.toggle('on', i < input.value.length));
    const press = (k) => {
      if (k === 'clear') input.value = '';
      else if (k === 'back') input.value = input.value.slice(0, -1);
      else if (input.value.length < 4) input.value += k;
      draw();
      if (input.value.length === 4) f.submit();
    };
    $$('[data-key]', f).forEach((b) => b.addEventListener('click', () => press(b.dataset.key)));
    document.addEventListener('keydown', (e) => {
      if (/^\d$/.test(e.key)) press(e.key);
      else if (e.key === 'Backspace') press('back');
    });
  });

  // Horloge des écrans de connexion
  $$('[data-clock]').forEach((el) => setInterval(() => {
    const d = new Date();
    el.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }, 10000));

  // Menu sur téléphone
  $$('[data-open-menu]').forEach((a) => a.addEventListener('click', (e) => {
    e.preventDefault();
    const s = document.getElementById('side');
    if (s) s.classList.toggle('open');
  }));
  document.addEventListener('click', (e) => {
    const s = document.getElementById('side');
    if (s && s.classList.contains('open') && !s.contains(e.target) && !e.target.closest('[data-open-menu]')) s.classList.remove('open');
  });

  // Fenêtres : <button data-dialog="id">, <button data-close>
  document.addEventListener('click', (e) => {
    const o = e.target.closest('[data-dialog]');
    if (o) {
      e.preventDefault();
      const d = document.getElementById(o.dataset.dialog);
      if (d) {
        // remplit les champs <input data-fill="clé"> avec les data-* du bouton
        $$('[data-fill]', d).forEach((i) => {
          const v = o.dataset[i.dataset.fill];
          if (v === undefined) return;
          if (i.tagName === 'INPUT' || i.tagName === 'SELECT' || i.tagName === 'TEXTAREA') i.value = v; else i.textContent = v;
        });
        d.showModal();
      }
      return;
    }
    const c = e.target.closest('dialog [data-close]');
    if (c) { e.preventDefault(); c.closest('dialog').close(); }
  });

  // Confirmation avant une action
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f.dataset.confirm && !confirm(f.dataset.confirm)) { e.preventDefault(); return; }
    const b = f.querySelector('[type=submit]');
    if (b && !f.dataset.noLock) setTimeout(() => { b.disabled = true; }, 0);
  });

  // Listes déroulantes qui rechargent la page
  $$('[data-autosubmit]').forEach((s) => s.addEventListener('change', () => s.form.submit()));

  // Lignes de tableau cliquables
  $$('tr[data-href]').forEach((tr) => tr.addEventListener('click', (e) => {
    if (e.target.closest('a,button,input,select,label')) return;
    location.href = tr.dataset.href;
  }));

  // Application installable sur le téléphone
  if ('serviceWorker' in navigator && location.protocol !== 'file:') {
    window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {}));
  }
})();
