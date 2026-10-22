<?php

namespace Belly\Usuarios\UseCases;

use Belly\Services\Autorizacion;
use Belly\Usuarios\Services\ConsultaAsesores;
use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;

/**
 * Asesores: las personas de la empresa que atienden los chats.
 * Crear un asesor es crear un usuario con el rol «Asesor»; las reglas de usuarios siguen siendo las mismas.
 */
class AsesorUseCase
{
    /** Chats que el asesor tiene abiertos o por responder. */
    private const ESTADOS_CHAT_ABIERTO = '1, 3';

    /** @return list<array<string, mixed>> todos los asesores de la empresa, activos o no */
    public function listar(): array
    {
        $sql = 'SELECT u.id, u.usuario, u.activo, u.created_at, e.nombre AS estado,
                    p.nombres, p.apellidos, p.correo, p.celular, p.foto_url,
                    (SELECT r.rol FROM grupo_usuario_usuario g
                        JOIN grupo_usuario_rol r ON r.id = g.id_grupo_usuario_rol
                        WHERE g.id_usuario = u.id AND g.deleted_at IS NULL
                        ORDER BY g.id DESC LIMIT 1) AS rol,
                    (SELECT COUNT(*) FROM conversacion c
                        WHERE c.id_usuario = u.id AND c.deleted_at IS NULL
                          AND c.id_estado IN (' . self::ESTADOS_CHAT_ABIERTO . ')) AS chats_abiertos
                FROM usuario u
                LEFT JOIN usuario_perfil p ON p.id_usuario = u.id AND p.deleted_at IS NULL
                LEFT JOIN estado_usuario e ON e.id = u.id_estado_usuario
                WHERE ' . ConsultaAsesores::condicion(false) . '
                ORDER BY u.activo DESC, p.nombres ASC, p.apellidos ASC, u.usuario ASC';

        $asesores = [];
        foreach (db_connect()->query($sql, [$this->idEmpresa()])->getResultArray() as $fila) {
            $asesores[] = [
                'id' => (int) $fila['id'],
                'usuario' => (string) $fila['usuario'],
                'nombre' => $this->nombre($fila),
                'correo' => $fila['correo'] ?: null,
                'celular' => $fila['celular'] ?: null,
                'foto' => $fila['foto_url'] ?: null,
                'rol' => $fila['rol'] ?: null,
                'estado' => $fila['estado'] ?: null,
                'activo' => (int) $fila['activo'] === 1,
                'chats_abiertos' => (int) $fila['chats_abiertos'],
                'creado' => $fila['created_at'],
            ];
        }

        return $asesores;
    }

    /**
     * Asesores activos a quienes se puede pasar una conversación.
     *
     * @return list<array{id:int, usuario:string, nombre_completo:string}>
     */
    public function disponibles(): array
    {
        $sql = 'SELECT u.id, u.usuario, p.nombres, p.apellidos
                FROM usuario u
                LEFT JOIN usuario_perfil p ON p.id_usuario = u.id AND p.deleted_at IS NULL
                WHERE ' . ConsultaAsesores::condicion() . '
                ORDER BY p.nombres ASC, p.apellidos ASC, u.usuario ASC';

        $lista = [];
        foreach (db_connect()->query($sql, [$this->idEmpresa()])->getResultArray() as $fila) {
            $lista[] = [
                'id' => (int) $fila['id'],
                'usuario' => (string) $fila['usuario'],
                'nombre_completo' => $this->nombre($fila),
            ];
        }

        return $lista;
    }

    /**
     * Crea un asesor con el rol «Asesor» de la empresa.
     *
     * @param array<string, mixed> $datos nombres, apellidos, usuario, password, correo, celular
     * @return array<string, mixed>
     */
    public function crear(array $datos): array
    {
        $idEmpresa = $this->idEmpresa();
        $rol = acceso()->rolPorDefecto($idEmpresa);

        try {
            $creado = usuario()->crearUsuario([
                'nombres' => $datos['nombres'] ?? null,
                'apellidos' => $datos['apellidos'] ?? null,
                'usuario' => $datos['usuario'] ?? null,
                'password' => $datos['password'] ?? null,
                'correo' => $datos['correo'] ?? null,
                'celular' => $datos['celular'] ?? null,
                'id_rol' => (int) $rol['id'],
            ]);
        } catch (\Exception $e) {
            $mensaje = $e->getMessage();
            if ($mensaje === '' || str_starts_with($mensaje, '{') || str_starts_with($mensaje, '[')) {
                log_message('error', '[ASESORES] ' . $mensaje);
                $mensaje = 'No se pudo crear al asesor. Revisa los datos.';
            }
            throw new DomainException($mensaje);
        }

        return $this->obtener((int) $creado['id_usuario']);
    }

    /** @return array<string, mixed> */
    public function cambiarEstado(int $id, bool $activo): array
    {
        $this->obtener($id);

        try {
            usuario()->cambiarEstadoUsuario(['estado' => $activo ? 1 : 0], $id);
        } catch (\Exception $e) {
            throw new DomainException($e->getMessage());
        }

        return $this->obtener($id);
    }

    /**
     * Foto de perfil del asesor (JPG/PNG/WEBP, máx. 5 MB).
     *
     * @return array<string, mixed>
     */
    public function subirFoto(int $id, ?UploadedFile $archivo): array
    {
        $this->obtener($id);
        $yo = Autorizacion::usuario();
        if ($yo === null) {
            throw new DomainException('No hay sesión.');
        }
        if (! Autorizacion::esAdministradorEmpresa() && (int) $yo->id !== $id) {
            throw new DomainException('Solo puedes cambiar tu propia foto.');
        }

        if ($archivo === null || ! $archivo->isValid() || $archivo->hasMoved()) {
            throw new DomainException('No se pudo recibir la imagen. Inténtalo de nuevo.');
        }
        if ((int) $archivo->getSize() <= 0) {
            throw new DomainException('La imagen está vacía.');
        }
        if ((int) $archivo->getSize() > 5 * 1024 * 1024) {
            throw new DomainException('La imagen es muy pesada. Se permite hasta 5 MB.');
        }

        $extension = match (strtolower((string) $archivo->getMimeType())) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($extension === null) {
            throw new DomainException('Usa una imagen JPG, PNG o WEBP.');
        }

        $ruta = 'asesores/' . $this->idEmpresa() . '/' . $id . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $guardado = media()->guardarContenidoEnUploads((string) file_get_contents($archivo->getTempName()), $ruta);

        $perfil = model('UsuarioPerfilModel')->find($id);
        if (! $perfil) {
            throw new DomainException('El asesor no tiene perfil.');
        }

        $anterior = (string) ($perfil->foto_path ?? '');
        model('UsuarioPerfilModel')->update($id, [
            'foto_url' => $guardado['media_url'],
            'foto_path' => $guardado['media_path'],
        ]);

        if ($anterior !== '' && $anterior !== $guardado['media_path']) {
            $viejo = media()->rutaDiscoUploads($anterior);
            if (is_file($viejo)) {
                @unlink($viejo);
            }
        }

        return $this->obtener($id);
    }

    /** @return array<string, mixed> */
    private function obtener(int $id): array
    {
        foreach ($this->listar() as $asesor) {
            if ($asesor['id'] === $id) {
                return $asesor;
            }
        }

        throw new DomainException('No encontramos a ese asesor.');
    }

    /** @param array<string, mixed> $fila */
    private function nombre(array $fila): string
    {
        $nombre = trim(trim((string) ($fila['nombres'] ?? '')) . ' ' . trim((string) ($fila['apellidos'] ?? '')));

        return $nombre !== '' ? $nombre : (string) $fila['usuario'];
    }

    private function idEmpresa(): int
    {
        helper('vip2system');
        $id = id_empresa_sesion();
        if ($id === null) {
            throw new DomainException('No se pudo identificar tu empresa.');
        }

        return $id;
    }
}
