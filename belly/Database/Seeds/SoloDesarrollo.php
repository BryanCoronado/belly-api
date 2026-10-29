<?php

namespace Belly\Database\Seeds;

/**
 * Impide sembrar datos de prueba cuando el entorno es producción.
 */
trait SoloDesarrollo
{
    protected function exigirDesarrollo(): void
    {
        if (ENVIRONMENT === 'production') {
            throw new \RuntimeException('Los datos de prueba no se siembran en producción. Usa InitSeeder.');
        }
    }
}
