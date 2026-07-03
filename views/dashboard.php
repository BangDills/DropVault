<?php
/** @var string $appName
 *  @var array $files, $folders, $chain, $notes
 *  @var ?int $folderId
 *  @var array $stats */
ob_start();
$vm = array_map('file_view_model', $files);
?>
<script>window.VAULT_BASE = <?= json_encode(url('')) ?>;</script>
<div x-data="vault(<?= e(json_encode(['files' => $vm, 'folders' => $folders, 'notes' => $notes, 'chain' => $chain, 'folder' => $folderId, 'stats' => $stats], JSON_UNESCAPED_UNICODE)) ?>)"
     @drop.prevent="onDrop" @dragover.prevent @paste.window="onPaste"
     class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">

  <!-- Top bar -->
  <header class="bento flex items-center justify-between gap-3 px-4 sm:px-5 h-14 mb-6">
    <div class="flex items-center gap-3 min-w-0">
      <div class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-cv-accent/10 text-cv-accent shrink-0">
        <?= lucide('cloud', 'w-5 h-5') ?>
      </div>
      <div class="min-w-0">
        <h1 class="text-[15px] font-semibold tracking-tight truncate"><?= e($appName) ?></h1>
        <p class="text-xs text-cv-muted"><span x-text="humanSize(stats.size)"></span> · <span x-text="stats.count"></span> file</p>
      </div>
    </div>
    <div class="flex items-center gap-1.5">
      <button @click="toggleTheme()" class="p-2 rounded-lg text-cv-muted hover:text-cv-text hover:bg-cv-surface transition cv-focus" title="Theme" aria-label="Toggle theme">
        <span x-show="theme==='dark'"><?= lucide('sun', 'w-[18px] h-[18px]') ?></span>
        <span x-show="theme==='light'"><?= lucide('moon', 'w-[18px] h-[18px]') ?></span>
      </button>
      <a href="<?= e(url('/logout')) ?>" class="p-2 rounded-lg text-cv-muted hover:text-cv-text hover:bg-cv-surface transition cv-focus" title="Logout" aria-label="Logout">
        <?= lucide('logout', 'w-[18px] h-[18px]') ?>
      </a>
    </div>
  </header>

  <!-- Breadcrumb -->
  <nav class="flex items-center flex-wrap gap-1 text-sm mb-5 text-cv-muted">
    <a :href="`<?= e(url('/')) ?>`" class="inline-flex items-center gap-1.5 hover:text-cv-text px-2.5 py-1.5 rounded-lg hover:bg-cv-surface transition">
      <?= lucide('home', 'w-4 h-4') ?><span>Root</span>
    </a>
    <template x-for="c in chain" :key="c.id">
      <span class="flex items-center gap-1">
        <?= lucide('chevron-right', 'w-4 h-4 text-cv-faint') ?>
        <a :href="`<?= e(url('/?folder=')) ?>${c.id}`" class="hover:text-cv-text px-2.5 py-1.5 rounded-lg hover:bg-cv-surface transition" x-text="c.name"></a>
      </span>
    </template>
  </nav>

  <!-- Upload zone (bento) -->
  <div class="bento card-hover relative transition-all mb-6 p-8 text-center cursor-pointer"
       :class="dragging ? 'border-cv-accent ring-4 ring-blue-500/10' : ''"
       @dragenter.prevent="dragging=true" @dragleave.prevent="dragging=false" @click="$refs.fileInput.click()">
    <input type="file" x-ref="fileInput" multiple class="hidden" @change="uploadFiles($event.target.files)">
    <div class="flex flex-col items-center gap-2.5">
      <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-cv-accent/10 text-cv-accent" :class="dragging && 'animate-bounce'">
        <?= lucide('upload', 'w-6 h-6') ?>
      </div>
      <p class="font-medium text-[15px]" x-text="dragging ? 'Lepaskan untuk upload' : 'Tarik file ke sini, atau klik'"></p>
      <p class="text-xs text-cv-faint">atau paste gambar (Ctrl+V)</p>
    </div>
  </div>

  <!-- Upload progress -->
  <div x-show="uploads.length" x-cloak class="space-y-2 mb-6">
    <template x-for="u in uploads" :key="u.id">
      <div class="bento px-4 py-3 flex items-center gap-3">
        <div class="flex-1">
          <div class="flex justify-between text-xs mb-1.5">
            <span x-text="u.name" class="truncate font-medium"></span>
            <span x-text="u.status" class="text-cv-muted"></span>
          </div>
          <div class="h-1.5 bg-cv-bg rounded-full overflow-hidden">
            <div class="h-full bg-cv-accent rounded-full transition-all" :style="`width:${u.pct}%`"></div>
          </div>
        </div>
      </div>
    </template>
  </div>

  <!-- Action bar: folder + note -->
  <div class="flex flex-col sm:flex-row gap-2 mb-5">
    <form @submit.prevent="createFolder()" class="flex gap-2 flex-1">
      <input x-model="newFolder" type="text" placeholder="Nama folder baru..."
             class="cv-focus flex-1 h-10 px-3.5 rounded-xl bg-cv-surface border border-cv-border text-sm transition">
      <button class="inline-flex items-center gap-1.5 h-10 px-4 rounded-xl bg-cv-surface hover:bg-cv-bg border border-cv-border text-sm font-medium transition cv-focus">
        <?= lucide('plus', 'w-4 h-4') ?> Folder
      </button>
    </form>
    <button @click="openNote(null)" class="inline-flex items-center justify-center gap-1.5 h-10 px-4 rounded-xl bg-cv-accent hover:bg-cv-accenthover text-cv-accentfg text-sm font-medium transition shadow-soft cv-focus">
      <?= lucide('plus', 'w-4 h-4') ?> Catatan
    </button>
  </div>

  <!-- Folders -->
  <div x-show="folders.length" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 mb-5">
    <template x-for="f in folders" :key="f.id">
      <a :href="`<?= e(url('/?folder=')) ?>${f.id}`" class="bento card-hover group p-4 flex flex-col items-center gap-2.5 hover:border-cv-accent/40 transition">
        <div class="text-cv-muted group-hover:text-cv-accent transition">
          <?= lucide('folder', 'w-8 h-8') ?>
        </div>
        <span class="text-xs text-center truncate w-full" x-text="f.name"></span>
      </a>
    </template>
  </div>

  <!-- Notes -->
  <div x-show="notes.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
    <template x-for="n in notes" :key="'n'+n.id">
      <div class="bento card-hover group relative p-4 hover:border-cv-accent/40 transition cursor-pointer" @click="openNote(n)">
        <div class="flex items-start gap-2.5 mb-2">
          <span class="text-cv-accent mt-0.5 shrink-0"><?= lucide('note', 'w-[18px] h-[18px]') ?></span>
          <p class="text-sm font-semibold truncate flex-1" x-text="n.title"></p>
        </div>
        <p class="text-xs text-cv-muted line-clamp-4 whitespace-pre-wrap break-words [&_a]:text-cv-accent [&_a]:underline" x-html="n.html"></p>
        <div class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition">
          <button @click.stop="deleteNote(n)" class="p-1.5 rounded-lg text-cv-muted hover:bg-red-500/10 hover:text-red-600 dark:hover:text-red-400 transition" title="Hapus" aria-label="Hapus catatan">
            <?= lucide('trash', 'w-4 h-4') ?>
          </button>
        </div>
      </div>
    </template>
  </div>

  <!-- Files -->
  <div x-show="!files.length && !notes.length" class="bento text-center py-16 text-cv-muted">
    <p>Belum ada file atau catatan. Upload pertamamu!</p>
  </div>
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
    <template x-for="f in files" :key="f.id">
      <div class="bento card-hover group relative overflow-hidden hover:border-cv-accent/40 transition">
        <!-- Preview -->
        <div class="aspect-[4/3] bg-cv-bg flex items-center justify-center overflow-hidden cursor-pointer" @click="preview(f)">
          <template x-if="f.kind==='image'">
            <img :src="f.preview" class="w-full h-full object-cover" loading="lazy">
          </template>
          <template x-if="f.kind==='video' && f.thumb">
            <div class="relative w-full h-full">
              <img :src="f.thumb" class="w-full h-full object-cover" loading="lazy">
              <span class="absolute inset-0 flex items-center justify-center text-white/95 drop-shadow">
                <svg class="w-9 h-9" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
              </span>
            </div>
          </template>
          <template x-if="f.kind==='video' && !f.thumb">
            <video :src="f.preview" class="w-full h-full object-cover" muted></video>
          </template>
          <template x-if="!['image','video'].includes(f.kind)">
            <span class="text-cv-faint" x-html="fileIconSvg(f.icon)"></span>
          </template>
        </div>
        <!-- Info -->
        <div class="px-3 py-2.5 flex items-center justify-between gap-2">
          <div class="min-w-0">
            <p class="text-[13px] font-medium truncate" x-text="f.name"></p>
            <p class="text-[11px] text-cv-faint" x-text="f.size_h"></p>
          </div>
        </div>
        <!-- Actions -->
        <div class="absolute top-1.5 right-1.5 opacity-0 group-hover:opacity-100 transition flex gap-1">
          <button @click.stop="shareFile(f)" class="p-1.5 rounded-lg bg-cv-surface/95 border border-cv-border text-cv-muted hover:text-cv-accent shadow-soft transition" title="Share" aria-label="Share file">
            <?= lucide('share', 'w-4 h-4') ?>
          </button>
          <a :href="`${f.preview}?download=1`" download class="p-1.5 rounded-lg bg-cv-surface/95 border border-cv-border text-cv-muted hover:text-cv-accent shadow-soft transition" title="Download" aria-label="Download file">
            <?= lucide('download', 'w-4 h-4') ?>
          </a>
          <button @click.stop="deleteFile(f)" class="p-1.5 rounded-lg bg-cv-surface/95 border border-cv-border text-cv-muted hover:text-red-600 dark:hover:text-red-400 shadow-soft transition" title="Hapus" aria-label="Hapus file">
            <?= lucide('trash', 'w-4 h-4') ?>
          </button>
        </div>
      </div>
    </template>
  </div>

  <!-- Preview modal -->
  <div x-show="modal" x-cloak @keydown.escape.window="modal=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="modal=false">
    <div class="relative max-w-5xl max-h-[90vh] w-full">
      <template x-if="modalFile?.kind==='image'">
        <img :src="modalFile.preview" class="max-h-[90vh] mx-auto rounded-bento shadow-pop">
      </template>
      <template x-if="modalFile?.kind==='video'">
        <video :src="modalFile.preview" controls autoplay class="max-h-[90vh] mx-auto rounded-bento shadow-pop"></video>
      </template>
      <template x-if="modalFile?.kind==='audio'">
        <div class="bento p-8 text-center">
          <div class="inline-flex w-12 h-12 rounded-xl bg-cv-accent/10 text-cv-accent items-center justify-center mb-3"><?= lucide('music','w-6 h-6') ?></div>
          <p class="font-medium mb-4" x-text="modalFile.name"></p>
          <audio :src="modalFile.preview" controls autoplay class="mx-auto"></audio>
        </div>
      </template>
      <template x-if="modalFile?.kind==='pdf'">
        <iframe :src="modalFile.preview" class="w-full h-[85vh] rounded-bento bg-white"></iframe>
      </template>
      <template x-if="modalFile?.kind==='text'">
        <iframe :src="modalFile.preview" class="w-full h-[85vh] rounded-bento bg-white"></iframe>
      </template>
      <template x-if="modalFile && !['image','video','audio','pdf','text'].includes(modalFile.kind)">
        <div class="bento p-10 text-center">
          <div class="inline-flex w-14 h-14 rounded-xl bg-cv-bg border border-cv-border text-cv-faint items-center justify-center mb-4" x-html="fileIconSvg(modalFile.icon)"></div>
          <p class="font-medium mb-4" x-text="modalFile.name"></p>
          <a :href="modalFile.preview" download class="inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-cv-accent hover:bg-cv-accenthover text-cv-accentfg font-medium transition shadow-soft">
            <?= lucide('download','w-4 h-4') ?> Download
          </a>
        </div>
      </template>
    </div>
  </div>

  <!-- Note editor modal -->
  <div x-show="noteModal" x-cloak @keydown.escape.window="noteModal=false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="closeNote()">
    <div class="bento p-6 w-full max-w-lg flex flex-col max-h-[90vh] shadow-pop">
      <input x-model="noteForm.title" type="text" placeholder="Judul catatan..."
             class="cv-focus w-full h-11 px-3.5 rounded-xl bg-cv-bg border border-cv-border font-semibold mb-3 transition">
      <textarea x-model="noteForm.body" rows="10" placeholder="Tulis catatan... URL otomatis jadi link."
                class="cv-focus w-full flex-1 px-3.5 py-2.5 rounded-xl bg-cv-bg border border-cv-border text-sm resize-none font-mono leading-relaxed transition"></textarea>
      <div class="flex justify-between items-center mt-4 gap-2">
        <button x-show="noteForm.id" @click="deleteNote({id: noteForm.id})" class="inline-flex items-center gap-1.5 px-3 h-9 rounded-lg text-sm text-red-600 dark:text-red-400 hover:bg-red-500/10 transition">
          <?= lucide('trash','w-4 h-4') ?> Hapus
        </button>
        <div class="flex gap-2 ml-auto">
          <button @click="closeNote()" class="h-9 px-4 rounded-lg bg-cv-surface hover:bg-cv-bg border border-cv-border text-sm transition cv-focus">Batal</button>
          <button @click="saveNote()" class="h-9 px-4 rounded-lg bg-cv-accent hover:bg-cv-accenthover text-cv-accentfg text-sm font-medium transition shadow-soft cv-focus">Simpan</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Share modal -->
  <div x-show="shareModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="shareModal=false">
    <div class="bento p-6 w-full max-w-md shadow-pop">
      <h3 class="font-semibold text-lg mb-4 tracking-tight">Share link</h3>
      <template x-if="!shareResult">
        <form @submit.prevent="createShare()" class="space-y-3">
          <div>
            <label class="text-sm block mb-1.5 text-cv-muted">Password (opsional)</label>
            <input x-model="shareForm.password" type="text" class="cv-focus w-full h-10 px-3.5 rounded-xl bg-cv-bg border border-cv-border text-sm transition">
          </div>
          <div>
            <label class="text-sm block mb-1.5 text-cv-muted">Kedaluwarsa (opsional)</label>
            <select x-model="shareForm.ttl_hours" class="cv-focus w-full h-10 px-3.5 rounded-xl bg-cv-bg border border-cv-border text-sm transition">
              <option value="">Tidak pernah</option>
              <option value="1">1 jam</option>
              <option value="24">1 hari</option>
              <option value="168">1 minggu</option>
            </select>
          </div>
          <button class="w-full h-11 rounded-xl bg-cv-accent hover:bg-cv-accenthover text-cv-accentfg font-medium transition shadow-soft">Buat link</button>
        </form>
      </template>
      <template x-if="shareResult">
        <div class="space-y-3">
          <div class="flex gap-2">
            <input :value="shareResult.url" readonly class="flex-1 h-10 px-3.5 rounded-xl bg-cv-bg border border-cv-border text-sm font-mono">
            <button @click="copy(shareResult.url)" class="px-3 h-10 rounded-xl bg-cv-accent hover:bg-cv-accenthover text-cv-accentfg text-sm font-medium transition" x-text="copied ? '✓' : 'Salin'"></button>
          </div>
          <div class="flex gap-2 justify-center pt-1">
            <a :href="`https://wa.me/?text=${encodeURIComponent(shareResult.url)}`" target="_blank" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg bg-cv-surface hover:bg-cv-bg border border-cv-border text-xs font-medium transition">WhatsApp</a>
            <a :href="`https://t.me/share/url?url=${encodeURIComponent(shareResult.url)}`" target="_blank" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg bg-cv-surface hover:bg-cv-bg border border-cv-border text-xs font-medium transition">Telegram</a>
            <button @click="copy(shareResult.url)" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg bg-cv-surface hover:bg-cv-bg border border-cv-border text-xs font-medium transition">Salin</button>
          </div>
          <div class="flex flex-col items-center pt-3 mt-1 border-t border-cv-border">
            <img :src="`${window.VAULT_BASE}/qr/${encodeURIComponent(shareResult.url)}`" alt="QR" class="w-36 h-36 rounded-xl bg-white p-1.5 border border-cv-border">
            <p class="text-xs text-cv-faint mt-2">Scan untuk buka di HP</p>
          </div>
          <button @click="shareModal=false" class="w-full h-10 rounded-xl bg-cv-surface hover:bg-cv-bg border border-cv-border text-sm transition">Selesai</button>
        </div>
      </template>
    </div>
  </div>

</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
