<?php

namespace Belly\Conversacion\Database\Seeds;

use Belly\Database\Seeds\EmpresaDemo;
use Belly\Usuarios\Services\ConsultaAsesores;
use CodeIgniter\Database\Seeder;

/**
 * Etiquetas, clientes y conversaciones de ejemplo repartidas entre los asesores de la empresa.
 * Ejecútalo después de sembrar los asesores y la conexión de WhatsApp.
 *
 * php spark db:seed "Belly\Conversacion\Database\Seeds\CreateChatsDemoSeeder"
 */
class CreateChatsDemoSeeder extends Seeder
{
    use EmpresaDemo;

    private const ESTADO_EN_ATENCION = 1;
    private const ESTADO_FINALIZADO = 2;
    private const ESTADO_SIN_ABRIR = 3;
    private const MENSAJE_RECIBIDO = 3;
    private const MENSAJE_LEIDO = 4;

    private const ETIQUETAS = [
        'Nuevo cliente' => '#3b82f6',
        'Interesado' => '#10b981',
        'Seguimiento' => '#f59e0b',
        'Venta cerrada' => '#8b5cf6',
    ];

    /**
     * Cada chat: contacto, estado, temperatura, etiqueta y mensajes [quien, texto, minutos atrás].
     *
     * @return list<array<string, mixed>>
     */
    private function chats(): array
    {
        return [
            ['telefono' => '51999000101', 'nombre' => 'Andrea', 'apellido' => 'Quispe', 'distrito' => 'Miraflores', 'estado' => self::ESTADO_SIN_ABRIR, 'temperatura' => 'tibio', 'etiqueta' => 'Nuevo cliente', 'mensajes' => [
                ['cliente', 'Hola, buenas tardes. ¿Qué precio tiene el plan mensual?', 12],
            ]],
            ['telefono' => '51999000102', 'nombre' => 'Carlos', 'apellido' => 'Mendoza', 'distrito' => 'San Isidro', 'estado' => self::ESTADO_EN_ATENCION, 'temperatura' => 'caliente', 'etiqueta' => 'Interesado', 'mensajes' => [
                ['cliente', 'Quiero comprar las zapatillas urbanas, ¿tienen talla 42?', 95],
                ['usuario', 'Hola Carlos, sí tenemos la talla 42. ¿Te las enviamos hoy?', 90],
                ['cliente', 'Sí, por favor. ¿Cuánto es el envío?', 75],
            ]],
            ['telefono' => '51999000103', 'nombre' => 'Lucía', 'apellido' => 'Fernández', 'distrito' => 'Surco', 'estado' => self::ESTADO_EN_ATENCION, 'temperatura' => 'tibio', 'etiqueta' => 'Seguimiento', 'mensajes' => [
                ['cliente', 'Me interesa la instalación a domicilio.', 300],
                ['usuario', 'Claro Lucía, ¿qué día te queda mejor?', 290],
                ['cliente', 'Déjame confirmarlo con mi esposo y te aviso.', 280],
            ]],
            ['telefono' => '51999000104', 'nombre' => 'Marco', 'apellido' => 'Rojas', 'distrito' => 'La Molina', 'estado' => self::ESTADO_FINALIZADO, 'temperatura' => 'venta', 'etiqueta' => 'Venta cerrada', 'motivo' => 2, 'mensajes' => [
                ['cliente', 'Buenos días, quisiera agendar una consulta inicial.', 1500],
                ['usuario', 'Buenos días Marco, te agendo para mañana a las 10:00.', 1490],
                ['cliente', 'Perfecto, gracias.', 1480],
            ]],
            ['telefono' => '51999000105', 'nombre' => 'Valeria', 'apellido' => 'Campos', 'distrito' => 'Jesús María', 'estado' => self::ESTADO_SIN_ABRIR, 'temperatura' => 'frio', 'etiqueta' => 'Nuevo cliente', 'mensajes' => [
                ['cliente', 'Hola', 40],
                ['cliente', '¿Tienen mochilas?', 39],
            ]],
            ['telefono' => '51999000106', 'nombre' => 'Renzo', 'apellido' => 'Huamán', 'distrito' => 'Callao', 'estado' => self::ESTADO_FINALIZADO, 'temperatura' => 'frio', 'etiqueta' => null, 'motivo' => 3, 'mensajes' => [
                ['cliente', '¿Cuánto cuesta el polo básico?', 3000],
                ['usuario', 'Cuesta S/ 39.90, Renzo.', 2990],
                ['cliente', 'Muy caro, gracias.', 2980],
            ]],
        ];
    }

    public function run()
    {
        $idEmpresa = $this->idEmpresaDemo();

        $app = $this->db->table('app')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->orderBy('id', 'ASC')->get(1)->getRowArray();
        $asesores = array_map(
            static fn (array $f): int => (int) $f['id'],
            $this->db->query('SELECT u.id FROM usuario u WHERE ' . ConsultaAsesores::condicion() . ' ORDER BY u.id', [$idEmpresa])->getResultArray()
        );
        if ($app === null || $asesores === []) {
            return;
        }

        $etiquetas = $this->etiquetas($idEmpresa);

        foreach ($this->chats() as $i => $chat) {
            $existe = $this->db->table('cliente')->where('id_empresa', $idEmpresa)->where('telefono', $chat['telefono'])->countAllResults();
            if ($existe > 0) {
                continue;
            }

            $idCliente = $this->crearCliente($idEmpresa, $chat);
            $idUsuario = $asesores[$i % count($asesores)];
            $this->crearConversacion($idEmpresa, (int) $app['id'], $idCliente, $idUsuario, $chat, $etiquetas);
        }
    }

    /** @return array<string, int> */
    private function etiquetas(int $idEmpresa): array
    {
        $ids = [];
        foreach (self::ETIQUETAS as $nombre => $color) {
            $fila = $this->db->table('etiqueta')->where('id_empresa', $idEmpresa)->where('nombre', $nombre)->where('deleted_at', null)->get(1)->getRowArray();
            if ($fila === null) {
                $this->db->table('etiqueta')->insert(['id_empresa' => $idEmpresa, 'nombre' => $nombre, 'color' => $color, 'created_at' => date('Y-m-d H:i:s')]);
                $ids[$nombre] = (int) $this->db->insertID();
                continue;
            }
            $ids[$nombre] = (int) $fila['id'];
        }

        return $ids;
    }

    /** @param array<string, mixed> $chat */
    private function crearCliente(int $idEmpresa, array $chat): int
    {
        $this->db->table('cliente')->insert([
            'id_empresa' => $idEmpresa,
            'telefono' => $chat['telefono'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $idCliente = (int) $this->db->insertID();

        $this->db->table('cliente_perfil')->insert([
            'id_cliente' => $idCliente,
            'nombre' => $chat['nombre'],
            'apellido' => $chat['apellido'],
            'nombre_completo' => $chat['nombre'] . ' ' . $chat['apellido'],
            'distrito' => $chat['distrito'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $idCliente;
    }

    /**
     * @param array<string, mixed> $chat
     * @param array<string, int> $etiquetas
     */
    private function crearConversacion(int $idEmpresa, int $idApp, int $idCliente, int $idUsuario, array $chat, array $etiquetas): void
    {
        $primero = max(array_column($chat['mensajes'], 2));
        $this->db->table('conversacion')->insert([
            'id_empresa' => $idEmpresa,
            'id_cliente' => $idCliente,
            'id_usuario' => $idUsuario,
            'id_estado' => $chat['estado'],
            'origen' => 'WhatsApp',
            'id_motivo' => $chat['motivo'] ?? null,
            'id_etiqueta' => $chat['etiqueta'] !== null ? ($etiquetas[$chat['etiqueta']] ?? null) : null,
            'temperatura' => $chat['temperatura'],
            'id_app' => $idApp,
            'asignado_at' => $this->haceMinutos($primero),
            'created_at' => $this->haceMinutos($primero),
        ]);
        $idConversacion = (int) $this->db->insertID();

        foreach ($chat['mensajes'] as [$quien, $texto, $minutos]) {
            $this->db->table('mensaje')->insert([
                'id_empresa' => $idEmpresa,
                'id_conversacion' => $idConversacion,
                'id_estado' => $quien === 'cliente' ? self::MENSAJE_RECIBIDO : self::MENSAJE_LEIDO,
                'mensaje' => $texto,
                'contesta' => $quien,
                'created_at' => $this->haceMinutos($minutos),
            ]);
        }
    }

    private function haceMinutos(int $minutos): string
    {
        return date('Y-m-d H:i:s', time() - $minutos * 60);
    }
}
