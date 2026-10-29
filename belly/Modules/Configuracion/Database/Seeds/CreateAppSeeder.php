<?php

namespace Belly\Configuracion\Database\Seeds;

use Belly\Database\Seeds\SoloDesarrollo;
use CodeIgniter\Database\Seeder;

/** Solo desarrollo: número de WhatsApp de prueba. */
class CreateAppSeeder extends Seeder
{
    use SoloDesarrollo;

    public function run()
    {
        $this->exigirDesarrollo();

        if ($this->db->table('app')->where('id_empresa', 1)->where('deleted_at', null)->countAllResults() > 0) {
            return;
        }

        $portafolio = $this->db->table('portafolio')
            ->select('id')
            ->where('id_empresa', 1)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();
        if ($portafolio === null) {
            return;
        }

        $this->db->table('app')->insert([
            'id_empresa' => 1,
            'id_portafolio' => (int) $portafolio['id'],
            'nombre' => 'belly whatsapp',
            'telefono' => '+51908528231',
            'phone_number_id' => '1136836639502449',
            'whatsapp_business_account_id' => '978774918039409',
            'estado' => 1,
            'access_token' => \Belly\Services\Cifrado::cifrar(env('META_SEED_ACCESS_TOKEN') ?: null),
            'verificado_at' => env('META_SEED_ACCESS_TOKEN') ? date('Y-m-d H:i:s') : null,
            'modo' => 'compartido',
            'max_usuarios' => 30,
            'tipo' => 'NORMAL',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
