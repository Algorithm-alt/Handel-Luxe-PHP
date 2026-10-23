# Deploying Handel Luxe GH

Plain PHP + MySQL. No build step — copy the files, point Apache at the folder,
import the schema. What follows is the list of things that are easy to get
wrong and expensive to get wrong.

## 0. Requirements

- **PHP 8.1 or newer.** The database helpers rely on mysqli reporting a failed
  query as an exception, which became the default in 8.1; before that the same
  failure returns `false` and the next call on it is fatal. `PROJECT_REPORT.md`
  carries the longer version.
- **MySQL**, with the connection settings in `secrets.php` (section 1).
- **The GD extension enabled.** Worth calling out because it fails in a way
  that points at the wrong thing. Without GD every other upload check still
  passes, since `getimagesize()` and the magic-byte test are PHP core rather
  than GD, so the file type and the file size both look fine and only the
  upload itself fails. Confirm with `php -m | grep -i gd`. Enabling it in
  `php.ini` needs an Apache restart before it takes effect.

WebP and EXIF support inside GD are optional and degrade rather than fail.
See Prerequisites in `PROJECT_REPORT.md`.

## 1. Credentials

All live secrets live in **`config/secrets.php`**. It is the only file holding
real values, it is listed in `.gitignore`, and `config/.htaccess` denies HTTP
access to the directory. It holds everything that differs between a local
XAMPP install and production:

| What | Constants |
|---|---|
| Database | `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` |
| Paystack | `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY` |
| SMTP mail | `EMAIL_HOST`, `EMAIL_PORT`, `EMAIL_USER`, `EMAIL_PASS`, `EMAIL_TO_ADMIN` |
| WhatsApp | `WHATSAPP_ADMIN_NUMBERS`, `TWILIO_*` / `WA_CLOUD_*` |
| Installer | `SETUP_KEY` |

```
cp config/secrets.example.php config/secrets.php   # then fill it in
```

**The file is now required for the site to run at all.** The database
connection settings live in it, and `config/db.php` refuses to connect while
`DB_HOST`, `DB_USER` or `DB_NAME` is empty — it will not fall back to
`localhost` as the current OS user. On a machine without `secrets.php`, the
homepage, the FAQ page and every `/api/*` route answer
`503 Database is not configured: ...`. `DB_PASS` itself may be empty; a stock
XAMPP root account has no password.

That is deliberate. A missing credentials file used to mean "payments are
off"; it now means "nothing runs", which is the failure you want to hit
before going live rather than after.

Never paste credentials back into `config/db.php`, `config/paystack.php`,
`config/mail.php`, `config/whatsapp.php` or `setup.php` — those files only read
what `secrets.php` defines, and each keeps an empty default so that a missing
file reports a readable error instead of fataling on an undefined constant.

Features that stop individually, with a `503` and a readable message, when
*their* credential is missing:

| Endpoint | Without credentials |
|---|---|
| `GET /api/payments/config` | `503 Payments are not configured on this site.` |
| `POST /api/payments/initialize` | `503` — no order is written, no stock reserved |
| `GET /api/payments/verify` | `503` — an order can never be marked paid keyless |
| `POST /api/webhook/paystack` | `503` — see note below |

The webhook guard is load-bearing, not cosmetic. The signature is an
HMAC-SHA512 **keyed on `PAYSTACK_SECRET_KEY`**. If that key were empty, anyone
could compute a valid signature and forge a `charge.success` event to mark
their own order paid. The endpoint now refuses to process anything at all
until the key is present.

Rotate after any suspected exposure:

- **Paystack** — dashboard → Settings → API Keys & Webhooks → roll the secret key
- **Gmail** — myaccount.google.com → Security → App passwords → revoke, then generate a new one
- **MySQL** — change the password, then update `DB_PASS` in `secrets.php`

The webhook signature uses the same Paystack secret, so rolling the key only
means updating `secrets.php`. Nothing else needs reconfiguring.

### Injecting credentials from the environment

Every credential is read through `env_or()` in `config/app.php`, which prefers
a real environment variable of the same name over `secrets.php`. Precedence is
**environment > `secrets.php` > built-in default**, so a host that injects
credentials needs no `secrets.php` on disk at all — the DB settings are read
the same way, through `dbConfig()`.

```
Apache (mod_php), vhost or .htaccess   SetEnv DB_PASS ...
php-fpm pool config                    env[DB_PASS] = ...
Docker                                 docker run -e DB_PASS=...
```

With php-fpm, variables inherited from the shell also need `clear_env = no`;
explicit `env[...]` entries work regardless.

`getenv()` is used rather than `$_ENV` because `$_ENV` is populated only when
`variables_order` contains `E`, which by default it does not. A value set with
`SetEnv` before an internal rewrite can reach CGI/FastCGI as `REDIRECT_<name>`;
`env_or()` accepts that spelling too, since this app routes every request
through a front controller.

An empty variable counts as unset rather than as a deliberate blank. The
common accident is `DB_PASS=$SECRET` in a shell where `SECRET` was never
exported: that expands to `""`, and falling back to the file beats connecting
with no password. To blank a value on purpose, edit `secrets.php`.

The one list-valued setting is comma-separated in the environment:

```
WHATSAPP_ADMIN_NUMBERS=233542332244,233549986486
```

This is the useful half of a `.env` file without the risk. A `.env` in the
webroot would be served as plain text — the rewrite rules in `.htaccess` say
"serve actual files first" — and unlike a `.php` file it leaks verbatim if
`AllowOverride` ever stops honouring `config/.htaccess`.

## 2. The installer

`setup.php` **DROPs and reseeds every `handel_*` table.** It requires two
independent factors:

1. `SETUP_KEY` (from `config/secrets.php`) passed as `?key=...`
2. a `setup-allow.txt` file in the app folder containing the word `allow`

   ```
   echo allow > setup-allow.txt
   # then open setup.php?key=<SETUP_KEY>
   rm setup-allow.txt
   ```

Both are required because the key travels in a URL — it lands in Apache access
logs and browser history, so a leak is a question of when. On its own it is now
inert, because the attacker would also need to create a file on the server's
disk.

Leaving `SETUP_KEY` empty in `secrets.php` disables the installer outright.

**On the production server, delete `setup.php` entirely.** It is an installer,
not runtime code, and the gate is a safety net for local development. This is
not optional — it is the difference between a locked door and no door.

## 3. Store backups

The private **`/developer` dashboard** has a **Download Full Backup** action.
It is separate from the client admin panel and uses its own credentials, not a
store user account. It creates a compressed `.tar.gz` containing a SQL dump of
the database and the files in `public/uploads/`. Treat the archive as
sensitive: it contains customer, order, and account data. Keep downloaded
copies private and do not leave them in the website's public folders.

Before enabling the dashboard, configure `DEVELOPER_DASH_USERNAME` and
`DEVELOPER_DASH_PASSWORD_HASH` in `config/.env` or `config/secrets.php`. Create
the password hash locally with:

```
php -r "echo password_hash('replace-with-a-long-unique-password', PASSWORD_DEFAULT);"
```

Never use the store admin's password for this separate login. Leaving either
setting empty disables the initial developer login. On first successful
login, the credentials are saved as a password hash in the application
database; after that, update the username or password from the Developer
Profile section of `/developer`. The dashboard is not linked from the store
navigation and is marked `noindex`; still use a strong unique password and
HTTPS.

For scheduled backups, configure `BACKUP_DIR` as an absolute writable path
**outside the website document root**. For example, on cPanel:

```
BACKUP_DIR=/home/ACCOUNT/private/handel-luxe-backups
```

Add this to `config/.env` (or set it in the hosting environment), replacing
`ACCOUNT` with the actual hosting account name. Then create a daily Cron Job
that runs the PHP CLI script, using the absolute paths supplied by the host:

```
/usr/local/bin/php /home/ACCOUNT/public_html/scripts/backup.php
```

The script refuses to store backups inside the application directory, creates
archives with restricted permissions, and keeps the newest 14 scheduled
archives. PHP CLI needs the Phar and zlib extensions. Verify the first run in
the host's cron output/log and confirm the archive exists and is non-empty.

Scheduled copies on the same hosting account protect against some mistakes,
but not account suspension, hosting failure, or server loss. Keep a separate
off-site copy as well—for example, periodically download a full backup or
configure the host to copy backups to storage under a different account.
Restoring is intentionally not available in the admin interface; test recovery
using a separate empty database before relying on a backup.

## 4. Launch checklist

- [ ] `config/secrets.php` exists on the server with production values,
      including `DB_HOST` / `DB_USER` / `DB_PASS` / `DB_NAME` — or those same
      values are injected as environment variables instead
- [ ] `setup.php` **deleted** from the server
- [ ] `setup-allow.txt` **deleted** from the server
- [ ] Paystack + Gmail credentials rotated
- [ ] Custom domain connected, and `BASE_URL` resolves correctly under it
- [ ] `robots.txt`, `sitemap.xml` and `llms.txt` regenerated — all three still
      contain `localhost` URLs
- [ ] Favicon in place; "Made with AI" tag absent
- [ ] Privacy (`/privacy`) and Terms (`/terms`) pages linked in the footer
- [ ] Paystack webhook URL pointed at `https://<domain>/api/webhook/paystack`
- [ ] Separate `DEVELOPER_DASH_USERNAME` and `DEVELOPER_DASH_PASSWORD_HASH`
      configured; developer login tested at `/developer`
- [ ] `BACKUP_DIR` points outside the webroot and the daily cron job is
      configured and verified from its last-run output
- [ ] One real ~GH₵1 live Paystack transaction run end to end
- [ ] Admin password changed from `admin123`
- [ ] `test-checkout.html` removed from the webroot (it is publicly reachable
      and is listed in `.gitignore`)
- [ ] `public/uploads/` carried over separately — it is gitignored as runtime
      content, so a fresh clone or deploy will not contain it

## 5. Known gaps

- `handel_users` has no `phone` column, so logged-in customers re-enter their
  phone number at every checkout.
- WhatsApp order notifications are wired up but dormant — `config/secrets.php`
  needs a Twilio or Meta Cloud API provider block filled in before
  `notifyAdminsWhatsApp()` does anything.
- The per-page WhatsApp float uses a click-to-chat link, which can only open a
  chat, never send by itself.
