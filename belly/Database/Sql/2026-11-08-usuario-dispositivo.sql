-- Tokens Expo Push de la app de asesores.
-- Ejecutar en bases ya existentes.

CREATE TABLE IF NOT EXISTS usuario_dispositivo (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_usuario INT UNSIGNED NOT NULL,
  id_empresa INT UNSIGNED NOT NULL,
  expo_push_token VARCHAR(255) NOT NULL,
  plataforma ENUM('ios','android') NOT NULL DEFAULT 'android',
  nombre VARCHAR(120) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  id_conversacion_abierta INT UNSIGNED NULL COMMENT 'Chat abierto en primer plano; evita push duplicado',
  visto_en DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_dispositivo_token (expo_push_token),
  KEY idx_usuario_dispositivo_usuario (id_usuario, activo),
  KEY idx_usuario_dispositivo_empresa (id_empresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
