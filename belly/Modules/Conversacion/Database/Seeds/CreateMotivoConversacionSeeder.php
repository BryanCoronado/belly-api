<?php

namespace Belly\Conversacion\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Motivos con los que se cierra una conversación. Sirven para cualquier negocio. */
class CreateMotivoConversacionSeeder extends Seeder
{
    public const MOTIVOS = [
        1 => 'Pidió precio',
        2 => 'Quiere agendar',
        3 => 'No le interesó por el precio',
        4 => 'No le interesó por la ubicación',
        5 => 'No cumple los requisitos',
        6 => 'Pendiente (lo pensará)',
        7 => 'No confirma la fecha',
        8 => 'Pidió algo que no ofrecemos',
        9 => 'No respondió tras el saludo',
        10 => 'Dejó de responder',
        11 => 'Volver a contactar',
        12 => 'Error del sistema',
        13 => 'Gestionado por llamada',
        14 => 'Cliente fuera de la zona',
        15 => 'Gestionado por otro asesor',
        16 => 'Cliente disconforme',
        17 => 'Pendiente de cotización',
        18 => 'Falta de respeto',
        19 => 'Agendamiento parcial',
    ];

    public function run()
    {
        $data = [];
        foreach (self::MOTIVOS as $id => $nombre) {
            $data[] = ['id' => $id, 'nombre' => $nombre];
        }

        $this->db->table('motivo_conversacion')->insertBatch($data);
    }
}
