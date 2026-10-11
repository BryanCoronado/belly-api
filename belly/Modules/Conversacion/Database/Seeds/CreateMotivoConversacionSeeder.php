<?php

namespace Belly\Conversacion\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateMotivoConversacionSeeder extends Seeder
{


    public function run()
    {
        $data = [
            [
                'id' => 1,
                'nombre' => 'Busca precio',
            ],
            [
                'id' => 2,
                'nombre' => 'Agenda',
            ],
            [
                'id' => 3,
                'nombre' => 'No interesado por el precio',
            ],
            [
                'id' => 4,
                'nombre' => 'No interesado por la distancia',
            ],
            [
                'id' => 5,
                'nombre' => 'Vehiculo antiguo o no califica',
            ],
            [
                'id' => 6,
                'nombre' => 'Pendiente (lo pensará)',
            ],
            [
                'id' => 7,
                'nombre' => 'No confirma fecha de agenda',
            ],
            [
                'id' => 8,
                'nombre' => 'No realizamos el servicio solicitado',
            ],
            [
                'id' => 9,
                'nombre' => 'No responde después del saludo/bienvenida',
            ],
            [
                'id' => 10,
                'nombre' => 'Deja de responder',
            ],
            [
                'id' => 11,
                'nombre' => 'Volver a contactar',
            ],
            [
                'id' => 12,
                'nombre' => 'Error del sistema',
            ],
            [
                'id' => 13,
                'nombre' => 'Gestionado por llamada',
            ],
            [
                'id' => 14,
                'nombre' => 'Cliente en provincia',
            ],
            [
                'id' => 15,
                'nombre' => 'Gestionado por otro agente',
            ],
            [
                'id' => 16,
                'nombre' => 'Disconformidad cliente',
            ],
            [
                'id' => 17,
                'nombre' => 'Pendiente de cotización',
            ],
            [
                'id' => 18,
                'nombre' => 'Broma / Falta de respeto',
            ],
            [
                'id' => 19,
                'nombre' => 'Agendamiento parcial',
            ],
        ];

        $this->db->table('motivo_conversacion')->insertBatch($data);

    }
}

