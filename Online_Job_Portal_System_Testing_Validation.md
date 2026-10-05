# NeXLace verification checklist

This is a manual test plan. The repository also includes real PHP/MySQL integration checks described in [verification](docs/verification.md). It does not include PHPUnit, Selenium or load-test suites, and there are no recorded measurements supporting performance or concurrency claims.

## Prerequisites

Use a local PHP/MySQL environment with the matching schema, two disposable users and a configured SMTP service if testing OTPs. See [setup](docs/setup.md). The checked-in `nexlace_schema.sql` is a tested reconstruction for fresh installations; no original database backup was available.

| Check | Expected behaviour |
| --- | --- |
| Register and sign in | Duplicate emails are rejected; valid credentials start a session |
| Reject a missing CSRF token | Protected state-changing endpoints return an error |
| Create a developer profile | The user's own profile appears with the saved fields |
| Post and apply for a job | The application is linked to the correct job and user |
| Send and respond to an invitation | Only the intended recipient can respond |
| Send a message between test users | Both users see the conversation; an unrelated user cannot read it |
| Upload an attachment | Allowed files under 2 MB work; disallowed types and oversized files are rejected |
| Receive a notification | The SSE stream shows updates without blocking normal requests |
| Revoke a session | The selected session loses access as intended |
| Use the optional assistant | Missing configuration is handled; no key appears in browser responses |

## Available local checks

```sh
php -l config/database.php
php -l config/gemini_config.php
php -l api/chatbot.php
node --check nodemailer/index.js
```

Syntax checks cannot prove that database queries, authorization or email delivery work. Record actual results, the environment and observed failures before describing any integration test as passed.
