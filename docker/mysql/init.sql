-- Runs only on first boot (empty volume). Safe to re-run — all statements are IF NOT EXISTS.

CREATE DATABASE IF NOT EXISTS `iptv_middleware`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `xcvm`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `xcvm_migrate`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Middleware user (created by MYSQL_USER env var, grant extra DBs)
GRANT ALL PRIVILEGES ON `iptv_middleware`.* TO 'iptv'@'%';

-- XC-VM dedicated user
CREATE USER IF NOT EXISTS 'xcvm'@'%' IDENTIFIED BY 'xcvmsecret';
GRANT ALL PRIVILEGES ON `xcvm`.* TO 'xcvm'@'%';
GRANT ALL PRIVILEGES ON `xcvm_migrate`.* TO 'xcvm'@'%';

FLUSH PRIVILEGES;
