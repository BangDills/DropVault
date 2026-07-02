// Alpine component for the dashboard.
// ponytail: no build step — plain script, served as-is from /assets.

function humanSize(bytes) {
  if (!bytes) return '0 B';
  const u = ['B','KB','MB','GB','TB'];
  let i = 0, n = bytes;
  while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
  return (i === 0 ? n : n.toFixed(1)) + ' ' + u[i];
}

function vault(initial) {
  return {
    theme: localStorage.getItem('cv-theme') || 'light',
    files: initial.files || [],
    folders: initial.folders || [],
    chain: initial.chain || [],
    folder: initial.folder || null,
    stats: initial.stats || { size: 0, count: 0 },
    dragging: false,
    uploads: [],
    newFolder: '',
    modal: false,
    modalFile: null,
    shareModal: false,
    shareFile: null,
    shareForm: { password: '', ttl_hours: '' },
    shareResult: null,
    copied: false,
    humanSize,

    init() {
      // Apply theme class to <html>.
      document.documentElement.classList.toggle('dark', this.theme === 'dark');
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
            this.stats.count++;
            this.stats.size += file.size;
            setTimeout(() => {
              this.uploads = this.uploads.filter(u => u.id !== id);
            }, 1500);
          } else {
            entry.status = 'gagal';
            entry.pct = 100;
            try {
              const err = JSON.parse(xhr.responseText);
              entry.status = err.error || 'gagal';
            } catch (_) {}
          }
        };
        xhr.onerror = () => { entry.status = 'gagal'; entry.pct = 100; };
        xhr.send(fd);
      });
    },

    createFolder() {
      if (!this.newFolder.trim()) return;
      fetch(window.VAULT_BASE + '/api/folder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: this.newFolder, parent: this.folder }),
      }).then(r => r.json()).then(res => {
        this.folders.push({ id: res.id, name: res.name, parent: res.parent });
        this.newFolder = '';
      });
    },

    deleteFile(f) {
      if (!confirm(`Hapus "${f.name}"?`)) return;
      fetch(window.VAULT_BASE + '/api/file/' + f.id, { method: 'DELETE' })
        .then(() => {
          this.files = this.files.filter(x => x.id !== f.id);
          this.stats.count--;
          this.stats.size -= f.size;
        });
    },

    preview(f) {
      this.modalFile = f;
      this.modal = true;
    },

    shareFile(f) {
      this.shareFile = f;
      this.shareResult = null;
      this.shareForm = { password: '', ttl_hours: '' };
      this.shareModal = true;
    },

    createShare() {
      fetch(window.VAULT_BASE + '/api/share', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          file_id: this.shareFile.id,
          password: this.shareForm.password || undefined,
          ttl_hours: this.shareForm.ttl_hours ? parseInt(this.shareForm.ttl_hours) : undefined,
        }),
      }).then(r => r.json()).then(res => {
        // Make absolute URL for sharing.
        const abs = new URL(res.url, window.location.origin).href;
        this.shareResult = { ...res, url: abs };
      });
    },

    async copy(text) {
      try {
        await navigator.clipboard.writeText(text);
        this.copied = true;
        setTimeout(() => this.copied = false, 1500);
      } catch (_) {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        this.copied = true;
        setTimeout(() => this.copied = false, 1500);
      }
    },
  };
}

// Base path for API calls — set by the dashboard via a script tag.
window.VAULT_BASE = window.VAULT_BASE || '';
