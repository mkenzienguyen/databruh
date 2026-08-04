-- Upgrade an existing databruh_password_db without deleting account data.
-- The current password_entity.sql already includes this field for fresh installs.

USE databruh_password_db;

ALTER TABLE account
    ADD COLUMN IF NOT EXISTS LinkedID VARCHAR(50) NULL AFTER TypeID;

ALTER TABLE account
    ADD UNIQUE INDEX IF NOT EXISTS uq_account_linked_id (LinkedID);
