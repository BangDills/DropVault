<?php /** @var string $appName, ?string $error */ ob_start(); ?>
<div class="min-h-screen flex items-center justify-center p-4 bg-cv-bg text-cv-text">
  <div class="w-full max-w-sm">
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-cv-accent mb-4">
        <svg class="w-8 h-8 text-cv-accentfg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
      </div>
      <h1 class="text-2xl font-bold"><?= e($appName) ?></h1>
      <p class="text-sm text-cv-muted mt-1">File hosting pribadi</p>
    </div>

    <form method="post" action="<?= e(url('/login')) ?>" class="bg-cv-surface rounded-2xl p-6 shadow-xl border border-cv-border">
      <label class="block text-sm font-medium mb-2">Password</label>
      <input type="password" name="password" autofocus required
             class="w-full px-4 py-3 rounded-xl bg-cv-bg border border-cv-border focus:border-cv-accent focus:ring-2 focus:ring-black/20 dark:focus:ring-white/20 outline-none transition">
      <?php if ($error): ?>
        <p class="text-sm text-red-600 dark:text-red-400 mt-3"><?= e($error) ?></p>
      <?php endif; ?>
      <button type="submit" class="w-full mt-4 py-3 rounded-xl bg-cv-accent text-cv-accentfg font-medium transition hover:opacity-90">
        Masuk
      </button>
    </form>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
