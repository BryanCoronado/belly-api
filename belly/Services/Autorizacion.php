<?php

namespace Belly\Services;

class Autorizacion
{
    public static function usuario(): ?object
    {
        helper('vip2system');

        return auth()->getUsuario();
    }

    public static function esAdministradorEmpresa(): bool
    {
        $usuario = self::usuario();

        return $usuario !== null && (int) ($usuario->sudo ?? 0) === 1;
    }

    public static function esOperadorPlataforma(): bool
    {
        return self::esOperadorDe(self::usuario());
    }

    public static function esOperadorDe(?object $usuario): bool
    {
        return $usuario !== null && (int) ($usuario->operador ?? 0) === 1;
    }

    public static function esUsuarioActual(int $idUsuario): bool
    {
        $usuario = self::usuario();

        return $usuario !== null && (int) $usuario->id === $idUsuario;
    }

    public static function exigirAdministradorEmpresa(string $mensaje = 'Solo el administrador de la empresa puede hacer esto.'): void
    {
        if (! self::esAdministradorEmpresa()) {
            throw new \Exception($mensaje);
        }
    }

    public static function exigirOperadorPlataforma(string $mensaje = 'Solo el operador de la plataforma puede hacer esto.'): void
    {
        if (! self::esOperadorPlataforma()) {
            throw new \Exception($mensaje);
        }
    }
}
