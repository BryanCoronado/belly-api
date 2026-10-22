<?php

namespace Belly\Mensajes\Database\Seeds;

use Belly\Database\Seeds\EmpresaDemo;
use CodeIgniter\Database\Seeder;

/**
 * Respuestas rápidas de ejemplo, a nombre del administrador de la empresa.
 *
 * php spark db:seed "Belly\Mensajes\Database\Seeds\CreatePlantillasRapidasDemoSeeder"
 */
class CreatePlantillasRapidasDemoSeeder extends Seeder
{
    use EmpresaDemo;

    private const PLANTILLAS = [
        'Saludo' => 'Hola, gracias por escribirnos. ¿En qué podemos ayudarte?',
        'Pedir datos' => 'Para ayudarte mejor, ¿me compartes tu nombre y tu distrito?',
        'Precios' => 'Con gusto te comparto los precios. ¿Qué producto o servicio te interesa?',
        'Despedida' => 'Gracias por tu tiempo. Quedamos atentos a cualquier consulta.',
    ];

    public function run()
    {
        $idEmpresa = $this->idEmpresaDemo();

        $admin = $this->db->table('usuario')
            ->select('id')
            ->where('id_empresa', $idEmpresa)
            ->where('sudo', 1)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();
        if ($admin === null) {
            return;
        }

        foreach (self::PLANTILLAS as $nombre => $contenido) {
            $existe = $this->db->table('plantilla_rapida')
                ->where('id_empresa', $idEmpresa)
                ->where('nombre', $nombre)
                ->where('deleted_at', null)
                ->countAllResults();
            if ($existe > 0) {
                continue;
            }

            $this->db->table('plantilla_rapida')->insert([
                'id_empresa' => $idEmpresa,
                'nombre' => $nombre,
                'contenido' => $contenido,
                'id_usuario' => (int) $admin['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
