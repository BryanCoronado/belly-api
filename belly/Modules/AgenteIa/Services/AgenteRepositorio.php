<?php

namespace Belly\AgenteIa\Services;

/** Datos de los agentes de IA. Siempre recibe la empresa de forma explícita (también sirve desde el webhook). */
class AgenteRepositorio
{
    // ───────────── conexión con OpenAI ─────────────

    /** @return array{api_key: string, pista: string, verificada_at: ?string}|null */
    public function conexion(int $idEmpresa): ?array
    {
        $fila = db_connect()->table('ia_conexion')->where('id_empresa', $idEmpresa)->get()->getRowArray();
        if ($fila === null) {
            return null;
        }

        try {
            $llave = service('encrypter')->decrypt(base64_decode((string) $fila['api_key'], true) ?: '');
        } catch (\Throwable $e) {
            log_message('error', '[IA] No se pudo leer la llave guardada de la empresa ' . $idEmpresa . ': ' . $e->getMessage());

            return null;
        }

        return ['api_key' => $llave, 'pista' => (string) $fila['pista'], 'verificada_at' => $fila['verificada_at']];
    }

    public function guardarConexion(int $idEmpresa, string $apiKey, string $pista): void
    {
        $datos = [
            'api_key' => base64_encode(service('encrypter')->encrypt($apiKey)),
            'pista' => $pista,
            'verificada_at' => date('Y-m-d H:i:s'),
        ];
        if (db_connect()->table('ia_conexion')->where('id_empresa', $idEmpresa)->countAllResults() > 0) {
            db_connect()->table('ia_conexion')->where('id_empresa', $idEmpresa)->update($datos + ['updated_at' => date('Y-m-d H:i:s')]);

            return;
        }
        db_connect()->table('ia_conexion')->insert($datos + ['id_empresa' => $idEmpresa, 'proveedor' => 'openai']);
    }

    public function eliminarConexion(int $idEmpresa): void
    {
        db_connect()->table('ia_conexion')->where('id_empresa', $idEmpresa)->delete();
    }

    // ───────────── agentes ─────────────

    /** @return list<array<string, mixed>> */
    public function agentes(int $idEmpresa): array
    {
        $filas = db_connect()->table('agente_ia')
            ->where('id_empresa', $idEmpresa)->where('deleted_at', null)
            ->orderBy('nombre', 'ASC')->get()->getResultArray();

        return array_map(fn (array $f): array => $this->aArray($f), $filas);
    }

    /** @return array<string, mixed>|null */
    public function agente(int $idEmpresa, int $id): ?array
    {
        $fila = db_connect()->table('agente_ia')
            ->where('id_empresa', $idEmpresa)->where('id', $id)->where('deleted_at', null)
            ->get()->getRowArray();

        return $fila === null ? null : $this->aArray($fila);
    }

    /** @param array<string, mixed> $datos */
    public function insertarAgente(int $idEmpresa, array $datos): int
    {
        db_connect()->table('agente_ia')->insert($this->columnas($datos) + ['id_empresa' => $idEmpresa]);

        return (int) db_connect()->insertID();
    }

    /** @param array<string, mixed> $datos */
    public function actualizarAgente(int $idEmpresa, int $id, array $datos): void
    {
        db_connect()->table('agente_ia')
            ->where('id_empresa', $idEmpresa)->where('id', $id)->where('deleted_at', null)
            ->update($this->columnas($datos) + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    public function eliminarAgente(int $idEmpresa, int $id): void
    {
        db_connect()->table('agente_ia')->where('id_empresa', $idEmpresa)->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s'), 'activo' => 0]);
        db_connect()->table('agente_ia_sesion')->where('id_agente', $id)->where('estado', 'activa')->update(['estado' => 'terminada', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    // ───────────── conocimiento ─────────────

    /** @return list<array{id:int, titulo:string, contenido:string, actualizado:?string}> */
    public function conocimiento(int $idAgente): array
    {
        $filas = db_connect()->table('agente_ia_conocimiento')
            ->where('id_agente', $idAgente)->where('deleted_at', null)
            ->orderBy('id', 'ASC')->get()->getResultArray();

        return array_map(static fn (array $f): array => [
            'id' => (int) $f['id'],
            'titulo' => (string) $f['titulo'],
            'contenido' => (string) $f['contenido'],
            'actualizado' => $f['updated_at'] ?? $f['created_at'],
        ], $filas);
    }

    public function insertarConocimiento(int $idAgente, string $titulo, string $contenido): int
    {
        db_connect()->table('agente_ia_conocimiento')->insert(['id_agente' => $idAgente, 'titulo' => $titulo, 'contenido' => $contenido]);

        return (int) db_connect()->insertID();
    }

    public function actualizarConocimiento(int $idAgente, int $id, string $titulo, string $contenido): bool
    {
        db_connect()->table('agente_ia_conocimiento')
            ->where('id_agente', $idAgente)->where('id', $id)->where('deleted_at', null)
            ->update(['titulo' => $titulo, 'contenido' => $contenido, 'updated_at' => date('Y-m-d H:i:s')]);

        return db_connect()->affectedRows() > 0;
    }

    public function eliminarConocimiento(int $idAgente, int $id): void
    {
        db_connect()->table('agente_ia_conocimiento')
            ->where('id_agente', $idAgente)->where('id', $id)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    // ───────────── sesiones de atención ─────────────

    /** @return array<string, mixed>|null */
    public function sesionActiva(int $idConversacion): ?array
    {
        return db_connect()->table('agente_ia_sesion')
            ->where('id_conversacion', $idConversacion)->where('estado', 'activa')
            ->orderBy('id', 'DESC')->get(1)->getRowArray();
    }

    public function crearSesion(int $idEmpresa, int $idConversacion, int $idAgente, ?string $nodo): void
    {
        db_connect()->table('agente_ia_sesion')->insert([
            'id_empresa' => $idEmpresa,
            'id_conversacion' => $idConversacion,
            'id_agente' => $idAgente,
            'nodo_reparto' => $nodo,
            'estado' => 'activa',
        ]);
    }

    /** @param array<string, mixed> $datos */
    public function actualizarSesion(int $id, array $datos): void
    {
        db_connect()->table('agente_ia_sesion')->where('id', $id)->update($datos + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    public function terminarSesiones(int $idConversacion, string $estado, ?string $motivo = null): void
    {
        db_connect()->table('agente_ia_sesion')
            ->where('id_conversacion', $idConversacion)->where('estado', 'activa')
            ->update(['estado' => $estado, 'motivo_traspaso' => $motivo, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** @return array<int, int> id_agente => chats atendidos hoy */
    public function atendidosHoy(int $idEmpresa): array
    {
        $filas = db_connect()->table('agente_ia_sesion')
            ->select('id_agente, COUNT(*) AS total')
            ->where('id_empresa', $idEmpresa)->where('DATE(created_at) = CURDATE()', null, false)
            ->groupBy('id_agente')->get()->getResultArray();

        return array_column(array_map(static fn (array $f): array => ['id' => (int) $f['id_agente'], 't' => (int) $f['total']], $filas), 't', 'id');
    }

    // ───────────── contexto de la conversación ─────────────

    /** Últimos mensajes del chat, del más viejo al más nuevo. @return list<array{contesta:string, mensaje:string, media_type:?string}> */
    public function historial(int $idConversacion, int $limite): array
    {
        $filas = db_connect()->table('mensaje')
            ->select('contesta, mensaje, media_type')
            ->where('id_conversacion', $idConversacion)->where('deleted_at', null)
            ->orderBy('id', 'DESC')->limit($limite)->get()->getResultArray();

        return array_reverse($filas);
    }

    /** Productos activos como texto simple para la IA. */
    public function catalogoTexto(int $idEmpresa): string
    {
        $filas = db_connect()->table('producto p')
            ->select('p.nombre, p.descripcion, p.codigo, p.precio, p.moneda, p.unidad, p.detalles, f.nombre AS familia')
            ->join('familia_producto f', 'f.id = p.id_familia AND f.deleted_at IS NULL', 'left')
            ->where('p.id_empresa', $idEmpresa)->where('p.activo', 1)->where('p.deleted_at', null)
            ->orderBy('f.orden', 'ASC')->orderBy('p.nombre', 'ASC')->limit(300)
            ->get()->getResultArray();

        $lineas = [];
        foreach ($filas as $f) {
            $precio = $f['precio'] === null ? 'a consultar' : $f['moneda'] . ' ' . number_format((float) $f['precio'], 2, '.', '') . ($f['unidad'] ? ' por ' . $f['unidad'] : '');
            $extra = [];
            foreach ((array) json_decode((string) ($f['detalles'] ?? '[]'), true) as $d) {
                if (is_array($d) && ($d['nombre'] ?? '') !== '') {
                    $extra[] = $d['nombre'] . ': ' . ($d['valor'] ?? '');
                }
            }
            $lineas[] = '- ' . ($f['familia'] ? '[' . $f['familia'] . '] ' : '') . $f['nombre'] . ' — ' . $precio
                . ($f['descripcion'] ? '. ' . $f['descripcion'] : '')
                . ($extra !== [] ? ' (' . implode('; ', $extra) . ')' : '');
        }

        return implode("\n", $lineas);
    }

    public function nombreEmpresa(int $idEmpresa): string
    {
        $fila = db_connect()->table('empresa')->where('id', $idEmpresa)->get()->getRowArray();

        return trim((string) ($fila['nombre'] ?? $fila['razon_social'] ?? '')) ?: 'nuestra empresa';
    }

    public function nombreCliente(int $idConversacion): ?string
    {
        $fila = db_connect()->table('conversacion cv')
            ->select('p.nombre, p.nombre_completo')
            ->join('cliente_perfil p', 'p.id_cliente = cv.id_cliente AND p.deleted_at IS NULL', 'left')
            ->where('cv.id', $idConversacion)->get()->getRowArray();

        $nombre = trim((string) ($fila['nombre'] ?? '')) ?: trim((string) ($fila['nombre_completo'] ?? ''));

        return $nombre !== '' ? $nombre : null;
    }

    /**
     * Usuario «Asistente IA» de la empresa: figura como dueño del chat mientras lo atiende la IA.
     * No puede iniciar sesión (cuenta bloqueada y contraseña al azar) ni recibe chats por reparto (es_bot).
     */
    public function usuarioAsistente(int $idEmpresa): int
    {
        $db = db_connect();
        $usuario = 'asistente.ia';
        $fila = $db->table('usuario')->select('id')->where('id_empresa', $idEmpresa)->where('usuario', $usuario)->get()->getRowArray();
        if ($fila !== null) {
            return (int) $fila['id'];
        }

        $db->table('usuario')->insert([
            'id_empresa' => $idEmpresa,
            'usuario' => $usuario,
            'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'id_estado_usuario' => 1,
            'activo' => 1,
            'cuenta_bloqueada' => 1,
            'es_bot' => 1,
            'sudo' => 0,
        ]);
        $id = (int) $db->insertID();
        $db->table('usuario_perfil')->insert(['id_usuario' => $id, 'nombres' => 'Asistente', 'apellidos' => 'IA']);

        return $id;
    }

    /** @param array<string, mixed> $f @return array<string, mixed> */
    private function aArray(array $f): array
    {
        $palabras = json_decode((string) ($f['palabras_traspaso'] ?? '[]'), true);

        return [
            'id' => (int) $f['id'],
            'nombre' => (string) $f['nombre'],
            'activo' => (int) $f['activo'] === 1,
            'modelo' => (string) $f['modelo'],
            'instrucciones' => (string) ($f['instrucciones'] ?? ''),
            'tono' => (string) $f['tono'],
            'usar_catalogo' => (int) $f['usar_catalogo'] === 1,
            'max_respuestas' => (int) $f['max_respuestas'],
            'palabras_traspaso' => is_array($palabras) ? array_values($palabras) : [],
            'mensaje_traspaso' => (string) ($f['mensaje_traspaso'] ?? ''),
            'temperatura' => (float) $f['temperatura'],
        ];
    }

    /** @param array<string, mixed> $d @return array<string, mixed> */
    private function columnas(array $d): array
    {
        return [
            'nombre' => $d['nombre'],
            'activo' => $d['activo'] ? 1 : 0,
            'modelo' => $d['modelo'],
            'instrucciones' => $d['instrucciones'],
            'tono' => $d['tono'],
            'usar_catalogo' => $d['usar_catalogo'] ? 1 : 0,
            'max_respuestas' => $d['max_respuestas'],
            'palabras_traspaso' => json_encode($d['palabras_traspaso'], JSON_UNESCAPED_UNICODE),
            'mensaje_traspaso' => $d['mensaje_traspaso'],
            'temperatura' => $d['temperatura'],
        ];
    }
}
