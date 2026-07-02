<?php
/** @var string $appName
 *  @var array $share, $file
 *  @var bool $unlocked
 *  @var ?string $error, $dlUrl, $previewUrl */
ob_start();
$kind = file_kind($file['mime'], $file['name']);
$icon = icon_for($kind);
?>
<div class="min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-lg">
    <div class="bg-cv-surface rounded-2xl shadow-2xl border border-cv-border overflow-hidden">

      <?php if (!$unlocked): ?>
        <!-- Password gate -->
        <div class="p-8">
          <div class="text-center mb-6">
            <span class="text-5xl">🔒</span>
            <h1 class="text-xl font-bold mt-3">Share terlindungi</h1>
            <p class="text-sm text-cv-muted mt-1">Masukkan password untuk melihat file</p>
          </div>
          <form method="post" class="space-y-3">
            <input type="password" name="password" autofocus required
                   class="w-full px-4 py-3 rounded-xl bg-cv-bg border border-cv-border focus:border-cv-accent outline-none">
            <?php if ($error): ?><p class="text-sm text-red-600 dark:text-red-400"><?= e($error) ?></p><?php endif; ?>
            <button class="w-full py-3 rounded-xl bg-cv-accent text-cv-accentfg font-medium hover:opacity-90">Buka</button>
          </form>
        </div>
      <?php else: ?>
        <!-- Preview + download -->
        <div class="aspect-video bg-cv-bg flex items-center justify-center overflow-hidden">
          <?php if ($kind === 'image'): ?>
            <img src="<?= e($previewUrl) ?>" class="max-h-full max-w-full object-contain">
          <?php elseif ($kind === 'video'): ?>
            <video src="<?= e($previewUrl) ?>" controls class="max-h-full max-w-full"></video>
          <?php elseif ($kind === 'audio'): ?>
            <div class="text-center"><span class="text-6xl">🎵</span><audio src="<?= e($previewUrl) ?>" controls class="mt-3 w-full max-w-xs"></audio></div>
          <?php elseif ($kind === 'pdf' || $kind === 'text'): ?>
            <iframe src="<?= e($previewUrl) ?>" class="w-full h-full bg-white"></iframe>
          <?php else: ?>
            <span class="text-7xl"><?= e($icon) ?></span>
          <?php endif; ?>
        </div>
        <div class="p-6">
          <div class="flex items-start justify-between gap-3 mb-4">
            <div class="min-w-0">
              <p class="font-semibold truncate"><?= e($file['name']) ?></p>
              <p class="text-sm text-cv-muted"><?= e(human_size((int)$file['size'])) ?></p>
            </div>
            <a href="<?= e($dlUrl) ?>" download
               class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-cv-accent text-cv-accentfg font-medium hover:opacity-90">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
              Download
            </a>
          </div>
          <div class="flex flex-col items-center pt-2 border-t border-cv-border">
            <div class="flex gap-2 text-2xl justify-center pt-2">
              <a href="https://wa.me/?text=<?= e(urlencode($dlUrl)) ?>" target="_blank" class="p-2 hover:scale-110 transition">💬</a>
              <a href="https://t.me/share/url?url=<?= e(urlencode($dlUrl)) ?>" target="_blank" class="p-2 hover:scale-110 transition">✈️</a>
            </div>
            <img src="<?= e(url('/qr/' . urlencode($dlUrl))) ?>" alt="QR" class="w-36 h-36 rounded-lg bg-white p-1 mt-2">
            <p class="text-xs text-cv-muted mt-1">Scan untuk buka di HP</p>
          </div>
          <p class="text-xs text-center text-cv-muted mt-4">Powered by <?= e($appName) ?></p>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
