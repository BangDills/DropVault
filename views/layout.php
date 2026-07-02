<?php /** @var string $appName */ ?>
<!DOCTYPE html>
<html lang="id" class="dark" x-data="{ theme: localStorage.getItem('theme') || 'dark' }" :class="theme" x-init="$watch('theme', v => localStorage.setItem('theme', v))">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            colors: {
              accent: { 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' },
            },
            fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
          },
        },
      };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
    <!-- app.js MUST load before Alpine so vault()/humanSize() are defined
         when Alpine initializes x-data. Both are deferred; defer preserves
         document order, so placing app.js first guarantees it runs first. -->
    <script defer src="<?= e(url('/assets/app.js')) ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 font-sans antialiased">
<?= $content ?? '' ?>
</body>
</html>
