<?php

namespace Belly\Reparto\Services;

use Belly\Reparto\Domain\GrupoAtencion;
use Belly\Reparto\Domain\NodosReparto;
use Belly\Usuarios\Services\ConsultaAsesores;

/** Acceso a datos del reparto de chats. Siempre recibe la empresa de forma explícita (también sirve desde el webhook). */
class RepartoRepositorio
{
    /** Chats que el asesor tiene abiertos o por responder. */
    private const ESTADOS_CHAT_ABIERTO = '1, 3';

    // ───────────── lienzo ─────────────

    /** @return array{nodos: list<array>, conexiones: list<array>} */
    public function definicion(int $idEmpresa): array
    {
        $fila = db_connect()->table('reparto_flujo')->select('definicion')->where('id_empresa', $idEmpresa)->get()->getRowArray();
        $definicion = $fila === null ? null : json_decode((string) $fila['definicion'], true);

        return is_array($definicion) && isset($definicion['nodos'], $definicion['conexiones']) ? $definicion : NodosReparto::porDefecto();
    }

    public function guardarDefinicion(int $idEmpresa, array $definicion): void
    {
        $json = json_encode($definicion, JSON_UNESCAPED_UNICODE);
        $tabla = db_connect()->table('reparto_flujo');
        if ($tabla->where('id_empresa', $idEmpresa)->countAllResults() > 0) {
            db_connect()->table('reparto_flujo')->where('id_empresa', $idEmpresa)->update(['definicion' => $json, 'updated_at' => date('Y-m-d H:i:s')]);

            return;
        }
        db_connect()->table('reparto_flujo')->insert(['id_empresa' => $idEmpresa, 'definicion' => $json]);
    }

    // ───────────── grupos ─────────────

    /** @return list<array{id:int, nombre:string, descripcion:?string, color:string, miembros:list<int>}> */
    public function grupos(int $idEmpresa): array
    {
        $grupos = db_connect()->table('grupo_atencion')
            ->where('id_empresa', $idEmpresa)->where('deleted_at', null)
            ->orderBy('nombre', 'ASC')->get()->getResultArray();
        if ($grupos === []) {
            return [];
        }

        $miembros = [];
        $filas = db_connect()->table('grupo_atencion_miembro m')
            ->select('m.id_grupo, m.id_usuario')
            ->join('usuario u', 'u.id = m.id_usuario AND u.deleted_at IS NULL')
            ->whereIn('m.id_grupo', array_column($grupos, 'id'))
            ->orderBy('m.orden', 'ASC')->orderBy('m.id_usuario', 'ASC')
            ->get()->getResultArray();
        foreach ($filas as $f) {
            $miembros[(int) $f['id_grupo']][] = (int) $f['id_usuario'];
        }

        return array_map(fn (array $g): array => $this->filaGrupo($g, $miembros[(int) $g['id']] ?? []), $grupos);
    }

    /** @return list<array{id: int, nombre: string, dias: list<int>, desde: string, hasta: string, miembros: list<int>}> */
    public function gruposTurno(int $idEmpresa): array
    {
        $lista = [];
        foreach ($this->grupos($idEmpresa) as $grupo) {
            if ($grupo['tipo'] !== GrupoAtencion::TURNO || $grupo['turno_desde'] === null || $grupo['turno_hasta'] === null) {
                continue;
            }
            $dias = array_values(array_filter(array_map('intval', $grupo['turno_dias']), static fn (int $d): bool => $d >= 1 && $d <= 7));
            if ($dias === []) {
                continue;
            }
            $lista[] = [
                'id' => $grupo['id'],
                'nombre' => $grupo['nombre'],
                'dias' => $dias,
                'desde' => $grupo['turno_desde'],
                'hasta' => $grupo['turno_hasta'],
                'miembros' => $grupo['miembros'],
            ];
        }

        return $lista;
    }

    /** @param list<int> $miembros @return array<string, mixed> */
    private function filaGrupo(array $g, array $miembros): array
    {
        $tipo = GrupoAtencion::tipoValido($g['tipo'] ?? null) ? (string) $g['tipo'] : GrupoAtencion::GENERAL;
        $dias = json_decode((string) ($g['turno_dias'] ?? '[]'), true);
        $dias = is_array($dias) ? array_values(array_unique(array_filter(array_map('intval', $dias), static fn (int $d): bool => $d >= 1 && $d <= 7))) : [];

        return [
            'id' => (int) $g['id'],
            'nombre' => (string) $g['nombre'],
            'descripcion' => $g['descripcion'],
            'color' => (string) $g['color'],
            'tipo' => $tipo,
            'turno_dias' => $tipo === GrupoAtencion::TURNO ? $dias : [],
            'turno_desde' => $tipo === GrupoAtencion::TURNO && is_string($g['turno_desde'] ?? null) ? (string) $g['turno_desde'] : null,
            'turno_hasta' => $tipo === GrupoAtencion::TURNO && is_string($g['turno_hasta'] ?? null) ? (string) $g['turno_hasta'] : null,
            'miembros' => $miembros,
        ];
    }

    /** @return array<string, mixed>|null */
    public function buscarGrupo(int $idEmpresa, int $id): ?array
    {
        foreach ($this->grupos($idEmpresa) as $grupo) {
            if ($grupo['id'] === $id) {
                return $grupo;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $datos */
    public function insertarGrupo(int $idEmpresa, array $datos): int
    {
        db_connect()->table('grupo_atencion')->insert($datos + ['id_empresa' => $idEmpresa]);

        return (int) db_connect()->insertID();
    }

    /** @param array<string, mixed> $datos */
    public function actualizarGrupo(int $idEmpresa, int $id, array $datos): void
    {
        db_connect()->table('grupo_atencion')
            ->where('id_empresa', $idEmpresa)->where('id', $id)->where('deleted_at', null)
            ->update($datos + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    public function eliminarGrupo(int $idEmpresa, int $id): void
    {
        db_connect()->table('grupo_atencion_miembro')->where('id_grupo', $id)->delete();
        db_connect()->table('grupo_atencion')
            ->where('id_empresa', $idEmpresa)->where('id', $id)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /** @param list<int> $ids en orden de prioridad */
    public function fijarMiembros(int $idGrupo, array $ids): void
    {
        $db = db_connect();
        $db->transStart();
        $db->table('grupo_atencion_miembro')->where('id_grupo', $idGrupo)->delete();
        foreach ($ids as $posicion => $id) {
            $db->table('grupo_atencion_miembro')->insert(['id_grupo' => $idGrupo, 'id_usuario' => $id, 'orden' => $posicion + 1]);
        }
        $db->transComplete();
    }

    // ───────────── personas ─────────────

    /**
     * Asesores que pueden recibir un chat ahora, con su carga. Con grupo, solo sus miembros y en su orden.
     *
     * @return list<array{id:int, nombre:string, hoy:int, abiertas:int, ultima:?string}>
     */
    public function candidatos(int $idEmpresa, ?int $idGrupo = null): array
    {
        $params = [$idEmpresa];
        $union = '';
        $orden = 'u.id ASC';
        if ($idGrupo !== null) {
            $union = ' JOIN grupo_atencion_miembro m ON m.id_usuario = u.id AND m.id_grupo = ?
                       JOIN grupo_atencion ga ON ga.id = m.id_grupo AND ga.deleted_at IS NULL AND ga.id_empresa = u.id_empresa';
            $params = [$idGrupo, $idEmpresa];
            $orden = 'm.orden ASC, u.id ASC';
        }

        $sql = 'SELECT u.id, u.usuario, p.nombres, p.apellidos,
                    (SELECT COUNT(*) FROM conversacion c WHERE c.id_usuario = u.id AND c.deleted_at IS NULL AND DATE(c.asignado_at) = CURDATE()) AS hoy,
                    (SELECT COUNT(*) FROM conversacion c WHERE c.id_usuario = u.id AND c.deleted_at IS NULL AND c.id_estado IN (' . self::ESTADOS_CHAT_ABIERTO . ')) AS abiertas,
                    (SELECT MAX(c.asignado_at) FROM conversacion c WHERE c.id_usuario = u.id AND c.deleted_at IS NULL) AS ultima
                FROM usuario u' . $union . '
                LEFT JOIN usuario_perfil p ON p.id_usuario = u.id AND p.deleted_at IS NULL
                WHERE ' . ConsultaAsesores::condicion() . '
                ORDER BY ' . $orden;

        return array_map(static function (array $f): array {
            $nombre = trim(trim((string) ($f['nombres'] ?? '')) . ' ' . trim((string) ($f['apellidos'] ?? '')));

            return [
                'id' => (int) $f['id'],
                'nombre' => $nombre !== '' ? $nombre : (string) $f['usuario'],
                'hoy' => (int) $f['hoy'],
                'abiertas' => (int) $f['abiertas'],
                'ultima' => $f['ultima'] !== null ? (string) $f['ultima'] : null,
            ];
        }, db_connect()->query($sql, $params)->getResultArray());
    }

    public function asesorDisponible(int $idEmpresa, int $idUsuario): bool
    {
        $sql = 'SELECT COUNT(*) AS n FROM usuario u WHERE u.id = ? AND ' . ConsultaAsesores::condicion();

        return $idUsuario > 0 && (int) (db_connect()->query($sql, [$idUsuario, $idEmpresa])->getRow()->n ?? 0) > 0;
    }

    /**
     * Todas las personas activas de la empresa (administradores incluidos).
     *
     * @return list<array{id:int, nombre:string, es_admin:bool}>
     */
    public function personas(int $idEmpresa): array
    {
        $filas = db_connect()->table('usuario u')
            ->select('u.id, u.usuario, u.sudo, p.nombres, p.apellidos')
            ->join('usuario_perfil p', 'p.id_usuario = u.id AND p.deleted_at IS NULL', 'left')
            ->where('u.id_empresa', $idEmpresa)->where('u.activo', 1)->where('u.cuenta_bloqueada', 0)
            ->where('u.es_bot', 0)->where('u.deleted_at', null)
            ->orderBy('u.sudo', 'DESC')->orderBy('p.nombres', 'ASC')->orderBy('u.usuario', 'ASC')
            ->get()->getResultArray();

        return array_map(static function (array $f): array {
            $nombre = trim(trim((string) ($f['nombres'] ?? '')) . ' ' . trim((string) ($f['apellidos'] ?? '')));

            return ['id' => (int) $f['id'], 'nombre' => $nombre !== '' ? $nombre : (string) $f['usuario'], 'es_admin' => (int) $f['sudo'] === 1];
        }, $filas);
    }

    public function usuarioActivo(int $idEmpresa, int $idUsuario): bool
    {
        return $idUsuario > 0 && db_connect()->table('usuario')
            ->where('id', $idUsuario)->where('id_empresa', $idEmpresa)
            ->where('activo', 1)->where('cuenta_bloqueada', 0)->where('es_bot', 0)->where('deleted_at', null)
            ->countAllResults() > 0;
    }

    /** Administrador más antiguo de la empresa: quien recibe los chats si nadie más puede. */
    public function primerAdministrador(int $idEmpresa): ?int
    {
        $fila = db_connect()->table('usuario')
            ->select('id')
            ->where('id_empresa', $idEmpresa)->where('sudo', 1)
            ->where('activo', 1)->where('cuenta_bloqueada', 0)->where('es_bot', 0)->where('deleted_at', null)
            ->orderBy('id', 'ASC')->limit(1)->get()->getRowArray();

        return $fila === null ? null : (int) $fila['id'];
    }
}
