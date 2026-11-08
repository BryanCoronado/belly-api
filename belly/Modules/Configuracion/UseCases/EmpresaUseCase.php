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

    public function buscarPorId(int $id): ?EmpresaEntity
    {
        if ($id <= 0) {
            return null;
        }

        $empresa = $this->modelo()->find($id);

        return $empresa instanceof EmpresaEntity ? $empresa : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listar(): array
    {
        $this->exigirOperador();

        $filas = $this->modelo()->orderBy('created_at', 'DESC')->findAll();
        $ids = array_map(static fn (EmpresaEntity $empresa) => (int) $empresa->id, $filas);
        $extras = $this->extrasOperador($ids);

        $salida = [];
        foreach ($filas as $empresa) {
            $id = (int) $empresa->id;
            if (Autorizacion::esEmpresaDueño($id)) {
                continue;
            }
            $salida[] = [
                ...$this->presentar($empresa),
                'propia' => false,
                'creada' => $empresa->created_at ? (string) $empresa->created_at : null,
                'personas' => $extras['personas'][$id] ?? 0,
                'whatsapp' => $extras['whatsapp'][$id] ?? false,
                'admin_nombre' => $extras['admins'][$id]['nombre'] ?? '',
                'admin_correo' => $extras['admins'][$id]['correo'] ?? '',
            ];
        }

        return $salida;
    }

    /** @param array<string, mixed> $datos */
    public function cambiarEstado(int $id, array $datos): array
    {
        $this->exigirOperador();

        $empresa = $this->modelo()->find($id);
        if (! $empresa instanceof EmpresaEntity) {
            throw new \Exception('No encontramos esa empresa.');
        }
        if (Autorizacion::esEmpresaDueño($id)) {
            throw new \Exception('El dueño no es una empresa del CRM.');
        }

        $activa = filter_var($datos['activa'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->modelo()->update($id, ['estado' => $activa ? 1 : 0]);

        if (! $activa) {
            $this->expulsarSesionesDe($id);
        }

        return ['id' => $id, 'estado' => $activa ? 1 : 0];
    }

    private function expulsarSesionesDe(int $idEmpresa): void
    {
        $ids = array_map(
            static fn (array $fila): int => (int) $fila['id'],
            db_connect()->table('usuario')->select('id')->where('id_empresa', $idEmpresa)->where('deleted_at', null)->get()->getResultArray()
        );
        if ($ids === []) {
            return;
        }

        $ahora = date('Y-m-d H:i:s');
        db_connect()->table('usuario_login')->whereIn('id_usuario', $ids)->where('deleted_at', null)->update([
            'deleted_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        helper('socket');
        \SocketHelper::expulsarEmpresa($idEmpresa);
    }

    /** @param array<string, mixed> $datos */
    public function registrar(array $datos): array
    {
        $inicial = $this->planInicial();
        $datos['plan'] = $inicial['clave'];
        $datos['id_plan'] = $inicial['id'];

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
        $elegido = $this->resolverPlan($datos);
        $plan = $elegido['clave'];
        $idPlan = $elegido['id'];
        $admin = $datos['admin'] ?? null;

        if ($nombre === '' || $slug === '') {
            throw new \Exception('El nombre de la empresa es obligatorio.');
        }
        if (mb_strlen($nombre) > 120 || mb_strlen($slug) > 80) {
            throw new \Exception('El nombre de la empresa supera el tamaño permitido.');
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
            $empresa->id_plan = $idPlan;
            $empresa->estado = 1;
            $this->aplicarIdentidad($empresa, $datos['identidad'] ?? null);

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

    /** @param array<string, mixed>|null $identidad */
    private function aplicarIdentidad(EmpresaEntity $empresa, ?array $identidad): void
    {
        if ($identidad === null || ! db_connect()->fieldExists('numero_doc', 'empresa')) {
            return;
        }

        $empresa->tipo_doc = (string) ($identidad['tipo'] ?? '');
        $empresa->numero_doc = (string) ($identidad['numero'] ?? '');
        $empresa->razon_social = (string) ($identidad['nombre'] ?? '');
        $empresa->estado_sunat = (string) ($identidad['estado'] ?? '');
        $empresa->condicion_sunat = (string) ($identidad['condicion'] ?? '');
        $empresa->direccion_fiscal = (string) ($identidad['direccion'] ?? '');
        $empresa->ubigeo = (string) ($identidad['ubigeo'] ?? '');
        $empresa->representante_nombre = (string) ($identidad['representante_nombre'] ?? '');
        $empresa->representante_cargo = (string) ($identidad['representante_cargo'] ?? '');
        $empresa->representante_documento = (string) ($identidad['representante_documento'] ?? '');
        $empresa->identidad_json = json_encode($identidad['crudo'] ?? $identidad, JSON_UNESCAPED_UNICODE);
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
        $cupo = (new \Belly\Services\CupoPlan())->resumen((int) $empresa->id);

        return [
            'id' => (int) $empresa->id,
            'nombre' => $empresa->nombre,
            'plan' => $empresa->plan ?: ($cupo['plan']['clave'] ?? 'gratis'),
            'id_plan' => (int) ($empresa->id_plan ?? $cupo['plan']['id'] ?? 0),
            'plan_nombre' => $cupo['plan']['nombre'] ?? 'Gratis',
            'plan_precio' => $cupo['plan']['precio_mensual'] ?? 0,
            'uso' => $cupo['uso'],
            'limites' => [
                'conversaciones' => $cupo['plan']['max_conversaciones'],
                'equipos' => $cupo['plan']['max_equipos'],
                'personas' => $cupo['plan']['max_personas'],
                'flujos' => $cupo['plan']['max_flujos'],
            ],
            'estado' => (int) $empresa->estado,
            'modo_ahorro' => $this->estaEnAhorro($empresa),
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{clave: string, id: int|null}
     */
    private function resolverPlan(array $datos): array
    {
        $pedido = trim((string) ($datos['plan'] ?? ''));
        if ($pedido !== '') {
            $id = $this->idPlanPorClave($pedido);
            if ($id) {
                return ['clave' => $pedido, 'id' => $id];
            }
        }

        return $this->planInicial();
    }

    /** Primer plan activo (Gratis): el de menor orden y precio.
     * @return array{clave: string, id: int|null}
     */
    private function planInicial(): array
    {
        if (! db_connect()->tableExists('plan')) {
            return ['clave' => 'gratis', 'id' => null];
        }

        $fila = db_connect()->table('plan')
            ->select('id, clave')
            ->where('activo', 1)
            ->where('deleted_at', null)
            ->orderBy('orden', 'ASC')
            ->orderBy('precio_mensual', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        if ($fila) {
            return ['clave' => (string) $fila['clave'], 'id' => (int) $fila['id']];
        }

        return ['clave' => 'gratis', 'id' => $this->idPlanPorClave('gratis')];
    }

    private function idPlanPorClave(string $clave): ?int
    {
        if ($clave === '' || ! db_connect()->tableExists('plan')) {
            return null;
        }
        $fila = db_connect()->table('plan')->select('id')->where('clave', $clave)->where('deleted_at', null)->get()->getRowArray();

        return $fila ? (int) $fila['id'] : null;
    }

    /**
     * @param list<int> $ids
     * @return array{personas: array<int, int>, whatsapp: array<int, bool>, admins: array<int, array{nombre:string, correo:string}>}
     */
    private function extrasOperador(array $ids): array
    {
        $personas = [];
        $whatsapp = [];
        $admins = [];
        if ($ids === []) {
            return ['personas' => $personas, 'whatsapp' => $whatsapp, 'admins' => $admins];
        }

        $db = db_connect();
        foreach ($db->table('usuario')->select('id_empresa, COUNT(*) as total')->whereIn('id_empresa', $ids)->where('deleted_at', null)->groupBy('id_empresa')->get()->getResultArray() as $fila) {
            $personas[(int) $fila['id_empresa']] = (int) $fila['total'];
        }

        foreach ($db->table('app')->select('id_empresa')->whereIn('id_empresa', $ids)->where('deleted_at', null)->where('phone_number_id IS NOT NULL', null, false)->where('phone_number_id !=', '')->get()->getResultArray() as $fila) {
            $whatsapp[(int) $fila['id_empresa']] = true;
        }

        foreach ($db->table('usuario as u')
            ->select('u.id_empresa, p.nombres, p.apellidos, p.correo')
            ->join('usuario_perfil as p', 'p.id_usuario = u.id', 'left')
            ->whereIn('u.id_empresa', $ids)
            ->where('u.sudo', 1)
            ->where('u.deleted_at', null)
            ->orderBy('u.id', 'ASC')
            ->get()
            ->getResultArray() as $fila) {
            $id = (int) $fila['id_empresa'];
            if (isset($admins[$id])) {
                continue;
            }
            $admins[$id] = [
                'nombre' => trim(($fila['nombres'] ?? '') . ' ' . ($fila['apellidos'] ?? '')),
                'correo' => (string) ($fila['correo'] ?? ''),
            ];
        }

        return ['personas' => $personas, 'whatsapp' => $whatsapp, 'admins' => $admins];
    }

    /** Modo de la empresa de la sesión (o la indicada). */
    public function modoAhorro(?int $idEmpresa = null): bool
    {
        $id = $idEmpresa ?? (int) (id_empresa_sesion() ?? 0);
        if ($id <= 0) {
            return false;
        }

        $empresa = $this->modelo()->find($id);

        return $empresa instanceof EmpresaEntity && $this->estaEnAhorro($empresa);
    }

    /** @return array{modo_ahorro:bool} */
    public function leerModo(): array
    {
        return ['modo_ahorro' => $this->modoAhorro()];
    }

    /** @param array<string, mixed> $datos */
    public function guardarModo(array $datos): array
    {
        Autorizacion::exigirAdministradorEmpresa('Solo el administrador puede cambiar el modo de la empresa.');

        $id = (int) (id_empresa_sesion() ?? 0);
        if ($id <= 0) {
            throw new \DomainException('No hay empresa en la sesión.');
        }
        if (! db_connect()->fieldExists('modo_ahorro', 'empresa')) {
            throw new \DomainException('Falta actualizar la base para el modo ahorro.');
        }

        $activo = filter_var($datos['modo_ahorro'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->modelo()->update($id, ['modo_ahorro' => $activo ? 1 : 0]);

        return ['modo_ahorro' => $activo];
    }

    private function estaEnAhorro(object $empresa): bool
    {
        if (! db_connect()->fieldExists('modo_ahorro', 'empresa')) {
            return false;
        }

        return (int) ($empresa->modo_ahorro ?? 0) === 1;
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
