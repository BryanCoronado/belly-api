<?php

namespace Belly\Configuracion\Database\Seeds;

use Belly\Database\Seeds\EmpresaDemo;
use Belly\Services\Cifrado;
use CodeIgniter\Database\Seeder;

/**
 * Solo desarrollo: simula un número Meta conectado (sin llamar a Graph).
 * Deja la empresa lista para pasar whatsappFilter y usar chat/app local.
 *
 * php spark db:seed "Belly\Configuracion\Database\Seeds\CreateWhatsappLocalSeeder"
 */
class CreateWhatsappLocalSeeder extends Seeder
{
    use EmpresaDemo;

    public const TOKEN_LOCAL = 'local-demo-token';

    public function run()
    {
        $idEmpresa = $this->idEmpresaDemo();
        $ahora = date('Y-m-d H:i:s');

        $idPortafolio = $this->asegurarPortafolio($idEmpresa, $ahora);
        $this->asegurarConexionMeta($idEmpresa, $ahora);
        $this->asegurarApp($idEmpresa, $idPortafolio, $ahora);
    }

    private function asegurarPortafolio(int $idEmpresa, string $ahora): int
    {
        $fila = $this->db->table('portafolio')
            ->select('id')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();

        if ($fila !== null) {
            return (int) $fila['id'];
        }

        $this->db->table('portafolio')->insert([
            'id_empresa' => $idEmpresa,
            'nombre' => 'whatsapp',
            'business_id' => 0,
            'estado' => 1,
            'created_at' => $ahora,
        ]);

        return (int) $this->db->insertID();
    }

    private function asegurarConexionMeta(int $idEmpresa, string $ahora): void
    {
        if (! $this->db->tableExists('conexion_meta')) {
            return;
        }

        $fila = $this->db->table('conexion_meta')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->get(1)
            ->getRowArray();

        $datos = [
            'meta_app_id' => '000000000000000',
            'app_secret' => Cifrado::cifrar('local-demo-app-secret'),
            'webhook_verificado_at' => $ahora,
            'updated_at' => $ahora,
            'deleted_at' => null,
        ];

        if ($fila !== null) {
            $this->db->table('conexion_meta')->where('id', $fila['id'])->update($datos);

            return;
        }

        $this->db->table('conexion_meta')->insert(array_merge($datos, [
            'id_empresa' => $idEmpresa,
            'webhook_clave' => bin2hex(random_bytes(16)),
            'verify_token' => 'belly-local-' . $idEmpresa,
            'created_at' => $ahora,
        ]));
    }

    private function asegurarApp(int $idEmpresa, int $idPortafolio, string $ahora): void
    {
        $token = Cifrado::cifrar(self::TOKEN_LOCAL);
        $fila = $this->db->table('app')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();

        $datos = [
            'id_portafolio' => $idPortafolio,
            'nombre' => 'WhatsApp local (demo)',
            'telefono' => '+51999990000',
            'phone_number_id' => '100000000000001',
            'whatsapp_business_account_id' => '200000000000001',
            'estado' => 1,
            'access_token' => $token,
            'verificado_at' => $ahora,
            'modo' => 'compartido',
            'max_usuarios' => 30,
            'tipo' => 'NORMAL',
            'updated_at' => $ahora,
            'deleted_at' => null,
        ];

        if ($fila !== null) {
            $this->db->table('app')->where('id', $fila['id'])->update($datos);

            return;
        }

        $this->db->table('app')->insert(array_merge($datos, [
            'id_empresa' => $idEmpresa,
            'created_at' => $ahora,
        ]));
    }
}
