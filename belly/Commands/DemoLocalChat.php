<?php

namespace Belly\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Prepara local: WhatsApp simulado + asesores + clientes/conversaciones de prueba.
 *
 * php spark demo:local-chat
 * SEED_EMPRESA_ID=4 php spark demo:local-chat
 */
class DemoLocalChat extends BaseCommand
{
    protected $group = 'Belly';
    protected $name = 'demo:local-chat';
    protected $description = 'Simula Meta conectado y siembra chats de prueba (solo desarrollo).';
    protected $usage = 'demo:local-chat';

    public function run(array $params)
    {
        if (ENVIRONMENT === 'production') {
            CLI::error('No se puede correr demo:local-chat en producción.');

            return EXIT_ERROR;
        }

        $db = Database::connect();
        $idEmpresa = (int) (env('SEED_EMPRESA_ID') ?: 1);
        $chatsAntes = $db->table('conversacion')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->countAllResults();

        $seeder = Database::seeder();
        $seeder->call(\Belly\Database\Seeds\CreateCatalogoSeeder::class);
        $seeder->call(\Belly\Usuarios\Database\Seeds\CreateUsuariosSeeder::class);
        $seeder->call(\Belly\Configuracion\Database\Seeds\CreatePortafolioSeeder::class);
        $seeder->call(\Belly\Configuracion\Database\Seeds\CreateWhatsappLocalSeeder::class);
        $seeder->call(\Belly\Usuarios\Database\Seeds\CreateAsesoresDemoSeeder::class);
        $seeder->call(\Belly\Configuracion\Database\Seeds\CreateUsuarioAppSeeder::class);
        $seeder->call(\Belly\Crm\Database\Seeds\CreateCrmDemoSeeder::class);
        $seeder->call(\Belly\Conversacion\Database\Seeds\CreateChatsDemoSeeder::class);
        $this->asignarUsuariosAlWhatsapp($db, $idEmpresa);

        $chatsDespues = $db->table('conversacion')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->countAllResults();
        $nuevos = max(0, $chatsDespues - $chatsAntes);

        $app = $db->table('app')
            ->select('id, nombre, telefono, phone_number_id, verificado_at')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();

        $empresa = $db->table('empresa')->select('id, nombre, slug')->where('id', $idEmpresa)->get(1)->getRowArray();

        CLI::write('');
        CLI::write('=== Demo local lista ===', 'green');
        CLI::write('Empresa: #' . $idEmpresa . ' ' . ($empresa['nombre'] ?? '') . ' (slug: ' . ($empresa['slug'] ?? '') . ')');
        if ($app) {
            CLI::write('WhatsApp simulado: ' . ($app['nombre'] ?? '') . ' · ' . ($app['telefono'] ?? '') . ' · phone_number_id=' . ($app['phone_number_id'] ?? ''));
            CLI::write('verificado_at: ' . ($app['verificado_at'] ?? 'null'));
        } else {
            CLI::error('No quedó ninguna app de WhatsApp para la empresa.');
        }
        CLI::write("Conversaciones nuevas: {$nuevos} (total empresa: {$chatsDespues})");
        CLI::write('');
        CLI::write('Login asesores demo (correo / clave):', 'yellow');
        CLI::write('  mtorres@demo.belly.test  /  Asesor.123');
        CLI::write('  lramirez@demo.belly.test /  Asesor.123');
        CLI::write('  cvega@demo.belly.test    /  Asesor.123');
        CLI::write('  dsalas@demo.belly.test   /  Asesor.123');
        CLI::write('');
        CLI::write('Admin demo:', 'yellow');
        CLI::write('  developer@demo.belly.test       /  Dev.123');
        CLI::write('  administrador@demo.belly.test   /  Admin.123');
        CLI::write('');
        CLI::write('Tip: el token local-demo activa el mock de envío (sin Graph). Opcional: WHATSAPP_MOCK=true');

        return EXIT_SUCCESS;
    }

    /** Vincula todos los usuarios activos de la empresa al número simulado. */
    private function asignarUsuariosAlWhatsapp($db, int $idEmpresa): void
    {
        $app = $db->table('app')
            ->select('id')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();
        if ($app === null || ! $db->tableExists('usuario_app')) {
            return;
        }

        $idApp = (int) $app['id'];
        $usuarios = $db->table('usuario')
            ->select('id')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($usuarios as $usuario) {
            $idUsuario = (int) $usuario['id'];
            $existe = $db->table('usuario_app')
                ->where('id_usuario', $idUsuario)
                ->where('id_app', $idApp)
                ->countAllResults();
            if ($existe > 0) {
                continue;
            }
            $db->table('usuario_app')->insert([
                'id_usuario' => $idUsuario,
                'id_app' => $idApp,
            ]);
        }
    }
}
