<?php

namespace Belly\Crm\Database\Seeds;

use Belly\Crm\Catalogo\CatalogoCrm;
use Belly\Database\Seeds\EmpresaDemo;
use CodeIgniter\Database\Seeder;

/**
 * Embudo, tipos de cliente y motivos de cierre de la empresa de prueba.
 * Si la empresa ya tiene filas, no las vuelve a crear.
 *
 * php spark db:seed "Belly\Crm\Database\Seeds\CreateCrmDemoSeeder"
 */
class CreateCrmDemoSeeder extends Seeder
{
    use EmpresaDemo;

    public function run()
    {
        $idEmpresa = $this->idEmpresaDemo();
        $ahora = date('Y-m-d H:i:s');

        $this->sembrarSiVacio('embudo_etapa', $idEmpresa, function () use ($idEmpresa, $ahora) {
            foreach (CatalogoCrm::etapas() as $orden => [$nombre, $color, $resultado]) {
                $this->db->table('embudo_etapa')->insert([
                    'id_empresa' => $idEmpresa,
                    'nombre' => $nombre,
                    'color' => $color,
                    'orden' => $orden + 1,
                    'resultado' => $resultado,
                    'created_at' => $ahora,
                ]);
            }
        });

        $this->sembrarSiVacio('cliente_tipo', $idEmpresa, function () use ($idEmpresa, $ahora) {
            foreach (CatalogoCrm::tipos() as $orden => [$nombre, $color]) {
                $this->db->table('cliente_tipo')->insert([
                    'id_empresa' => $idEmpresa,
                    'nombre' => $nombre,
                    'color' => $color,
                    'orden' => $orden + 1,
                    'created_at' => $ahora,
                ]);
            }
        });

        $this->sembrarSiVacio('motivo_conversacion', $idEmpresa, function () use ($idEmpresa, $ahora) {
            $globales = $this->db->table('motivo_conversacion')
                ->where('id_empresa', null)
                ->where('deleted_at', null)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
            $fuente = $globales !== []
                ? $globales
                : array_map(static fn (array $m) => ['nombre' => $m[0], 'color' => $m[1]], CatalogoCrm::motivos());

            foreach ($fuente as $orden => $fila) {
                $this->db->table('motivo_conversacion')->insert([
                    'id_empresa' => $idEmpresa,
                    'nombre' => $fila['nombre'],
                    'color' => $fila['color'] ?? '#64748b',
                    'orden' => $orden + 1,
                    'created_at' => $ahora,
                ]);
            }
        });
    }

    private function sembrarSiVacio(string $tabla, int $idEmpresa, callable $sembrar): void
    {
        if ($this->db->table($tabla)->where('id_empresa', $idEmpresa)->countAllResults() > 0) {
            return;
        }
        $sembrar();
    }
}
