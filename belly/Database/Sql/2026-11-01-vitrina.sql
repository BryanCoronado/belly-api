-- Catálogo virtual (vitrina) y más detalle en productos. Ejecutar en la base.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS vitrina (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_empresa INT UNSIGNED NOT NULL,
  slug VARCHAR(60) NOT NULL,
  titulo VARCHAR(80) NOT NULL,
  eslogan VARCHAR(160) NULL,
  color VARCHAR(7) NOT NULL DEFAULT '#0B1026',
  logo_url VARCHAR(500) NULL,
  whatsapp VARCHAR(20) NULL,
  mostrar_precios TINYINT(1) NOT NULL DEFAULT 1,
  publicado TINYINT(1) NOT NULL DEFAULT 0,
  pagina LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_vitrina_empresa (id_empresa),
  UNIQUE KEY uk_vitrina_slug (slug),
  CONSTRAINT fk_vitrina_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CALL belly_columna_si_falta('producto', 'resumen', 'ALTER TABLE producto ADD COLUMN resumen VARCHAR(180) NULL AFTER descripcion');
CALL belly_columna_si_falta('producto', 'marca', 'ALTER TABLE producto ADD COLUMN marca VARCHAR(80) NULL AFTER codigo');
CALL belly_columna_si_falta('producto', 'precio_oferta', 'ALTER TABLE producto ADD COLUMN precio_oferta DECIMAL(12,2) NULL AFTER precio');
CALL belly_columna_si_falta('producto', 'disponibilidad', "ALTER TABLE producto ADD COLUMN disponibilidad VARCHAR(20) NOT NULL DEFAULT 'disponible' AFTER detalles");
CALL belly_columna_si_falta('producto', 'destacado', 'ALTER TABLE producto ADD COLUMN destacado TINYINT(1) NOT NULL DEFAULT 0 AFTER disponibilidad');
CALL belly_columna_si_falta('producto', 'visible_catalogo', 'ALTER TABLE producto ADD COLUMN visible_catalogo TINYINT(1) NOT NULL DEFAULT 1 AFTER destacado');
CALL belly_columna_si_falta('producto', 'orden', 'ALTER TABLE producto ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER visible_catalogo');

DROP PROCEDURE IF EXISTS belly_columna_si_falta;
