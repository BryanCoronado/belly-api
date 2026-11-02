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

    /** La cuenta Belly (dueño), no un cliente del CRM. */
    public static function esEmpresaDueño(int $idEmpresa): bool
    {
        if ($idEmpresa <= 0) {
            return false;
        }

        $operadora = (int) config(\Belly\Config\Plataforma::class)->idOperadora();
        if ($operadora > 0 && $idEmpresa === $operadora) {
            return true;
        }

        $db = db_connect();
        if ($db->table('usuario')->where('id_empresa', $idEmpresa)->where('operador', 1)->where('deleted_at', null)->countAllResults() > 0) {
            return true;
        }

        $fila = $db->table('empresa')->select('slug')->where('id', $idEmpresa)->get()->getRowArray();

        return $fila !== null && in_array((string) $fila['slug'], ['belly-plataforma', 'belly'], true);
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

    public static function idEmpresaSesion(): int
    {
        helper('vip2system');

        return (int) (id_empresa_sesion() ?? 0);
    }

    /**
     * Empresa de la petición: sesión o webhook. Nunca un id_empresa del body.
     *
     * @param array<string, mixed> $datos
     */
    public static function idEmpresaDe(array $datos = []): int
    {
        $sesion = self::idEmpresaSesion();
        $pedido = (int) ($datos['id_empresa'] ?? 0);
        if ($sesion > 0 && $pedido > 0 && $pedido !== $sesion) {
            throw new \DomainException('Esa empresa no es la tuya.');
        }

        return $sesion;
    }

    public static function exigirMismaEmpresa(int $idEmpresa, string $mensaje = 'Eso no pertenece a tu empresa.'): void
    {
        $sesion = self::idEmpresaSesion();
        if ($sesion > 0 && $idEmpresa !== $sesion) {
            throw new \DomainException($mensaje);
        }
    }
}
