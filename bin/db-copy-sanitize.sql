-- IMPORTANT AFTER COPYING DB TO TEST/LOCAL
-- Run these statements to prevent live syncs, scheduled jobs, and outbound mail.

-- 1. Clear all Google Calendar (and other external) tokens from users
TRUNCATE TABLE external_account;

-- 2. Disable all Scheduled Jobs (Cronjobs)
UPDATE scheduled_job SET status = 'Inactive';

-- 3. Disable all Incoming (Group) mailboxes
UPDATE inbound_email SET status = 'Inactive';

-- 4. Disable all Personal mailboxes (user accounts)
UPDATE email_account SET status = 'Inactive';

-- 5. Clear the Google Integration API keys at system level
UPDATE integration SET data = NULL WHERE id = 'Google';
