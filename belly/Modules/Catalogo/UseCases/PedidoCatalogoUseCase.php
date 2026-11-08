<?php

namespace Belly\Catalogo\UseCases;

use Belly\Catalogo\Entities\PedidoCatalogoEntity;
use Belly\Catalogo\Models\PedidoCatalogoModel;
use DomainException;

/** Pedidos que llegan del carrito web por WhatsApp. */
class PedidoCatalogoUseCase
{
    private PedidoCatalogoModel $pedidos;

    public function __construct(?PedidoCatalogoModel $pedidos = null)
    {
        $this->pedidos = $pedidos ?? new PedidoCatalogoModel();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(int $limite = 50): array
    {
        $filas = $this->pedidos->orderBy('id', 'DESC')->findAll(max(1, min(200, $limite)));

        return array_map(fn (PedidoCatalogoEntity $p): array => $this->aArray($p), $filas);
    }

    /** @return array<string, mixed> */
    public function cambiarEstado(int $id, string $estado): array
    {
        $pedido = $this->pedidos->find($id);
        if (! $pedido instanceof PedidoCatalogoEntity) {
            throw new DomainException('No encontramos ese pedido.');
        }
        $ok = in_array($estado, ['nuevo', 'atendido', 'cerrado'], true) ? $estado : 'nuevo';
        $this->pedidos->update($id, ['estado' => $ok]);

        return $this->aArray($this->pedidos->find($id));
    }

    /**
     * Si el mensaje es un pedido del catálogo web, lo guarda.
     *
     * @return array<string, mixed>|null
     */
    public function registrarDesdeMensaje(int $idEmpresa, ?int $idCliente, ?int $idConversacion, string $texto): ?array
    {
        if ($idEmpresa <= 0 || ! $this->parecePedido($texto)) {
            return null;
        }

        // Evita duplicar el mismo texto en la misma conversación (reintentos de webhook).
        if ($idConversacion) {
            $existe = db_connect()->table('pedido_catalogo')
                ->where('id_empresa', $idEmpresa)
                ->where('id_conversacion', $idConversacion)
                ->where('texto', mb_substr($texto, 0, 65000))
                ->countAllResults();
            if ($existe > 0) {
                return null;
            }
        }

        $parseado = $this->parsear($texto);
        $db = db_connect();
        $db->table('pedido_catalogo')->insert([
            'id_empresa' => $idEmpresa,
            'id_cliente' => $idCliente ?: null,
            'id_conversacion' => $idConversacion ?: null,
            'tienda' => $parseado['tienda'],
            'cliente_nombre' => $parseado['cliente_nombre'],
            'nota' => $parseado['nota'],
            'items' => json_encode($parseado['items'], JSON_UNESCAPED_UNICODE),
            'total' => $parseado['total'],
            'moneda' => $parseado['moneda'],
            'estado' => 'nuevo',
            'texto' => mb_substr($texto, 0, 65000),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $db->insertID();
        $pedido = $db->table('pedido_catalogo')->where('id', $id)->get(1)->getRowArray();
        if (! is_array($pedido)) {
            return ['id' => $id];
        }

        return [
            'id' => (int) $pedido['id'],
            'id_cliente' => $pedido['id_cliente'] !== null ? (int) $pedido['id_cliente'] : null,
            'id_conversacion' => $pedido['id_conversacion'] !== null ? (int) $pedido['id_conversacion'] : null,
            'tienda' => $pedido['tienda'],
            'cliente_nombre' => $pedido['cliente_nombre'],
            'nota' => $pedido['nota'],
            'items' => json_decode((string) ($pedido['items'] ?? '[]'), true) ?: [],
            'total' => $pedido['total'] !== null ? (float) $pedido['total'] : null,
            'moneda' => $pedido['moneda'] ?: 'PEN',
            'estado' => $pedido['estado'] ?: 'nuevo',
            'texto' => $pedido['texto'],
            'created_at' => $pedido['created_at'] ?? null,
        ];
    }

    public function parecePedido(string $texto): bool
    {
        if (! mensaje_viene_de_catalogo($texto)) {
            return false;
        }

        return str_contains(mb_strtolower($texto), 'quiero hacer un pedido')
            || (bool) preg_match('/•\s*\d+\s*[×x]/u', $texto);
    }

    /**
     * @return array{tienda:?string,cliente_nombre:?string,nota:?string,items:list<array{cantidad:int,nombre:string,variante:?string,subtotal:?float}>,total:?float,moneda:string}
     */
    private function parsear(string $texto): array
    {
        $tienda = null;
        if (preg_match('/pedido en \*([^*]+)\*/u', $texto, $m)) {
            $tienda = trim($m[1]);
        }

        $items = [];
        if (preg_match_all('/•\s*(\d+)\s*[×x]\s*(.+?)(?:\s*—\s*([A-Z]{3})\s*([0-9]+(?:[.,][0-9]+)?))?$/mu', $texto, $filas, PREG_SET_ORDER)) {
            foreach ($filas as $fila) {
                $nombre = trim($fila[2]);
                $variante = null;
                if (preg_match('/^(.*?)\s*\((.+)\)\s*$/u', $nombre, $partes)) {
                    $nombre = trim($partes[1]);
                    $variante = trim($partes[2]);
                }
                $subtotal = isset($fila[4]) ? (float) str_replace(',', '.', $fila[4]) : null;
                $items[] = [
                    'cantidad' => (int) $fila[1],
                    'nombre' => mb_substr($nombre, 0, 150),
                    'variante' => $variante,
                    'subtotal' => $subtotal,
                ];
            }
        }

        $total = null;
        $moneda = 'PEN';
        if (preg_match('/\*Total:\s*([A-Z]{3})\s*([0-9]+(?:[.,][0-9]+)?)\*/u', $texto, $m)) {
            $moneda = $m[1];
            $total = (float) str_replace(',', '.', $m[2]);
        } elseif ($items !== []) {
            $suma = 0.0;
            $tiene = true;
            foreach ($items as $item) {
                if ($item['subtotal'] === null) {
                    $tiene = false;
                    break;
                }
                $suma += (float) $item['subtotal'];
            }
            if ($tiene) {
                $total = $suma;
            }
        }

        $cliente = null;
        if (preg_match('/Nombre:\s*(.+)$/mu', $texto, $m)) {
            $cliente = mb_substr(trim($m[1]), 0, 120);
        }
        $nota = null;
        if (preg_match('/Nota:\s*(.+)$/mu', $texto, $m)) {
            $nota = mb_substr(trim($m[1]), 0, 1000);
        }

        return [
            'tienda' => $tienda,
            'cliente_nombre' => $cliente,
            'nota' => $nota,
            'items' => $items,
            'total' => $total,
            'moneda' => $moneda,
        ];
    }

    /** @return array<string, mixed> */
    private function aArray(PedidoCatalogoEntity $pedido): array
    {
        return [
            'id' => $pedido->id,
            'id_cliente' => $pedido->id_cliente,
            'id_conversacion' => $pedido->id_conversacion,
            'tienda' => $pedido->tienda,
            'cliente_nombre' => $pedido->cliente_nombre,
            'nota' => $pedido->nota,
            'items' => is_array($pedido->items) ? $pedido->items : [],
            'total' => $pedido->total,
            'moneda' => $pedido->moneda ?: 'PEN',
            'estado' => $pedido->estado ?: 'nuevo',
            'texto' => $pedido->texto,
            'created_at' => $pedido->created_at?->format('c'),
        ];
    }

}
