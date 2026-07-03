<?php /** @var string $appName, ?string $error */ ob_start(); ?>
<div class="min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-sm">
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-bento bg-cv-surface border border-cv-border shadow-soft mb-4 text-cv-accent">
        <?= lucide('cloud', 'w-7 h-7') ?>
      </div>
      <h1 class="text-[22px] font-semibold tracking-tight"><?= e($appName) ?></h1>
      <p class="text-sm text-cv-muted mt-1">File hosting pribadi</p>
    </div>

    <form method="post" action="<?= e(url('/login')) ?>" class="bento p-6">
      <label class="block text-sm font-medium mb-2">Password</label>
      <input type="password" name="password" autofocus required
             class="cv-focus w-full px-4 h-11 rounded-xl bg-cv-bg border border-cv-border text-[15px] transition">
      <?php if ($error): ?>
        <p class="text-sm text-red-600 dark:text-red-400 mt-3"><?= e($error) ?></p>
      <?php endif; ?>
      <button type="submit" class="w-full mt-4 h-11 rounded-xl bg-cv-accent hover:bg-cv-accenthover text-cv-accentfg font-medium transition shadow-soft">
        Masuk
      </button>
    </form>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
