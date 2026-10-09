-- Crea el usuario de MySQL que usa api/core/.env y le da acceso solo a MedLabDB.
-- Ejecútalo en phpMyAdmin (pestaña SQL) con el usuario root, DESPUÉS de importar MedLabDB.sql.
CREATE USER IF NOT EXISTS 'usr_proyecto'@'localhost' IDENTIFIED BY 'T!ejJ/YLYM8l*_)t';
GRANT ALL PRIVILEGES ON MedLabDB.* TO 'usr_proyecto'@'localhost';
FLUSH PRIVILEGES;
