/* Shared utilities */
(function () {
  'use strict';

  const APP = window.APP || { baseUrl: '', apiUrl: '/api', csrfToken: '', role: null };

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }
  function $all(sel, root) {
    return Array.from((root || document).querySelectorAll(sel));
  }

  function toast(message, ms) {
    const el = $('#toast');
    if (!el) {
      alert(message);
      return;
    }
    el.textContent = message;
    el.hidden = false;
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.hidden = true; }, ms || 3200);
  }

  function withView(href) {
    if (!APP.role || !href) return href;
    try {
      const url = new URL(href, window.location.href);
      if (url.searchParams.get('action') === 'logout' || url.searchParams.get('action') === 'switch') {
        return href;
      }
      url.searchParams.set('view', APP.role);
      return url.toString();
    } catch (e) {
      return href;
    }
  }

  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a || !APP.role) return;
    const raw = a.getAttribute('href');
    if (!raw || raw.startsWith('#') || raw.startsWith('mailto:') || raw.startsWith('javascript:')) return;
    try {
      const url = new URL(a.href, window.location.href);
      if (url.origin !== window.location.origin) return;
      a.setAttribute('href', withView(url.href));
    } catch (err) { /* ignore */ }
  }, true);

  async function api(path, options) {
    options = options || {};
    const method = (options.method || 'GET').toUpperCase();
    const headers = Object.assign({}, options.headers || {});
    let body = options.body;

    if (APP.role) {
      headers['X-App-Role'] = APP.role;
    }

    if (!(body instanceof FormData)) {
      if (body && typeof body === 'object') {
        body = JSON.stringify(Object.assign({ csrf_token: APP.csrfToken }, body));
        headers['Content-Type'] = 'application/json';
      }
      headers['X-CSRF-Token'] = APP.csrfToken;
    } else {
      body.append('csrf_token', APP.csrfToken);
      headers['X-CSRF-Token'] = APP.csrfToken;
    }

    const url = path.startsWith('http') ? path : (APP.apiUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, ''));
    const res = await fetch(url, { method, headers, body, credentials: 'same-origin' });
    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error('Invalid server response.');
    }
    if (!res.ok || data.success === false) {
      if (res.status === 401 && data.error === 'Please sign in.') {
        window.location.href = (APP.baseUrl || '') + '/pages/login.php';
      }
      const err = new Error(data.error || 'Request failed.');
      err.data = data;
      err.status = res.status;
      throw err;
    }
    return data;
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatDate(d) {
    if (!d) return '';
    const dt = new Date(d + (String(d).length <= 10 ? 'T00:00:00' : ''));
    if (isNaN(dt.getTime())) return d;
    return dt.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' });
  }

  function truncate(str, n) {
    str = String(str || '');
    return str.length > n ? str.slice(0, n - 1) + '…' : str;
  }

  function openModal(html, opts) {
    opts = opts || {};
    const root = $('#modalRoot');
    root.innerHTML = `<div class="modal-backdrop" role="dialog" aria-modal="true">
      <div class="modal ${opts.large ? 'modal-lg' : ''}">${html}</div>
    </div>`;
    const backdrop = root.firstElementChild;
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop && opts.closeOnBackdrop !== false) closeModal();
    });
    $all('[data-close-modal]', root).forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        closeModal();
      });
    });
    // Escape key closes
    const onKey = (e) => {
      if (e.key === 'Escape') {
        closeModal();
        document.removeEventListener('keydown', onKey);
      }
    };
    document.addEventListener('keydown', onKey);
    root._escHandler = onKey;
    return root.querySelector('.modal');
  }

  function closeModal() {
    const root = $('#modalRoot');
    if (root && root._escHandler) {
      document.removeEventListener('keydown', root._escHandler);
      root._escHandler = null;
    }
    if (root) root.innerHTML = '';
  }

  /** Wire an avatar picker (#avatarFile + preview + clear). Returns getFile(). */
  function bindAvatarPicker(root) {
    root = root || document;
    const fileInput = $('#avatarFile', root);
    const preview = $('#avatarPreview', root);
    const clearBtn = $('#avatarClear', root);
    const chooseBtn = $('#avatarChoose', root);
    if (!fileInput || !preview) {
      return { getFile: () => null };
    }

    let objectUrl = null;
    const placeholder = preview.innerHTML;

    function setPreview(file) {
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = null;
      if (!file) {
        preview.innerHTML = placeholder;
        if (clearBtn) clearBtn.hidden = true;
        return;
      }
      objectUrl = URL.createObjectURL(file);
      preview.innerHTML = `<img src="${objectUrl}" alt="">`;
      if (clearBtn) clearBtn.hidden = false;
    }

    if (chooseBtn) {
      chooseBtn.addEventListener('click', () => fileInput.click());
    }
    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (file && !String(file.type || '').startsWith('image/')) {
        toast('Please choose an image file.');
        fileInput.value = '';
        setPreview(null);
        return;
      }
      setPreview(file || null);
    });
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        fileInput.value = '';
        setPreview(null);
      });
    }

    return {
      getFile: () => (fileInput.files && fileInput.files[0]) || null,
      clear: () => {
        fileInput.value = '';
        setPreview(null);
      }
    };
  }

  /** Wire a .file-drop zone with nested file input. */
  function bindFileDrop(dropEl) {
    if (!dropEl) return null;
    const input = dropEl.querySelector('input[type="file"]');
    const nameEl = dropEl.querySelector('.file-drop__name');
    if (!input) return null;

    function showName() {
      const file = input.files && input.files[0];
      if (nameEl) nameEl.textContent = file ? file.name : '';
    }

    input.addEventListener('change', showName);
    ['dragenter', 'dragover'].forEach((ev) => {
      dropEl.addEventListener(ev, (e) => {
        e.preventDefault();
        dropEl.classList.add('is-dragover');
      });
    });
    ['dragleave', 'drop'].forEach((ev) => {
      dropEl.addEventListener(ev, (e) => {
        e.preventDefault();
        dropEl.classList.remove('is-dragover');
      });
    });
    dropEl.addEventListener('drop', (e) => {
      const files = e.dataTransfer && e.dataTransfer.files;
      if (files && files.length) {
        input.files = files;
        showName();
      }
    });
    return input;
  }

  async function uploadPatientAvatar(patientId, file, description) {
    if (!file) return null;
    const fd = new FormData();
    fd.append('image', file);
    fd.append('patient_id', String(patientId));
    fd.append('description', description || 'Profile picture');
    fd.append('is_profile_picture', '1');
    return api('images.php?action=upload', { method: 'POST', body: fd });
  }

  function confirmDeletePhrase(opts) {
    return new Promise((resolve) => {
      openModal(`
        <div class="modal-header">
          <h2>${escapeHtml(opts.title || 'Confirm deletion')}</h2>
          <button type="button" class="btn btn-ghost btn-sm" data-close-modal>✕</button>
        </div>
        <div class="modal-body">
          <div class="warning-box">
            <strong>WARNING</strong>
            <div style="margin-top:0.5rem;white-space:pre-wrap">${escapeHtml(opts.warning || '')}</div>
          </div>
          <p>Type <strong>${escapeHtml(opts.phrase)}</strong> to confirm:</p>
          <div class="field">
            <input type="text" id="confirmPhraseInput" autocomplete="off" placeholder="${escapeHtml(opts.phrase)}">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
          <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>Delete permanently</button>
        </div>
      `);
      const input = $('#confirmPhraseInput');
      const btn = $('#confirmDeleteBtn');
      input.addEventListener('input', () => {
        btn.disabled = input.value !== opts.phrase;
      });
      btn.addEventListener('click', () => {
        closeModal();
        resolve(true);
      });
      const observer = new MutationObserver(() => {
        if (!$('#modalRoot').innerHTML) {
          observer.disconnect();
          resolve(false);
        }
      });
      observer.observe($('#modalRoot'), { childList: true });
      input.focus();
    });
  }

  /**
   * Duplicate-number selection modal.
   * Returns { none: true } or { selectedIds: number[] }
   */
  function duplicateNumberPicker(patients, opts) {
    opts = opts || {};
    return new Promise((resolve) => {
      const items = patients.map((p) => `
        <label class="duplicate-option">
          <input type="checkbox" name="dup_patient" value="${p.id}">
          <strong>${escapeHtml(p.name)}</strong><br>
          <span class="muted">Mother: ${escapeHtml(p.mother_name || '—')} · City: ${escapeHtml(p.city || '—')} · Country: ${escapeHtml(p.country || '—')} · Occupation: ${escapeHtml(p.occupation || '—')} · ${escapeHtml(p.number || '')}</span>
        </label>
      `).join('');

      openModal(`
        <div class="modal-header">
          <h2>${escapeHtml(opts.title || 'Multiple patients found with this number')}</h2>
          <button type="button" class="btn btn-ghost btn-sm" data-close-modal>✕</button>
        </div>
        <div class="modal-body">
          <p class="muted">${escapeHtml(opts.message || 'Select the patient(s) that apply, or choose None.')}</p>
          <div id="dupList">${items}</div>
          <label class="duplicate-option">
            <input type="checkbox" id="dupNone">
            <strong>None</strong>
            <div class="muted">Do not select any of the patients above.</div>
          </label>
          <p id="dupError" class="alert alert-error" hidden>Please select at least one patient or choose None.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
          <button type="button" class="btn" id="dupDone">Done</button>
        </div>
      `, { large: true });

      const none = $('#dupNone');
      none.addEventListener('change', () => {
        if (none.checked) {
          $all('input[name="dup_patient"]').forEach((c) => { c.checked = false; });
        }
      });
      $all('input[name="dup_patient"]').forEach((c) => {
        c.addEventListener('change', () => {
          if (c.checked) none.checked = false;
        });
      });

      $('#dupDone').addEventListener('click', () => {
        const selected = $all('input[name="dup_patient"]:checked').map((c) => parseInt(c.value, 10));
        const isNone = none.checked;
        if (!isNone && selected.length === 0) {
          const err = $('#dupError');
          err.hidden = false;
          return;
        }
        closeModal();
        resolve(isNone ? { none: true, selectedIds: [] } : { none: false, selectedIds: selected });
      });

      const observer = new MutationObserver(() => {
        if (!$('#modalRoot').innerHTML) {
          observer.disconnect();
          resolve(null);
        }
      });
      observer.observe($('#modalRoot'), { childList: true });
    });
  }

  // Sidebar toggle (mobile). Also closes on outside click and Escape.
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = $('#navToggle');
    const nav = $('#appNav');
    if (!toggle || !nav) return;
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      nav.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!nav.classList.contains('open')) return;
      if (nav.contains(e.target) || toggle.contains(e.target)) return;
      nav.classList.remove('open');
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') nav.classList.remove('open');
    });
  });

  function debounce(fn, wait) {
    let t = null;
    return function debounced(...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  const icons = {
    present: '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a5 5 0 0 1 5 5v1.1A7 7 0 0 1 19 15v2l2 2v1H3v-1l2-2v-2a7 7 0 0 1 2-4.9V7a5 5 0 0 1 5-5zm0 18a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3z"/></svg>',
    edit: '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>',
    trash: '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>',
    copy: '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16 1H4a2 2 0 0 0-2 2v12h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></svg>',
    eye: '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 5C7 5 2.73 8.11 1 12c1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 .001 6.001A3 3 0 0 0 12 9z"/></svg>'
  };

  const ImageCache = {
    urls: new Map(),
    async load(imageId, imgEl) {
      if (!imageId || !imgEl) return;
      const key = String(imageId);
      if (this.urls.has(key)) {
        imgEl.src = this.urls.get(key);
        imgEl.classList.add('img-ready');
        return;
      }
      imgEl.classList.add('img-loading');
      try {
        const url = (APP.apiUrl.replace(/\/$/, '') + '/image_proxy.php?id=' + encodeURIComponent(key));
        const res = await fetch(url, {
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': APP.csrfToken }
        });
        if (!res.ok) throw new Error('load failed');
        const blob = await res.blob();
        const obj = URL.createObjectURL(blob);
        this.urls.set(key, obj);
        imgEl.src = obj;
        imgEl.classList.remove('img-loading');
        imgEl.classList.add('img-ready');
      } catch (e) {
        imgEl.classList.remove('img-loading');
        imgEl.classList.add('img-error');
        imgEl.alt = 'Image unavailable';
      }
    },
    loadAll(root) {
      $all('img[data-image-id]', root || document).forEach((img) => {
        this.load(img.getAttribute('data-image-id'), img);
      });
    },
    clear() {
      this.urls.forEach((url) => {
        try { URL.revokeObjectURL(url); } catch (e) { /* ignore */ }
      });
      this.urls.clear();
    }
  };

  window.addEventListener('pagehide', () => ImageCache.clear());
  window.addEventListener('beforeunload', () => ImageCache.clear());

  async function copyText(text) {
    const value = String(text || '').trim();
    if (!value) throw new Error('Nothing to copy.');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(value);
      return;
    }
    const ta = document.createElement('textarea');
    ta.value = value;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
  }

  const LIST_PER_PAGE = 50;

  function readListContext(defaultFrom) {
    const p = new URLSearchParams(window.location.search);
    return {
      from: p.get('from') || defaultFrom || 'patients',
      page: Math.max(1, parseInt(p.get('page') || '1', 10)),
      q: p.get('q') || '',
      sort: p.get('sort') || 'last_activity',
      dir: p.get('dir') || 'DESC',
    };
  }

  function listContextQuery(ctx) {
    const q = new URLSearchParams();
    if (ctx.page > 1) q.set('page', String(ctx.page));
    if (ctx.q) q.set('q', ctx.q);
    if (ctx.from === 'patients' || ctx.from === 'advisor') {
      if (ctx.sort && ctx.sort !== 'last_activity') q.set('sort', ctx.sort);
      if (ctx.dir && ctx.dir !== 'DESC') q.set('dir', ctx.dir);
    }
    const s = q.toString();
    return s ? '?' + s : '';
  }

  function syncListUrl(basePath, ctx) {
    if (!window.history.replaceState) return;
    const url = (APP.baseUrl || '') + basePath + listContextQuery(ctx);
    window.history.replaceState({}, '', withView(url));
  }

  function listReturnUrl(ctx) {
    if (ctx.from === 'pending') {
      return withView((APP.baseUrl || '') + '/pages/pending.php' + listContextQuery(ctx));
    }
    if (ctx.from === 'advisor') {
      return withView((APP.baseUrl || '') + '/pages/advisor.php' + listContextQuery(ctx));
    }
    return withView((APP.baseUrl || '') + '/pages/patients.php' + listContextQuery(ctx));
  }

  function backLinkLabel(from) {
    if (from === 'pending') return '← Pending replies';
    if (from === 'advisor') return '← All patients';
    return '← Patients';
  }

  function patientDetailUrl(id, ctx) {
    const q = new URLSearchParams({ id: String(id), from: ctx.from });
    if (ctx.page > 1) q.set('page', String(ctx.page));
    if (ctx.q) q.set('q', ctx.q);
    if (ctx.from === 'patients' || ctx.from === 'advisor') {
      if (ctx.sort && ctx.sort !== 'last_activity') q.set('sort', ctx.sort);
      if (ctx.dir && ctx.dir !== 'DESC') q.set('dir', ctx.dir);
    }
    return withView((APP.baseUrl || '') + '/pages/patient.php?' + q.toString());
  }

  function advisorDetailUrl(id, ctx) {
    const q = new URLSearchParams({ patient: String(id), from: ctx.from });
    if (ctx.page > 1) q.set('page', String(ctx.page));
    if (ctx.q) q.set('q', ctx.q);
    return withView((APP.baseUrl || '') + '/pages/advisor.php?' + q.toString());
  }

  function readPatientNavContext() {
    const p = new URLSearchParams(window.location.search);
    const from = p.get('from') === 'pending' ? 'pending' : 'patients';
    return {
      from,
      context: from === 'pending' ? 'pending' : 'patients',
      page: Math.max(1, parseInt(p.get('page') || '1', 10)),
      q: p.get('q') || '',
      sort: p.get('sort') || 'last_activity',
      dir: p.get('dir') || 'DESC',
    };
  }

  async function fetchPatientNeighbors(id, ctx) {
    const params = new URLSearchParams({
      action: 'navigate',
      id: String(id),
      context: ctx.context,
      page: String(ctx.page),
      per_page: String(LIST_PER_PAGE),
      sort: ctx.sort,
      dir: ctx.dir,
    });
    if (ctx.q) params.set('q', ctx.q);
    const res = await api('patients.php?' + params.toString());
    return res.navigation || {};
  }

  function patientUrlWithPage(id, ctx, page) {
    return patientDetailUrl(id, Object.assign({}, ctx, { page: page || ctx.page }));
  }

  window.AppUtil = {
    $, $all, toast, api, escapeHtml, formatDate, truncate, openModal, closeModal,
    confirmDeletePhrase, duplicateNumberPicker, debounce, icons, ImageCache,
    bindAvatarPicker, bindFileDrop, uploadPatientAvatar, copyText, withView,
    LIST_PER_PAGE, readListContext, listContextQuery, syncListUrl, listReturnUrl,
    backLinkLabel, patientDetailUrl, advisorDetailUrl, readPatientNavContext,
    fetchPatientNeighbors, patientUrlWithPage
  };

  /**
   * Ameer Sahab: if the Editor presents a patient while this tab is on
   * Dashboard / Pending / Gallery / etc., jump to that patient.
   * The Patients (advisor) page handles this itself without a full reload.
   */
  function watchPresentToAmeer() {
    if (!APP.isAmeer) return;
    if (/(^|\/)advisor\.php$/i.test(window.location.pathname)) return;

    let primed = false;
    let lastNonce = null;

    async function poll() {
      try {
        const res = await api('active_patient.php?action=get');
        const state = res.state || {};
        const forcedId = state.active_patient_id ? Number(state.active_patient_id) : null;
        const nonce = Number(state.present_nonce || 0);

        if (!primed) {
          primed = true;
          lastNonce = nonce;
          return;
        }

        const isNewForce = forcedId && nonce !== lastNonce;
        lastNonce = nonce;

        if (isNewForce) {
          window.location.href = withView((APP.baseUrl || '') + '/pages/advisor.php?patient=' + forcedId + '&presented=1');
        }
      } catch (e) {
        // keep polling
      }
    }

    poll();
    setInterval(poll, 1500);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watchPresentToAmeer);
  } else {
    watchPresentToAmeer();
  }
})();
