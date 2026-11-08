-- Pedidos del catálogo web (carrito → WhatsApp).
-- Ejecutar en bases ya existentes.

CREATE TABLE IF NOT EXISTS pedido_catalogo (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_empresa INT UNSIGNED NOT NULL,
  id_cliente INT UNSIGNED NULL,
  id_conversacion INT UNSIGNED NULL,
  tienda VARCHAR(150) NULL,
  cliente_nombre VARCHAR(120) NULL,
  nota TEXT NULL,
  items LONGTEXT NULL,
  total DECIMAL(12,2) NULL,
  moneda VARCHAR(3) NOT NULL DEFAULT 'PEN',
  estado VARCHAR(20) NOT NULL DEFAULT 'nuevo',
  texto TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_pedido_catalogo_empresa (id_empresa, estado, created_at),
  CONSTRAINT fk_pedido_catalogo_empresa FOREIGN KEY (id_empresa) REFERENCES empresa (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
