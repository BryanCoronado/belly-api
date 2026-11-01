-- Historial de pagos de planes. Ejecutar en la base.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pago (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_empresa INT UNSIGNED NOT NULL,
  id_plan INT UNSIGNED NULL,
  periodo CHAR(7) NOT NULL,
  monto DECIMAL(10,2) NOT NULL DEFAULT 0,
  moneda CHAR(3) NOT NULL DEFAULT 'PEN',
  estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  metodo VARCHAR(40) NULL,
  referencia VARCHAR(80) NULL,
  nota VARCHAR(180) NULL,
  vencimiento DATE NULL,
  pagado_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_pago_empresa_periodo (id_empresa, periodo),
  CONSTRAINT fk_pago_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pago_plan FOREIGN KEY (id_plan) REFERENCES plan(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO pago (id_empresa, id_plan, periodo, monto, moneda, estado, vencimiento, created_at)
SELECT e.id, e.id_plan, DATE_FORMAT(NOW(), '%Y-%m'), p.precio_mensual, p.moneda, 'pendiente', LAST_DAY(NOW()), NOW()
FROM empresa e
JOIN plan p ON p.id = e.id_plan
WHERE e.deleted_at IS NULL
  AND p.precio_mensual > 0
  AND e.id <> 4
  AND NOT EXISTS (
    SELECT 1 FROM pago x
    WHERE x.id_empresa = e.id AND x.periodo = DATE_FORMAT(NOW(), '%Y-%m') AND x.estado IN ('pendiente', 'pagado')
  );
