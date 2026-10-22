<?php

namespace Belly\Usuarios\Services;

/**
 * Qué personas cuentan como «asesor»: usuarios de la empresa con un rol que permite atender el Chat.
 * Los administradores no son asesores: gestionan la empresa, no reciben chats por reparto.
 */
final class ConsultaAsesores
{
    /** Permiso «Chat» del catálogo de acceso. */
    private const PERMISO_CHAT = 6;

    /**
     * Condición SQL sobre la tabla `usuario` con alias `u`. Lleva un parámetro: el id de la empresa.
     *
     * @param bool $soloDisponibles true: solo activos y no bloqueados (los que pueden recibir chats).
     */
    public static function condicion(bool $soloDisponibles = true): string
    {
        $disponibles = $soloDisponibles ? ' AND u.activo = 1 AND u.cuenta_bloqueada = 0' : '';

        return 'u.id_empresa = ?' . $disponibles . ' AND u.es_bot = 0 AND u.sudo = 0 AND u.deleted_at IS NULL
            AND EXISTS (
                SELECT 1 FROM grupo_usuario_usuario g
                JOIN grupo_usuario_rol r ON r.id = g.id_grupo_usuario_rol AND r.es_admin = 0 AND r.deleted_at IS NULL
                JOIN grupo_usuario_permiso_rol pr ON pr.id_grupo_usuario_rol = r.id
                    AND pr.id_permiso = ' . self::PERMISO_CHAT . '
                    AND pr.acc_lectura = 1 AND pr.deleted_at IS NULL
                WHERE g.id_usuario = u.id AND g.deleted_at IS NULL
            )';
    }
}
