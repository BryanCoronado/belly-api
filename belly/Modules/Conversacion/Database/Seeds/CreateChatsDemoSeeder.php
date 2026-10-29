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
            ['telefono' => '51999000101', 'nombre' => 'Andrea', 'apellido' => 'Quispe', 'distrito' => 'Miraflores', 'estado' => self::ESTADO_SIN_ABRIR, 'temperatura' => 'tibio', 'etiqueta' => 'Nuevo cliente', 'etapa' => 'Nuevo', 'tipo' => 'Prospecto', 'mensajes' => [
                ['cliente', 'Hola, buenas tardes. ¿Qué precio tiene el plan mensual?', 12],
            ]],
            ['telefono' => '51999000102', 'nombre' => 'Carlos', 'apellido' => 'Mendoza', 'distrito' => 'San Isidro', 'estado' => self::ESTADO_EN_ATENCION, 'temperatura' => 'caliente', 'etiqueta' => 'Interesado', 'etapa' => 'Interesado', 'tipo' => 'Cliente', 'mensajes' => [
                ['cliente', 'Quiero comprar las zapatillas urbanas, ¿tienen talla 42?', 95],
                ['usuario', 'Hola Carlos, sí tenemos la talla 42. ¿Te las enviamos hoy?', 90],
                ['cliente', 'Sí, por favor. ¿Cuánto es el envío?', 75],
            ]],
            ['telefono' => '51999000103', 'nombre' => 'Lucía', 'apellido' => 'Fernández', 'distrito' => 'Surco', 'estado' => self::ESTADO_EN_ATENCION, 'temperatura' => 'tibio', 'etiqueta' => 'Seguimiento', 'etapa' => 'Contactado', 'tipo' => 'Prospecto', 'mensajes' => [
                ['cliente', 'Me interesa la instalación a domicilio.', 300],
                ['usuario', 'Claro Lucía, ¿qué día te queda mejor?', 290],
                ['cliente', 'Déjame confirmarlo con mi esposo y te aviso.', 280],
            ]],
            ['telefono' => '51999000104', 'nombre' => 'Marco', 'apellido' => 'Rojas', 'distrito' => 'La Molina', 'estado' => self::ESTADO_FINALIZADO, 'temperatura' => 'venta', 'etiqueta' => 'Venta cerrada', 'etapa' => 'Ganado', 'tipo' => 'Cliente', 'motivo' => 'Quiere agendar', 'mensajes' => [
                ['cliente', 'Buenos días, quisiera agendar una consulta inicial.', 1500],
                ['usuario', 'Buenos días Marco, te agendo para mañana a las 10:00.', 1490],
                ['cliente', 'Perfecto, gracias.', 1480],
            ]],
            ['telefono' => '51999000105', 'nombre' => 'Valeria', 'apellido' => 'Campos', 'distrito' => 'Jesús María', 'estado' => self::ESTADO_SIN_ABRIR, 'temperatura' => 'frio', 'etiqueta' => 'Nuevo cliente', 'etapa' => 'Nuevo', 'tipo' => 'Prospecto', 'mensajes' => [
                ['cliente', 'Hola', 40],
                ['cliente', '¿Tienen mochilas?', 39],
            ]],
            ['telefono' => '51999000106', 'nombre' => 'Renzo', 'apellido' => 'Huamán', 'distrito' => 'Callao', 'estado' => self::ESTADO_FINALIZADO, 'temperatura' => 'frio', 'etiqueta' => null, 'etapa' => 'Perdido', 'tipo' => 'Prospecto', 'motivo' => 'No le interesó por el precio', 'mensajes' => [
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
        $etapas = $this->idsPorNombre('embudo_etapa', $idEmpresa);
        $tipos = $this->idsPorNombre('cliente_tipo', $idEmpresa);
        $motivos = $this->idsPorNombre('motivo_conversacion', $idEmpresa);

        foreach ($this->chats() as $i => $chat) {
            $existe = $this->db->table('cliente')->where('id_empresa', $idEmpresa)->where('telefono', $chat['telefono'])->countAllResults();
            if ($existe > 0) {
                continue;
            }

            $idUsuario = $asesores[$i % count($asesores)];
            $idCliente = $this->crearCliente($idEmpresa, $chat, $idUsuario, $etapas, $tipos);
            $this->crearConversacion($idEmpresa, (int) $app['id'], $idCliente, $idUsuario, $chat, $etiquetas, $motivos);
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

    /**
     * @param array<string, mixed> $chat
     * @param array<string, int> $etapas
     * @param array<string, int> $tipos
     */
    private function crearCliente(int $idEmpresa, array $chat, int $idUsuario, array $etapas, array $tipos): int
    {
        $ahora = date('Y-m-d H:i:s');
        $this->db->table('cliente')->insert([
            'id_empresa' => $idEmpresa,
            'telefono' => $chat['telefono'],
            'id_etapa' => $etapas[$chat['etapa'] ?? ''] ?? null,
            'etapa_at' => $ahora,
            'id_tipo' => $tipos[$chat['tipo'] ?? ''] ?? null,
            'id_responsable' => $idUsuario,
            'origen' => null,
            'created_at' => $ahora,
        ]);
        $idCliente = (int) $this->db->insertID();

        $this->db->table('cliente_perfil')->insert([
            'id_cliente' => $idCliente,
            'nombre' => $chat['nombre'],
            'apellido' => $chat['apellido'],
            'nombre_completo' => $chat['nombre'] . ' ' . $chat['apellido'],
            'distrito' => $chat['distrito'],
            'created_at' => $ahora,
        ]);

        return $idCliente;
    }

    /**
     * @param array<string, mixed> $chat
     * @param array<string, int> $etiquetas
     * @param array<string, int> $motivos
     */
    private function crearConversacion(int $idEmpresa, int $idApp, int $idCliente, int $idUsuario, array $chat, array $etiquetas, array $motivos): void
    {
        $idEtiqueta = $chat['etiqueta'] !== null ? ($etiquetas[$chat['etiqueta']] ?? null) : null;
        $primero = max(array_column($chat['mensajes'], 2));
        $this->db->table('conversacion')->insert([
            'id_empresa' => $idEmpresa,
            'id_cliente' => $idCliente,
            'id_usuario' => $idUsuario,
            'id_estado' => $chat['estado'],
            'origen' => 'WhatsApp',
            'id_motivo' => isset($chat['motivo']) ? ($motivos[$chat['motivo']] ?? null) : null,
            'id_etiqueta' => $idEtiqueta,
            'temperatura' => $chat['temperatura'],
            'id_app' => $idApp,
            'asignado_at' => $this->haceMinutos($primero),
            'created_at' => $this->haceMinutos($primero),
        ]);
        $idConversacion = (int) $this->db->insertID();

        if ($idEtiqueta !== null && $this->db->tableExists('cliente_etiqueta')) {
            $this->db->table('cliente_etiqueta')->insert([
                'id_empresa' => $idEmpresa,
                'id_cliente' => $idCliente,
                'id_etiqueta' => $idEtiqueta,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        foreach ($chat['mensajes'] as [$quien, $texto, $minutos]) {
            $this->db->table('mensaje')->insert([
                'id_empresa' => $idEmpresa,
                'id_conversacion' => $idConversacion,
                'id_estado' => $quien === 'cliente' ? self::MENSAJE_RECIBIDO : self::MENSAJE_LEIDO,
                'mensaje' => $texto,
                'contesta' => $quien,
                'id_usuario' => $quien === 'usuario' ? $idUsuario : null,
                'created_at' => $this->haceMinutos($minutos),
            ]);
        }
    }

    /** @return array<string, int> */
    private function idsPorNombre(string $tabla, int $idEmpresa): array
    {
        if (! $this->db->tableExists($tabla)) {
            return [];
        }

        $ids = [];
        foreach ($this->db->table($tabla)->select('id, nombre')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->get()->getResultArray() as $fila) {
            $ids[(string) $fila['nombre']] = (int) $fila['id'];
        }

        return $ids;
    }

    private function haceMinutos(int $minutos): string
    {
        return date('Y-m-d H:i:s', time() - $minutos * 60);
    }
}
