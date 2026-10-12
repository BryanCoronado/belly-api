<?php

namespace Belly\Services;

/**
 * Fija la empresa activa cuando no hay usuario logueado (webhooks).
 * Así FiltraPorEmpresa limita todas las consultas a esa empresa y un evento
 * nunca puede tocar datos de otra.
 */
final class ContextoEmpresa
{
    private static ?int $idEmpresa = null;

    public static function actual(): ?int
    {
        return self::$idEmpresa;
    }

    /**
     * @template T
     * @param callable(): T $accion
     * @return T
     */
    public static function ejecutarComo(int $idEmpresa, callable $accion)
    {
        $anterior = self::$idEmpresa;
        self::$idEmpresa = $idEmpresa;

        try {
            return $accion();
        } finally {
            self::$idEmpresa = $anterior;
        }
    }
}
