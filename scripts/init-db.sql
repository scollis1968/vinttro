-- scripts/init-db.sql
CREATE DATABASE IF NOT EXISTS `wordpress`;
CREATE DATABASE IF NOT EXISTS `suitecrm`;
GRANT ALL PRIVILEGES ON *.* TO 'vinttro_user'@'%';
FLUSH PRIVILEGES;