-- Belly: planes + cupos + ñ del dueño
-- Ejecutar en crm_belly (o la base que uses). Charset UTF-8.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS plan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave VARCHAR(40) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(180) NULL,
  precio_mensual DECIMAL(10,2) NOT NULL DEFAULT 0,
  moneda CHAR(3) NOT NULL DEFAULT 'PEN',
  max_conversaciones INT NOT NULL DEFAULT 0,
  max_equipos INT NOT NULL DEFAULT 0,
  max_personas_equipo INT NOT NULL DEFAULT 0,
  max_personas INT NOT NULL DEFAULT 0,
  max_flujos INT NOT NULL DEFAULT 0,
  max_envios INT NOT NULL DEFAULT 0,
  max_numeros INT NOT NULL DEFAULT 1,
  incluye_agente_ia TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_plan_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @col_id_plan := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'id_plan'
);
SET @sql_id_plan := IF(@col_id_plan = 0,
  'ALTER TABLE empresa ADD COLUMN id_plan INT UNSIGNED NULL AFTER plan',
  'SELECT 1'
);
PREPARE stmt_id_plan FROM @sql_id_plan;
EXECUTE stmt_id_plan;
DEALLOCATE PREPARE stmt_id_plan;

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND CONSTRAINT_NAME = 'fk_empresa_plan'
);
SET @sql_fk := IF(@fk = 0,
  'ALTER TABLE empresa ADD CONSTRAINT fk_empresa_plan FOREIGN KEY (id_plan) REFERENCES plan(id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

INSERT INTO plan (
  clave, nombre, descripcion, precio_mensual, moneda,
  max_conversaciones, max_equipos, max_personas_equipo, max_personas,
  max_flujos, max_envios, max_numeros, incluye_agente_ia, activo, orden, created_at
)
SELECT v.clave, v.nombre, v.descripcion, v.precio_mensual, v.moneda,
  v.max_conversaciones, v.max_equipos, v.max_personas_equipo, v.max_personas,
  v.max_flujos, v.max_envios, v.max_numeros, v.incluye_agente_ia, v.activo, v.orden, NOW()
FROM (
  SELECT 'gratis' AS clave, 'Gratis' AS nombre, 'Chats, Mi Catálogo y un equipo chico para probar.' AS descripcion, 0.00 AS precio_mensual, 'PEN' AS moneda, 300 AS max_conversaciones, 1 AS max_equipos, 2 AS max_personas_equipo, 3 AS max_personas, 1 AS max_flujos, 0 AS max_envios, 1 AS max_numeros, 0 AS incluye_agente_ia, 1 AS activo, 1 AS orden
  UNION ALL SELECT 'base', 'Base', 'Atención diaria, Mi Catálogo, flujos y un par de equipos.', 79.00, 'PEN', 2000, 2, 5, 8, 8, 5, 1, 0, 1, 2
  UNION ALL SELECT 'pro', 'Pro', 'Más chats, Mi Catálogo, envíos y el asistente IA.', 179.00, 'PEN', 10000, 6, 10, 30, 30, 30, 2, 1, 1, 3
  UNION ALL SELECT 'empresa', 'Empresa', 'Sin tope. Incluye Mi Catálogo, IA, equipos y flujos.', 349.00, 'PEN', 0, 0, 0, 0, 0, 0, 5, 1, 1, 4
) AS v
WHERE NOT EXISTS (SELECT 1 FROM plan p WHERE p.clave = v.clave AND p.deleted_at IS NULL);

UPDATE empresa e
JOIN plan p ON p.clave COLLATE utf8mb4_general_ci = CASE
  WHEN e.plan IN ('base', 'pro', 'empresa', 'gratis') THEN e.plan
  ELSE 'gratis'
END COLLATE utf8mb4_general_ci AND p.deleted_at IS NULL
SET e.id_plan = p.id
WHERE e.id_plan IS NULL AND e.deleted_at IS NULL;

UPDATE usuario_perfil
SET nombres = CONCAT('Due', CONVERT(UNHEX('C3B1') USING utf8mb4), 'o')
WHERE id_usuario = 7;

SET @col_op := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuario' AND COLUMN_NAME = 'operador'
);
SET @sql_op := IF(@col_op = 0,
  'ALTER TABLE usuario ADD COLUMN operador TINYINT(1) NOT NULL DEFAULT 0 AFTER sudo',
  'SELECT 1'
);
PREPARE stmt_op FROM @sql_op;
EXECUTE stmt_op;
DEALLOCATE PREPARE stmt_op;

UPDATE usuario SET operador = 1 WHERE usuario = 'dueno' AND id_empresa = 4;
