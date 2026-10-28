<?php

namespace Belly\Usuarios\UseCases;

use Belly\Configuracion\Models\EmpresaModel;
use Belly\Services\Autorizacion;
use Belly\Usuarios\Models\UsuarioModel;
use Belly\Usuarios\Models\UsuarioPerfilModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use DomainException;

class PerfilUseCase
{
    /** @return array<string, mixed> */
    public function ver(): array
    {
        $usuario = $this->usuario();
        $perfil = $this->perfil((int) $usuario->id);
        $empresa = $this->empresa((int) $usuario->id_empresa);

        return [
            'nombres' => (string) ($perfil->nombres ?? ''),
            'apellidos' => (string) ($perfil->apellidos ?? ''),
            'correo' => (string) ($perfil->correo ?? ''),
            'celular' => (string) ($perfil->celular ?? ''),
            'foto' => $perfil->foto_url ?: null,
            'usuario' => (string) $usuario->usuario,
            'empresa' => [
                'nombre' => (string) ($empresa->nombre ?? ''),
                'correo' => (string) ($empresa->correo ?? ''),
            ],
        ];
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(array $datos): array
    {
        $usuario = $this->usuario();
        $id = (int) $usuario->id;

        $nombres = trim((string) ($datos['nombres'] ?? ''));
        $apellidos = trim((string) ($datos['apellidos'] ?? ''));
        $correo = strtolower(trim((string) ($datos['correo'] ?? '')));
        $celular = preg_replace('/\D/', '', (string) ($datos['celular'] ?? '')) ?? '';

        if (mb_strlen($nombres) < 2) {
            throw new DomainException('Escribe tu nombre.');
        }
        if (mb_strlen($nombres) > 80 || mb_strlen($apellidos) > 80) {
            throw new DomainException('El nombre es demasiado largo.');
        }
        if ($correo === '' || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('El correo no es válido.');
        }
        if ($this->correoOcupado($correo, $id)) {
            throw new DomainException('Ese correo ya está en otra cuenta.');
        }
        if ($celular !== '' && (strlen($celular) < 8 || strlen($celular) > 15)) {
            throw new DomainException('Revisa el número de celular.');
        }

        $this->cambiarContrasena($usuario, $datos);

        model(UsuarioPerfilModel::class)->update($id, [
            'nombres' => $nombres,
            'apellidos' => $apellidos !== '' ? $apellidos : null,
            'correo' => $correo,
            'celular' => $celular !== '' ? $celular : null,
        ]);

        return $this->ver();
    }

    public function subirFoto(?UploadedFile $archivo): array
    {
        $usuario = $this->usuario();
        $id = (int) $usuario->id;

        if ($archivo === null || ! $archivo->isValid() || $archivo->hasMoved()) {
            throw new DomainException('No se pudo recibir la imagen.');
        }
        if ((int) $archivo->getSize() <= 0 || (int) $archivo->getSize() > 5 * 1024 * 1024) {
            throw new DomainException('La imagen debe pesar menos de 5 MB.');
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

        $ruta = 'perfiles/' . (int) $usuario->id_empresa . '/' . $id . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $guardado = media()->guardarContenidoEnUploads((string) file_get_contents($archivo->getTempName()), $ruta);

        $perfil = $this->perfil($id);
        $anterior = (string) ($perfil->foto_path ?? '');
        model(UsuarioPerfilModel::class)->update($id, [
            'foto_url' => $guardado['media_url'],
            'foto_path' => $guardado['media_path'],
        ]);

        if ($anterior !== '' && $anterior !== $guardado['media_path']) {
            $viejo = media()->rutaDiscoUploads($anterior);
            if (is_file($viejo)) {
                @unlink($viejo);
            }
        }

        return $this->ver();
    }

    /** @param array<string, mixed> $datos */
    public function actualizarEmpresa(array $datos): array
    {
        if (! Autorizacion::esAdministradorEmpresa()) {
            throw new DomainException('Solo el administrador puede cambiar los datos de la empresa.');
        }

        $usuario = $this->usuario();
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $correo = strtolower(trim((string) ($datos['correo'] ?? '')));

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120) {
            throw new DomainException('Escribe el nombre de la empresa.');
        }
        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('El correo de la empresa no es válido.');
        }

        $modelo = model(EmpresaModel::class);
        $modelo->update((int) $usuario->id_empresa, [
            'nombre' => $nombre,
            'correo' => $correo !== '' ? $correo : null,
        ]);

        return $this->ver();
    }

    /** @param array<string, mixed> $datos */
    private function cambiarContrasena(object $usuario, array $datos): void
    {
        $nueva = (string) ($datos['password_nueva'] ?? '');
        if ($nueva === '') {
            return;
        }
        if (strlen($nueva) < 8) {
            throw new DomainException('La contraseña nueva necesita al menos 8 caracteres.');
        }
        $actual = (string) ($datos['password_actual'] ?? '');
        if ($actual === '' || ! password_verify($actual, (string) $usuario->password)) {
            throw new DomainException('La contraseña actual no coincide.');
        }

        model(UsuarioModel::class)->update((int) $usuario->id, [
            'password' => password_hash($nueva, PASSWORD_BCRYPT),
        ]);
    }

    private function correoOcupado(string $correo, int $idUsuario): bool
    {
        return db_connect()->table('usuario_perfil')
            ->where('correo', $correo)
            ->where('id_usuario !=', $idUsuario)
            ->where('deleted_at', null)
            ->countAllResults() > 0;
    }

    private function usuario(): object
    {
        $usuario = Autorizacion::usuario();
        if ($usuario === null) {
            throw new DomainException('No hay sesión.');
        }

        return $usuario;
    }

    private function perfil(int $idUsuario): object
    {
        $perfil = model(UsuarioPerfilModel::class)->find($idUsuario);
        if (! $perfil) {
            throw new DomainException('No encontramos tu perfil.');
        }

        return $perfil;
    }

    private function empresa(int $idEmpresa): object
    {
        $empresa = model(EmpresaModel::class)->find($idEmpresa);
        if (! $empresa) {
            throw new DomainException('No encontramos la empresa.');
        }

        return $empresa;
    }
}
