<?php /** @var string $appName, string $message */ ob_start(); ?>
<div class="min-h-screen flex items-center justify-center p-4">
  <div class="text-center">
    <span class="text-7xl block mb-4">🔍</span>
    <h1 class="text-2xl font-bold"><?= e($message) ?></h1>
    <a href="<?= e(url('/')) ?>" class="inline-block mt-6 px-5 py-2.5 rounded-xl bg-cv-accent text-cv-accentfg font-medium hover:opacity-90">Kembali</a>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php';
