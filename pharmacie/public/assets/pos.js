/* Caisse : recherche, ticket, encaissement, remise, tickets en attente, verrouillage, hors connexion */
(function () {
  'use strict';
  const D = JSON.parse(document.getElementById('pos-data').textContent);
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const K = (k) => 'pos.' + k + '.' + D.session;
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* stockage plein ou bloqué */ } },
  };
  const fmt = (n) => String(Math.round(Math.abs(n))).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  const norm = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'v' + Date.now() + Math.random().toString(16).slice(2));
  const csrf = document.querySelector('meta[name=csrf]').content;

  let cat = store.get('pos.cat', { produits: [], top: [] });
  let byId = {};
  let cart = store.get(K('cart'), []);
  let remise = store.get(K('remise'), { amt: 0, pin: '' });
  let held = store.get(K('held'), []);
  let queue = store.get(K('queue'), []);
  let recv = '';
  let mode = 'especes';
  let lastSale = null;
  let pendingShort = null;
  let pendingExp = null;

  function index() { byId = {}; cat.produits.forEach((p) => { byId[p.id] = p; }); }
  index();

  async function api(url, body) {
    const r = await fetch(url, {
      method: body ? 'POST' : 'GET',
      headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf, 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'same-origin',
    });
    if (r.status === 423) { location.href = D.urls.verrou; throw new Error('locked'); }
    if (r.status === 401) { location.href = 'index.php?r=login'; throw new Error('auth'); }
    const data = await r.json().catch(() => ({}));
    if (!r.ok) { const e = new Error(data.error || 'Erreur'); e.status = r.status; throw e; }
    return data;
  }

  // ---------- Catalogue ----------
  async function loadCatalog() {
    try {
      const c = await api(D.urls.catalogue);
      cat = c; index(); store.set('pos.cat', c);
      setNet(true);
      renderTiles(); renderResults(); renderCart();
    } catch (e) {
      if (e.status === undefined) setNet(false);
    }
  }

  function dispo(p) {
    // stock restant une fois le ticket en cours déduit
    let used = 0;
    cart.forEach((l) => { if (l.id === p.id) used += unitOf(p, l.u).f * l.n; });
    return p.q - used;
  }
  function unitOf(p, u) { return p.u.find((x) => x.u === u) || p.u[0]; }

  function dispoText(p) {
    if (p.st === 'noprice') return ['Sans prix · non vendable', 'mute'];
    if (p.st === 'exp') return ['Périmé · bloqué', 'bad'];
    if (p.q <= 0) return ['Rupture', 'mute'];
    if (p.st === 'low') return ['Plus que ' + p.qs, 'warn'];
    return [p.qs + ' en rayon', 'ok'];
  }

  // ---------- Accès rapide et recherche ----------
  function renderTiles() {
    let ids = cat.top.filter((id) => byId[id] && byId[id].u.length);
    if (ids.length < 12) cat.produits.filter((p) => p.q > 0 && p.u.length && !ids.includes(p.id)).slice(0, 12 - ids.length).forEach((p) => ids.push(p.id));
    const el = $('#tiles');
    if (!ids.length) { el.innerHTML = '<p class="muted">Aucun produit en stock. Le stock arrive par les réceptions et les transferts.</p>'; return; }
    el.innerHTML = ids.slice(0, 12).map((id) => {
      const p = byId[id]; const u = p.u[0]; const low = p.st === 'low';
      return `<button class="tile" data-add="${p.id}" ${p.q <= 0 ? 'disabled style="opacity:.5"' : ''}>
        <span style="display:flex;flex-direction:column"><span style="font-weight:600">${esc(p.n)}</span><span class="small muted">${esc(u.l)}</span></span>
        <span class="between"><span class="num" style="font-weight:600">${fmt(u.p)}</span><span class="small" style="color:${low ? 'var(--warn-ink)' : 'var(--muted)'}">${p.q <= 0 ? 'Rupture' : low ? 'Plus que ' + esc(p.qs) : esc(p.qs)}</span></span>
      </button>`;
    }).join('');
  }

  function search(q) {
    const words = norm(q).split(/\s+/).filter(Boolean);
    if (!words.length) return [];
    return cat.produits.filter((p) => {
      if (p.cb && p.cb === q.trim()) return true;
      const hay = norm(p.n + ' ' + p.d + ' ' + p.a.join(' '));
      return words.every((w) => hay.includes(w));
    }).sort((a, b) => (b.q > 0) - (a.q > 0)).slice(0, 40);
  }

  function suggestions(q) {
    // produits proches : préfixe commun de 4 lettres
    const w = norm(q).replace(/\s+/g, '').slice(0, 4);
    if (w.length < 3) return [];
    return cat.produits.filter((p) => norm(p.n + ' ' + p.a.join(' ')).replace(/\s+/g, '').includes(w.slice(0, 3))).slice(0, 3);
  }

  function renderResults() {
    const q = $('#q').value;
    const has = q.trim() !== '';
    $('#quick').hidden = has;
    $('#results').hidden = !has;
    $('[data-act=clearq]').hidden = !has;
    if (!has) return;
    const res = search(q);
    $('#rescount').textContent = res.length + (res.length > 1 ? ' produits' : ' produit');
    if (!res.length) {
      const sug = suggestions(q);
      $('#reslist').innerHTML = `<div class="empty"><strong>Aucun produit trouvé</strong><br><span class="small">Vérifiez l'orthographe ou essayez le nom de la molécule.</span>
        ${sug.length ? '<div class="flex" style="justify-content:center;margin-top:12px"><span class="small">Vouliez-vous dire :</span>' + sug.map((p) => `<button class="chip" data-q="${esc(p.n)}">${esc(p.n)}</button>`).join('') + '</div>' : ''}</div>`;
      return;
    }
    $('#reslist').innerHTML = res.map((p) => {
      const [t, c] = dispoText(p);
      const blocked = p.st === 'noprice' || (p.q <= 0 && p.st !== 'exp');
      return `<button class="res" data-add="${p.id}" ${blocked ? 'disabled' : ''}>
        <span style="display:flex;flex-direction:column;min-width:0"><span style="font-weight:600;color:${blocked || p.st === 'exp' ? 'var(--muted)' : 'var(--ink)'}">${esc(p.n)}${p.rx ? ' <span class="pill info plain">Ordonnance</span>' : ''}</span><span class="small muted ell">${esc(p.st === 'exp' ? 'Lot expiré en ' + p.exp : p.d)}</span></span>
        <span><span class="pill ${c}">${esc(t)}</span></span>
        <span class="small">${esc(p.u.map((u) => u.l).join(' / '))}</span>
        <span class="num ra" style="font-weight:600">${p.u.length ? fmt(p.u[0].p) : '—'}</span>
      </button>`;
    }).join('');
  }

  // ---------- Ticket ----------
  function add(id, u) {
    const p = byId[id];
    if (!p) return;
    if (p.st === 'exp' && p.q <= 0) { pendingExp = p; $('#exp-txt').textContent = p.n + ' · lot expiré en ' + p.exp; $('#dlg-exp').showModal(); return; }
    if (!p.u.length) return;
    u = u || p.u[0].u;
    const un = unitOf(p, u);
    if (dispo(p) < un.f) {
      const left = Math.floor(dispo(p) / un.f);
      if (left <= 0) { toast('Plus de ' + p.n + ' en stock dans l\'officine.'); return; }
    }
    const l = cart.find((x) => x.id === id && x.u === u);
    if (l) l.n += 1; else cart.push({ id, u, n: 1 });
    if (p.rx && !l) toast('Ordonnance : ' + p.n + ' est délivré sur ordonnance.');
    save(); renderCart();
  }

  function setQty(i, n) {
    const l = cart[i]; const p = byId[l.id];
    if (n <= 0) { cart.splice(i, 1); save(); renderCart(); return; }
    const un = unitOf(p, l.u);
    const others = dispo(p) + un.f * l.n; // stock disponible pour cette ligne
    if (un.f * n > others) {
      const max = Math.floor(others / un.f);
      pendingShort = { i, max };
      $('#short-txt').innerHTML = `Il reste <strong>${max} ${esc(un.l.toLowerCase())}${max > 1 ? 's' : ''}</strong> de ${esc(p.n)} dans l'officine. Le client en demande ${n}.`;
      $('.btn[data-act=short-ok]').textContent = 'Vendre les ' + max;
      $('#dlg-short').showModal();
      return;
    }
    l.n = n; save(); renderCart();
  }

  function totals() {
    let brut = 0, count = 0;
    cart.forEach((l) => { const p = byId[l.id]; if (!p) return; brut += unitOf(p, l.u).p * l.n; count += l.n; });
    const r = Math.min(remise.amt || 0, brut);
    return { brut, remise: r, total: brut - r, count };
  }

  function renderCart() {
    cart = cart.filter((l) => byId[l.id]);
    const t = totals();
    const empty = cart.length === 0;
    $('#count').textContent = t.count + (t.count > 1 ? ' articles' : ' article');
    $$('[data-act=clear],[data-act=hold],[data-act=pay],[data-act=discount]').forEach((b) => { b.disabled = empty; });
    $('#total').textContent = fmt(t.total);
    $('#discnote').innerHTML = t.remise ? 'Remise de <strong>' + fmt(t.remise) + ' FCFA</strong> sur ' + fmt(t.brut) : 'Au-delà de ' + D.remiseMax + ' % : code d\'un responsable';
    if (empty) {
      $('#lines').innerHTML = '<div class="empty"><strong>Le ticket est vide</strong><br><span class="small">Touchez un produit de l\'accès rapide, recherchez-le ou scannez son code-barres.</span></div>';
      return;
    }
    $('#lines').innerHTML = cart.map((l, i) => {
      const p = byId[l.id]; const un = unitOf(p, l.u);
      return `<div class="line">
        <div class="between"><span style="display:flex;flex-direction:column;min-width:0"><span style="font-weight:600" class="ell">${esc(p.n)}</span><span class="small muted">${fmt(un.p)} FCFA / ${esc(un.l.toLowerCase())}</span></span>
          <button class="ibtn" data-rm="${i}" aria-label="Retirer ${esc(p.n)}"><svg class="ic" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
        <div class="between">
          <span class="stepper"><button data-dec="${i}" aria-label="Diminuer">−</button><span>${l.n}</span><button data-inc="${i}" aria-label="Augmenter">+</button></span>
          ${p.u.length > 1 ? `<span class="unit" role="group" aria-label="Unité">${p.u.map((u) => `<button class="${u.u === l.u ? 'on' : ''}" data-unit="${i}" data-u="${u.u}">${esc(u.l)}</button>`).join('')}</span>` : ''}
          <span class="num" style="font-weight:600;font-size:17px">${fmt(un.p * l.n)}</span>
        </div></div>`;
    }).join('');
  }

  function save() { store.set(K('cart'), cart); store.set(K('remise'), remise); }

  function clearCart() { cart = []; remise = { amt: 0, pin: '' }; save(); renderCart(); }

  // ---------- Tickets en attente ----------
  function renderHeld() {
    const el = $('#held');
    el.hidden = !held.length;
    el.innerHTML = held.map((h, i) => `<div class="banner info" style="align-items:center"><span style="flex:1">Ticket mis de côté à ${esc(h.at)} · <strong>${fmt(h.total)} FCFA</strong> · ${h.cart.length} produit(s)</span>
      <button class="btn" data-resume="${i}">Reprendre</button><button class="btn ghost" data-drop="${i}">Abandonner</button></div>`).join('');
  }

  // ---------- Encaissement ----------
  function openPay() {
    if (!cart.length) return;
    mode = 'especes'; recv = '';
    $('#pay-step').hidden = false; $('#credit-step').hidden = true; $('#done-step').hidden = true;
    $('#pay-t').textContent = 'Encaissement en espèces';
    const t = totals().total;
    $$('[data-v=total]').forEach((e) => { e.textContent = fmt(t); });
    const q = [t];
    [500, 1000, 2000, 5000, 10000].forEach((m) => { const v = Math.ceil(t / m) * m; if (v > t && !q.includes(v) && q.length < 4) q.push(v); });
    $('#quickamt').innerHTML = q.map((v) => `<button class="chip" data-recv="${v}">${v === t ? 'Montant exact' : fmt(v)}</button>`).join('');
    renderRecv();
    $('#dlg-pay').showModal();
  }

  function renderRecv() {
    const t = totals().total;
    const r = parseInt(recv || '0', 10);
    $('#recv').textContent = fmt(r);
    const short = r < t;
    $('#change').classList.toggle('short', short && recv !== '');
    $('#chlabel').textContent = short ? (recv === '' ? 'Saisissez le montant reçu' : 'Il manque') : 'Monnaie à rendre';
    $('#chtext').textContent = recv === '' ? '—' : fmt(short ? t - r : r - t);
    $('[data-act=validate]').disabled = short;
  }

  function buildPad() {
    const keys = ['7', '8', '9', '4', '5', '6', '1', '2', '3', '00', '0', '⌫'];
    $('#paypad').innerHTML = keys.map((k) => `<button class="key ${k === '⌫' ? 'fn' : ''}" data-pad="${k}">${k}</button>`).join('');
  }

  async function validate(credit) {
    const t = totals();
    const payload = {
      uuid: uuid(), at: new Date().toISOString(),
      lines: cart.map((l) => ({ id: l.id, u: l.u, n: l.n })),
      remise: t.remise, pin: remise.pin || '', mode: credit ? 'credit' : 'especes',
      recu: credit ? null : parseInt(recv || '0', 10),
    };
    if (credit) {
      const c = $('input[name=client]:checked');
      if (!c) { showCreditErr('Choisissez le client.'); return; }
      if (parseInt(c.dataset.reste, 10) < t.total) { showCreditErr('Le plafond de crédit de ce client est dépassé.'); return; }
      payload.client_id = parseInt(c.value, 10);
    }
    $$('[data-act=validate],[data-act=validate-credit]').forEach((b) => { b.disabled = true; });
    try {
      const r = await api(D.urls.valider, payload);
      afterSale({ id: r.id, numero: r.numero, total: r.total, rendu: r.rendu, credit, offline: false });
    } catch (e) {
      if (e.status === undefined) {
        // pas de réseau : la vente est gardée sur le poste
        queue.push(Object.assign(payload, { offline: true }));
        store.set(K('queue'), queue);
        setNet(false);
        afterSale({ id: null, numero: 'en attente d\'envoi', total: t.total, rendu: credit ? null : payload.recu - t.total, credit, offline: true });
      } else if (e.message !== 'locked') {
        alertIn(credit ? '#credit-err' : null, e.message);
      }
    } finally {
      $$('[data-act=validate-credit]').forEach((b) => { b.disabled = false; });
      renderRecv();
    }
  }

  function afterSale(s) {
    // décompte local du stock pour que la caisse reste juste jusqu'au prochain rafraîchissement
    cart.forEach((l) => { const p = byId[l.id]; if (p) { p.q -= unitOf(p, l.u).f * l.n; } });
    store.set('pos.cat', cat);
    lastSale = s;
    clearCart();
    renderTiles(); renderResults();
    $('#pay-step').hidden = true; $('#credit-step').hidden = true; $('#done-step').hidden = false;
    $('#done-sub').textContent = 'Ticket ' + s.numero + ' · ' + fmt(s.total) + ' FCFA ' + (s.credit ? 'à crédit' : 'payés en espèces');
    $('#done-change').hidden = !!s.credit;
    $('#done-rendu').textContent = fmt(s.rendu || 0);
    $('[data-act=print]').disabled = !s.id;
    updateNet();
  }

  function showCreditErr(m) { const e = $('#credit-err'); e.textContent = m; e.hidden = false; }
  function alertIn(sel, m) { if (sel) showCreditErr(m); else { $('#chlabel').textContent = m; $('#change').classList.add('short'); $('#chtext').textContent = ''; } }

  // ---------- Remise ----------
  function openDiscount() {
    const t = totals();
    $('#disc-amt').value = remise.amt || '';
    $('#disc-pin').value = '';
    $('#disc-err').hidden = true;
    $('#disc-chips').innerHTML = [5, 10, 15, 20].map((p) => `<button class="chip" data-pct="${p}">${p} %</button>`).join('');
    discCheck();
    $('#dlg-disc').showModal();
    $('#disc-amt').focus();
    return t;
  }
  function discPct() { const t = totals(); const a = parseInt($('#disc-amt').value.replace(/\D/g, '') || '0', 10); return t.brut ? a / t.brut * 100 : 0; }
  function discCheck() {
    const p = discPct();
    const need = p > D.remiseMax + 0.001;
    $('#disc-pin-w').hidden = !need;
    $('#disc-who').textContent = p > 20 ? '(propriétaire uniquement)' : '(gérant ou propriétaire)';
  }
  async function discApply() {
    const a = parseInt($('#disc-amt').value.replace(/\D/g, '') || '0', 10);
    const t = totals();
    if (a > t.brut) { $('#disc-err').textContent = 'La remise dépasse le total.'; $('#disc-err').hidden = false; return; }
    const p = discPct();
    let pin = '';
    if (p > D.remiseMax + 0.001) {
      pin = $('#disc-pin').value;
      try {
        const r = await api(D.urls.autoriser, { pin, action: 'remise', pct: p });
        if (!r.ok) { $('#disc-err').textContent = r.error; $('#disc-err').hidden = false; return; }
        toast('Remise autorisée par ' + r.nom + ' (' + r.role + ').');
      } catch (e) { $('#disc-err').textContent = 'Connexion nécessaire pour faire autoriser la remise.'; $('#disc-err').hidden = false; return; }
    }
    remise = { amt: a, pin };
    save(); renderCart();
    $('#dlg-disc').close();
  }

  // ---------- Réseau et envoi des ventes gardées ----------
  let online = navigator.onLine;
  function setNet(on) { online = on; updateNet(); }
  function updateNet() {
    const n = queue.length;
    const el = $('#net');
    el.classList.toggle('off', !online || n > 0);
    el.textContent = !online ? 'Hors connexion' + (n ? ' · ' + n + ' à envoyer' : '') : n ? n + ' vente(s) à envoyer' : 'Synchronisé';
    $('#offline').hidden = online;
  }
  let syncing = false;
  async function sync() {
    if (syncing || !queue.length) return;
    syncing = true;
    const failed = [];
    for (const s of queue.slice()) {
      try {
        await api(D.urls.valider, s);
        queue = queue.filter((x) => x.uuid !== s.uuid);
        store.set(K('queue'), queue);
        setNet(true);
      } catch (e) {
        if (e.status === undefined) { setNet(false); break; }
        if (e.status === 409) break; // session fermée : à régulariser par le responsable
        failed.push(s.uuid + ' : ' + e.message);
        break;
      }
    }
    syncing = false;
    const err = $('#syncerr');
    err.hidden = !(queue.length && online);
    $('#syncerr-t').textContent = queue.length + ' vente(s) n\'ont pas pu être envoyées. Elles restent enregistrées sur ce poste. Nouvel essai dans 30 secondes.' + (failed.length ? ' (' + failed[0] + ')' : '');
    updateNet();
    if (!queue.length) loadCatalog();
  }
  window.addEventListener('online', () => { setNet(true); sync(); loadCatalog(); });
  window.addEventListener('offline', () => setNet(false));
  setInterval(sync, 30000);
  setInterval(() => { if (navigator.onLine && !queue.length) loadCatalog(); }, 120000);

  // ---------- Verrouillage après inactivité ----------
  let idle;
  function resetIdle() {
    clearTimeout(idle);
    idle = setTimeout(async () => {
      try { await api(D.urls.lock, {}); location.href = D.urls.verrou; } catch (e) { /* hors connexion : on reste sur la caisse */ }
    }, Math.max(1, D.verrou) * 60000);
  }
  ['pointerdown', 'keydown', 'touchstart'].forEach((ev) => document.addEventListener(ev, resetIdle, { passive: true }));
  resetIdle();

  // ---------- Messages ----------
  let toastT;
  function toast(m) {
    let el = $('#toast');
    if (!el) { el = document.createElement('div'); el.id = 'toast'; el.className = 'banner info'; el.setAttribute('role', 'status'); el.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:100;box-shadow:var(--sh-2);max-width:90vw'; document.body.appendChild(el); }
    el.textContent = m; el.hidden = false;
    clearTimeout(toastT); toastT = setTimeout(() => { el.hidden = true; }, 3500);
  }

  // ---------- Événements ----------
  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('button,[data-close]');
    if (!b) return;
    const d = b.dataset;
    if (d.add) { add(parseInt(d.add, 10)); if ($('#q').value) { $('#q').select(); } return; }
    if (d.rm !== undefined) { cart.splice(+d.rm, 1); save(); renderCart(); return; }
    if (d.inc !== undefined) { setQty(+d.inc, cart[+d.inc].n + 1); return; }
    if (d.dec !== undefined) { setQty(+d.dec, cart[+d.dec].n - 1); return; }
    if (d.unit !== undefined) {
      const l = cart[+d.unit]; const p = byId[l.id];
      const un = unitOf(p, d.u);
      if (dispo(p) + unitOf(p, l.u).f * l.n < un.f * l.n) { toast('Pas assez de stock pour vendre ' + l.n + ' ' + un.l.toLowerCase() + '(s).'); return; }
      l.u = d.u; save(); renderCart(); return;
    }
    if (d.q) { $('#q').value = d.q; renderResults(); return; }
    if (d.pct) { $('#disc-amt').value = Math.round(totals().brut * d.pct / 100); discCheck(); return; }
    if (d.recv) { recv = d.recv; renderRecv(); return; }
    if (d.pad) {
      if (d.pad === '⌫') recv = recv.slice(0, -1); else if (recv.length < 9) recv = (recv + d.pad).replace(/^0+/, '');
      renderRecv(); return;
    }
    if (d.resume !== undefined) {
      if (cart.length) { held.push({ cart, remise, total: totals().total, at: new Date().toTimeString().slice(0, 5) }); }
      const h = held.splice(+d.resume, 1)[0]; cart = h.cart; remise = h.remise || { amt: 0, pin: '' };
      store.set(K('held'), held); save(); renderCart(); renderHeld(); return;
    }
    if (d.drop !== undefined) { if (confirm('Abandonner ce ticket ?')) { held.splice(+d.drop, 1); store.set(K('held'), held); renderHeld(); } return; }
    if (b.hasAttribute('data-close')) { b.closest('dialog').close(); return; }
    switch (d.act) {
      case 'clearq': $('#q').value = ''; renderResults(); $('#q').focus(); break;
      case 'clear': if (confirm('Vider le ticket ?')) clearCart(); break;
      case 'hold':
        held.push({ cart, remise, total: totals().total, at: new Date().toTimeString().slice(0, 5) });
        store.set(K('held'), held); clearCart(); renderHeld(); break;
      case 'pay': openPay(); break;
      case 'discount': openDiscount(); break;
      case 'disc-ok': discApply(); break;
      case 'disc-remove': remise = { amt: 0, pin: '' }; save(); renderCart(); $('#dlg-disc').close(); break;
      case 'validate': validate(false); break;
      case 'credit': mode = 'credit'; $('#pay-step').hidden = true; $('#credit-step').hidden = false; $('#credit-err').hidden = true; $('#pay-t').textContent = 'Vente à crédit'; break;
      case 'cash': openPay(); break;
      case 'validate-credit': validate(true); break;
      case 'new': $('#dlg-pay').close(); $('#q').value = ''; renderResults(); $('#q').focus(); break;
      case 'print': if (lastSale && lastSale.id) window.open(D.urls.ticket + '&id=' + lastSale.id + '&print=1', '_blank', 'width=420,height=640'); break;
      case 'short-ok': if (pendingShort) { const l = cart[pendingShort.i]; if (pendingShort.max <= 0) cart.splice(pendingShort.i, 1); else l.n = pendingShort.max; save(); renderCart(); } $('#dlg-short').close(); break;
      case 'exp-signal': if (pendingExp) { api(D.urls.signaler, { id: pendingExp.id }).catch(() => {}); toast('Retrait signalé au propriétaire.'); } $('#dlg-exp').close(); break;
      case 'retry': sync(); break;
    }
  });

  $('#q').addEventListener('input', renderResults);
  $('#q').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      const v = $('#q').value.trim();
      const exact = cat.produits.find((p) => p.cb && p.cb === v);
      const res = exact ? [exact] : search(v);
      if (res.length === 1) { add(res[0].id); $('#q').value = ''; renderResults(); }
      e.preventDefault();
    }
    if (e.key === 'Escape') { $('#q').value = ''; renderResults(); }
  });
  $('#disc-amt').addEventListener('input', discCheck);

  document.addEventListener('keydown', (e) => {
    const payOpen = $('#dlg-pay').open;
    if (e.key === 'F2') { e.preventDefault(); $('#q').focus(); $('#q').select(); }
    else if (e.key === 'F9') { e.preventDefault(); if (!payOpen) openPay(); }
    else if (payOpen && !$('#done-step').hidden && e.key === 'Enter') { e.preventDefault(); $('[data-act=new]').click(); }
    else if (payOpen && !$('#pay-step').hidden) {
      if (/^\d$/.test(e.key)) { recv = (recv + e.key).replace(/^0+/, ''); renderRecv(); e.preventDefault(); }
      else if (e.key === 'Backspace') { recv = recv.slice(0, -1); renderRecv(); e.preventDefault(); }
      else if (e.key === 'Enter') { e.preventDefault(); if (!$('[data-act=validate]').disabled) validate(false); }
    }
  });

  buildPad();
  renderTiles(); renderCart(); renderHeld(); updateNet();
  loadCatalog().then(sync);
  $('#q').focus();
})();
