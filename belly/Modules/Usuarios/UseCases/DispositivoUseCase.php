<?php

namespace Belly\Usuarios\UseCases;

use Belly\Usuarios\Entities\UsuarioDispositivoEntity;
use Belly\Usuarios\Models\UsuarioDispositivoModel;
use DomainException;

/** Registra tokens Expo Push de la app de asesores. */
class DispositivoUseCase
{
    private UsuarioDispositivoModel $dispositivos;

    public function __construct(?UsuarioDispositivoModel $dispositivos = null)
    {
        $this->dispositivos = $dispositivos ?? new UsuarioDispositivoModel();
    }

    /**
     * @param array{expo_push_token?: string, plataforma?: string, nombre?: string|null} $datos
     * @return array<string, mixed>
     */
    public function registrar(int $idUsuario, int $idEmpresa, array $datos): array
    {
        $token = trim((string) ($datos['expo_push_token'] ?? ''));
        if ($token === '' || (! str_starts_with($token, 'ExponentPushToken[') && ! str_starts_with($token, 'ExpoPushToken['))) {
            throw new DomainException('Token de notificaciones inválido.');
        }

        $plataforma = strtolower(trim((string) ($datos['plataforma'] ?? 'android')));
        if (! in_array($plataforma, ['ios', 'android'], true)) {
            throw new DomainException('Plataforma no válida.');
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $nombre = $nombre !== '' ? mb_substr($nombre, 0, 120) : null;

        $existente = $this->dispositivos
            ->withDeleted()
            ->where('expo_push_token', $token)
            ->first();

        $fila = [
            'id_usuario' => $idUsuario,
            'id_empresa' => $idEmpresa,
            'expo_push_token' => $token,
            'plataforma' => $plataforma,
            'nombre' => $nombre,
            'activo' => 1,
            'deleted_at' => null,
        ];

        if ($existente instanceof UsuarioDispositivoEntity) {
            $this->dispositivos->update($existente->id, $fila);

            return $this->aArray($this->dispositivos->find($existente->id));
        }

        $id = $this->dispositivos->insert($fila);
        if (! $id) {
            throw new DomainException('No se pudo registrar el dispositivo.');
        }

        return $this->aArray($this->dispositivos->find($id));
    }

    public function desactivar(int $idUsuario, string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            throw new DomainException('Falta el token del dispositivo.');
        }

        $fila = $this->dispositivos
            ->where('expo_push_token', $token)
            ->where('id_usuario', $idUsuario)
            ->first();

        if (! $fila instanceof UsuarioDispositivoEntity) {
            return;
        }

        $this->dispositivos->update($fila->id, [
            'activo' => 0,
            'id_conversacion_abierta' => null,
            'visto_en' => null,
        ]);
        $this->dispositivos->delete($fila->id);
    }

    /** Marca el chat abierto en primer plano para no mandar push duplicado. */
    public function marcarActivo(int $idUsuario, string $token, ?int $idConversacion): void
    {
        $token = trim($token);
        if ($token === '') {
            throw new DomainException('Falta el token del dispositivo.');
        }

        $fila = $this->dispositivos
            ->where('expo_push_token', $token)
            ->where('id_usuario', $idUsuario)
            ->where('activo', 1)
            ->first();

        if (! $fila instanceof UsuarioDispositivoEntity) {
            throw new DomainException('Dispositivo no registrado.');
        }

        $this->dispositivos->update($fila->id, [
            'id_conversacion_abierta' => $idConversacion && $idConversacion > 0 ? $idConversacion : null,
            'visto_en' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Tokens activos del usuario, omitiendo el chat abierto reciente.
     *
     * @return list<string>
     */
    public function tokensParaAviso(int $idUsuario, ?int $idConversacion = null): array
    {
        if ($idUsuario <= 0) {
            return [];
        }

        $filas = $this->dispositivos
            ->where('id_usuario', $idUsuario)
            ->where('activo', 1)
            ->findAll();

        $tokens = [];
        $limite = time() - 90;

        foreach ($filas as $fila) {
            if (! $fila instanceof UsuarioDispositivoEntity) {
                continue;
            }
            $token = trim((string) $fila->expo_push_token);
            if ($token === '') {
                continue;
            }

            if ($idConversacion && (int) $fila->id_conversacion_abierta === $idConversacion) {
                $visto = $fila->visto_en;
                $ts = is_object($visto) && method_exists($visto, 'getTimestamp')
                    ? (int) $visto->getTimestamp()
                    : (is_string($visto) ? strtotime($visto) : 0);
                if ($ts >= $limite) {
                    continue;
                }
            }

            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    /** Desactiva todos los dispositivos del usuario (p. ej. al bloquearle la app). */
    public function desactivarTodosDeUsuario(int $idUsuario): void
    {
        if ($idUsuario <= 0) {
            return;
        }

        $filas = $this->dispositivos
            ->where('id_usuario', $idUsuario)
            ->where('activo', 1)
            ->findAll();

        foreach ($filas as $fila) {
            if (! $fila instanceof UsuarioDispositivoEntity) {
                continue;
            }
            $this->dispositivos->update($fila->id, [
                'activo' => 0,
                'id_conversacion_abierta' => null,
                'visto_en' => null,
            ]);
            $this->dispositivos->delete($fila->id);
        }
    }

    /**
     * Usuarios de la empresa con estado de app + dispositivos activos.
     *
     * @return list<array<string, mixed>>
     */
    public function listarAccesosEmpresa(int $idEmpresa): array
    {
        if ($idEmpresa <= 0) {
            return [];
        }

        $db = db_connect();
        $tieneBloqueo = $db->fieldExists('app_asesor_bloqueada', 'usuario');

        $sql = 'SELECT u.id, u.usuario, u.activo,
                       ' . ($tieneBloqueo ? 'u.app_asesor_bloqueada' : '0 AS app_asesor_bloqueada') . ',
                       up.nombres, up.apellidos, up.foto_url
                FROM usuario u
                LEFT JOIN usuario_perfil up ON up.id = u.id
                WHERE u.id_empresa = ?
                  AND u.es_bot = 0
                  AND u.deleted_at IS NULL
                ORDER BY up.nombres ASC, u.usuario ASC';

        $usuarios = $db->query($sql, [$idEmpresa])->getResultArray();
        if ($usuarios === []) {
            return [];
        }

        $ids = array_map(static fn ($u) => (int) $u['id'], $usuarios);
        $dispositivos = $this->dispositivos
            ->whereIn('id_usuario', $ids)
            ->where('id_empresa', $idEmpresa)
            ->where('activo', 1)
            ->orderBy('visto_en', 'DESC')
            ->findAll();

        $porUsuario = [];
        $ahora = time();
        foreach ($dispositivos as $fila) {
            if (! $fila instanceof UsuarioDispositivoEntity) {
                continue;
            }
            $idU = (int) $fila->id_usuario;
            $visto = $fila->visto_en;
            $ts = is_object($visto) && method_exists($visto, 'getTimestamp')
                ? (int) $visto->getTimestamp()
                : (is_string($visto) && $visto !== '' ? (int) strtotime($visto) : 0);
            $porUsuario[$idU][] = [
                'id' => (int) $fila->id,
                'plataforma' => (string) $fila->plataforma,
                'nombre' => $fila->nombre,
                'visto_en' => $ts > 0 ? date('c', $ts) : null,
                'conectado' => $ts > 0 && ($ahora - $ts) <= 300,
            ];
        }

        $salida = [];
        foreach ($usuarios as $u) {
            $id = (int) $u['id'];
            $devs = $porUsuario[$id] ?? [];
            $conectado = false;
            $ultimo = null;
            foreach ($devs as $d) {
                if ($d['conectado']) {
                    $conectado = true;
                }
                if ($d['visto_en'] && ($ultimo === null || $d['visto_en'] > $ultimo)) {
                    $ultimo = $d['visto_en'];
                }
            }
            $nombres = trim((string) ($u['nombres'] ?? ''));
            $apellidos = trim((string) ($u['apellidos'] ?? ''));
            $salida[] = [
                'id' => $id,
                'usuario' => (string) $u['usuario'],
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'nombre' => trim($nombres . ' ' . $apellidos) ?: (string) $u['usuario'],
                'foto' => $u['foto_url'] ?? null,
                'activo' => (int) ($u['activo'] ?? 0) === 1,
                'app_bloqueada' => (int) ($u['app_asesor_bloqueada'] ?? 0) === 1,
                'conectado' => $conectado,
                'ultimo_visto' => $ultimo,
                'dispositivos' => $devs,
            ];
        }

        return $salida;
    }

    /** @return array<string, mixed> */
    private function aArray(?UsuarioDispositivoEntity $fila): array
    {
        if (! $fila instanceof UsuarioDispositivoEntity) {
            return [];
        }

        return [
            'id' => (int) $fila->id,
            'plataforma' => (string) $fila->plataforma,
            'nombre' => $fila->nombre,
            'activo' => (int) $fila->activo === 1,
        ];
    }
}
