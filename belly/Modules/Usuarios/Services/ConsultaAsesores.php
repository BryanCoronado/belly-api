<?php

namespace Belly\Usuarios\Services;

/**
 * Quién cuenta como asesor: usuario de la empresa con un rol que no es administrador.
 * Ese es el mismo criterio en Asesores, grupos de atención y reparto de chats.
 * (Antes también se exigía permiso Chat; eso ocultaba a gente creada en Equipo.)
 */
final class ConsultaAsesores
{
    /**
     * Condición SQL sobre la tabla `usuario` con alias `u`. Lleva un parámetro: el id de la empresa.
     *
     * @param bool $soloDisponibles true: solo activos y no bloqueados (pueden recibir chats).
     */
    public static function condicion(bool $soloDisponibles = true): string
    {
        $disponibles = $soloDisponibles ? ' AND u.activo = 1 AND u.cuenta_bloqueada = 0' : '';

        return 'u.id_empresa = ?' . $disponibles . ' AND u.es_bot = 0 AND u.sudo = 0 AND u.deleted_at IS NULL
            AND EXISTS (
                SELECT 1 FROM grupo_usuario_usuario g
                JOIN grupo_usuario_rol r ON r.id = g.id_grupo_usuario_rol
                    AND r.es_admin = 0 AND r.deleted_at IS NULL
                WHERE g.id_usuario = u.id AND g.deleted_at IS NULL
            )';
    }
}
