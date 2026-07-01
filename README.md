# DropVault

Self-hosted personal file hosting — single PHP app, zero build step, made for **cPanel/shared hosting**. Upload, preview, and share files with password-protected, expiring links. Dark/light UI, drag & drop, paste-to-upload, folders.

> No framework, no Node, no Docker, no Redis. Just PHP + SQLite + a few CDN scripts. Deploy by uploading files.

## Features

- **Drag & drop / multi-file / paste (Ctrl+V) upload** with live progress
- **Built-in preview**: image, video, audio, PDF, text/code
- **Video thumbnails** — auto-generated via ffmpeg (graceful fallback to icon if ffmpeg is absent, common on shared hosting)
- **Share links**: custom token, optional password, optional expiry, hit counter
- **Folders** with breadcrumbs (nested, drag-ready structure)
- **Signed download URLs** — files live outside the web root, served only via short-lived HMAC tokens
- **Dark / light theme** toggle, persisted
- **Single-user auth** — one password, constant-time check, brute-force throttle
- **Responsive** + keyboard-friendly
- **Zero-config DB** — SQLite (file-based), WAL mode

## Requirements

- PHP 8.1+ with `pdo_sqlite` (or `sqlite3`), `fileinfo` — standard on cPanel
- Apache with `mod_rewrite` (for pretty URLs) — optional, falls back to `?route=`
- Writable directory for file storage (outside `public_html`)
- **ffmpeg** (optional) — only needed for video thumbnails; uploads work without it

## Quick start (local)

```bash
git clone <your-repo> dropvault && cd dropvault
cp config.example.php config.php
# edit config.php: set password + secret
php -S 127.0.0.1:8765 -t public public/index.php
# open http://127.0.0.1:8765
```

The SQLite DB (`data.sqlite`) and upload folder are created automatically on first run.

## Deploy to cPanel

1. Upload all files into a folder, e.g. `/home/user/filehost/`.
2. Make sure `public/index.php` is web-accessible. Two options:
   - **Recommended:** point a (sub)domain's document root at `public/`.
   - **In `public_html`:** copy `public/index.php`, `.htaccess`, and `assets/` into `public_html/`, and keep the rest of the app one level up.
3. **Storage outside web root:** in `config.php` set
   ```php
   'storage_path' => dirname(__DIR__) . '/storage/uploads', // already above web root
   ```
   so uploaded files are never directly URL-accessible.
4. `cp config.example.php config.php` and fill in:
   - `password` — your login password (hash it for production, see note below)
   - `secret` — long random string for signing download tokens:
     ```bash
     php -r "echo bin2hex(random_bytes(32));"
     ```
5. Ensure the **storage folder** and **`data.sqlite` location** are writable by the web server (`chmod 0700` / set owner).
6. Set PHP upload limits in cPanel → **MultiPHP INI Editor**:
   ```ini
   upload_max_filesize = 512M
   post_max_size = 512M
   max_execution_time = 300
   ```
7. Visit your URL, log in, done.

## Configuration (`config.php`)

| Key | Meaning | Default |
|---|---|---|
| `password` | Login password | `change-me-please` |
| `secret` | HMAC key for signed download tokens | set your own |
| `storage_path` | Where uploaded files are stored (keep outside web root) | `../storage/uploads` |
| `db_path` | SQLite database file path | `data.sqlite` |
| `max_upload` | Max upload size in bytes | `512 MiB` |
| `share_purge_after` | Auto-delete shares older than N seconds (0 = off) | `0` |
| `app_name` | Name shown in UI | `DropVault` |

## Security notes

- **Files never served by URL.** They live above `public_html` and are streamed only through PHP via time-limited (5 min) HMAC-signed tokens.
- **Share downloads are signed** server-side — the share page generates a fresh token per view; links can't be forged or reused after expiry.
- **Password gate** on shares uses `password_hash` / `password_verify`.
- **Login** is single-user with `hash_equals` (constant-time) + a 1s sleep on failure to throttle brute force.
- **Filename sanitization** — stored under random `storage_id`, original name only used for `Content-Disposition`.
- **What to harden before going public:** store a `password_hash()` in config instead of plaintext (swap `check_password`), enable HTTPS (cookie `secure` flag auto-detects it), and consider ClamAV scanning for untrusted uploads.

## Project structure

```
.
├── public/             # web root (only this is exposed)
│   ├── index.php       # front controller + router
│   └── .htaccess       # pretty URLs + upload limits
├── app/
│   ├── bootstrap.php   # config, session, db init
│   ├── db.php          # SQLite schema + access
│   ├── helpers.php     # auth, url, streaming, tokens
│   ├── files.php       # file + folder operations
│   ├── shares.php      # share link operations
│   └── actions.php     # route handlers + view renderer
├── views/              # PHP templates (layout, login, dashboard, share, error)
├── assets/
│   ├── app.js          # Alpine component (upload, share, preview)
│   └── app.css         # tiny extras (Tailwind via CDN)
├── storage/uploads/    # your files (gitignored, outside web root in prod)
├── config.php          # your settings (gitignored)
├── config.example.php  # template to copy
└── .gitignore
```

## API (authed, JSON)

| Method | Path | Action |
|---|---|---|
| `POST` | `/api/upload?folder=<id>` | Upload file(s) — `multipart/form-data`, field `file` or `file[]` |
| `POST` | `/api/folder` | Create folder — `{name, parent}` |
| `POST` | `/api/share` | Create share — `{file_id, password?, ttl_hours?}` → `{url, token}` |
| `DELETE` | `/api/file/<id>` | Delete file |
| `PATCH` | `/api/file/<id>` | Move file — `{folder}` |
| `DELETE` | `/api/share/<id>` | Delete share |

## License

MIT — do what you want. Attribution appreciated but not required.
