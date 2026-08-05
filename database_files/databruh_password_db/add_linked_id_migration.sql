-- Upgrade an existing databruh_password_db without deleting account data.
-- The current password_entity.sql already includes this field for fresh installs.

USE databruh_password_db;

SET @databruh_linked_id_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'account'
      AND COLUMN_NAME = 'LinkedID'
);

SET @databruh_add_linked_id_column_sql = IF(
    @databruh_linked_id_column_exists = 0,
    'ALTER TABLE account ADD COLUMN LinkedID VARCHAR(50) NULL AFTER TypeID',
    'DO 0'
);

PREPARE databruh_linked_id_statement
FROM @databruh_add_linked_id_column_sql;
EXECUTE databruh_linked_id_statement;
DEALLOCATE PREPARE databruh_linked_id_statement;

-- Check the indexed columns, not only the index name. Fresh installs
-- already have an automatically named UNIQUE index from password_entity.sql.
SET @databruh_linked_id_unique_index_exists = (
    SELECT COUNT(*)
    FROM (
        SELECT INDEX_NAME
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'account'
          AND NON_UNIQUE = 0
        GROUP BY INDEX_NAME
        HAVING COUNT(*) = 1
           AND MAX(CASE WHEN COLUMN_NAME = 'LinkedID' THEN 1 ELSE 0 END) = 1
    ) AS databruh_linked_id_unique_indexes
);

SET @databruh_add_linked_id_index_sql = IF(
    @databruh_linked_id_unique_index_exists = 0,
    'ALTER TABLE account ADD UNIQUE INDEX uq_account_linked_id (LinkedID)',
    'DO 0'
);

PREPARE databruh_linked_id_statement
FROM @databruh_add_linked_id_index_sql;
EXECUTE databruh_linked_id_statement;
DEALLOCATE PREPARE databruh_linked_id_statement;

-- DDL auto-commits in MariaDB. Back up first. Dropping LinkedID is not a
-- safe rollback after accounts have been linked, because that mapping is data.
