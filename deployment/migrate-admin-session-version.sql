SET @cloudsys_session_version_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'admins'
    AND COLUMN_NAME = 'session_version'
);

SET @cloudsys_session_version_sql = IF(
  @cloudsys_session_version_exists = 0,
  'ALTER TABLE admins ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER must_change_password',
  'SELECT ''session_version already exists'' AS migration_status'
);

PREPARE cloudsys_session_version_statement FROM @cloudsys_session_version_sql;
EXECUTE cloudsys_session_version_statement;
DEALLOCATE PREPARE cloudsys_session_version_statement;
