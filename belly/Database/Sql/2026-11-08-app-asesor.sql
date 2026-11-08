-- App móvil de asesores: interruptor por empresa.
-- Ejecutar en bases ya existentes.

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'empresa'
    AND COLUMN_NAME = 'app_asesor_activa'
);

SET @sql := IF(
  @col = 0,
  'ALTER TABLE empresa ADD COLUMN app_asesor_activa TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''Permite entrar a la app móvil de asesores'' AFTER modo_ahorro',
  'SELECT ''app_asesor_activa ya existe'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
