<?php

namespace Belly\Configuracion\Database\Seeds;

use Belly\Database\Seeds\SoloDesarrollo;
use Belly\Services\Cifrado;
use CodeIgniter\Database\Seeder;

/** Solo desarrollo: número de WhatsApp de prueba (simulado o con token Meta real). */
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

        $tokenMeta = trim((string) (env('META_SEED_ACCESS_TOKEN') ?: ''));
        $tokenPlano = $tokenMeta !== '' ? $tokenMeta : CreateWhatsappLocalSeeder::TOKEN_LOCAL;

        $this->db->table('app')->insert([
            'id_empresa' => 1,
            'id_portafolio' => (int) $portafolio['id'],
            'nombre' => $tokenMeta !== '' ? 'belly whatsapp' : 'WhatsApp local (demo)',
            'telefono' => $tokenMeta !== '' ? '+51908528231' : '+51999990000',
            'phone_number_id' => $tokenMeta !== '' ? '1136836639502449' : '100000000000001',
            'whatsapp_business_account_id' => $tokenMeta !== '' ? '978774918039409' : '200000000000001',
            'estado' => 1,
            'access_token' => Cifrado::cifrar($tokenPlano),
            'verificado_at' => date('Y-m-d H:i:s'),
            'modo' => 'compartido',
            'max_usuarios' => 30,
            'tipo' => 'NORMAL',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
