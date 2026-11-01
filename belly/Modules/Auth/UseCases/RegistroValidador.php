<?php

namespace Belly\Auth\UseCases;

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

        $nombre = trim((string) ($datos['nombre'] ?? ''));
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

        $confirmado = strtolower(trim((string) ($datos['correo_confirmado'] ?? $datos['confirm_correo'] ?? '')));
        if ($confirmado !== '' && $confirmado !== $correo) {
            throw new \RuntimeException('El correo y su confirmación no coinciden.');
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
        ];
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
            return null;
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
