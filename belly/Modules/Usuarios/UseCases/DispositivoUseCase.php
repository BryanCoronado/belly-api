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
