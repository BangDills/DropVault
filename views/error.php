<?php /** @var string $appName, string $message */ ob_start(); ?>
<div class="min-h-screen flex items-center justify-center p-4">
  <div class="text-center">
    <span class="text-7xl block mb-4">🔍</span>
    <h1 class="text-2xl font-bold text-slate-800 dark:text-white"><?= e($message) ?></h1>
    <a href="<?= e(url('/')) ?>" class="inline-block mt-6 px-5 py-2.5 rounded-xl bg-accent-600 hover:bg-accent-700 text-white font-medium">Kembali</a>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
