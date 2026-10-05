-- Create nexlace_app@localhost separately using a strong password kept outside Git.
-- Use a migration administrator for setup_database.php, then this DML-only account
-- for the running PHP application. Substitute the selected database/user names.
GRANT SELECT, INSERT, UPDATE, DELETE ON nexlace.* TO 'nexlace_app'@'localhost';
-- Do not grant CREATE, ALTER, DROP, FILE, GRANT OPTION, or global privileges.
-- MySQL has no application row-level policy here: PHP session checks and WHERE
-- predicates enforce ownership. See docs/database.md for the access map and limits.
