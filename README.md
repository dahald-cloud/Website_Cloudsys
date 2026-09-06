# CloudSys website

Static CloudSys marketing site with PHP contact and chatbot endpoints, Cloudflare Turnstile, Resend delivery, OpenRouter AI routing, and a MySQL-backed admin control panel. Node.js is not required.

## Server requirements

- Apache/cPanel hosting with PHP 8.1 or newer
- PHP cURL extension
- PHP PDO MySQL extension
- MySQL database
- HTTPS enabled for the public domain

## Private configuration

The real credentials must live outside `public_html`.

1. Copy `deployment/cloudsys-config.example.php` to `/home/YOUR_CPANEL_USERNAME/cloudsys-config.php`.
2. Replace every placeholder value in that private copy.
3. Do not upload the real config file into `public_html` or commit it to Git.

The PHP files automatically load `cloudsys-config.php` from the cPanel home directory. The host may instead provide a `CLOUDSYS_CONFIG` environment variable containing its absolute path. A plain `.env` file is not loaded automatically.

Required values:

- `TURNSTILE_SECRET_KEY`: production secret matching the public site key in `form.js` and `chat.js`
- `RESEND_API_KEY`: Resend API key with sending permission
- `RESEND_FROM`: verified sender such as `CloudSys Website <website@cloudsysllc.com>`
- `CONTACT_TO`: `support@cloudsysllc.com`
- `OPENROUTER_API_KEY`: private OpenRouter API key
- `OPENROUTER_MODEL`: approved OpenRouter model ID including its provider prefix
- `OPENROUTER_BASE_URL`: `https://openrouter.ai/api/v1`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`: cPanel MySQL credentials

## Database and first administrator

1. Create a MySQL database and database user in cPanel, then grant that user access to the database.
2. Import `deployment/schema.sql` using phpMyAdmin. This creates the `admins` and `site_settings` tables and sets chatbot access to `Admins only`.
3. Copy `deployment/create-admin.php` temporarily to `/home/YOUR_CPANEL_USERNAME/create-admin.php`, beside the private config file.
4. Run `php ~/create-admin.php` in the cPanel terminal and enter the administrator details.
5. Delete the helper immediately: `rm ~/create-admin.php`.
6. Sign in at `https://cloudsysllc.com/login` and open the admin dashboard.

Administrator passwords use PHP `password_hash()` and `password_verify()`. Login attempts are limited to five per IP every 15 minutes, sessions use secure HTTP-only cookies, password changes invalidate older sessions, and dashboard updates require CSRF tokens.

## Chatbot access modes

The admin dashboard provides exactly three server-side modes:

- `Admins only` — default; only a signed-in administrator can see or use chat.
- `Everyone` — all visitors can use chat after Turnstile verification.
- `Disabled` — the launcher is hidden and `/api/chat.php` rejects all requests.

The browser calls `/api/chat-access.php` to decide whether to reveal the launcher. `/api/chat.php` independently checks the database on every message, so changing browser code cannot bypass the setting.

## cPanel deployment

Upload these items into `public_html`:

- `.htaccess`
- `index.html`
- `privacy.html`
- `cookies.html`
- `cookie-preferences.html`
- `style.css`
- `form-styles.css`
- `accessibility.css`
- `chat-styles.css`
- `admin-styles.css`
- `form.js`
- `cookie-consent.js`
- `chat.js`
- `login.php`
- `logout.php`
- `admin/`
- `api/`
- `includes/`
- `assets/`
- `robots.txt`
- `sitemap.xml`

Do not upload into `public_html`:

- `deployment/`
- the real `cloudsys-config.php`
- `.env`
- `.git/`
- `work/` or `outputs/`
- `.DS_Store`
- Node.js files or `node_modules/`

## Contact flow

1. The browser submits JSON to `/api/contact.php`.
2. PHP validates fields, the honeypot, request size, and separate one-hour pair/email limits plus a five-request shared-IP limit.
3. PHP validates the Turnstile token with Cloudflare Siteverify.
4. PHP sends the message through Resend over HTTPS.
5. Secrets never reach browser JavaScript.

## Website guide

- Turnstile is verified server-side before the first message.
- A verified chat expires after 30 minutes and allows up to 15 messages.
- Each connection is limited to 30 model requests per hour, including failed upstream attempts.
- Only general NetSuite, ERP, automation, and AI-agent questions are accepted.
- Company-specific questions are redirected to the assessment form without calling the model.
- Conversation context is kept in a short-lived server session; refreshing discards the browser token and starts a new chat.
- CAPTCHA token expiry does not clear a valid server chat session. When the server session expires, the visitor is explicitly told to verify and start a new conversation.

## Audit fixes and local checks

Contact pair/email/IP limits and chat IP/session updates now hold nonblocking file locks across the complete read/check/request/write operation. A simultaneous request returns a short retry message instead of racing the counter. Unreadable/corrupt state and failed writes cause an error, not unlimited access. PHP must support `flock` and have a writable temporary directory. Do not delete active lock files; persistent lock files are reused. These locks target the single cPanel host; a multi-server deployment would require shared transactional rate-limit storage.

The real `cloudsys-config.php` belongs one directory above the website root, not in the upload or ZIP. The Apache rule also denies accidental configuration copies and backup suffixes. No configuration values were changed by the audit fixes.

Cookie policy links now use root-relative paths. Admin saved previews use the same article renderer and styles as published articles, while retaining authentication and noindex/no-store behavior. Related articles are intentionally omitted from private previews.

Local regression checks (no external services):

- `php deployment/test-insights.php`
- `php deployment/test-audit-fixes.php` — isolated temporary fixtures and concurrent PHP workers; requires CLI `proc_open`.
- `node deployment/test-chat-client.cjs` — development-only mocked client regression test; Node is not required on hosting.

Keep deployment helpers/tests out of the public upload. Real MySQL, Apache routing, email/AI delivery, and browser visual checks are still required on staging before launch.

## Verification before launch

Run over SSH:

- `php -l ~/public_html/includes/bootstrap.php`
- `php -l ~/public_html/login.php`
- `php -l ~/public_html/change-password.php`
- `php -l ~/public_html/reset-password.php`
- `php -l ~/public_html/forgot-password.php`
- `php -l ~/public_html/logout.php`
- `php -l ~/public_html/admin/index.php`
- `php -l ~/public_html/api/chat-access.php`
- `php -l ~/public_html/api/chat.php`
- `php -l ~/public_html/api/contact.php`
- `php -l ~/cloudsys-config.php`

Then verify:

1. An anonymous visitor cannot see chat in `Admins only` mode.
2. An administrator can sign in and use chat.
3. Switching to `Everyone` reveals chat in a private/incognito window.
4. Switching to `Disabled` hides chat and causes direct chat API requests to return `403`.
5. A real contact request arrives in Resend and the CloudSys support mailbox.
6. Changing or resetting an admin password signs out every older session while the current change-password session remains active.
7. `curl -I https://cloudsysllc.com` includes the `Strict-Transport-Security` header.
8. The final approved Privacy and Cookie Policy wording has replaced all draft text.
## Administrator password management

Only `dahald@cloudsysllc.com` and `adhakal@cloudsysllc.com` are accepted by the administrator creation helper. Run `php ~/create-admin.php` once for each address with a separate temporary password, then delete the helper. Each administrator is forced to choose a new private password immediately after the first sign-in.

For an existing database, first import `deployment/migrate-admin-passwords.sql` if it has not already been applied, then import the idempotent `deployment/migrate-admin-session-version.sql` before deploying the updated PHP files. A fresh database should use the complete `deployment/schema.sql` instead.

The forgot-password flow sends a single-use Resend link that expires after 30 minutes. It gives the same browser response for known and unknown addresses, rate-limits requests by IP, stores only a SHA-256 hash of each reset token, invalidates older links, and requires passwords of 14–72 characters containing uppercase, lowercase, and a number.

Upload `forgot-password.php`, `reset-password.php`, and `change-password.php` with the other public PHP application files. Set `SITE_URL` to `https://cloudsysllc.com` in the private server configuration.
