<?php /** @var string $appName */ ?>
<!DOCTYPE html>
<html lang="id" x-data="{ theme: localStorage.getItem('cv-theme') || 'light' }" :class="theme" x-init="$watch('theme', v => localStorage.setItem('cv-theme', v))">
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
              // ChatGPT-style neutral palette — no purple/neon.
              // Light: white surfaces, near-black text & accent.
              // Dark: #212121 surfaces, near-white text & accent.
              cv: {
                bg:      '#ffffff', surface: '#f9f9f9', border: '#e5e5e5',
                text:    '#0d0d0d', muted: '#6e6e80',
                accent:  '#0d0d0d', accentfg: '#ffffff',
              },
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
<body class="min-h-screen bg-cv-bg text-cv-text font-sans antialiased dark:bg-[#212121] dark:text-[#ececec]">
<?= $content ?? '' ?>
</body>
</html>
