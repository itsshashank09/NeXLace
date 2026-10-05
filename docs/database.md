# Database recovery and access

No original MySQL backup was available. `nexlace_schema.sql` was reconstructed from the application's PHP queries on 2026-10-05 and tested from an empty database. It is a fresh-install schema, not a recovered production export or an in-place migration for an unknown existing database.

The twelve tables cover accounts, developer profiles, jobs, applications, invitations, messages/attachments, notifications, reviews, persisted sessions, login attempts, bookmarks and administrators. Foreign keys preserve relationships; uniqueness covers emails, developer ownership, application/bookmark pairs and session tokens. Rating and budget/rate checks protect the stored values. There are no seeded accounts or passwords.

## Access map

MySQL has no application row-level policies here. `database/access-policies.sql` grants the PHP account DML access only; PHP session checks and scoped WHERE clauses enforce user ownership. That account can access all application rows, so a missing endpoint authorization check remains significant.

| Data | Application boundary |
| --- | --- |
| Accounts/profiles | Current user supplies ownership through the PHP session; developer/profile listings expose public profile fields |
| Jobs/applications | Authenticated users post jobs; applications link to a job and developer; a user cannot apply to their own job |
| Invitations | Lists are scoped to sender/recipient; only the recipient can respond |
| Messages | Reads include the current user in the sender/recipient pair; a third user cannot read another pair's conversation |
| Notifications | Reads and deletions use the current user's ID |
| Sessions | Device list/revocation use the current user's ID; database requests recheck the persisted session and active account |
| Bookmarks | Writes use the current user's ID and a unique user/job pair |
| Reviews | Signed-in users can submit reviews; eligibility based on a completed contract is not implemented |
| Administration | Separate PHP admin session; provisioned password hashes are checked with `password_verify` |

Request-time table creation/alteration has been removed from login, jobs, applications, bookmarking and job listings. Setup, historical migrations and administrator provisioning are CLI-only. The running app was verified using an account without DDL grants.

## Limits

The integration tests cover representative authenticated workflows and cross-user denial, not every route. Review all mutation routes for CSRF and ownership consistency before public deployment. User-upload storage and attachment delivery need a separate access review. The email OTP flow needs server-side binding to PHP registration; admin session expiry/rate limiting and full browser/SSE behaviour need further testing.

For an existing database, first obtain a backup and compare its columns, password formats and relationships. Do not apply the fresh schema over existing data or assume it represents an unavailable original database exactly.
