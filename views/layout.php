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
              // Bento workspace palette — light-first, premium neutral + modern blue.
              cv: {
                bg:      '#fbfbfc',   // app background (very light grey)
                surface: '#ffffff',   // cards / panels
                border:  '#ececef',   // hairline borders
                text:    '#1f2024',   // primary text
                muted:   '#71717a',   // secondary text
                faint:   '#a1a1aa',   // tertiary / placeholders
                // Primary accent: modern blue. Success: green (status only).
                accent:  '#2563eb', accenthover: '#1d4ed8', accentfg: '#ffffff',
                success: '#16a34a',
              },
            },
            borderRadius: { bento: '16px' },
            boxShadow: {
              soft:   '0 1px 2px 0 rgba(16,24,40,.04), 0 1px 3px 0 rgba(16,24,40,.04)',
              float:  '0 4px 16px -4px rgba(16,24,40,.10), 0 2px 6px -2px rgba(16,24,40,.06)',
              pop:    '0 12px 40px -12px rgba(16,24,40,.22)',
            },
            fontFamily: { sans: ['Inter', 'Geist', 'Manrope', 'system-ui', 'sans-serif'] },
          },
        },
      };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
    <!-- app.js before Alpine so vault()/humanSize() exist when x-data initializes. -->
    <script defer src="<?= e(url('/assets/app.js')) ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-cv-bg text-cv-text font-sans antialiased selection:bg-blue-100 dark:bg-[#0f1011] dark:text-[#e7e7ea] dark:selection:bg-blue-500/30">
<?= $content ?? '' ?>
</body>
</html>
