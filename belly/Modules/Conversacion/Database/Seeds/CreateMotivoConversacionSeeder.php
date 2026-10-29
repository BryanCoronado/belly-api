<?php

namespace Belly\Conversacion\Database\Seeds;

use Belly\Database\Seeds\AseguraCatalogo;
use CodeIgniter\Database\Seeder;

/** Motivos globales de ejemplo. Cada empresa copia los suyos la primera vez que abre el CRM. */
class CreateMotivoConversacionSeeder extends Seeder
{
    use AseguraCatalogo;

    public const MOTIVOS = [
        1 => ['Pidió precio', '#f59e0b'],
        2 => ['Quiere agendar', '#10b981'],
        3 => ['No le interesó por el precio', '#f43f5e'],
        4 => ['No le interesó por la ubicación', '#f43f5e'],
        5 => ['No cumple los requisitos', '#64748b'],
        6 => ['Pendiente (lo pensará)', '#0ea5e9'],
        7 => ['No confirma la fecha', '#f59e0b'],
        8 => ['Pidió algo que no ofrecemos', '#8b5cf6'],
        9 => ['No respondió tras el saludo', '#64748b'],
        10 => ['Dejó de responder', '#64748b'],
        11 => ['Volver a contactar', '#0ea5e9'],
        12 => ['Error del sistema', '#94a3b8'],
        13 => ['Gestionado por llamada', '#8b5cf6'],
        14 => ['Cliente fuera de la zona', '#f43f5e'],
        15 => ['Gestionado por otro asesor', '#8b5cf6'],
        16 => ['Cliente disconforme', '#f43f5e'],
        17 => ['Pendiente de cotización', '#f59e0b'],
        18 => ['Falta de respeto', '#f43f5e'],
        19 => ['Agendamiento parcial', '#10b981'],
    ];

    public function run()
    {
        $filas = [];
        foreach (self::MOTIVOS as $id => [$nombre, $color]) {
            $filas[] = [
                'id' => $id,
                'id_empresa' => null,
                'nombre' => $nombre,
                'color' => $color,
                'orden' => $id,
            ];
        }

        $this->asegurarPorId('motivo_conversacion', $filas);
    }
}
