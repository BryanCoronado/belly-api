<?php

namespace Belly\Configuracion\UseCases;

use Belly\Configuracion\Config\ConfiguracionConfig;
use Belly\Configuracion\Entities\EmpresaEntity;
use Belly\Configuracion\Entities\PortafolioEntity;
use Belly\Configuracion\Models\EmpresaModel;
use Belly\Configuracion\Models\PortafolioModel;
use Belly\Services\Autorizacion;

class EmpresaUseCase
{
    protected ConfiguracionConfig $config;

    public function __construct(ConfiguracionConfig $config)
    {
        $this->config = $config;
    }

    public function buscarActivaPorSlug(string $slug): ?EmpresaEntity
    {
        $slug = $this->normalizarSlug($slug);
        if ($slug === '') {
            return null;
        }

        $empresa = $this->modelo()->where('slug', $slug)->where('estado', 1)->first();

        return $empresa instanceof EmpresaEntity ? $empresa : null;
    }

    public function buscarActivaPorId(int $id): ?EmpresaEntity
    {
        if ($id <= 0) {
            return null;
        }

        $empresa = $this->modelo()->where('id', $id)->where('estado', 1)->first();

        return $empresa instanceof EmpresaEntity ? $empresa : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listar(): array
    {
        $this->exigirOperador();

        $filas = $this->modelo()->orderBy('nombre', 'ASC')->findAll();
        $salida = [];
        foreach ($filas as $empresa) {
            $salida[] = $this->presentar($empresa);
        }

        return $salida;
    }

    /** @param array<string, mixed> $datos */
    public function registrar(array $datos): array
    {
        return $this->guardarEmpresa($datos);
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): array
    {
        $this->exigirOperador();

        return $this->guardarEmpresa($datos);
    }

    /** @param array<string, mixed> $datos */
    private function guardarEmpresa(array $datos): array
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $slugPedido = trim((string) ($datos['slug'] ?? ''));
        $slug = $this->slugDisponible($slugPedido !== '' ? $slugPedido : $nombre);
        $plan = trim((string) ($datos['plan'] ?? 'base'));
        $admin = $datos['admin'] ?? null;

        if ($nombre === '' || $slug === '') {
            throw new \Exception('El nombre de la empresa es obligatorio.');
        }
        if (mb_strlen($nombre) > 120 || mb_strlen($slug) > 80) {
            throw new \Exception('El nombre de la empresa supera el tamaño permitido.');
        }
        if ($plan === '') {
            $plan = 'base';
        }
        if (mb_strlen($plan) > 40) {
            throw new \Exception('El plan supera los 40 caracteres.');
        }
        if (! is_array($admin)) {
            throw new \Exception('El administrador de la empresa es obligatorio.');
        }
        $db = db_connect();
        $db->transStart();

        try {
            $empresa = new EmpresaEntity();
            $empresa->nombre = $nombre;
            $empresa->slug = $slug;
            $empresa->plan = $plan;
            $empresa->estado = 1;

            $modelo = $this->modelo();
            if (! $modelo->save($empresa)) {
                throw new \Exception(json_encode($modelo->errors()));
            }

            $idEmpresa = (int) $modelo->insertID;
            $this->crearPortafolioInicial($idEmpresa);
            $administrador = usuario()->crearAdministradorEmpresa($idEmpresa, $admin);
            helper('vip2system');
            $roles = acceso()->crearRolesIniciales($idEmpresa);
            acceso()->asignarUsuarioARol((int) $administrador['id_usuario'], $roles['admin'], $idEmpresa);

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \Exception('No se pudo crear la empresa.');
            }

            return [
                'id' => $idEmpresa,
                'nombre' => $nombre,
                'slug' => $slug,
                'plan' => $plan,
                'estado' => 1,
                'admin' => $administrador,
            ];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function crearPortafolioInicial(int $idEmpresa): void
    {
        $portafolio = new PortafolioEntity();
        $portafolio->id_empresa = $idEmpresa;
        $portafolio->nombre = 'whatsapp';
        $portafolio->business_id = 0;
        $portafolio->estado = 1;

        $modelo = new PortafolioModel();
        if (! $modelo->save($portafolio)) {
            throw new \Exception('No se pudo crear el portafolio inicial.');
        }
    }

    private function exigirOperador(): void
    {
        Autorizacion::exigirOperadorPlataforma('No autorizado para administrar empresas.');
    }

    private function presentar(EmpresaEntity $empresa): array
    {
        return [
            'id' => (int) $empresa->id,
            'nombre' => $empresa->nombre,
            'slug' => $empresa->slug,
            'plan' => $empresa->plan,
            'estado' => (int) $empresa->estado,
        ];
    }

    private function slugDisponible(string $origen): string
    {
        $base = $this->normalizarSlug($origen);
        if ($base === '') {
            $base = 'empresa';
        }
        $base = substr($base, 0, 70);

        $slug = $base;
        $n = 2;
        while ($this->modelo()->where('slug', $slug)->first() !== null) {
            $slug = $base . '-' . $n;
            $n++;
            if ($n > 80) {
                $slug = $base . '-' . bin2hex(random_bytes(2));
                break;
            }
        }

        return $slug;
    }

    private function normalizarSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function modelo(): EmpresaModel
    {
        $clase = $this->config->empresa_provider;

        return new $clase();
    }
}
