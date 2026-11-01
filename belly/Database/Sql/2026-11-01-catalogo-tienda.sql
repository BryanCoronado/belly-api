-- Catálogo tipo tienda: galería, variantes y etiqueta en productos; imagen en familias. Ejecutar en la base.

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS belly_columna_si_falta;
DELIMITER //
CREATE PROCEDURE belly_columna_si_falta(
  IN p_tabla VARCHAR(64),
  IN p_columna VARCHAR(64),
  IN p_ddl TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_tabla AND COLUMN_NAME = p_columna
  ) THEN
    SET @sql = p_ddl;
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END //
DELIMITER ;

CALL belly_columna_si_falta('producto', 'imagenes', 'ALTER TABLE producto ADD COLUMN imagenes LONGTEXT NULL AFTER imagen_url');
CALL belly_columna_si_falta('producto', 'variantes', 'ALTER TABLE producto ADD COLUMN variantes LONGTEXT NULL AFTER detalles');
CALL belly_columna_si_falta('producto', 'etiqueta', 'ALTER TABLE producto ADD COLUMN etiqueta VARCHAR(20) NULL AFTER variantes');
CALL belly_columna_si_falta('familia_producto', 'imagen_url', 'ALTER TABLE familia_producto ADD COLUMN imagen_url VARCHAR(500) NULL AFTER descripcion');

DROP PROCEDURE IF EXISTS belly_columna_si_falta;
