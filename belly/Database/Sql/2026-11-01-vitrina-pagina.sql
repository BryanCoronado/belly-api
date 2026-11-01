-- Mini web del catálogo: banners, aviso, sobre, horario y carrito. Ejecutar en la base.

SET NAMES utf8mb4;

ALTER TABLE vitrina
  ADD COLUMN pagina LONGTEXT NULL AFTER publicado;
