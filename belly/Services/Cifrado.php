<?php

namespace Belly\Services;

/**
 * Cifra credenciales (tokens de Meta, App Secret) antes de guardarlas.
 * Los valores antiguos en texto plano se siguen leyendo hasta que se vuelvan a guardar.
 */
final class Cifrado
{
    private const PREFIJO = 'enc:';

    public static function cifrar(?string $valor): ?string
    {
        $valor = $valor === null ? null : trim($valor);
        if ($valor === null || $valor === '' || self::estaCifrado($valor)) {
            return $valor === '' ? null : $valor;
        }

        return self::PREFIJO . base64_encode(service('encrypter')->encrypt($valor));
    }

    public static function descifrar(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (! self::estaCifrado($valor)) {
            return $valor;
        }

        $crudo = base64_decode(substr($valor, strlen(self::PREFIJO)), true);
        if ($crudo === false) {
            return null;
        }

        try {
            return service('encrypter')->decrypt($crudo);
        } catch (\Throwable $e) {
            log_message('error', '[CIFRADO] No se pudo descifrar una credencial: revisa encryption.key');

            return null;
        }
    }

    public static function estaCifrado(?string $valor): bool
    {
        return is_string($valor) && str_starts_with($valor, self::PREFIJO);
    }

    public static function tokenAleatorio(int $bytes = 24): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
