-- DNI/RUC de cada empresa al registrarse.
SET NAMES utf8mb4;

SET @col_tipo := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'tipo_doc'
);
SET @sql_tipo := IF(@col_tipo = 0,
  'ALTER TABLE empresa ADD COLUMN tipo_doc VARCHAR(10) NULL AFTER modo_ahorro',
  'SELECT 1');
PREPARE stmt FROM @sql_tipo; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_num := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'numero_doc'
);
SET @sql_num := IF(@col_num = 0,
  'ALTER TABLE empresa ADD COLUMN numero_doc VARCHAR(20) NULL AFTER tipo_doc',
  'SELECT 1');
PREPARE stmt FROM @sql_num; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_razon := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'razon_social'
);
SET @sql_razon := IF(@col_razon = 0,
  'ALTER TABLE empresa ADD COLUMN razon_social VARCHAR(180) NULL AFTER numero_doc',
  'SELECT 1');
PREPARE stmt FROM @sql_razon; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_estado := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'estado_sunat'
);
SET @sql_estado := IF(@col_estado = 0,
  'ALTER TABLE empresa ADD COLUMN estado_sunat VARCHAR(80) NULL AFTER razon_social',
  'SELECT 1');
PREPARE stmt FROM @sql_estado; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_cond := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'condicion_sunat'
);
SET @sql_cond := IF(@col_cond = 0,
  'ALTER TABLE empresa ADD COLUMN condicion_sunat VARCHAR(80) NULL AFTER estado_sunat',
  'SELECT 1');
PREPARE stmt FROM @sql_cond; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_dir := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'direccion_fiscal'
);
SET @sql_dir := IF(@col_dir = 0,
  'ALTER TABLE empresa ADD COLUMN direccion_fiscal VARCHAR(255) NULL AFTER condicion_sunat',
  'SELECT 1');
PREPARE stmt FROM @sql_dir; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_ubi := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'ubigeo'
);
SET @sql_ubi := IF(@col_ubi = 0,
  'ALTER TABLE empresa ADD COLUMN ubigeo VARCHAR(20) NULL AFTER direccion_fiscal',
  'SELECT 1');
PREPARE stmt FROM @sql_ubi; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_rn := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'representante_nombre'
);
SET @sql_rn := IF(@col_rn = 0,
  'ALTER TABLE empresa ADD COLUMN representante_nombre VARCHAR(180) NULL AFTER ubigeo',
  'SELECT 1');
PREPARE stmt FROM @sql_rn; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_rc := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'representante_cargo'
);
SET @sql_rc := IF(@col_rc = 0,
  'ALTER TABLE empresa ADD COLUMN representante_cargo VARCHAR(120) NULL AFTER representante_nombre',
  'SELECT 1');
PREPARE stmt FROM @sql_rc; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_rd := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'representante_documento'
);
SET @sql_rd := IF(@col_rd = 0,
  'ALTER TABLE empresa ADD COLUMN representante_documento VARCHAR(20) NULL AFTER representante_cargo',
  'SELECT 1');
PREPARE stmt FROM @sql_rd; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_json := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND COLUMN_NAME = 'identidad_json'
);
SET @sql_json := IF(@col_json = 0,
  'ALTER TABLE empresa ADD COLUMN identidad_json LONGTEXT NULL AFTER representante_documento',
  'SELECT 1');
PREPARE stmt FROM @sql_json; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa' AND INDEX_NAME = 'idx_empresa_numero_doc'
);
SET @sql_idx := IF(@idx = 0,
  'ALTER TABLE empresa ADD INDEX idx_empresa_numero_doc (numero_doc)',
  'SELECT 1');
PREPARE stmt FROM @sql_idx; EXECUTE stmt; DEALLOCATE PREPARE stmt;
