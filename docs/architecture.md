# Architecture

## Request flow

The public HTML pages lead into PHP pages and JSON endpoints. Shared helpers create PDO connections and check PHP sessions. Browser JavaScript sends asynchronous requests to endpoints for profiles, jobs, applications and messages.

MySQL stores application state. `nodemailer/` is a separate Node process for sending and verifying email OTPs. `api/chatbot.php` is a server-side proxy for Gemini. Neither service replaces the PHP application's database or session handling.

## Features to examine

| Workflow | Main code |
| --- | --- |
| Login and registration | `api/login.php`, `api/register.php`, `config/csrf.php` |
| Developer profiles | `createdeveloperprofile.php`, `devprofiles.php`, `api/save_profile.php` |
| Job posting and applications | `api/post_job.php`, `api/apply_job.php`, `findwork.php` |
| Invitations | `api/send_invitation.php`, `api/respond_invitation.php` |
| Messaging | `messages.php`, `api/send_message.php`, `api/get_messages.php` |
| Notifications | `js/notifications_sse.js`, `api/notifications_stream.php` |
| Email verification | `registration.html`, `nodemailer/index.js` |

The notification stream polls MySQL while maintaining an SSE connection. Releasing the session lock lets other PHP requests continue for the same user. This is useful to inspect when discussing session behaviour and long-lived requests.

## Boundaries and remaining work

Prepared queries, user-password hashing and CSRF helpers are present. Their presence does not establish that every endpoint has equivalent protection. In particular, review authorization and CSRF coverage across state-changing routes, the legacy admin credential handling, user uploads and schema changes performed during requests.

The Node OTP store is process-local and disappears on restart. Its verification response is consumed by the registration page; PHP registration needs a server-verifiable proof of that step. Replacing the random OTP generator with cryptographic randomness and enforcing SMTP certificate verification improves the email service but does not solve that integration gap.

The repository currently contains some historical user/portfolio images. New runtime uploads are ignored. Use synthetic data for screenshots and review existing assets before sharing or deploying a database copy.

The root npm dependencies and `js/supabaseClient.js` do not establish a Supabase integration in the PHP request flow. The billing page and written roadmap describe ideas that go beyond the implemented backend; payment and escrow are not completed features.
