<?php

/**
 * Publica avisos en el servidor de tiempo real (carpeta socket/).
 * Solo viajan identificadores: el front vuelve a pedir los datos al API, que aplica los permisos.
 */
class SocketHelper
{
    public static function emitirMensaje($conversacionId, $mensaje = null, $idUsuario = null): bool
    {
        return self::emitir('mensaje', ['id_conversacion' => (int) $conversacionId]);
    }

    public static function emitirConversacion($conversacionData): bool
    {
        return self::emitir('conversacion', ['id_conversacion' => self::idConversacion($conversacionData)]);
    }

    public static function emitirActualizacionConversacion($conversacionData): bool
    {
        return self::emitir('conversacion', ['id_conversacion' => self::idConversacion($conversacionData)]);
    }

    public static function emitirEstadoMensaje($mensajeId, $nuevoEstado, $datosMensaje = null): bool
    {
        return self::emitir('mensaje', ['id_conversacion' => (int) ($datosMensaje['id_conversacion'] ?? 0)]);
    }

    public static function emitirActualizacionContadores($conversacionId): bool
    {
        return self::emitir('contadores', ['id_conversacion' => (int) $conversacionId]);
    }

    private static function idConversacion($datos): int
    {
        $datos = (array) $datos;

        return (int) ($datos['id'] ?? $datos['id_conversacion'] ?? 0);
    }

    /** Nunca debe frenar ni romper la petición: si el socket está caído, se registra y se sigue. */
    private static function emitir(string $evento, array $datos): bool
    {
        $clave = trim((string) env('SOCKET_CLAVE', ''));
        if ($clave === '') {
            return false;
        }

        if (! function_exists('id_empresa_sesion')) {
            helper('vip2system');
        }
        $idEmpresa = id_empresa_sesion();
        if (! $idEmpresa) {
            log_message('warning', "[SOCKET] Evento {$evento} sin empresa en contexto; no se emite.");

            return false;
        }

        $url = rtrim((string) (env('SOCKET_URL') ?: 'http://127.0.0.1:4000'), '/') . '/emitir';
        $cuerpo = json_encode(['evento' => $evento, 'id_empresa' => $idEmpresa, 'datos' => $datos]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $cuerpo,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Socket-Clave: ' . $clave],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 300,
            CURLOPT_TIMEOUT_MS => 800,
            CURLOPT_NOSIGNAL => true,
        ]);
        curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($codigo !== 200) {
            log_message('warning', "[SOCKET] No se pudo emitir {$evento} (HTTP {$codigo}) {$error}");

            return false;
        }

        return true;
    }
}

if (! function_exists('emitir_mensaje_socket')) {
    function emitir_mensaje_socket($conversacionId, $mensaje = null, $idUsuario = null)
    {
        return SocketHelper::emitirMensaje($conversacionId, $mensaje, $idUsuario);
    }
}

if (! function_exists('emitir_conversacion_socket')) {
    function emitir_conversacion_socket($conversacionData)
    {
        return SocketHelper::emitirConversacion($conversacionData);
    }
}

if (! function_exists('emitir_actualizacion_conversacion_socket')) {
    function emitir_actualizacion_conversacion_socket($conversacionData)
    {
        return SocketHelper::emitirActualizacionConversacion($conversacionData);
    }
}

if (! function_exists('emitir_estado_mensaje_socket')) {
    function emitir_estado_mensaje_socket($mensajeId, $nuevoEstado, $datosMensaje = null)
    {
        return SocketHelper::emitirEstadoMensaje($mensajeId, $nuevoEstado, $datosMensaje);
    }
}

if (! function_exists('calcular_y_emitir_contadores_no_leidos')) {
    function calcular_y_emitir_contadores_no_leidos($id_conversacion)
    {
        return SocketHelper::emitirActualizacionContadores($id_conversacion);
    }
}
