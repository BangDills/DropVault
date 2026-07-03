<?php
/** @var string $appName
 *  @var array $share, $file
 *  @var bool $unlocked
 *  @var ?string $error, $dlUrl, $previewUrl */
ob_start();
$kind = file_kind($file['mime'], $file['name']);
$iconName = icon_for($kind);
?>
<div class="min-h-screen flex items-center justify-center p-4">
  <div class="bento w-full max-w-lg overflow-hidden shadow-float">

    <?php if (!$unlocked): ?>
      <!-- Password gate -->
      <div class="p-8">
        <div class="text-center mb-6">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-cv-bg border border-cv-border text-cv-muted mb-3"><?= lucide('lock','w-6 h-6') ?></div>
          <h1 class="text-lg font-semibold tracking-tight">Share terlindungi</h1>
          <p class="text-sm text-cv-muted mt-1">Masukkan password untuk melihat file</p>
        </div>
        <form method="post" class="space-y-3">
          <input type="password" name="password" autofocus required
                 class="cv-focus w-full h-11 px-4 rounded-xl bg-cv-bg border border-cv-border transition">
          <?php if ($error): ?><p class="text-sm text-red-600 dark:text-red-400"><?= e($error) ?></p><?php endif; ?>
          <button class="w-full h-11 rounded-xl bg-cv-accent hover:bg-cv-accenthover text-cv-accentfg font-medium transition shadow-soft">Buka</button>
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
          <div class="text-center">
            <div class="inline-flex w-12 h-12 rounded-xl bg-cv-surface border border-cv-border text-cv-accent items-center justify-center mb-3"><?= lucide('music','w-6 h-6') ?></div>
            <audio src="<?= e($previewUrl) ?>" controls class="mt-1 w-full max-w-xs"></audio>
          </div>
        <?php elseif ($kind === 'pdf' || $kind === 'text'): ?>
          <iframe src="<?= e($previewUrl) ?>" class="w-full h-full bg-white"></iframe>
        <?php else: ?>
          <div class="text-cv-faint"><?= lucide($iconName, 'w-16 h-16') ?></div>
        <?php endif; ?>
      </div>
      <div class="p-6">
        <div class="flex items-start justify-between gap-3 mb-4">
          <div class="min-w-0">
            <p class="font-semibold truncate"><?= e($file['name']) ?></p>
            <p class="text-sm text-cv-muted"><?= e(human_size((int)$file['size'])) ?></p>
          </div>
          <a href="<?= e($dlUrl) ?>" download
             class="shrink-0 inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-cv-accent hover:bg-cv-accenthover text-cv-accentfg font-medium transition shadow-soft">
            <?= lucide('download','w-[18px] h-[18px]') ?> Download
          </a>
        </div>
        <div class="flex flex-col items-center pt-3 border-t border-cv-border">
          <div class="flex gap-2 pt-1">
            <a href="https://wa.me/?text=<?= e(urlencode($dlUrl)) ?>" target="_blank" class="inline-flex items-center h-9 px-3 rounded-lg bg-cv-surface hover:bg-cv-bg border border-cv-border text-xs font-medium transition">WhatsApp</a>
            <a href="https://t.me/share/url?url=<?= e(urlencode($dlUrl)) ?>" target="_blank" class="inline-flex items-center h-9 px-3 rounded-lg bg-cv-surface hover:bg-cv-bg border border-cv-border text-xs font-medium transition">Telegram</a>
          </div>
          <img src="<?= e(url('/qr/' . urlencode($dlUrl))) ?>" alt="QR" class="w-36 h-36 rounded-xl bg-white p-1.5 border border-cv-border mt-3">
          <p class="text-xs text-cv-faint mt-2">Scan untuk buka di HP</p>
        </div>
        <p class="text-xs text-center text-cv-faint mt-4">Powered by <?= e($appName) ?></p>
      </div>
    <?php endif; ?>

  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
