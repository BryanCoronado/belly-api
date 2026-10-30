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
    public const CATALOGO = 3;
    public const INTEGRACIONES = 4;

    public const ID_PERMISO_CHAT = 6;

    /**
     * Ids de permisos y módulos que existieron antes y ya no forman parte del producto.
     * `catalogo:sincronizar` los elimina de bases existentes.
     */
    public const PERMISOS_RETIRADOS = [3, 4, 7, 8, 9, 10, 12, 13, 14, 20, 21, 22, 23, 24, 37, 45];
    public const MODULOS_RETIRADOS = [3, 4, 7, 8, 9, 10, 12, 13, 14, 15, 16, 17, 18, 19, 37];

    /** @return list<array{id:int, grupo:string, descripcion:string}> */
    public static function grupos(): array
    {
        return [
            ['id' => self::GENERAL, 'grupo' => 'General', 'descripcion' => 'Acceso base y resumen de la empresa.'],
            ['id' => self::ATENCION, 'grupo' => 'Atención', 'descripcion' => 'Chat, clientes, asesores y respuestas automáticas.'],
            ['id' => self::CATALOGO, 'grupo' => 'Catálogo', 'descripcion' => 'Productos y familias de productos de la empresa.'],
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

            self::permiso(5, self::ATENCION, null, 'mod_leads_access', 'Clientes', 'Base de clientes de la empresa.', orden: 30),
            self::permiso(32, self::ATENCION, 5, 'mod_leads_clientes_access', 'Todos los clientes', 'Ver y editar clientes.', orden: 31),
            self::permiso(31, self::ATENCION, 5, 'mod_leads_lista_negra_access', 'Lista negra', 'Números bloqueados.', orden: 32),

            self::permiso(48, self::ATENCION, null, 'mod_envios_masivos_access', 'Envíos masivos', 'Mandar mensajes y plantillas de WhatsApp a muchos clientes a la vez.', wa: true, orden: 35),

            self::permiso(11, self::ATENCION, null, 'mod_asesores_access', 'Asesores', 'Ver y crear a las personas que atienden los chats.', admin: true, orden: 40),

            self::permiso(46, self::ATENCION, null, 'mod_flujos_access', 'Respuestas automáticas', 'Armar el asistente automático de WhatsApp con botones y respuestas.', wa: true, admin: true, orden: 55),
            self::permiso(47, self::ATENCION, null, 'mod_agente_ia_access', 'Asistente IA', 'Asistentes con inteligencia artificial que atienden WhatsApp.', admin: true, orden: 56),

            self::permiso(38, self::CATALOGO, null, 'mod_producto_access', 'Productos', 'Crear y ordenar los productos y sus familias.', admin: true, orden: 60),

            self::permiso(35, self::INTEGRACIONES, null, 'mod_configuracion_conversacion_access', 'WhatsApp', 'Conectar el número de WhatsApp de la empresa.', admin: true, orden: 80),
            self::permiso(49, self::INTEGRACIONES, null, 'mod_costos_whatsapp_access', 'Costos WhatsApp', 'Cupo de 1.000 mensajes y lo que Meta cobra este mes.', wa: true, admin: true, orden: 81),
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

            self::modulo(5, 5, self::ATENCION, null, 'Clientes', 'mod_leads', '/leads', 'person_alt', 3),
            self::modulo(26, 32, self::ATENCION, 5, 'Todos los clientes', 'mod_leads_clientes', '/leads/clientes', 'people', 1),
            self::modulo(25, 31, self::ATENCION, 5, 'Lista negra', 'mod_leads_lista_negra', '/leads/lista-negra', 'person', 2),

            self::modulo(40, 48, self::ATENCION, null, 'Envíos masivos', 'mod_envios_masivos', '/envios', 'send', 4),

            self::modulo(11, 11, self::ATENCION, null, 'Asesores', 'mod_asesores', '/asesores', 'person_alt', 5),

            self::modulo(38, 46, self::ATENCION, null, 'Respuestas automáticas', 'mod_flujos', '/automatizacion', 'bot', 6),
            self::modulo(39, 47, self::ATENCION, null, 'Asistente IA', 'mod_agente_ia', '/agente-ia', 'sparkles', 7),

            self::modulo(31, 38, self::CATALOGO, null, 'Productos', 'mod_producto', '/productos', 'store', 1),

            self::modulo(29, 35, self::INTEGRACIONES, null, 'WhatsApp', 'mod_configuracion', '/integraciones/whatsapp', 'whatsapp', 1),
            self::modulo(41, 49, self::INTEGRACIONES, null, 'Costos WhatsApp', 'mod_costos_whatsapp', '/integraciones/costos', 'wallet', 2),
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
