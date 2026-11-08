<?php

namespace Belly\Auth\UseCases;

use Belly\Services\JsonPe;

class RegistroValidador
{
    private const DESCARTABLES = [
        '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 'tempmail.com',
        'temp-mail.org', 'yopmail.com', 'trashmail.com', 'discard.email',
        'sharklasers.com', 'guerrillamail.de', 'getnada.com', 'moakt.com',
        'emailondeck.com', 'fakeinbox.com', 'maildrop.cc', 'tempail.com',
    ];

    /** @param array<string, mixed> $datos */
    public function validar(array $datos): array
    {
        if (trim((string) ($datos['web'] ?? $datos['sitio'] ?? '')) !== '') {
            throw new \RuntimeException('No se pudo crear la cuenta.');
        }

        $identidad = $this->exigirDocumento($datos);

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '' && $identidad['tipo'] === 'ruc') {
            $nombre = (string) $identidad['nombre'];
        }
        if (mb_strlen($nombre) < 3) {
            throw new \RuntimeException('El nombre del negocio debe tener al menos 3 letras.');
        }
        if (mb_strlen($nombre) > 80) {
            throw new \RuntimeException('El nombre del negocio es demasiado largo.');
        }
        if (! preg_match('/\p{L}/u', $nombre)) {
            throw new \RuntimeException('El nombre del negocio tiene que tener letras.');
        }
        if (preg_match('/^(test|prueba|asdf|qwerty|empresa|negocio|xxx+|aaa+)$/iu', $nombre)) {
            throw new \RuntimeException('Pon el nombre real de tu negocio.');
        }

        $nombres = trim((string) ($datos['nombres'] ?? ''));
        $apellidos = trim((string) ($datos['apellidos'] ?? ''));
        if ($nombres === '' && $identidad['tipo'] === 'dni') {
            $nombres = (string) $identidad['nombres'];
        }
        if ($apellidos === '' && $identidad['tipo'] === 'dni') {
            $apellidos = (string) $identidad['apellidos'];
        }
        if (mb_strlen($nombres) < 2) {
            throw new \RuntimeException('Escribe tu nombre.');
        }
        if (mb_strlen($apellidos) < 2) {
            throw new \RuntimeException('Escribe tu apellido.');
        }

        $correo = strtolower(trim((string) ($datos['correo'] ?? '')));
        if ($correo === '' || ! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('El correo no es válido.');
        }
        $dominio = substr(strrchr($correo, '@') ?: '', 1);
        if ($dominio !== '' && in_array($dominio, self::DESCARTABLES, true)) {
            throw new \RuntimeException('Usa un correo de empresa o personal, no uno temporal.');
        }

        $clave = (string) ($datos['password'] ?? '');
        if (strlen($clave) < 8) {
            throw new \RuntimeException('La contraseña necesita al menos 8 caracteres.');
        }
        if (! preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]/u', $clave) || ! preg_match('/\d/', $clave)) {
            throw new \RuntimeException('La contraseña debe tener letras y números.');
        }

        $celular = $this->celular($datos);
        if ($celular === null) {
            throw new \RuntimeException('El WhatsApp es obligatorio y tiene que ser un número real.');
        }

        $this->exigirCupo();

        return [
            'nombre' => $nombre,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'correo' => $correo,
            'password' => $clave,
            'celular' => $celular,
            'codigo_pais' => preg_replace('/\D/', '', (string) ($datos['codigo_pais'] ?? '')) ?? '',
            'identidad' => $identidad,
            'nro_documento' => (string) $identidad['numero'],
            'id_tipo_documento' => $identidad['tipo'] === 'dni' ? 1 : null,
        ];
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function exigirDocumento(array $datos): array
    {
        $numero = preg_replace('/\D+/', '', (string) ($datos['documento'] ?? $datos['nro_documento'] ?? $datos['dni'] ?? $datos['ruc'] ?? '')) ?? '';
        if ($numero === '') {
            throw new \RuntimeException('El DNI o RUC es obligatorio.');
        }

        $this->exigirDocumentoLibre($numero);
        $identidad = (new JsonPe())->consultar($numero);
        $this->exigirDocumentoLibre((string) $identidad['numero']);

        return $identidad;
    }

    public function exigirDocumentoLibre(string $numero): void
    {
        $numero = preg_replace('/\D+/', '', $numero) ?? '';
        if ($numero === '') {
            return;
        }

        $db = db_connect();
        if ($db->fieldExists('numero_doc', 'empresa')) {
            $usado = $db->table('empresa')
                ->where('numero_doc', $numero)
                ->where('deleted_at', null)
                ->countAllResults();
            if ($usado > 0) {
                throw new \RuntimeException('Ese DNI o RUC ya tiene una cuenta en Belly.');
            }
        }

        $perfil = $db->table('usuario_perfil p')
            ->join('usuario u', 'u.id = p.id_usuario')
            ->where('p.nro_documento', $numero)
            ->where('p.deleted_at', null)
            ->where('u.deleted_at', null)
            ->countAllResults();
        if ($perfil > 0) {
            throw new \RuntimeException('Ese DNI o RUC ya tiene una cuenta en Belly.');
        }
    }

    public function marcarAlta(): void
    {
        $clave = $this->claveCupo();
        if ($clave === null) {
            return;
        }
        cache()->save($clave, ((int) cache($clave)) + 1, 12 * 3600);
    }

    private function exigirCupo(): void
    {
        $clave = $this->claveCupo();
        if ($clave === null) {
            return;
        }
        if ((int) cache($clave) >= 3) {
            throw new \RuntimeException('Ya se crearon varias cuentas desde aquí. Prueba más tarde o escríbenos.');
        }
    }

    private function claveCupo(): ?string
    {
        $ip = trim((string) (service('request')->getIPAddress() ?? ''));
        if ($ip === '' || $ip === '0.0.0.0') {
            $ip = 'desconocida';
        }

        return 'registro-ip-' . md5($ip);
    }

    /** @param array<string, mixed> $datos */
    private function celular(array $datos): ?string
    {
        $codigo = preg_replace('/\D/', '', (string) ($datos['codigo_pais'] ?? '')) ?? '';
        $numero = preg_replace('/\D/', '', (string) ($datos['celular'] ?? '')) ?? '';
        if ($numero === '') {
            return null;
        }
        if ($codigo !== '' && ! str_starts_with($numero, $codigo)) {
            $numero = $codigo . ltrim($numero, '0');
        }
        $largo = strlen($numero);

        return $largo >= 8 && $largo <= 15 ? $numero : null;
    }
}
