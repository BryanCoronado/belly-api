<?php

/**
 * Publica avisos en el servidor de tiempo real (carpeta socket/).
 * El front refresca las listas y, si el aviso es para esa persona, suena y muestra el toast.
 */
class SocketHelper
{
    public static function emitirMensaje($conversacionId, $mensaje = null, $idUsuario = null): bool
    {
        $datos = self::avisoDeConversacion((int) $conversacionId, $mensaje);
        $datos['motivo'] = 'mensaje';

        return self::emitir('mensaje', $datos);
    }

    public static function emitirConversacion($conversacionData): bool
    {
        $datos = self::avisoDeConversacion(self::idConversacion($conversacionData), $conversacionData);
        $datos['motivo'] = 'asignada';

        return self::emitir('conversacion', $datos);
    }

    public static function emitirActualizacionConversacion($conversacionData): bool
    {
        $datos = self::avisoDeConversacion(self::idConversacion($conversacionData), $conversacionData);
        $datos['motivo'] = 'actualizacion';

        return self::emitir('conversacion', $datos);
    }

    public static function emitirEstadoMensaje($mensajeId, $nuevoEstado, $datosMensaje = null): bool
    {
        $datos = self::avisoDeConversacion((int) ($datosMensaje['id_conversacion'] ?? 0), $datosMensaje);
        $datos['motivo'] = 'estado';

        return self::emitir('mensaje', $datos);
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

    /** @param mixed $extra */
    private static function avisoDeConversacion(int $idConversacion, $extra = null): array
    {
        $extra = is_array($extra) ? $extra : (array) $extra;
        $aviso = [
            'id_conversacion' => $idConversacion,
            'id_usuario' => self::enteroONulo($extra['id_usuario'] ?? null),
            'es_bot' => 0,
            'id_aviso_ia' => null,
            'nombre' => self::textoONulo($extra['nombre_completo_cliente'] ?? $extra['nombre_cliente'] ?? $extra['nombre'] ?? null),
            'preview' => self::previewMensaje($extra),
            'contesta' => self::textoONulo($extra['contesta'] ?? $extra['ultimo_mensaje_contesta'] ?? null),
        ];

        if ($idConversacion <= 0) {
            return self::conDestinatarios($aviso);
        }

        try {
            $fila = self::contextoConversacion($idConversacion);
        } catch (\Throwable $e) {
            log_message('warning', '[SOCKET] No se pudo armar el aviso: ' . $e->getMessage());
            $fila = null;
        }

        if ($fila !== null) {
            $aviso['id_usuario'] = self::enteroONulo($fila['id_usuario'] ?? null);
            $aviso['es_bot'] = (int) ($fila['es_bot'] ?? 0);
            $aviso['id_aviso_ia'] = self::resolverAvisoIa((int) ($fila['id_empresa'] ?? 0), $fila['id_aviso_ia'] ?? null);
            if ($aviso['nombre'] === null) {
                $aviso['nombre'] = self::textoONulo($fila['nombre'] ?? null);
            }
        }

        return self::conDestinatarios($aviso);
    }

    /** @return array<string, mixed>|null */
    private static function contextoConversacion(int $idConversacion): ?array
    {
        $db = db_connect();
        $avisoIa = $db->fieldExists('id_aviso_ia', 'empresa') ? 'e.id_aviso_ia' : 'NULL AS id_aviso_ia';

        $fila = $db->query(
            'SELECT c.id_usuario, c.id_empresa, u.es_bot, ' . $avisoIa . ',
                    COALESCE(
                        NULLIF(TRIM(cp.nombre_completo), ""),
                        NULLIF(TRIM(CONCAT(IFNULL(cp.nombre, ""), " ", IFNULL(cp.apellido, ""))), ""),
                        cl.telefono
                    ) AS nombre
             FROM conversacion c
             LEFT JOIN usuario u ON u.id = c.id_usuario
             LEFT JOIN empresa e ON e.id = c.id_empresa
             LEFT JOIN cliente cl ON cl.id = c.id_cliente
             LEFT JOIN cliente_perfil cp ON cp.id_cliente = c.id_cliente AND cp.deleted_at IS NULL
             WHERE c.id = ?
             LIMIT 1',
            [$idConversacion],
        )->getRowArray();

        return is_array($fila) ? $fila : null;
    }

    private static function resolverAvisoIa(int $idEmpresa, mixed $configurado): ?int
    {
        $id = (int) $configurado;
        if ($id > 0) {
            return $id;
        }
        if ($idEmpresa <= 0) {
            return null;
        }

        $admin = db_connect()->table('usuario')
            ->select('id')
            ->where('id_empresa', $idEmpresa)
            ->where('sudo', 1)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRow();

        return $admin ? (int) $admin->id : null;
    }

    /** @param array<string, mixed> $extra */
    private static function previewMensaje(array $extra): ?string
    {
        $texto = $extra['mensaje'] ?? $extra['texto_ultimo_mensaje'] ?? $extra['preview'] ?? null;
        if (is_array($texto)) {
            $texto = $texto['mensaje'] ?? null;
        }
        $texto = trim((string) $texto);
        if ($texto === '' || $texto === '[Sin texto]') {
            $tipo = (string) ($extra['media_type'] ?? '');
            if ($tipo !== '') {
                return match ($tipo) {
                    'image', 'sticker' => 'Imagen',
                    'video' => 'Video',
                    'audio' => 'Audio',
                    'document' => 'Documento',
                    default => 'Archivo',
                };
            }

            return null;
        }

        return mb_substr($texto, 0, 140);
    }

    /** @param array<string, mixed> $aviso */
    private static function conDestinatarios(array $aviso): array
    {
        $destino = (int) ($aviso['es_bot'] ?? 0) === 1
            ? (int) ($aviso['id_aviso_ia'] ?? 0)
            : (int) ($aviso['id_usuario'] ?? 0);
        $aviso['para'] = $destino > 0 ? [$destino] : [];

        return $aviso;
    }

    private static function enteroONulo(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    private static function textoONulo(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : null;
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
            $idConversacion = (int) ($datos['id_conversacion'] ?? 0);
            if ($idConversacion > 0) {
                $fila = db_connect()->table('conversacion')->select('id_empresa')->where('id', $idConversacion)->get()->getRow();
                $idEmpresa = $fila ? (int) $fila->id_empresa : null;
            }
        }
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
