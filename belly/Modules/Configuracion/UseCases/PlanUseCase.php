<?php

namespace Belly\Configuracion\UseCases;

use Belly\Configuracion\Entities\PlanEntity;
use Belly\Configuracion\Models\PlanModel;
use Belly\Services\Autorizacion;

class PlanUseCase
{
    /** @return array<int, array<string, mixed>> */
    public function listar(): array
    {
        $this->exigir();
        $filas = (new PlanModel())->orderBy('orden', 'ASC')->orderBy('id', 'ASC')->findAll();

        return array_map(fn (PlanEntity $plan) => $this->presentar($plan), $filas);
    }

    /** @param array<string, mixed> $datos */
    public function guardar(array $datos, ?int $id = null): array
    {
        $this->exigir();
        $modelo = new PlanModel();
        $plan = $id ? $modelo->find($id) : new PlanEntity();
        if ($id && ! $plan instanceof PlanEntity) {
            throw new \Exception('No encontramos ese plan.');
        }

        $clave = $this->clave((string) ($datos['clave'] ?? $plan->clave ?? $datos['nombre'] ?? ''));
        if ($clave === '') {
            throw new \Exception('El plan necesita un nombre corto.');
        }
        $ocupada = $modelo->where('clave', $clave);
        if ($id) {
            $ocupada->where('id !=', $id);
        }
        if ($ocupada->first() !== null) {
            throw new \Exception('Ya hay un plan con esa clave.');
        }

        $plan->clave = $clave;
        $plan->nombre = trim((string) ($datos['nombre'] ?? '')) ?: ucfirst($clave);
        $plan->descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $plan->precio_mensual = max(0, (float) ($datos['precio_mensual'] ?? 0));
        $plan->moneda = strtoupper(substr(trim((string) ($datos['moneda'] ?? 'PEN')), 0, 3)) ?: 'PEN';
        $plan->max_conversaciones = max(0, (int) ($datos['max_conversaciones'] ?? 0));
        $plan->max_equipos = max(0, (int) ($datos['max_equipos'] ?? 0));
        $plan->max_personas_equipo = max(0, (int) ($datos['max_personas_equipo'] ?? 0));
        $plan->max_personas = max(0, (int) ($datos['max_personas'] ?? 0));
        $plan->max_flujos = max(0, (int) ($datos['max_flujos'] ?? 0));
        $plan->max_envios = max(0, (int) ($datos['max_envios'] ?? 0));
        $plan->max_numeros = max(0, (int) ($datos['max_numeros'] ?? 1));
        $plan->incluye_agente_ia = filter_var($datos['incluye_agente_ia'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $plan->activo = filter_var($datos['activo'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $plan->orden = (int) ($datos['orden'] ?? 0);

        if (! $modelo->save($plan)) {
            throw new \Exception('No se pudo guardar el plan.');
        }

        return $this->presentar($modelo->find((int) ($id ?: $modelo->insertID)));
    }

    public function eliminar(int $id): void
    {
        $this->exigir();
        $usos = db_connect()->table('empresa')->where('id_plan', $id)->where('deleted_at', null)->countAllResults();
        if ($usos > 0) {
            throw new \Exception('Hay empresas en este plan. Cámbialas antes de borrarlo.');
        }
        (new PlanModel())->delete($id);
    }

    /** @param array<string, mixed> $datos */
    public function asignar(int $idEmpresa, array $datos): array
    {
        $this->exigir();
        $idPlan = (int) ($datos['id_plan'] ?? 0);
        $plan = (new PlanModel())->find($idPlan);
        if (! $plan instanceof PlanEntity) {
            throw new \Exception('No encontramos ese plan.');
        }
        $empresa = empresa()->buscarActivaPorId($idEmpresa) ?? (new \Belly\Configuracion\Models\EmpresaModel())->find($idEmpresa);
        if ($empresa === null) {
            throw new \Exception('No encontramos esa empresa.');
        }
        if (Autorizacion::esEmpresaDueño($idEmpresa)) {
            throw new \Exception('El dueño no es una empresa del CRM.');
        }

        (new \Belly\Configuracion\Models\EmpresaModel())->update($idEmpresa, [
            'id_plan' => $idPlan,
            'plan' => $plan->clave,
        ]);

        $presentado = $this->presentar($plan);
        (new PagoUseCase())->asegurarCargoMes($idEmpresa, $presentado);

        return [
            'id' => $idEmpresa,
            'id_plan' => $idPlan,
            'plan' => $presentado,
        ];
    }

    /** @return array<string, mixed> */
    private function presentar(PlanEntity $plan): array
    {
        return [
            'id' => (int) $plan->id,
            'clave' => (string) $plan->clave,
            'nombre' => (string) $plan->nombre,
            'descripcion' => (string) ($plan->descripcion ?? ''),
            'precio_mensual' => (float) $plan->precio_mensual,
            'moneda' => (string) ($plan->moneda ?: 'PEN'),
            'max_conversaciones' => (int) $plan->max_conversaciones,
            'max_equipos' => (int) $plan->max_equipos,
            'max_personas_equipo' => (int) $plan->max_personas_equipo,
            'max_personas' => (int) $plan->max_personas,
            'max_flujos' => (int) $plan->max_flujos,
            'max_envios' => (int) $plan->max_envios,
            'max_numeros' => (int) $plan->max_numeros,
            'incluye_agente_ia' => (int) $plan->incluye_agente_ia === 1,
            'incluye' => \Belly\Services\CupoPlan::incluye((int) $plan->incluye_agente_ia === 1),
            'activo' => (int) $plan->activo === 1,
            'orden' => (int) $plan->orden,
        ];
    }

    private function clave(string $origen): string
    {
        $clave = strtolower(trim($origen));
        $clave = preg_replace('/[^a-z0-9]+/', '-', $clave) ?? '';

        return trim($clave, '-');
    }

    private function exigir(): void
    {
        Autorizacion::exigirOperadorPlataforma('No autorizado para administrar planes.');
    }
}
