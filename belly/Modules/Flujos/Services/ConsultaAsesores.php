<?php

namespace Belly\Flujos\Services;

/**
 * Qué personas cuentan como «asesor»: usuarios activos de la empresa cuyo rol permite atender el Chat.
 * (También se reconoce el rol histórico «Personal de Contact Center».)
 */
final class ConsultaAsesores
{
    /** Permiso «Chat» del catálogo de acceso. */
    private const PERMISO_CHAT = 6;
    /** Rol histórico «Personal de Contact Center». */
    private const ROL_HISTORICO = 5;

    /** Condición SQL sobre la tabla `usuario` con alias `u`. Lleva un parámetro: el id de la empresa. */
    public static function condicion(): string
    {
        return 'u.id_empresa = ? AND u.activo = 1 AND u.cuenta_bloqueada = 0 AND u.es_bot = 0 AND u.deleted_at IS NULL
            AND EXISTS (
                SELECT 1 FROM grupo_usuario_usuario g
                WHERE g.id_usuario = u.id AND g.deleted_at IS NULL
                  AND (
                    g.id_grupo_usuario_rol = ' . self::ROL_HISTORICO . '
                    OR EXISTS (
                        SELECT 1 FROM grupo_usuario_permiso_rol pr
                        WHERE pr.id_grupo_usuario_rol = g.id_grupo_usuario_rol
                          AND pr.id_permiso = ' . self::PERMISO_CHAT . '
                          AND pr.acc_lectura = 1 AND pr.deleted_at IS NULL
                    )
                  )
            )';
    }
}
