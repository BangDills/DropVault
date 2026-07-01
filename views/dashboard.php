<?php
/** @var string $appName
 *  @var array $files, $folders, $chain
 *  @var ?int $folderId
 *  @var array $stats */
ob_start();
// Map PHP rows → view models (JSON-encoded for Alpine).
$vm = array_map('file_view_model', $files);
?>
<script>window.VAULT_BASE = <?= json_encode(url('')) ?>;</script>
<div x-data="vault(<?= e(json_encode(['files' => $vm, 'folders' => $folders, 'chain' => $chain, 'folder' => $folderId, 'stats' => $stats], JSON_UNESCAPED_UNICODE)) ?>)"
     @drop.prevent="onDrop" @dragover.prevent @paste.window="onPaste"
     class="max-w-6xl mx-auto p-4 sm:p-6">

  <!-- Header -->
  <header class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
      <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-accent-500 to-accent-700 shadow-lg shadow-accent-500/30">
        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
      </div>
      <div>
        <h1 class="text-lg font-bold text-slate-800 dark:text-white"><?= e($appName) ?></h1>
        <p class="text-xs text-slate-500 dark:text-slate-400"><span x-text="humanSize(stats.size)"></span> · <span x-text="stats.count"></span> file</p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <button @click="toggleTheme()" class="p-2 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800 transition" title="Theme">
        <svg class="w-5 h-5" x-show="theme==='dark'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <svg class="w-5 h-5" x-show="theme==='light'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
      </button>
      <a href="<?= e(url('/logout')) ?>" class="p-2 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800 transition" title="Logout">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      </a>
    </div>
  </header>

  <!-- Breadcrumb -->
  <nav class="flex items-center flex-wrap gap-1 text-sm mb-4 text-slate-500 dark:text-slate-400">
    <a :href="`<?= e(url('/')) ?>`" class="hover:text-accent-500 px-2 py-1 rounded">Root</a>
    <template x-for="c in chain" :key="c.id">
      <span class="flex items-center gap-1">
        <span class="opacity-40">/</span>
        <a :href="`<?= e(url('/?folder=')) ?>${c.id}`" class="hover:text-accent-500 px-2 py-1 rounded" x-text="c.name"></a>
      </span>
    </template>
  </nav>

  <!-- Upload zone -->
  <div class="relative rounded-2xl border-2 border-dashed transition-all mb-6 p-8 text-center cursor-pointer"
       :class="dragging ? 'border-accent-500 bg-accent-500/5 scale-[1.01]' : 'border-slate-300 dark:border-slate-700 hover:border-accent-500/50'"
       @dragenter.prevent="dragging=true" @dragleave.prevent="dragging=false" @click="$refs.fileInput.click()">
    <input type="file" x-ref="fileInput" multiple class="hidden" @change="uploadFiles($event.target.files)">
    <div class="flex flex-col items-center gap-2">
      <svg class="w-10 h-10 text-accent-500" :class="dragging && 'animate-bounce'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
      <p class="font-medium" x-text="dragging ? 'Lepaskan untuk upload' : 'Tarik file ke sini, atau klik'"></p>
      <p class="text-xs text-slate-400">atau paste gambar (Ctrl+V)</p>
    </div>
  </div>

  <!-- Upload progress -->
  <div x-show="uploads.length" x-cloak class="space-y-2 mb-6">
    <template x-for="u in uploads" :key="u.id">
      <div class="bg-white dark:bg-slate-900 rounded-lg p-3 border border-slate-200 dark:border-slate-800 flex items-center gap-3">
        <div class="flex-1">
          <div class="flex justify-between text-xs mb-1">
            <span x-text="u.name" class="truncate"></span>
            <span x-text="u.status"></span>
          </div>
          <div class="h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
            <div class="h-full bg-accent-500 transition-all" :style="`width:${u.pct}%`"></div>
          </div>
        </div>
      </div>
    </template>
  </div>

  <!-- New folder bar -->
  <form @submit.prevent="createFolder()" class="flex gap-2 mb-4">
    <input x-model="newFolder" type="text" placeholder="Nama folder baru..."
           class="flex-1 px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-accent-500 outline-none text-sm">
    <button class="px-4 py-2 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-sm font-medium">+ Folder</button>
  </form>

  <!-- Folders -->
  <div x-show="folders.length" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 mb-4">
    <template x-for="f in folders" :key="f.id">
      <a :href="`<?= e(url('/?folder=')) ?>${f.id}`" class="group bg-white dark:bg-slate-900 rounded-xl p-4 border border-slate-200 dark:border-slate-800 hover:border-accent-500 transition flex flex-col items-center gap-2">
        <svg class="w-8 h-8 text-amber-400 group-hover:scale-110 transition" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V8a2 2 0 00-2-2h-8l-2-2z"/></svg>
        <span class="text-xs text-center truncate w-full" x-text="f.name"></span>
      </a>
    </template>
  </div>

  <!-- Files -->
  <div x-show="!files.length" class="text-center py-12 text-slate-400">
    <p>Belum ada file. Upload pertamamu!</p>
  </div>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
    <template x-for="f in files" :key="f.id">
      <div class="group relative bg-white dark:bg-slate-900 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 hover:border-accent-500 hover:shadow-lg transition">
        <!-- Preview thumbnail -->
        <div class="aspect-square bg-slate-100 dark:bg-slate-800 flex items-center justify-center overflow-hidden cursor-pointer" @click="preview(f)">
          <template x-if="f.kind==='image'">
            <img :src="f.preview" class="w-full h-full object-cover" loading="lazy">
          </template>
          <template x-if="f.kind==='video' && f.thumb">
            <div class="relative w-full h-full">
              <img :src="f.thumb" class="w-full h-full object-cover" loading="lazy">
              <span class="absolute inset-0 flex items-center justify-center text-white/90 drop-shadow"><svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span>
            </div>
          </template>
          <template x-if="f.kind==='video' && !f.thumb">
            <video :src="f.preview" class="w-full h-full object-cover" muted></video>
          </template>
          <template x-if="!['image','video'].includes(f.kind)">
            <span class="text-4xl" x-text="f.icon"></span>
          </template>
        </div>
        <!-- Info -->
        <div class="p-2.5">
          <p class="text-sm font-medium truncate" x-text="f.name"></p>
          <p class="text-xs text-slate-400" x-text="f.size_h"></p>
        </div>
        <!-- Actions -->
        <div class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition flex gap-1">
          <button @click.stop="shareFile(f)" class="p-1.5 rounded-lg bg-white/90 dark:bg-slate-800/90 hover:bg-accent-500 hover:text-white shadow" title="Share">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
          </button>
          <a :href="`${f.preview}?download=1`" download class="p-1.5 rounded-lg bg-white/90 dark:bg-slate-800/90 hover:bg-accent-500 hover:text-white shadow" title="Download">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          </a>
          <button @click.stop="deleteFile(f)" class="p-1.5 rounded-lg bg-white/90 dark:bg-slate-800/90 hover:bg-red-500 hover:text-white shadow" title="Hapus">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </div>
    </template>
  </div>

  <!-- Preview modal -->
  <div x-show="modal" x-cloak @keydown.escape.window="modal=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="modal=false">
    <div class="relative max-w-5xl max-h-[90vh] w-full">
      <template x-if="modalFile?.kind==='image'">
        <img :src="modalFile.preview" class="max-h-[90vh] mx-auto rounded-lg">
      </template>
      <template x-if="modalFile?.kind==='video'">
        <video :src="modalFile.preview" controls autoplay class="max-h-[90vh] mx-auto rounded-lg"></video>
      </template>
      <template x-if="modalFile?.kind==='audio'">
        <div class="text-center text-white"><span class="text-6xl">🎵</span><p x-text="modalFile.name"></p><audio :src="modalFile.preview" controls autoplay class="mt-4 mx-auto"></audio></div>
      </template>
      <template x-if="modalFile?.kind==='pdf'">
        <iframe :src="modalFile.preview" class="w-full h-[85vh] rounded-lg bg-white"></iframe>
      </template>
      <template x-if="modalFile?.kind==='text'">
        <iframe :src="modalFile.preview" class="w-full h-[85vh] rounded-lg bg-white"></iframe>
      </template>
      <template x-if="modalFile && !['image','video','audio','pdf','text'].includes(modalFile.kind)">
        <div class="text-center text-white"><span class="text-6xl" x-text="modalFile.icon"></span><p class="mt-2" x-text="modalFile.name"></p><a :href="modalFile.preview" download class="mt-4 inline-block px-4 py-2 rounded-lg bg-accent-600 text-white">Download</a></div>
      </template>
    </div>
  </div>

  <!-- Share modal -->
  <div x-show="shareModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="shareModal=false">
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 w-full max-w-md border border-slate-200 dark:border-slate-800">
      <h3 class="font-bold text-lg mb-4">Share link</h3>
      <template x-if="!shareResult">
        <form @submit.prevent="createShare()" class="space-y-3">
          <div>
            <label class="text-sm block mb-1">Password (opsional)</label>
            <input x-model="shareForm.password" type="text" class="w-full px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 border border-transparent focus:border-accent-500 outline-none text-sm">
          </div>
          <div>
            <label class="text-sm block mb-1">Kedaluwarsa (opsional)</label>
            <select x-model="shareForm.ttl_hours" class="w-full px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 border border-transparent focus:border-accent-500 outline-none text-sm">
              <option value="">Tidak pernah</option>
              <option value="1">1 jam</option>
              <option value="24">1 hari</option>
              <option value="168">1 minggu</option>
            </select>
          </div>
          <button class="w-full py-2.5 rounded-lg bg-accent-600 hover:bg-accent-700 text-white font-medium">Buat link</button>
        </form>
      </template>
      <template x-if="shareResult">
        <div class="space-y-3">
          <div class="flex gap-2">
            <input :value="shareResult.url" readonly class="flex-1 px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-sm font-mono">
            <button @click="copy(shareResult.url)" class="px-3 rounded-lg bg-accent-600 text-white text-sm" x-text="copied ? '✓' : 'Salin'"></button>
          </div>
          <div class="flex gap-2 text-2xl justify-center pt-2">
            <a :href="`https://wa.me/?text=${encodeURIComponent(shareResult.url)}`" target="_blank" class="p-2 hover:scale-110 transition">💬</a>
            <a :href="`https://t.me/share/url?url=${encodeURIComponent(shareResult.url)}`" target="_blank" class="p-2 hover:scale-110 transition">✈️</a>
            <button @click="copy(shareResult.url)" class="p-2 hover:scale-110 transition">📋</button>
            <a :href="`https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(shareResult.url)}`" target="_blank" class="p-2 hover:scale-110 transition">📱</a>
          </div>
          <button @click="shareModal=false" class="w-full py-2 rounded-lg bg-slate-200 dark:bg-slate-800 text-sm">Selesai</button>
        </div>
      </template>
    </div>
  </div>

</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
