# Local setup

## PHP and MySQL

Place the repository in a local Apache/Laragon document root, or use `php -S 127.0.0.1:8080` from the repository root for a basic preview. Open `/index.html`. The built-in PHP server is useful for previewing pages; concurrent SSE requests are better tested under Apache.

The application reads these variables from the PHP process environment:

```text
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nexlace
DB_USER=root
DB_PASSWORD=
```

The defaults are for local development. Use a dedicated database user elsewhere. The PHP configuration does not parse a `.env` file automatically: configure variables in the shell, Apache or your hosting environment. An example is provided in `.env.example` for reference.

The setup script expects `nexlace_schema.sql`, which is absent. A complete schema export is needed for `register`, `developers`, jobs, applications, invitations, messages, notifications and related session/review data. Some handlers create or alter individual tables, but they do not supply a complete migration system. Do not infer that opening a page creates every prerequisite.

`setup_database.php` contains seed/reset behaviour. Inspect it and run it only against a disposable local database after obtaining the matching schema. Keep setup, diagnostic and migration scripts out of public production access.

## Optional email service

From `nodemailer/`:

```sh
npm ci
# Copy .env.example to .env and supply your SMTP account values.
npm start
```

The service listens on port 3000. Registration uses that URL for local hostnames. A deployed PHP site needs an explicitly configured email-service endpoint; localhost in a customer's browser refers to their machine. Read [OTP setup](../OTP_SERVER_SETUP.md).

## Optional assistant

Set `GEMINI_API_KEY` in the PHP server environment. `GEMINI_MODEL` is optional and defaults to the model named in `config/gemini_config.php`. Missing keys produce a configuration error rather than an outbound request with a committed credential.

Rotate any key previously committed before enabling the assistant. Do not paste credentials into a README or client script.

## Verification

Run PHP syntax checks with an installed PHP runtime, for example `php -l config/database.php` and `php -l config/gemini_config.php`. Run `node --check nodemailer/index.js` for the email service's JavaScript syntax. Full feature checks need the matching database, a configured SMTP service and two disposable user accounts. See the [verification checklist](../Online_Job_Portal_System_Testing_Validation.md).
