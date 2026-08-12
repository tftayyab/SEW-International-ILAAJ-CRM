(function () {
  const { $, $all, api, toast, escapeHtml, openModal, closeModal, ImageCache, icons, bindFileDrop } = AppUtil;
  const root = $('#galleryRoot');
  const patientId = parseInt(root.dataset.patientId, 10);
  const canEdit = root.dataset.canEdit === '1';

  async function load() {
    ImageCache.clear();
    const res = await api('images.php?action=list&patient_id=' + patientId);
    if (!res.images.length) {
      root.innerHTML = '<div class="empty-state">No photos yet.' +
        (canEdit ? ' Click <strong>+ Upload photo</strong> to add one.' : '') +
        '</div>';
      return;
    }
    root.innerHTML = `<div class="gallery-page-grid">${res.images.map((img) => `
      <div class="gallery-item">
        <img data-image-id="${img.id}" alt="" class="img-loading">
        <div class="cap">
          ${img.is_profile_picture == 1 ? '<span class="badge">Profile</span> ' : ''}
          ${escapeHtml(img.description || 'No description')}
          ${canEdit ? `<div class="icon-actions" style="margin-top:0.55rem">
            <button type="button" class="icon-btn" data-edit="${img.id}" title="Edit">${icons.edit}</button>
            <button type="button" class="icon-btn icon-danger" data-del="${img.id}" title="Delete">${icons.trash}</button>
          </div>` : ''}
        </div>
      </div>`).join('')}</div>`;

    ImageCache.loadAll(root);

    if (!canEdit) return;
    $all('[data-edit]', root).forEach((btn) => {
      const img = res.images.find((x) => String(x.id) === btn.dataset.edit);
      btn.addEventListener('click', () => editImage(img));
    });
    $all('[data-del]', root).forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Delete this photo from the CRM and Google Drive?')) return;
        try {
          await api('images.php?action=delete', { method: 'POST', body: { id: btn.dataset.del } });
          toast('Photo deleted.');
          await load();
        } catch (e) { toast(e.message); }
      });
    });
  }

  function editImage(img) {
    openModal(`
      <div class="modal-header">
        <div>
          <h2>Edit photo</h2>
          <p class="modal-sub">Update the caption or set this as the profile picture.</p>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <form id="gEditForm" class="form-grid">
          <div class="field full"><label>Description</label>
            <input type="text" name="description" value="${escapeHtml(img.description || '')}" placeholder="Optional caption">
          </div>
          <label class="check-row field full">
            <input type="checkbox" name="is_profile_picture" ${img.is_profile_picture == 1 ? 'checked' : ''}>
            <span class="check-row__text">
              <strong>Use as profile picture</strong>
              <span>Shown on the patient page and advisor cards.</span>
            </span>
          </label>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="gSaveEdit">Save</button>
      </div>`);
    $('#gSaveEdit').addEventListener('click', async () => {
      const form = $('#gEditForm');
      try {
        await api('images.php?action=update', {
          method: 'POST',
          body: {
            id: img.id,
            image_url: img.image_url,
            drive_file_id: img.drive_file_id || '',
            description: form.querySelector('[name="description"]').value,
            is_profile_picture: form.querySelector('[name="is_profile_picture"]').checked ? 1 : 0
          }
        });
        closeModal();
        toast('Saved.');
        await load();
      } catch (e) { toast(e.message); }
    });
  }

  function uploadImage() {
    openModal(`
      <div class="modal-header">
        <div>
          <h2>Upload photo</h2>
          <p class="modal-sub">Photos are stored on Google Drive and linked here.</p>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" data-close-modal aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <form id="gUpForm" class="form-grid">
          <div class="field full">
            <div class="file-drop" id="gFileDrop">
              <div class="file-drop__title">Drop a photo here</div>
              <div class="file-drop__hint">or click to browse · JPG, PNG, GIF, WebP</div>
              <div class="file-drop__name"></div>
              <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required>
            </div>
          </div>
          <div class="field full"><label>Description</label>
            <input type="text" name="description" placeholder="Optional caption">
          </div>
          <label class="check-row field full">
            <input type="checkbox" name="is_profile_picture">
            <span class="check-row__text">
              <strong>Use as profile picture</strong>
              <span>Shown on the patient page and advisor cards.</span>
            </span>
          </label>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
        <button type="button" class="btn" id="gDoUpload">Upload</button>
      </div>`);
    bindFileDrop($('#gFileDrop'));
    $('#gDoUpload').addEventListener('click', async () => {
      const form = $('#gUpForm');
      const file = form.querySelector('[name="image"]').files[0];
      if (!file) { toast('Choose a photo.'); return; }
      const fd = new FormData();
      fd.append('image', file);
      fd.append('patient_id', String(patientId));
      fd.append('description', form.querySelector('[name="description"]').value || '');
      fd.append('is_profile_picture', form.querySelector('[name="is_profile_picture"]').checked ? '1' : '0');
      const btn = $('#gDoUpload');
      try {
        btn.disabled = true;
        btn.textContent = 'Uploading…';
        await api('images.php?action=upload', { method: 'POST', body: fd });
        closeModal();
        toast('Uploaded.');
        await load();
      } catch (e) {
        toast(e.message);
        btn.disabled = false;
        btn.textContent = 'Upload';
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const up = $('#btnUploadGallery');
    if (up) up.addEventListener('click', uploadImage);
    if (canEdit) {
      api('images.php?action=status').then((res) => {
        const el = $('#driveStatus');
        if (!el) return;
        if (!res.drive.configured) {
          el.hidden = false;
          el.innerHTML = 'Google Drive is not connected. Open <a href="' + APP.baseUrl + '/pages/drive_setup.php">Drive Setup</a>.';
        }
      }).catch(() => {});
    }
    load().catch((e) => {
      root.innerHTML = '<div class="empty-state">' + escapeHtml(e.message) + '</div>';
    });
  });
})();
