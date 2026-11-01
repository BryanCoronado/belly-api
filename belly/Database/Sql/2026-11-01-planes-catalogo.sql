-- Actualiza el texto de los planes: todos incluyen Mi Catálogo.
SET NAMES utf8mb4;

UPDATE plan SET descripcion = 'Chats, Mi Catálogo y un equipo chico para probar.' WHERE clave = 'gratis' AND deleted_at IS NULL;
UPDATE plan SET descripcion = 'Atención diaria, Mi Catálogo, flujos y un par de equipos.' WHERE clave = 'base' AND deleted_at IS NULL;
UPDATE plan SET descripcion = 'Más chats, Mi Catálogo, envíos y el asistente IA.' WHERE clave = 'pro' AND deleted_at IS NULL;
UPDATE plan SET descripcion = 'Sin tope. Incluye Mi Catálogo, IA, equipos y flujos.' WHERE clave = 'empresa' AND deleted_at IS NULL;
