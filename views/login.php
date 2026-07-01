<?php /** @var string $appName, ?string $error */ ob_start(); ?>
<div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-950 dark:to-slate-900">
  <div class="w-full max-w-sm">
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-accent-500 to-accent-700 shadow-lg shadow-accent-500/30 mb-4">
        <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
      </div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-white"><?= e($appName) ?></h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">File hosting pribadi</p>
    </div>

    <form method="post" action="<?= e(url('/login')) ?>" class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-xl border border-slate-200 dark:border-slate-800">
      <label class="block text-sm font-medium mb-2">Password</label>
      <input type="password" name="password" autofocus required
             class="w-full px-4 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 border border-transparent focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 outline-none transition">
      <?php if ($error): ?>
        <p class="text-sm text-red-500 mt-3"><?= e($error) ?></p>
      <?php endif; ?>
      <button type="submit" class="w-full mt-4 py-3 rounded-xl bg-accent-600 hover:bg-accent-700 text-white font-medium transition shadow-lg shadow-accent-500/30">
        Masuk
      </button>
    </form>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
