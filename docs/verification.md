# Verification

## Real PHP/MySQL integration

`tests/integration.py` uses Python's standard library, a real PHP HTTP server and a disposable localhost MySQL/MariaDB server. It creates a database beginning `nexlace_test_`, loads the checked-in schema, creates a temporary DML-only database account and removes the database/account when finished. It does not call SMTP, Gemini or production services.

With a disposable local database service listening on port 33079:

```sh
PHP_BINARY=php MYSQL_BINARY=mysql python tests/integration.py
```

On PowerShell, set `$env:PHP_BINARY` and `$env:MYSQL_BINARY` before running Python. Optional `NEXLACE_TEST_PORT` and `NEXLACE_TEST_HTTP_PORT` change the localhost ports. The administrator defaults to local root; use `NEXLACE_TEST_ADMIN_USER` and `NEXLACE_TEST_ADMIN_PASSWORD` if needed. Use a dedicated disposable server: the script creates and drops its test database and account, not a production service.

On 2026-10-05, **33 checks passed** using PHP 8.4 and MariaDB 11.4. Coverage included fresh-schema installation, registration/sign-in, CSRF denial, developer profile publication, job posting/listing, application/invitation acceptance, persisted private messages, cross-user denial, notifications, bookmarks, device/session ownership, revocation, account deactivation and administrator hash provisioning/login. Runtime queries succeeded without table-management privileges.

The tests exposed a session-revocation defect: deleting a device record did not invalidate its PHP session. Database-backed session validation now rejects that cookie on its next authenticated database request. The suite also verifies missing-CSRF denial on profile publication and absence of automatic default-admin creation.

## Syntax and manual coverage

Run `php -l` on modified PHP files and `node --check nodemailer/index.js`. Syntax checks were also run across the repository's PHP files during recovery.

The [manual checklist](../Online_Job_Portal_System_Testing_Validation.md) covers remaining browser, attachment, SSE, SMTP and assistant checks. These have not been established by the integration suite. There are no recorded load or performance measurements, and this is not a full security audit.
