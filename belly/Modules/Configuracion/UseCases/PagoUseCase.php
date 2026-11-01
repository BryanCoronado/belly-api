<?php

namespace Belly\Configuracion\UseCases;

use Belly\Services\Autorizacion;

class PagoUseCase
{
    /** @return array<int, array<string, mixed>> */
    public function listarOperador(): array
    {
        Autorizacion::exigirOperadorPlataforma();

        return $this->filas(null);
    }

    /** @return array{plan: array<string, mixed>, pagos: array<int, array<string, mixed>>} */
    public function listarEmpresa(): array
    {
        Autorizacion::exigirAdministradorEmpresa();
        helper('vip2system');
        $id = (int) (id_empresa_sesion() ?? 0);

        return [
            'plan' => cupo()->deEmpresa($id),
            'pagos' => $this->filas($id),
        ];
    }

    /** @param array<string, mixed> $datos */
    public function registrar(array $datos): array
    {
        Autorizacion::exigirOperadorPlataforma();
        $idEmpresa = (int) ($datos['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            throw new \Exception('Falta la empresa.');
        }

        return $this->guardarCargo($idEmpresa, $datos);
    }

    /** @param array<string, mixed> $datos */
    public function marcar(int $id, array $datos): array
    {
        Autorizacion::exigirOperadorPlataforma();
        $fila = db_connect()->table('pago')->where('id', $id)->get()->getRowArray();
        if ($fila === null) {
            throw new \Exception('No encontramos ese pago.');
        }

        $estado = trim((string) ($datos['estado'] ?? 'pagado'));
        if (! in_array($estado, ['pendiente', 'pagado', 'vencido', 'anulado'], true)) {
            throw new \Exception('Estado no válido.');
        }

        $cambios = [
            'estado' => $estado,
            'metodo' => trim((string) ($datos['metodo'] ?? $fila['metodo'] ?? '')) ?: null,
            'referencia' => trim((string) ($datos['referencia'] ?? $fila['referencia'] ?? '')) ?: null,
            'nota' => trim((string) ($datos['nota'] ?? $fila['nota'] ?? '')) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $cambios['pagado_at'] = $estado === 'pagado' ? date('Y-m-d H:i:s') : null;
        db_connect()->table('pago')->where('id', $id)->update($cambios);

        return $this->presentar(array_merge($fila, $cambios), $this->nombres());
    }

    /** @param array<string, mixed> $plan */
    public function asegurarCargoMes(int $idEmpresa, array $plan): void
    {
        if ($idEmpresa <= 0 || (float) ($plan['precio_mensual'] ?? 0) <= 0) {
            return;
        }
        if (! db_connect()->tableExists('pago')) {
            return;
        }

        $periodo = date('Y-m');
        $existe = db_connect()->table('pago')
            ->where('id_empresa', $idEmpresa)
            ->where('periodo', $periodo)
            ->whereIn('estado', ['pendiente', 'pagado'])
            ->countAllResults();
        if ($existe > 0) {
            return;
        }

        $this->insertar($idEmpresa, [
            'id_plan' => (int) ($plan['id'] ?? 0),
            'periodo' => $periodo,
            'monto' => (float) $plan['precio_mensual'],
            'moneda' => (string) ($plan['moneda'] ?? 'PEN'),
            'estado' => 'pendiente',
            'vencimiento' => date('Y-m-t'),
        ]);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function guardarCargo(int $idEmpresa, array $datos): array
    {
        $periodo = preg_match('/^\d{4}-\d{2}$/', (string) ($datos['periodo'] ?? ''))
            ? (string) $datos['periodo']
            : date('Y-m');
        $monto = max(0, (float) ($datos['monto'] ?? 0));
        if ($monto <= 0) {
            throw new \Exception('El monto tiene que ser mayor a 0.');
        }

        $id = $this->insertar($idEmpresa, [
            'id_plan' => (int) ($datos['id_plan'] ?? 0) ?: null,
            'periodo' => $periodo,
            'monto' => $monto,
            'moneda' => strtoupper(substr((string) ($datos['moneda'] ?? 'PEN'), 0, 3)),
            'estado' => in_array($datos['estado'] ?? '', ['pendiente', 'pagado'], true) ? $datos['estado'] : 'pendiente',
            'metodo' => trim((string) ($datos['metodo'] ?? '')) ?: null,
            'referencia' => trim((string) ($datos['referencia'] ?? '')) ?: null,
            'nota' => trim((string) ($datos['nota'] ?? '')) ?: null,
            'vencimiento' => $datos['vencimiento'] ?? date('Y-m-t'),
            'pagado_at' => ($datos['estado'] ?? '') === 'pagado' ? date('Y-m-d H:i:s') : null,
        ]);

        $fila = db_connect()->table('pago')->where('id', $id)->get()->getRowArray() ?? [];

        return $this->presentar($fila, $this->nombres());
    }

    /** @param array<string, mixed> $datos */
    private function insertar(int $idEmpresa, array $datos): int
    {
        db_connect()->table('pago')->insert([
            'id_empresa' => $idEmpresa,
            'id_plan' => $datos['id_plan'] ?: null,
            'periodo' => $datos['periodo'],
            'monto' => $datos['monto'],
            'moneda' => $datos['moneda'] ?? 'PEN',
            'estado' => $datos['estado'] ?? 'pendiente',
            'metodo' => $datos['metodo'] ?? null,
            'referencia' => $datos['referencia'] ?? null,
            'nota' => $datos['nota'] ?? null,
            'vencimiento' => $datos['vencimiento'] ?? null,
            'pagado_at' => $datos['pagado_at'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) db_connect()->insertID();
    }

    /** @return array<int, array<string, mixed>> */
    private function filas(?int $idEmpresa): array
    {
        if (! db_connect()->tableExists('pago')) {
            return [];
        }

        $q = db_connect()->table('pago')->orderBy('periodo', 'DESC')->orderBy('id', 'DESC');
        if ($idEmpresa) {
            $q->where('id_empresa', $idEmpresa);
        }
        $nombres = $this->nombres();
        $salida = [];
        foreach ($q->get()->getResultArray() as $fila) {
            $salida[] = $this->presentar($fila, $nombres);
        }

        return $salida;
    }

    /**
     * @param array<string, mixed> $fila
     * @param array<int, string> $nombres
     * @return array<string, mixed>
     */
    private function presentar(array $fila, array $nombres): array
    {
        $idEmpresa = (int) ($fila['id_empresa'] ?? 0);

        return [
            'id' => (int) ($fila['id'] ?? 0),
            'id_empresa' => $idEmpresa,
            'empresa' => $nombres[$idEmpresa] ?? '',
            'id_plan' => (int) ($fila['id_plan'] ?? 0) ?: null,
            'periodo' => (string) ($fila['periodo'] ?? ''),
            'monto' => (float) ($fila['monto'] ?? 0),
            'moneda' => (string) ($fila['moneda'] ?? 'PEN'),
            'estado' => (string) ($fila['estado'] ?? 'pendiente'),
            'metodo' => $fila['metodo'] ?? null,
            'referencia' => $fila['referencia'] ?? null,
            'nota' => $fila['nota'] ?? null,
            'vencimiento' => $fila['vencimiento'] ?? null,
            'pagado_at' => $fila['pagado_at'] ?? null,
            'creado' => $fila['created_at'] ?? null,
        ];
    }

    /** @return array<int, string> */
    private function nombres(): array
    {
        $mapa = [];
        foreach (db_connect()->table('empresa')->select('id, nombre')->get()->getResultArray() as $fila) {
            $mapa[(int) $fila['id']] = (string) $fila['nombre'];
        }

        return $mapa;
    }
}
