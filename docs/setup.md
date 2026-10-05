# Local setup

## PHP and database

Use PHP 8+, MySQL 8+ or MariaDB 10.6+, and extensions `pdo_mysql`, `fileinfo`, `mbstring` and `curl`. The application reads process environment variables; it does not parse `.env` automatically. Configure them in the shell, Apache or your host:

```text
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=nexlace
DB_USER=nexlace_app
DB_PASSWORD=<your-local-password>
```

For a fresh installation, select an empty database and import `nexlace_schema.sql` with a migration administrator. Alternatively, set the administrator's database environment values temporarily and run `php setup_database.php`. The CLI installer refuses an existing non-empty database and creates no accounts or default passwords. The two historical `migrate_*.php` scripts are CLI-only and are not needed after the complete fresh baseline.

Create a separate `nexlace_app` MySQL account with a strong password, then apply the privileges in `database/access-policies.sql`. Switch the running PHP process to that account. It needs SELECT/INSERT/UPDATE/DELETE on this database, not CREATE/ALTER/DROP or global privileges.

Run `php -S 127.0.0.1:8080` from the root and open `/index.html`, or use Apache/Laragon. The built-in server is suitable for local development; use a concurrent server when testing long-lived SSE connections. [Database notes](database.md) explain the schema's reconstruction and access boundaries.

## Administrator

Fresh installations contain no administrator. Set `NEXLACE_ADMIN_PASSWORD` in the CLI process to a new password of at least 16 characters and run:

```sh
php scripts/provision-admin.php myadmin "Local Administrator"
```

Then remove the password environment variable. This stores a `password_hash` value and intentionally replaces the password of an existing matching username. Do not use a command-line password argument or commit the password. Existing legacy plaintext administrator rows must be explicitly reset with this tool before signing in; there is no plaintext fallback.

## Optional email service

From `nodemailer/`, run `npm ci`, copy `.env.example` to `.env`, supply your SMTP values, then `npm start`. The local service listens on port 3000. A deployed PHP site needs its own email-service endpoint; localhost in a customer's browser refers to their machine. See [OTP setup](../OTP_SERVER_SETUP.md).

The PHP registration endpoint does not yet verify server-bound proof of the Node OTP step. Do not treat the browser's OTP success as an account-security guarantee.

## Optional assistant

Set `GEMINI_API_KEY` in the PHP process. `GEMINI_MODEL` is optional. Missing configuration returns an error without an external request. The previously committed key is blocked by Google as leaked; provider deletion is still required before re-enabling the assistant with a replacement key. Keep credentials out of source and browser scripts.

## Verification

[Verification](verification.md) contains reproducible PHP/MySQL tests and their scope. The SMTP and Gemini services are optional and are not called by the integration suite.
