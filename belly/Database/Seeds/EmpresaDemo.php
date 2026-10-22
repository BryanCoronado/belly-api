<?php

namespace Belly\Database\Seeds;

/**
 * Empresa a la que van los datos de prueba. Por defecto la empresa 1;
 * para otra, define SEED_EMPRESA_ID antes de sembrar (PowerShell: `$env:SEED_EMPRESA_ID = 4`).
 */
trait EmpresaDemo
{
    private function idEmpresaDemo(): int
    {
        $id = (int) (env('SEED_EMPRESA_ID') ?: 1);
        if ($this->db->table('empresa')->where('id', $id)->countAllResults() === 0) {
            throw new \RuntimeException("La empresa {$id} no existe; no se pueden sembrar datos de prueba.");
        }

        return $id;
    }
}
