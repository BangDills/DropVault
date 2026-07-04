<?php
/** @var string $appName, $view
 *  @var array $files, $folders, $chain, $notes, $stats, $favIds
 *  @var ?int $folderId
 *  @var ?array $shares */
ob_start();
$fIds = $favIds ?? [];
$vm = array_map(fn($f) => file_view_model($f, $fIds), $files);
$shareVm = [];
if (!empty($shares)) {
    foreach ($shares as $s) {
        $shareVm[] = [
            'id' => (int)$s['id'], 'token' => $s['token'],
            'file_name' => $s['file_name'] ?? '?', 'file_id' => (int)$s['file_id'],
            'has_password' => $s['password'] !== null,
            'expires_at' => $s['expires_at'], 'hits' => (int)$s['hits'],
            'created' => $s['created_at'],
            'url' => url('/s/' . $s['token']),
        ];
    }
}
$v = $view ?? 'dashboard';
?>
<script>window.VAULT_BASE = <?= json_encode(url('')) ?>;</script>
<div x-data="vault(<?= e(json_encode(['files' => $vm, 'folders' => $folders, 'notes' => $notes, 'chain' => $chain, 'folder' => $folderId, 'stats' => $stats, 'view' => $v, 'shares' => $shareVm], JSON_UNESCAPED_UNICODE)) ?>)"
     @drop.prevent="onDrop" @dragover.prevent @paste.window="onPaste"
     class="cv-app-shell">

  <!-- ═══════════ SIDEBAR ═══════════ -->
  <aside class="cv-sidebar" :class="sidebarOpen && 'is-open'">
    <div class="cv-sidebar-inner">
      <div class="cv-brand">
        <div class="cv-brand-icon"><?= lucide('cloud', 'w-6 h-6') ?></div>
        <span class="cv-brand-text"><?= e($appName) ?></span>
      </div>
      <nav class="cv-nav">
        <a href="<?= e(url('/')) ?>" class="cv-nav-item <?= $v === 'dashboard' ? 'is-active' : '' ?>"><?= lucide('layout-dashboard', 'w-[18px] h-[18px]') ?><span>Dashboard</span></a>
        <a href="<?= e(url('/?view=files')) ?>" class="cv-nav-item <?= $v === 'files' ? 'is-active' : '' ?>"><?= lucide('folder', 'w-[18px] h-[18px]') ?><span>My Files</span></a>
        <a href="<?= e(url('/?view=recent')) ?>" class="cv-nav-item <?= $v === 'recent' ? 'is-active' : '' ?>"><?= lucide('clock', 'w-[18px] h-[18px]') ?><span>Recent</span></a>
        <a href="<?= e(url('/?view=shared')) ?>" class="cv-nav-item <?= $v === 'shared' ? 'is-active' : '' ?>"><?= lucide('users', 'w-[18px] h-[18px]') ?><span>Shared</span></a>
        <a href="<?= e(url('/?view=favorites')) ?>" class="cv-nav-item <?= $v === 'favorites' ? 'is-active' : '' ?>"><?= lucide('star', 'w-[18px] h-[18px]') ?><span>Favorites</span></a>
        <a href="<?= e(url('/?view=trash')) ?>" class="cv-nav-item <?= $v === 'trash' ? 'is-active' : '' ?>"><?= lucide('trash', 'w-[18px] h-[18px]') ?><span>Trash</span><?php if (($stats['trash'] ?? 0) > 0): ?><span class="cv-nav-badge"><?= $stats['trash'] ?></span><?php endif; ?></a>
      </nav>
      <div class="cv-nav-bottom">
        <a href="<?= e(url('/?view=settings')) ?>" class="cv-nav-item <?= $v === 'settings' ? 'is-active' : '' ?>"><?= lucide('settings', 'w-[18px] h-[18px]') ?><span>Settings</span></a>
        <a href="<?= e(url('/logout')) ?>" class="cv-nav-item"><?= lucide('logout', 'w-[18px] h-[18px]') ?><span>Logout</span></a>
      </div>
    </div>
  </aside>
  <div class="cv-sidebar-overlay" :class="sidebarOpen && 'is-open'" @click="sidebarOpen=false"></div>

  <!-- ═══════════ MAIN ═══════════ -->
  <div class="cv-main">
    <header class="cv-topbar">
      <button @click="sidebarOpen=!sidebarOpen" class="cv-menu-btn lg:hidden"><?= lucide('menu', 'w-5 h-5') ?></button>
      <div class="cv-search-wrap">
        <span class="cv-search-icon"><?= lucide('search', 'w-[18px] h-[18px]') ?></span>
        <input x-ref="searchInput" x-model="search" type="text" placeholder="Search files and folders..." class="cv-search-input" id="search-input">
      </div>
      <div class="cv-topbar-actions">
        <button @click="toggleTheme()" class="cv-topbar-btn hidden sm:flex"><span x-show="theme==='dark'"><?= lucide('sun', 'w-[18px] h-[18px]') ?></span><span x-show="theme==='light'"><?= lucide('moon', 'w-[18px] h-[18px]') ?></span></button>
        <a href="<?= e(url('/logout')) ?>" class="cv-topbar-btn" title="Logout"><?= lucide('logout', 'w-[18px] h-[18px]') ?></a>
      </div>
    </header>

    <div class="cv-content">

<?php if ($v === 'dashboard'): ?>
<!-- ══ DASHBOARD ══ -->
<nav x-show="chain.length" class="cv-breadcrumb" x-cloak>
  <a href="<?= e(url('/?view=files')) ?>" class="cv-bc-link"><?= lucide('home', 'w-4 h-4') ?><span>Root</span></a>
  <template x-for="c in chain" :key="c.id"><span class="flex items-center gap-1"><?= lucide('chevron-right', 'w-4 h-4 text-cv-faint') ?><a :href="`<?= e(url('/?view=files&folder=')) ?>${c.id}`" class="cv-bc-link" x-text="c.name"></a></span></template>
</nav>
<div class="cv-grid-main">
  <div class="cv-col-left">
    <section class="cv-section">
      <h2 class="cv-section-title">Storage Actions</h2>
      <div class="cv-actions-grid">
        <button @click="createFolderPrompt()" class="cv-action-card"><div class="cv-action-icon"><?= lucide('folder', 'w-6 h-6') ?></div><span class="cv-action-label">New Folder</span><span class="cv-action-sub">Create folder</span></button>
        <button @click="$refs.fileInput.click()" class="cv-action-card"><div class="cv-action-icon cv-action-icon--accent"><?= lucide('upload', 'w-6 h-6') ?></div><span class="cv-action-label">Upload Files</span><span class="cv-action-sub">From device</span></button>
        <button @click="shareFirstFile()" class="cv-action-card"><div class="cv-action-icon"><?= lucide('link', 'w-6 h-6') ?></div><span class="cv-action-label">Share File</span><span class="cv-action-sub">Get link</span></button>
        <button @click="openNote(null)" class="cv-action-card"><div class="cv-action-icon"><?= lucide('file-plus', 'w-6 h-6') ?></div><span class="cv-action-label">Create Note</span><span class="cv-action-sub">Quick note</span></button>
      </div>
      <input type="file" x-ref="fileInput" multiple class="hidden" @change="uploadFiles($event.target.files)">
    </section>
    <?php include __DIR__ . '/_upload_progress.php'; ?>
    <?php include __DIR__ . '/_file_list.php'; ?>
    <?php include __DIR__ . '/_notes_section.php'; ?>
    <section class="cv-section"><h2 class="cv-section-title">Activity Feed</h2><div class="cv-card">
      <div class="cv-activity-item"><div class="cv-activity-icon bg-blue-50 text-blue-500 dark:bg-blue-500/10"><?= lucide('upload', 'w-4 h-4') ?></div><div class="cv-activity-text"><p><span x-text="stats.count"></span> files stored</p><span>Total <span x-text="humanSize(stats.size)"></span></span></div><span class="cv-activity-time">Now</span></div>
      <div class="cv-activity-item"><div class="cv-activity-icon bg-green-50 text-green-500 dark:bg-green-500/10"><?= lucide('folder', 'w-4 h-4') ?></div><div class="cv-activity-text"><p><span x-text="folders.length"></span> folders</p><span>Current directory</span></div><span class="cv-activity-time">Current</span></div>
    </div></section>
  </div>
  <div class="cv-col-right">
    <section class="cv-section"><h2 class="cv-section-title">Quick Upload</h2>
      <div class="cv-card cv-upload-zone" :class="dragging ? 'cv-upload-active' : ''" @dragenter.prevent="dragging=true" @dragleave.prevent="dragging=false" @click="$refs.fileInput.click()">
        <div class="cv-upload-inner"><div class="cv-upload-icon" :class="dragging && 'animate-bounce'"><?= lucide('upload', 'w-7 h-7') ?></div><p class="cv-upload-text" x-text="dragging ? 'Drop files here' : 'Drag & drop files here'"></p><span class="cv-upload-or">or</span><span class="cv-upload-browse">Browse Files</span></div>
      </div>
    </section>
    <section class="cv-section"><div class="cv-section-header"><h2 class="cv-section-title">Folders</h2><a href="<?= e(url('/?view=files')) ?>" class="cv-view-all">View all</a></div>
      <div class="cv-folders-grid">
        <template x-for="f in folders" :key="f.id"><a :href="`<?= e(url('/?view=files&folder=')) ?>${f.id}`" @dragover.prevent @drop="onFolderDrop(f, $event)" class="cv-folder-card group"><div class="cv-folder-icon"><?= lucide('folder', 'w-8 h-8') ?></div><span class="cv-folder-name" x-text="f.name"></span></a></template>
        <button @click="createFolderPrompt()" class="cv-folder-card cv-folder-new"><div class="cv-folder-icon cv-folder-icon--add"><?= lucide('plus', 'w-6 h-6') ?></div><span class="cv-folder-name text-cv-faint">New Folder</span></button>
      </div>
    </section>
    <section class="cv-section"><div class="cv-section-header"><h2 class="cv-section-title">Server</h2><span class="cv-status-badge"><span class="cv-status-dot"></span> Online</span></div>
      <div class="cv-card p-4"><div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-cv-bg border border-cv-border flex items-center justify-center text-cv-muted"><?= lucide('monitor', 'w-5 h-5') ?></div><div class="min-w-0"><p class="text-sm font-semibold">This Device <span class="cv-badge-current">Current</span></p><p class="text-xs text-cv-muted"><span x-text="stats.count"></span> files · <span x-text="humanSize(stats.size)"></span></p></div></div></div>
    </section>
  </div>
</div>

<?php elseif ($v === 'files'): ?>
<!-- ══ MY FILES ══ -->
<nav class="cv-breadcrumb">
  <a href="<?= e(url('/?view=files')) ?>" class="cv-bc-link"><?= lucide('home', 'w-4 h-4') ?><span>Root</span></a>
  <template x-for="c in chain" :key="c.id"><span class="flex items-center gap-1"><?= lucide('chevron-right', 'w-4 h-4 text-cv-faint') ?><a :href="`<?= e(url('/?view=files&folder=')) ?>${c.id}`" class="cv-bc-link" x-text="c.name"></a></span></template>
</nav>
<div class="cv-section-header mb-4"><h2 class="cv-section-title" style="margin-bottom:0">My Files</h2>
  <div class="flex gap-2"><button @click="$refs.fileInput.click()" class="cv-btn-primary"><?= lucide('upload', 'w-4 h-4') ?> Upload</button><button @click="createFolderPrompt()" class="cv-btn-secondary"><?= lucide('plus', 'w-4 h-4') ?> Folder</button></div>
</div>
<input type="file" x-ref="fileInput" multiple class="hidden" @change="uploadFiles($event.target.files)">
<?php include __DIR__ . '/_upload_progress.php'; ?>
<div x-show="folders.length" class="mb-6">
  <div class="cv-section-header mb-3"><h3 class="text-sm font-semibold text-cv-muted">Folders</h3></div>
  <div class="cv-folders-grid">
    <template x-for="f in folders" :key="f.id"><a :href="`<?= e(url('/?view=files&folder=')) ?>${f.id}`" @dragover.prevent @drop="onFolderDrop(f, $event)" class="cv-folder-card group"><div class="cv-folder-icon"><?= lucide('folder', 'w-8 h-8') ?></div><span class="cv-folder-name" x-text="f.name"></span></a></template>
  </div>
</div>
<?php $fileListTitle = 'Files'; include __DIR__ . '/_file_list.php'; ?>
<?php include __DIR__ . '/_notes_section.php'; ?>

<?php elseif ($v === 'recent'): ?>
<!-- ══ RECENT ══ -->
<?php $fileListTitle = 'Recent Files'; include __DIR__ . '/_file_list.php'; ?>

<?php elseif ($v === 'shared'): ?>
<!-- ══ SHARED ══ -->
<h2 class="cv-section-title">Shared Links</h2>
<div class="cv-card" x-show="shares.length">
  <template x-for="s in shares" :key="s.id">
    <div class="cv-file-row">
      <div class="cv-file-icon cv-ficon-file"><?= lucide('link', 'w-5 h-5') ?></div>
      <div class="cv-file-info"><span class="cv-file-name" x-text="s.file_name"></span><span class="cv-file-cat"><span x-text="s.hits"></span> hits<template x-if="s.has_password"> · 🔒</template><template x-if="s.expires_at"> · exp <span x-text="s.expires_at?.slice(0,16)"></span></template></span></div>
      <span class="cv-file-date" x-text="s.created ? s.created.slice(0,16).replace('T',' ') : ''"></span>
      <div class="cv-file-actions flex gap-1" @click.stop>
        <button @click="copy(location.origin + s.url)" class="cv-dot-btn" title="Copy link"><?= lucide('link', 'w-4 h-4') ?></button>
        <button @click="deleteShareById(s.id)" class="cv-dot-btn" title="Delete"><?= lucide('trash', 'w-4 h-4') ?></button>
      </div>
    </div>
  </template>
</div>
<div x-show="!shares.length" class="cv-card px-4 py-10 text-center text-cv-muted text-sm">No shared links yet.</div>

<?php elseif ($v === 'favorites'): ?>
<!-- ══ FAVORITES ══ -->
<?php $fileListTitle = 'Favorites'; include __DIR__ . '/_file_list.php'; ?>

<?php elseif ($v === 'trash'): ?>
<!-- ══ TRASH ══ -->
<div class="cv-section-header mb-4"><h2 class="cv-section-title" style="margin-bottom:0">Trash</h2>
  <button x-show="filteredFiles.length" @click="emptyTrash()" class="cv-btn-secondary text-red-500" style="border-color:#fca5a5"><?= lucide('trash', 'w-4 h-4') ?> Empty Trash</button>
</div>
<div class="cv-card" x-show="filteredFiles.length">
  <template x-for="f in filteredFiles" :key="f.id">
    <div class="cv-file-row">
      <div class="cv-file-icon" :class="'cv-ficon-' + f.kind" x-html="fileIconSvg(f.icon, 'w-5 h-5')"></div>
      <div class="cv-file-info"><span class="cv-file-name" x-text="f.name"></span><span class="cv-file-cat" x-text="'Deleted ' + (f.deleted ? f.deleted.slice(0,16).replace('T',' ') : '')"></span></div>
      <span class="cv-file-size" x-text="f.size_h"></span>
      <div class="cv-file-actions flex gap-1" @click.stop>
        <button @click="restoreFromTrash(f)" class="cv-btn-primary text-xs h-7 px-2.5">Restore</button>
        <button @click="permanentDelete(f)" class="cv-dot-btn text-red-500"><?= lucide('trash', 'w-4 h-4') ?></button>
      </div>
    </div>
  </template>
</div>
<div x-show="!filteredFiles.length" class="cv-card px-4 py-10 text-center text-cv-muted text-sm">Trash is empty.</div>

<?php elseif ($v === 'settings'): ?>
<!-- ══ SETTINGS ══ -->
<div class="max-w-md">
  <div class="cv-section-header mb-4">
    <h2 class="cv-section-title" style="margin-bottom:0">Settings</h2>
  </div>
  <div class="cv-card p-6">
    <h3 class="font-semibold text-base mb-1">Ubah Password</h3>
    <p class="text-xs text-cv-muted mb-4">Ubah password yang digunakan untuk login ke Celiuz Vault Anda.</p>
    
    <form @submit.prevent="changePassword()" class="space-y-4">
      <div>
        <label class="text-xs font-semibold block mb-1.5 text-cv-muted">Password Saat Ini</label>
        <input x-model="passwordForm.current" type="password" required class="cv-input w-full" placeholder="Masukkan password saat ini">
      </div>
      <div>
        <label class="text-xs font-semibold block mb-1.5 text-cv-muted">Password Baru</label>
        <input x-model="passwordForm.new" type="password" required class="cv-input w-full" placeholder="Masukkan password baru">
      </div>
      <div>
        <label class="text-xs font-semibold block mb-1.5 text-cv-muted">Konfirmasi Password Baru</label>
        <input x-model="passwordForm.confirm" type="password" required class="cv-input w-full" placeholder="Ulangi password baru">
      </div>
      <button type="submit" class="cv-btn-primary w-full h-11 flex items-center justify-center gap-2">
        <?= lucide('lock', 'w-4 h-4') ?> Simpan Password Baru
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

    </div>
  </div>

  <!-- ═══════════ MODALS ═══════════ -->
  <div x-show="folderModal" x-cloak @keydown.escape.window="folderModal=false" class="cv-modal-overlay" @click.self="folderModal=false"><div class="cv-modal cv-modal-sm"><h3 class="font-semibold text-lg mb-4">New Folder</h3><form @submit.prevent="createFolder()"><input x-model="newFolder" type="text" placeholder="Folder name..." autofocus class="cv-input w-full mb-4" x-ref="folderNameInput"><div class="flex gap-2 justify-end"><button type="button" @click="folderModal=false" class="cv-btn-secondary">Cancel</button><button type="submit" class="cv-btn-primary">Create</button></div></form></div></div>

  <div x-show="versionModal" x-cloak @keydown.escape.window="versionModal=false" class="cv-modal-overlay" @click.self="versionModal=false"><div class="cv-modal cv-modal-sm"><h3 class="font-semibold text-lg mb-1">Riwayat versi</h3><p class="text-xs text-cv-muted mb-4 truncate" x-text="versionFile?.name"></p><template x-if="!versionList.length"><p class="text-sm text-cv-muted py-4 text-center">Tidak ada versi lama.</p></template><div class="space-y-2 max-h-72 overflow-auto"><template x-for="v in versionList" :key="v.id"><div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl bg-cv-bg border border-cv-border"><div class="min-w-0"><p class="text-xs font-medium" x-text="v.size_h"></p><p class="text-[11px] text-cv-faint" x-text="v.created"></p></div><div class="flex gap-1.5 shrink-0"><button @click="restoreVersion(v.id)" class="cv-btn-primary text-xs h-7 px-2.5">Restore</button><button @click="deleteVersion(v.id)" class="cv-btn-secondary text-xs h-7 px-2.5">Hapus</button></div></div></template></div><button @click="versionModal=false" class="cv-btn-secondary w-full mt-4">Tutup</button></div></div>

  <div x-show="modal" x-cloak @keydown.escape.window="modal=false" class="cv-modal-overlay" @click.self="modal=false"><div class="relative max-w-5xl max-h-[90vh] w-full"><template x-if="modalFile?.kind==='image'"><img :src="modalFile.preview" class="max-h-[90vh] mx-auto rounded-bento shadow-pop"></template><template x-if="modalFile?.kind==='video'"><video :src="modalFile.preview" controls autoplay class="max-h-[90vh] mx-auto rounded-bento shadow-pop"></video></template><template x-if="modalFile?.kind==='audio'"><div class="cv-card p-8 text-center"><div class="inline-flex w-12 h-12 rounded-xl bg-cv-accent/10 text-cv-accent items-center justify-center mb-3"><?= lucide('music','w-6 h-6') ?></div><p class="font-medium mb-4" x-text="modalFile.name"></p><audio :src="modalFile.preview" controls autoplay class="mx-auto"></audio></div></template><template x-if="modalFile?.kind==='pdf'"><iframe :src="modalFile.preview" class="w-full h-[85vh] rounded-bento bg-white"></iframe></template><template x-if="modalFile?.kind==='text'"><iframe :src="modalFile.preview" class="w-full h-[85vh] rounded-bento bg-white"></iframe></template><template x-if="modalFile && !['image','video','audio','pdf','text'].includes(modalFile.kind)"><div class="cv-card p-10 text-center"><div class="inline-flex w-14 h-14 rounded-xl bg-cv-bg border border-cv-border text-cv-faint items-center justify-center mb-4" x-html="fileIconSvg(modalFile.icon)"></div><p class="font-medium mb-4" x-text="modalFile.name"></p><a :href="modalFile.preview" download class="cv-btn-primary inline-flex items-center gap-2"><?= lucide('download','w-4 h-4') ?> Download</a></div></template></div></div>

  <div x-show="noteModal" x-cloak @keydown.escape.window="noteModal=false" class="cv-modal-overlay" @click.self="closeNote()"><div class="cv-modal cv-modal-md flex flex-col max-h-[90vh]"><input x-model="noteForm.title" type="text" placeholder="Judul catatan" class="cv-input w-full font-semibold mb-3"><textarea x-model="noteForm.body" rows="10" placeholder="Tulis catatan . . ." class="cv-input w-full flex-1 resize-none text-sm leading-relaxed"></textarea><div class="flex justify-between items-center mt-4 gap-2"><button x-show="noteForm.id" @click="deleteNote({id: noteForm.id})" class="inline-flex items-center gap-1.5 px-3 h-9 rounded-lg text-sm text-red-600 dark:text-red-400 hover:bg-red-500/10 transition"><?= lucide('trash','w-4 h-4') ?> Hapus</button><div class="flex gap-2 ml-auto"><button @click="closeNote()" class="cv-btn-secondary">Batal</button><button @click="saveNote()" class="cv-btn-primary">Simpan</button></div></div></div></div>

  <div x-show="shareModal" x-cloak class="cv-modal-overlay" @click.self="shareModal=false"><div class="cv-modal cv-modal-sm"><h3 class="font-semibold text-lg mb-4">Share link</h3><template x-if="!shareResult"><form @submit.prevent="createShare()" class="space-y-3"><div><label class="text-sm block mb-1.5 text-cv-muted">Password (opsional)</label><input x-model="shareForm.password" type="text" class="cv-input w-full"></div><div><label class="text-sm block mb-1.5 text-cv-muted">Kedaluwarsa</label><select x-model="shareForm.ttl_hours" class="cv-input w-full"><option value="">Tidak pernah</option><option value="1">1 jam</option><option value="24">1 hari</option><option value="168">1 minggu</option></select></div><button class="cv-btn-primary w-full h-11">Buat link</button></form></template><template x-if="shareResult"><div class="space-y-3"><div class="flex gap-2"><input :value="shareResult.url" readonly class="cv-input flex-1 font-mono text-xs"><button @click="copy(shareResult.url)" class="cv-btn-primary px-3" x-text="copied ? '✓' : 'Salin'"></button></div><div class="flex gap-2 justify-center pt-1"><a :href="`https://wa.me/?text=${encodeURIComponent(shareResult.url)}`" target="_blank" class="cv-btn-secondary text-xs h-9 px-3">WhatsApp</a><a :href="`https://t.me/share/url?url=${encodeURIComponent(shareResult.url)}`" target="_blank" class="cv-btn-secondary text-xs h-9 px-3">Telegram</a></div><div class="flex flex-col items-center pt-3 mt-1 border-t border-cv-border"><img :src="`${window.VAULT_BASE}/qr/${encodeURIComponent(shareResult.url)}`" alt="QR" class="w-36 h-36 rounded-xl bg-white p-1.5 border border-cv-border"><p class="text-xs text-cv-faint mt-2">Scan untuk buka di HP</p></div><button @click="shareModal=false" class="cv-btn-secondary w-full">Selesai</button></div></template></div></div>

  <div class="fixed bottom-4 left-1/2 -translate-x-1/2 z-[60] flex flex-col items-center gap-2 pointer-events-none"><template x-for="t in toasts" :key="t.id"><div class="cv-card shadow-pop px-4 py-2.5 text-sm font-medium flex items-center gap-2" :class="t.isError ? 'text-red-600 dark:text-red-400' : 'text-cv-text'"><span x-show="!t.isError" class="text-cv-success">●</span><span x-show="t.isError" class="text-red-500">●</span><span x-text="t.msg"></span></div></template></div>

</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
