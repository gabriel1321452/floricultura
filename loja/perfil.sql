USE loja;

SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'usuarios_login' AND column_name = 'email');
SET @q = IF(@c = 0, 'ALTER TABLE usuarios_login ADD COLUMN email VARCHAR(150) NULL', 'SELECT 1');
PREPARE m FROM @q; EXECUTE m; DEALLOCATE PREPARE m;

SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'usuarios_login' AND column_name = 'foto');
SET @q = IF(@c = 0, 'ALTER TABLE usuarios_login ADD COLUMN foto VARCHAR(255) NULL', 'SELECT 1');
PREPARE m FROM @q; EXECUTE m; DEALLOCATE PREPARE m;
