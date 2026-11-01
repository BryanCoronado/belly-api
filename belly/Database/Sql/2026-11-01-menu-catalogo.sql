-- Mi Catálogo en el menú (grupo Catálogo, junto a Productos).
-- Ejecutar en la base. No crea tablas nuevas.

SET NAMES utf8mb4;

UPDATE grupo_permiso
SET grupo = 'Catálogo',
    descripcion = 'Productos, familias y la tienda pública de la empresa.'
WHERE id = 3;

UPDATE modulo_grupo
SET grupo = 'Catálogo'
WHERE id = 3;

INSERT INTO permiso (
  id, id_grupo_permiso, id_permiso_padre, permiso, alias, descripcion,
  tipo, requiere_whatsapp, solo_admin, orden, activo, created_at
) VALUES (
  50, 3, NULL, 'Mi Catálogo', 'mod_vitrina_access',
  'Armar la tienda pública: banners, enlace, QR y vitrina.',
  'modulo', 0, 1, 61, 1, NOW()
)
ON DUPLICATE KEY UPDATE
  id_grupo_permiso = VALUES(id_grupo_permiso),
  id_permiso_padre = VALUES(id_permiso_padre),
  permiso = VALUES(permiso),
  alias = VALUES(alias),
  descripcion = VALUES(descripcion),
  tipo = VALUES(tipo),
  requiere_whatsapp = VALUES(requiere_whatsapp),
  solo_admin = VALUES(solo_admin),
  orden = VALUES(orden),
  activo = 1,
  deleted_at = NULL;

INSERT INTO modulo (
  id, id_permiso, id_modulo_grupo, parent_id, nombre, alias, descripcion,
  activo, orden, url, icon, created_at
) VALUES (
  42, 50, 3, NULL, 'Mi Catálogo', 'mod_vitrina', 'Mi Catálogo',
  1, 2, '/catalogo', 'book', NOW()
)
ON DUPLICATE KEY UPDATE
  id_permiso = VALUES(id_permiso),
  id_modulo_grupo = VALUES(id_modulo_grupo),
  parent_id = VALUES(parent_id),
  nombre = VALUES(nombre),
  alias = VALUES(alias),
  descripcion = VALUES(descripcion),
  activo = 1,
  orden = VALUES(orden),
  url = VALUES(url),
  icon = VALUES(icon),
  deleted_at = NULL;
