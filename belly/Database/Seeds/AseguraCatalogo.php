<?php

namespace Belly\Database\Seeds;

/**
 * Inserta filas de catálogo por id sin duplicar. Sirve para volver a sembrar una base ya instalada.
 */
trait AseguraCatalogo
{
    /** @param list<array<string, mixed>> $filas */
    protected function asegurarPorId(string $tabla, array $filas): void
    {
        foreach ($filas as $fila) {
            if ($this->db->table($tabla)->where('id', $fila['id'])->countAllResults() > 0) {
                continue;
            }
            $this->db->table($tabla)->insert($fila);
        }
    }
}
