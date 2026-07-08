// Alpine component for the dashboard.
// ponytail: no build step — plain script, served as-is from /assets.

function humanSize(bytes) {
  if (!bytes) return '0 B';
  const u = ['B','KB','MB','GB','TB'];
  let i = 0, n = bytes;
  while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
  return (i === 0 ? n : n.toFixed(1)) + ' ' + u[i];
}

// Design-only: render a Lucide outline icon by name as inline SVG.
// Mirrors app/helpers.php lucide() so server- and client-rendered icons match.
const LUCIDE = {
  file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/>',
  image: '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
  video: '<path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>',
  music: '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
  archive: '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
  note: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h8M8 17h5"/>',
  'square-pen': '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
};
function fileIconSvg(name, cls = 'w-10 h-10') {
  const body = LUCIDE[name] || LUCIDE.file;
  return '<svg class="' + cls + '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + body + '</svg>';
}

function vault(initial) {
  return {
    theme: localStorage.getItem('cv-theme') || 'light',
    files: initial.files || [],
    folders: initial.folders || [],
    chain: initial.chain || [],
    folder: initial.folder || null,
    stats: initial.stats || { size: 0, count: 0, trash: 0 },
    dragging: false,
    uploads: [],
    newFolder: '',
    folderModal: false,
    modal: false,
    modalFile: null,
    shareModal: false,
    activeShareFile: null,
    shareForm: { password: '', ttl_hours: '' },
    shareResult: null,
    passwordForm: { current: '', new: '', confirm: '' },
    selectedFiles: [],
    bulkMoveModal: false,
    bulkMoveFolderId: '',
    allFolders: [],
    activeTypeFilter: '',
    copied: false,
    notes: initial.notes || [],
    // iCloud-style two-pane notes editor state.
    noteSearch: '',
    activeNoteId: null,
    activeNote: null,
    noteSaveStatus: '',
    _noteSaveTimer: null,
    search: '',
    sortKey: 'date',
    versionModal: false,
    versionFile: null,
    versionList: [],
    toasts: [],
    sidebarOpen: false,
    currentView: initial.view || 'dashboard',
    shares: initial.shares || [],
    csrfToken: initial.csrfToken || '',
    humanSize,
    fileIconSvg,

    // Wrapper around fetch that injects the CSRF token header for state-changing
    // requests. GET must stay header-free (no CORS preflight, no token needed).
    apiFetch(url, opts = {}) {
      const method = (opts.method || 'GET').toUpperCase();
      if (method !== 'GET') {
        const headers = { ...(opts.headers || {}) };
        if (!headers['X-CSRF-Token'] && this.csrfToken) {
          headers['X-CSRF-Token'] = this.csrfToken;
        }
        opts.headers = headers;
      }
      return fetch(url, opts);
    },

    init() {
      document.documentElement.classList.toggle('dark', this.theme === 'dark');
      // Keyboard shortcuts (dashboard only; ignore when typing in a field).
      document.addEventListener('keydown', (e) => {
        const tag = (e.target?.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.metaKey || e.ctrlKey) return;
        if (e.key === '/') { e.preventDefault(); this.$refs.searchInput?.focus(); }
        else if (e.key.toLowerCase() === 'n') { e.preventDefault(); this.newNoteInline(); }
        else if (e.key.toLowerCase() === 'u') { e.preventDefault(); this.$refs.fileInput?.click(); }
      });
    },

    // --- Toasts ---
    toast(msg, isError = false) {
      const id = Math.random().toString(36).slice(2);
      this.toasts.push({ id, msg, isError });
      setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 2800);
    },

    // --- Search + sort (client-side, design/UX only; no backend change) ---
    get filteredFiles() {
      const q = this.search.trim().toLowerCase();
      let list = this.files;
      if (q) {
        list = list.filter(f => f.name.toLowerCase().includes(q));
      }
      if (this.activeTypeFilter) {
        list = list.filter(f => f.kind === this.activeTypeFilter);
      }
      const by = this.sortKey;
      return list.slice().sort((a, b) => {
        if (by === 'name') return a.name.localeCompare(b.name);
        if (by === 'size') return b.size - a.size;
        return String(b.created).localeCompare(String(a.created)); // date desc
      });
    },
    get noteListFiltered() {
      const q = this.noteSearch.trim().toLowerCase();
      return this.notes.filter(n => !q
        || (n.title || '').toLowerCase().includes(q)
        || (n.body || '').toLowerCase().includes(q));
    },

    toggleTheme() {
      this.theme = this.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.classList.toggle('dark', this.theme === 'dark');
      localStorage.setItem('cv-theme', this.theme);
    },

    onDrop(e) {
      this.dragging = false;
      const files = e.dataTransfer?.files;
      if (files?.length) this.uploadFiles(files);
    },

    onPaste(e) {
      const items = e.clipboardData?.items;
      if (!items) return;
      const files = [];
      for (const it of items) {
        if (it.kind === 'file') files.push(it.getAsFile());
      }
      if (files.length) this.uploadFiles(files);
    },

    uploadFiles(fileList) {
      [...fileList].forEach(file => {
        const id = Math.random().toString(36).slice(2);
        const entry = { id, name: file.name, pct: 0, status: 'mengunggah…' };
        this.uploads.push(entry);

        const xhr = new XMLHttpRequest();
        const fd = new FormData();
        fd.append('file', file);

        const url = window.VAULT_BASE + '/api/upload' + (this.folder != null ? '?folder=' + this.folder : '');
        xhr.open('POST', url);
        if (this.csrfToken) xhr.setRequestHeader('X-CSRF-Token', this.csrfToken);
        xhr.upload.onprogress = ev => {
          if (ev.lengthComputable) entry.pct = Math.round((ev.loaded / ev.total) * 90);
        };
        xhr.onload = () => {
          if (xhr.status >= 200 && xhr.status < 300) {
            const res = JSON.parse(xhr.responseText);
            entry.pct = 100;
            entry.status = 'selesai';
            const nf = res.files[0];
            if (nf) this.files.unshift(nf);
            // Prefer server-reported totals so a versioned (replace) upload
            // doesn't inflate count/size client-side.
            if (res.stats) this.stats.count = res.stats.count, this.stats.size = res.stats.size;
            else { this.stats.count++; this.stats.size += file.size; }
            this.toast(nf.versions > 0 ? 'Versi baru disimpan' : 'Upload selesai');
            setTimeout(() => {
              this.uploads = this.uploads.filter(u => u.id !== id);
            }, 1500);
          } else {
            entry.status = 'gagal';
            entry.pct = 100;
            try {
              const err = JSON.parse(xhr.responseText);
              entry.status = err.error || 'gagal';
              this.toast(err.error || 'Upload gagal', true);
            } catch (_) { this.toast('Upload gagal', true); }
          }
        };
        xhr.onerror = () => { entry.status = 'gagal'; entry.pct = 100; };
        xhr.send(fd);
      });
    },

    createFolderPrompt() {
      this.newFolder = '';
      this.folderModal = true;
      this.$nextTick(() => { this.$refs.folderNameInput?.focus(); });
    },

    createFolder() {
      if (!this.newFolder.trim()) return;
      this.apiFetch(window.VAULT_BASE + '/api/folder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: this.newFolder, parent: this.folder }),
      }).then(r => r.json()).then(res => {
        this.folders.push({ id: res.id, name: res.name, parent: res.parent });
        this.newFolder = '';
        this.folderModal = false;
        this.toast('Folder dibuat');
      });
    },

    deleteFile(f) {
      if (!confirm(`Hapus "${f.name}"?`)) return;
      this.apiFetch(window.VAULT_BASE + '/api/file/' + f.id, { method: 'DELETE' })
        .then(() => {
          this.files = this.files.filter(x => x.id !== f.id);
          this.stats.count--;
          this.stats.size -= f.size;
          this.toast('File dihapus');
        });
    },

    preview(f) {
      this.modalFile = f;
      this.modal = true;
    },

    shareFile(f) {
      this.activeShareFile = f;
      this.shareResult = null;
      this.shareForm = { password: '', ttl_hours: '' };
      this.shareModal = true;
    },

    createShare() {
      this.apiFetch(window.VAULT_BASE + '/api/share', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          file_id: this.activeShareFile.id,
          password: this.shareForm.password || undefined,
          ttl_hours: this.shareForm.ttl_hours ? parseInt(this.shareForm.ttl_hours) : undefined,
        }),
      }).then(r => r.json()).then(res => {
        // Make absolute URL for sharing.
        const abs = new URL(res.url, window.location.origin).href;
        this.shareResult = { ...res, url: abs };
      });
    },

    // --- File versioning ---

    openVersions(f) {
      this.versionFile = f;
      this.versionList = [];
      this.versionModal = true;
      fetch(window.VAULT_BASE + '/api/file/' + f.id + '/versions')
        .then(r => r.json())
        .then(res => { this.versionList = res.versions || []; });
    },

    restoreVersion(vid) {
      if (!this.versionFile) return;
      this.apiFetch(window.VAULT_BASE + '/api/file/' + this.versionFile.id + '/versions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ version_id: vid }),
      }).then(r => r.json()).then(updated => {
        if (updated.error) { alert(updated.error); return; }
        const idx = this.files.findIndex(x => x.id === updated.id);
        if (idx >= 0) this.files.splice(idx, 1, updated);
        this.versionFile = updated;
        this.openVersions(updated);
        this.toast('Versi dipulihkan');
      });
    },

    deleteVersion(vid) {
      if (!confirm('Hapus versi lama ini?')) return;
      this.apiFetch(window.VAULT_BASE + '/api/version/' + vid, { method: 'DELETE' })
        .then(() => { this.versionList = this.versionList.filter(v => v.id !== vid); });
    },

    // --- Drag file -> folder move ---

    onFileDragStart(f, e) {
      e.dataTransfer.setData('text/plain', String(f.id));
      e.dataTransfer.effectAllowed = 'move';
    },
    onFolderDrop(folder, e) {
      e.preventDefault();
      const id = parseInt(e.dataTransfer.getData('text/plain'), 10);
      if (!id) return;
      this.apiFetch(window.VAULT_BASE + '/api/file/' + id, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ folder: folder ? folder.id : null }),
      }).then(r => r.json()).then(res => {
        if (res.error) { this.toast(res.error, true); return; }
        this.files = this.files.filter(x => x.id !== id);
        this.toast('Dipindahkan ke ' + (folder ? folder.name : 'Root'));
      });
    },

    // --- Notes ---

    // --- Notes (iCloud two-pane) ---
    // Load a note into the editor pane.
    selectNote(id) {
      const n = this.notes.find(x => x.id === id);
      if (!n) return;
      // Drop the synthetic "Catatan tanpa judul" display label so editing is clean.
      this.activeNoteId = id;
      this.activeNote = {
        id: n.id,
        title: n.title === 'Catatan tanpa judul' ? '' : n.title,
        body: n.body,
      };
      this.noteSaveStatus = '';
    },

    // Create a new note and focus the editor. Saves on first input.
    newNoteInline() {
      this.activeNoteId = null;
      this.activeNote = { id: null, title: '', body: '' };
      this.noteSaveStatus = '';
      this.$nextTick(() => {
        document.querySelector('.cv-notes-title-input')?.focus();
      });
    },

    // Debounced autosave — writes after typing pauses for 600ms.
    scheduleNoteSave() {
      if (!this.activeNote) return;
      clearTimeout(this._noteSaveTimer);
      this.noteSaveStatus = 'Menyimpan…';
      this._noteSaveTimer = setTimeout(() => this.saveActiveNote(), 600);
    },

    saveActiveNote() {
      if (!this.activeNote) return;
      const n = this.activeNote;
      const base = window.VAULT_BASE + '/api/note' + (this.folder != null ? '?folder=' + this.folder : '');
      const method = n.id ? 'PUT' : 'POST';
      const url = n.id ? window.VAULT_BASE + '/api/note/' + n.id : base;
      this.apiFetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: n.title, body: n.body, folder: this.folder }),
      }).then(r => r.json()).then(saved => {
        if (saved.error) { this.noteSaveStatus = 'Gagal menyimpan'; return; }
        const idx = this.notes.findIndex(x => x.id === saved.id);
        if (idx >= 0) this.notes.splice(idx, 1, saved);
        else this.notes.unshift(saved);
        this.activeNoteId = saved.id;
        this.activeNote = {
          id: saved.id,
          title: saved.title === 'Catatan tanpa judul' ? '' : saved.title,
          body: saved.body,
        };
        this.noteSaveStatus = 'Tersimpan';
        setTimeout(() => { if (this.noteSaveStatus === 'Tersimpan') this.noteSaveStatus = ''; }, 1500);
      });
    },

    deleteActiveNote() {
      if (!this.activeNote || !this.activeNote.id) {
        // Unsaved empty note — just clear the editor.
        this.activeNote = null;
        this.activeNoteId = null;
        return;
      }
      if (!confirm('Hapus catatan ini?')) return;
      this.apiFetch(window.VAULT_BASE + '/api/note/' + this.activeNote.id, { method: 'DELETE' })
        .then(() => {
          this.notes = this.notes.filter(x => x.id !== this.activeNote.id);
          this.activeNote = null;
          this.activeNoteId = null;
          this.toast('Catatan dihapus');
        });
    },

    // First line of body, stripped — used as a list snippet.
    noteSnippet(n) {
      const body = (n.body || '').trim();
      const firstLine = body.split('\n').find(l => l.trim() !== '') || '';
      return firstLine.slice(0, 60) || (n.updated ? n.updated.slice(0, 10) : '');
    },

    async copy(text) {
      try {
        await navigator.clipboard.writeText(text);
      } catch (_) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
      }
      this.copied = true;
      this.toast('Tersalin');
      setTimeout(() => this.copied = false, 1500);
    },

    // --- Favorites ---
    toggleFavorite(f) {
      this.apiFetch(window.VAULT_BASE + '/api/favorite/' + f.id, { method: 'POST' })
        .then(r => r.json())
        .then(res => {
          f.favorited = res.favorited;
          this.toast(res.favorited ? 'Added to favorites' : 'Removed from favorites');
        });
    },

    // --- Trash ---
    restoreFromTrash(f) {
      this.apiFetch(window.VAULT_BASE + '/api/trash/' + f.id + '/restore', { method: 'POST' })
        .then(r => r.json())
        .then(() => {
          this.files = this.files.filter(x => x.id !== f.id);
          this.stats.trash--;
          this.toast('File restored');
        });
    },

    permanentDelete(f) {
      if (!confirm(`Permanently delete "${f.name}"? This cannot be undone.`)) return;
      this.apiFetch(window.VAULT_BASE + '/api/trash/' + f.id, { method: 'DELETE' })
        .then(() => {
          this.files = this.files.filter(x => x.id !== f.id);
          this.stats.trash--;
          this.toast('Permanently deleted');
        });
    },

    emptyTrash() {
      if (!confirm('Permanently delete all files in trash?')) return;
      this.apiFetch(window.VAULT_BASE + '/api/trash', { method: 'DELETE' })
        .then(() => {
          this.files = [];
          this.stats.trash = 0;
          this.toast('Trash emptied');
        });
    },

    // --- Shared links ---
    deleteShareById(id) {
      if (!confirm('Delete this share link?')) return;
      this.apiFetch(window.VAULT_BASE + '/api/share/' + id, { method: 'DELETE' })
        .then(() => {
          this.shares = this.shares.filter(s => s.id !== id);
          this.toast('Share link deleted');
        });
    },

    changePassword() {
      if (this.passwordForm.new !== this.passwordForm.confirm) {
        this.toast('Password baru dan konfirmasi tidak cocok', true);
        return;
      }
      this.apiFetch(window.VAULT_BASE + '/api/password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          current_password: this.passwordForm.current,
          new_password: this.passwordForm.new
        }),
      })
      .then(async r => {
        const data = await r.json();
        if (!r.ok) {
          throw new Error(data.error || 'Gagal mengubah password');
        }
        return data;
      })
      .then(() => {
        this.toast('Password berhasil diperbarui');
        this.passwordForm.current = '';
        this.passwordForm.new = '';
        this.passwordForm.confirm = '';
      })
      .catch(err => {
        this.toast(err.message, true);
      });
    },

    // --- Bulk selection & actions ---
    toggleSelectFile(id) {
      if (this.selectedFiles.includes(id)) {
        this.selectedFiles = this.selectedFiles.filter(x => x !== id);
      } else {
        this.selectedFiles.push(id);
      }
    },
    deselectAll() {
      this.selectedFiles = [];
    },
    isAllSelected() {
      if (!this.filteredFiles.length) return false;
      const visibleIds = this.filteredFiles.map(f => f.id);
      return visibleIds.every(id => this.selectedFiles.includes(id));
    },
    toggleSelectAll() {
      const visibleIds = this.filteredFiles.map(f => f.id);
      if (this.isAllSelected()) {
        this.selectedFiles = this.selectedFiles.filter(id => !visibleIds.includes(id));
      } else {
        visibleIds.forEach(id => {
          if (!this.selectedFiles.includes(id)) {
            this.selectedFiles.push(id);
          }
        });
      }
    },
    bulkAction(action, extra = {}) {
      if (!this.selectedFiles.length) return;
      this.apiFetch(window.VAULT_BASE + '/api/bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ids: this.selectedFiles, ...extra }),
      })
      .then(r => r.json())
      .then(res => {
        if (res.error) {
          this.toast(res.error, true);
          return;
        }
        
        if (action === 'trash') {
          this.files = this.files.filter(f => !this.selectedFiles.includes(f.id));
          this.stats.trash += this.selectedFiles.length;
          this.toast(this.selectedFiles.length + ' file dipindahkan ke Trash');
        } else if (action === 'restore') {
          this.files = this.files.filter(f => !this.selectedFiles.includes(f.id));
          this.stats.trash = Math.max(0, this.stats.trash - this.selectedFiles.length);
          this.toast(this.selectedFiles.length + ' file dikembalikan');
        } else if (action === 'delete') {
          this.files = this.files.filter(f => !this.selectedFiles.includes(f.id));
          this.toast(this.selectedFiles.length + ' file dihapus permanen');
        } else if (action === 'favorite') {
          this.files.forEach(f => {
            if (this.selectedFiles.includes(f.id)) f.favorited = true;
          });
          this.toast(this.selectedFiles.length + ' file ditambahkan ke favorit');
        } else if (action === 'unfavorite') {
          this.files.forEach(f => {
            if (this.selectedFiles.includes(f.id)) f.favorited = false;
          });
          // In the Favorites view, unfavorited items no longer belong here.
          if (this.currentView === 'favorites') {
            this.files = this.files.filter(f => !this.selectedFiles.includes(f.id));
          }
          this.toast(this.selectedFiles.length + ' file dihapus dari favorit');
        } else if (action === 'move') {
          const folderName = extra.folder ? (this.allFolders.find(f => f.id === parseInt(extra.folder))?.name || 'Folder') : 'Root';
          this.files = this.files.filter(f => !this.selectedFiles.includes(f.id));
          this.toast(this.selectedFiles.length + ' file dipindahkan ke ' + folderName);
          this.bulkMoveModal = false;
        }
        
        this.deselectAll();
      })
      .catch(err => {
        this.toast('Gagal melakukan aksi bulk: ' + err.message, true);
      });
    },
    openBulkMoveModal() {
      fetch(window.VAULT_BASE + '/api/folders')
        .then(r => r.json())
        .then(res => {
          this.allFolders = res.folders || [];
          this.bulkMoveFolderId = '';
          this.bulkMoveModal = true;
        });
    },
    bulkToggleFavorite() {
      const selectedObj = this.files.filter(f => this.selectedFiles.includes(f.id));
      const hasUnfavorited = selectedObj.some(f => !f.favorited);
      this.bulkAction(hasUnfavorited ? 'favorite' : 'unfavorite');
    },
  };
}

// Base path for API calls — set by the dashboard via a script tag.
window.VAULT_BASE = window.VAULT_BASE || '';
