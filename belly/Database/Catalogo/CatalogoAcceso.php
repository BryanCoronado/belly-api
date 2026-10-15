<?php

namespace Belly\Database\Catalogo;

/**
 * Fuente única de grupos, permisos y módulos del menú.
 * La usan los seeders (instalación nueva) y `php spark catalogo:sincronizar` (bases existentes).
 * Los ids son estables: los referencian los permisos guardados de cada rol.
 */
final class CatalogoAcceso
{
    public const GENERAL = 1;
    public const ATENCION = 2;
    public const MARKETING = 3;
    public const INTEGRACIONES = 4;

    public const ID_PERMISO_CHAT = 6;

    /** @return list<array{id:int, grupo:string, descripcion:string}> */
    public static function grupos(): array
    {
        return [
            ['id' => self::GENERAL, 'grupo' => 'General', 'descripcion' => 'Acceso base y resumen de la empresa.'],
            ['id' => self::ATENCION, 'grupo' => 'Atención', 'descripcion' => 'Chat, conversaciones, clientes y métricas de atención.'],
            ['id' => self::MARKETING, 'grupo' => 'Marketing', 'descripcion' => 'Material de marketing y productos.'],
            ['id' => self::INTEGRACIONES, 'grupo' => 'Integraciones', 'descripcion' => 'Conexiones con servicios externos.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function permisos(): array
    {
        $chat = self::ID_PERMISO_CHAT;

        return [
            self::permiso(1, self::GENERAL, null, 'mod_sistemas_access', 'Acceso al sistema', 'Permiso base para usar Belly.', orden: 1),
            self::permiso(2, self::GENERAL, null, 'mod_dashboard_access', 'Dashboard', 'Ver el resumen de la empresa.', orden: 2),

            self::permiso($chat, self::ATENCION, null, 'mod_conversaciones_chat_access', 'Chat', 'Atender las conversaciones de WhatsApp asignadas.', wa: true, orden: 10),
            self::permiso(34, self::ATENCION, $chat, 'mod_conversaciones_chat_mis_conversaciones_access', 'Mis conversaciones', 'Bandeja de chats asignados.', wa: true, orden: 11),
            self::permiso(33, self::ATENCION, $chat, 'mod_conversaciones_chat_mis_clientes_access', 'Mis clientes', 'Clientes que atiende el usuario.', wa: true, orden: 12),
            self::permiso(39, self::ATENCION, $chat, 'mod_mis_plantillas_rapidas_access', 'Respuestas rápidas', 'Textos guardados para responder más rápido.', wa: true, orden: 13),
            self::accion(15, $chat, 'imagenes_access', 'Enviar imágenes', 'Enviar y ver imágenes en el chat', 14),
            self::accion(16, $chat, 'videos_access', 'Enviar videos', 'Enviar y ver videos en el chat', 15),
            self::accion(17, $chat, 'documentos_access', 'Enviar documentos', 'Enviar y ver documentos en el chat', 16),
            self::accion(18, $chat, 'camara_access', 'Usar la cámara', 'Tomar fotos con la cámara desde el chat', 17),
            self::accion(19, $chat, 'plantillas_mensajes_access', 'Enviar plantillas de WhatsApp', 'Enviar plantillas aprobadas por Meta desde el chat', 18),
            self::accion(37, $chat, 'catalogo_access', 'Compartir catálogo', 'Compartir productos del catálogo en el chat', 19),

            self::permiso(3, self::ATENCION, null, 'mod_conversaciones_access', 'Conversaciones', 'Supervisar las conversaciones de todo el equipo.', wa: true, orden: 20),
            self::permiso(7, self::ATENCION, 3, 'mod_conversaciones_contact_center', 'De asesores', 'Conversaciones del equipo de ventas.', wa: true, orden: 21),
            self::permiso(8, self::ATENCION, 3, 'mod_conversaciones_customer_success', 'De customer success', 'Conversaciones del equipo de postventa.', wa: true, orden: 22),

            self::permiso(5, self::ATENCION, null, 'mod_leads_access', 'Clientes', 'Base de clientes de la empresa.', orden: 30),
            self::permiso(32, self::ATENCION, 5, 'mod_leads_clientes_access', 'Todos los clientes', 'Ver y editar clientes.', orden: 31),
            self::permiso(31, self::ATENCION, 5, 'mod_leads_lista_negra_access', 'Lista negra', 'Números bloqueados.', orden: 32),

            self::permiso(11, self::ATENCION, null, 'mod_asesores_access', 'Asesores', 'Gestionar a quienes atienden chats.', wa: true, orden: 40),
            self::permiso(12, self::ATENCION, 11, 'mod_asesores_asesores_contac_center_access', 'De venta', 'Asesores de venta.', wa: true, orden: 41),
            self::permiso(13, self::ATENCION, 11, 'mod_metricas_asesores_customer_success_access', 'De customer success', 'Asesores de postventa.', wa: true, orden: 42),

            self::permiso(46, self::ATENCION, null, 'mod_flujos_access', 'Respuestas automáticas', 'Armar el asistente automático de WhatsApp con botones y respuestas.', wa: true, admin: true, orden: 55),

            self::permiso(4, self::ATENCION, null, 'mod_metricas_access', 'Métricas', 'Indicadores de atención.', wa: true, orden: 50),
            self::permiso(9, self::ATENCION, 4, 'mod_metricas_metricas_contact_center_access', 'De asesores', 'Métricas del equipo de ventas.', wa: true, orden: 51),
            self::permiso(10, self::ATENCION, 4, 'mod_metricas_metricas_customer_success_access', 'De customer success', 'Métricas del equipo de postventa.', wa: true, orden: 52),
            self::permiso(45, self::ATENCION, 4, 'mod_metricas_origenes_access', 'Por origen', 'De dónde llegan las conversaciones.', wa: true, orden: 53),

            self::permiso(14, self::MARKETING, null, 'mod_marketing_access', 'Marketing', 'Material de marketing.', orden: 60),
            self::permiso(20, self::MARKETING, 14, 'mod_marketing_imagenes_access', 'Imágenes', 'Galería de imágenes.', orden: 61),
            self::permiso(21, self::MARKETING, 14, 'mod_marketing_videos_access', 'Videos', 'Galería de videos.', orden: 62),
            self::permiso(22, self::MARKETING, 14, 'mod_marketing_plantillas_access', 'Plantillas de WhatsApp', 'Crear plantillas para Meta.', wa: true, orden: 63),
            self::permiso(23, self::MARKETING, 14, 'mod_metricas_marketing_access', 'Métricas de marketing', 'Resultados de campañas.', wa: true, orden: 64),
            self::permiso(38, self::MARKETING, null, 'mod_producto_access', 'Productos', 'Catálogo de productos.', orden: 70),

            self::permiso(35, self::INTEGRACIONES, null, 'mod_configuracion_conversacion_access', 'WhatsApp', 'Conectar el número de WhatsApp de la empresa.', admin: true, orden: 80),
            self::permiso(24, self::INTEGRACIONES, null, 'mod_ventas_access', 'Ventas', 'Integración de ventas.', orden: 81),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function modulos(): array
    {
        return [
            self::modulo(2, 2, self::GENERAL, null, 'Dashboard', 'mod_dashboard', '/dashboard', 'house_alt', 1),

            self::modulo(6, 6, self::ATENCION, null, 'Chat', 'mod_conversaciones_chat', '/conversaciones/chat', 'message', 1),
            self::modulo(28, 34, self::ATENCION, 6, 'Mis conversaciones', 'mod_mis_conversaciones', '/conversaciones/chat/mis_conversaciones', 'chat', 1),
            self::modulo(27, 33, self::ATENCION, 6, 'Mis clientes', 'mod_mis_clientes', '/conversaciones/chat/mis_clientes', 'people', 2),
            self::modulo(32, 39, self::ATENCION, 6, 'Respuestas rápidas', 'mod_mis_plantillas_rapidas', '/conversaciones/chat/mis_plantillas', 'message', 3),

            self::modulo(3, 3, self::ATENCION, null, 'Conversaciones', 'mod_conversaciones', '/conversaciones', 'chat_bubble', 2),
            self::modulo(7, 7, self::ATENCION, 3, 'De asesores', 'mod_conversaciones_contac_center', '/conversaciones/contact_center', 'tag', 1),
            self::modulo(8, 8, self::ATENCION, 3, 'De customer success', 'mod_conversaciones_customer_success', '/conversaciones/customer_success', 'tag', 2),

            self::modulo(5, 5, self::ATENCION, null, 'Clientes', 'mod_leads', '/leads', 'person_alt', 3),
            self::modulo(26, 32, self::ATENCION, 5, 'Todos los clientes', 'mod_leads_clientes', '/leads/clientes', 'people', 1),
            self::modulo(25, 31, self::ATENCION, 5, 'Lista negra', 'mod_leads_lista_negra', '/leads/lista-negra', 'person', 2),

            self::modulo(11, 11, self::ATENCION, null, 'Asesores', 'mod_asesores', '/asesores', 'person_alt', 4),
            self::modulo(12, 12, self::ATENCION, 11, 'De venta', 'mod_asesores_asesores_contac_center', '/asesores/contact_center', 'person_alt', 1),
            self::modulo(13, 13, self::ATENCION, 11, 'De customer success', 'mod_asesores_asesores_customer_success', '/asesores/customer_success', 'person_alt', 2),

            self::modulo(38, 46, self::ATENCION, null, 'Respuestas automáticas', 'mod_flujos', '/automatizacion', 'bot', 6),

            self::modulo(4, 4, self::ATENCION, null, 'Métricas', 'mod_metricas', '/metricas', 'chart_bar_alt_fill', 5),
            self::modulo(9, 9, self::ATENCION, 4, 'De asesores', 'mod_metricas_metricas_contact_center', '/metricas/contact_center', 'tag', 1),
            self::modulo(10, 10, self::ATENCION, 4, 'De customer success', 'mod_metricas_metricas_customer_success', '/metricas/customer_success', 'tag', 2),
            self::modulo(37, 45, self::ATENCION, 4, 'Por origen', 'mod_metricas_origenes', '/metricas/origenes', 'chart_bar_alt_fill', 3),

            self::modulo(14, 14, self::MARKETING, null, 'Marketing', 'mod_marketing', '/marketing', 'campaign', 1),
            self::modulo(15, 20, self::MARKETING, 14, 'Imágenes', 'mod_marketing_imagenes', '/marketing/image', 'photo_fill', 1),
            self::modulo(16, 21, self::MARKETING, 14, 'Videos', 'mod_marketing_videos', '/marketing/video', 'videocam_fill', 2),
            self::modulo(17, 22, self::MARKETING, 14, 'Plantillas de WhatsApp', 'mod_marketing_plantillas', '/marketing/plantilla', 'document_fill', 3),
            self::modulo(18, 23, self::MARKETING, 14, 'Métricas', 'mod_metricas_marketing', '/metricas/marketing', 'chart_bar_alt_fill', 4),
            self::modulo(31, 38, self::MARKETING, null, 'Productos', 'mod_producto', '/productos', 'store', 2),

            self::modulo(29, 35, self::INTEGRACIONES, null, 'WhatsApp', 'mod_configuracion', '/integraciones/whatsapp', 'whatsapp', 1),
            self::modulo(19, 24, self::INTEGRACIONES, null, 'Ventas', 'mod_ventas', '/ventas', 'shopping_cart', 2),
        ];
    }

    /** @return array<string, mixed> */
    private static function permiso(
        int $id,
        int $grupo,
        ?int $padre,
        string $alias,
        string $nombre,
        string $descripcion,
        bool $wa = false,
        bool $admin = false,
        int $orden = 0,
        string $tipo = 'modulo',
    ): array {
        return [
            'id' => $id,
            'id_grupo_permiso' => $grupo,
            'id_permiso_padre' => $padre,
            'permiso' => $nombre,
            'alias' => $alias,
            'descripcion' => $descripcion,
            'tipo' => $tipo,
            'requiere_whatsapp' => $wa ? 1 : 0,
            'solo_admin' => $admin ? 1 : 0,
            'orden' => $orden,
            'activo' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private static function accion(int $id, int $padre, string $alias, string $nombre, string $descripcion, int $orden): array
    {
        return self::permiso($id, self::ATENCION, $padre, $alias, $nombre, $descripcion, wa: true, orden: $orden, tipo: 'accion');
    }

    /** @return array<string, mixed> */
    private static function modulo(int $id, int $permiso, int $grupo, ?int $padre, string $nombre, string $alias, string $url, string $icono, int $orden): array
    {
        return [
            'id' => $id,
            'id_permiso' => $permiso,
            'id_modulo_grupo' => $grupo,
            'parent_id' => $padre,
            'nombre' => $nombre,
            'alias' => $alias,
            'descripcion' => $nombre,
            'activo' => 1,
            'orden' => $orden,
            'url' => $url,
            'icon' => $icono,
        ];
    }
}
