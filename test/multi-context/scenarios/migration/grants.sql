-- The rights of the database users in the migration scenario, one user per application.
-- They follow what each context reads and writes:
--   the existing system and the desired system have their own database and nothing else;
--   the migration context reads the existing system, and reads and writes the desired system.
CREATE USER 'kurk1'@'%' IDENTIFIED BY 'ampersand';
CREATE USER 'kurk2'@'%' IDENTIFIED BY 'ampersand';
CREATE USER 'migration'@'%' IDENTIFIED BY 'ampersand';

GRANT ALL PRIVILEGES ON `kurk_1`.* TO 'kurk1'@'%';
GRANT ALL PRIVILEGES ON `kurk_2`.* TO 'kurk2'@'%';

GRANT ALL PRIVILEGES ON `migration`.* TO 'migration'@'%';
GRANT SELECT ON `kurk_1`.* TO 'migration'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `kurk_2`.* TO 'migration'@'%';
