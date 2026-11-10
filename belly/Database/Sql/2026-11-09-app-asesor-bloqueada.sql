-- Acceso individual a la app Belly Asesor (bloqueado por el admin).
-- Ejecutar en bases ya existentes.

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuario'
    AND COLUMN_NAME = 'app_asesor_bloqueada'
);

SET @sql := IF(
  @col = 0,
  'ALTER TABLE usuario ADD COLUMN app_asesor_bloqueada TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = el admin le quitó el acceso a la app de asesores'' AFTER forzar_cambio_pw',
  'SELECT ''app_asesor_bloqueada ya existe'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
