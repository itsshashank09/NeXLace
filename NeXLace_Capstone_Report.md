# NeXLace project notes

NeXLace is a freelance marketplace prototype built with PHP, MySQL and browser JavaScript. The aim is to connect clients who post development work with developers who maintain profiles and apply for jobs.

## Implemented areas

The repository includes public pages, session login, developer profiles, job posting and applications, invitations, messaging with attachments, notification streams, settings and an administration area. The email OTP service is a separate Node/Express process. The assistant calls Gemini through a PHP endpoint.

## Technical decisions

- PDO centralizes MySQL connections and supports prepared queries.
- PHP sessions identify users across pages and API requests.
- CSRF helpers provide session-bound request tokens.
- Asynchronous browser requests update jobs, profiles and conversations.
- SSE notifications avoid requiring a full page refresh, while the server checks MySQL for updates.

[Architecture](docs/architecture.md) maps those decisions to actual files. [Setup](docs/setup.md) explains the missing schema and optional services.

## What still needs work

A complete database schema and repeatable integration suite are not checked in. Request-time schema changes, legacy admin authentication, OTP-to-registration verification and upload access need further work. Billing and escrow should be treated as future work, not as delivered integrations.

This document does not claim commercial readiness, measured response times, concurrency capacity or a completed security audit. The [manual verification checklist](Online_Job_Portal_System_Testing_Validation.md) describes how to evaluate the available workflows.
