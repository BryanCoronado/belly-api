<?php

namespace Belly\Catalogo\UseCases;

use Belly\Catalogo\Entities\FamiliaProductoEntity;
use Belly\Catalogo\Models\FamiliaProductoModel;
use Belly\Catalogo\Models\ProductoModel;
use DomainException;

/** Familias con las que la empresa ordena sus productos (por ejemplo: Bebidas, Servicios, Planes). */
class FamiliaProductoUseCase
{
    private FamiliaProductoModel $familias;
    private ProductoModel $productos;

    public function __construct(?FamiliaProductoModel $familias = null, ?ProductoModel $productos = null)
    {
        $this->familias = $familias ?? new FamiliaProductoModel();
        $this->productos = $productos ?? new ProductoModel();
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $totales = [];
        $filas = $this->productos
            ->asArray()
            ->select('id_familia, COUNT(*) AS total')
            ->where('id_familia IS NOT NULL', null, false)
            ->groupBy('id_familia')
            ->findAll();
        foreach ($filas as $fila) {
            $totales[(int) $fila['id_familia']] = (int) $fila['total'];
        }

        $familias = $this->familias->orderBy('orden', 'ASC')->orderBy('nombre', 'ASC')->findAll();

        return array_map(fn (FamiliaProductoEntity $f): array => $this->aArray($f, $totales[$f->id] ?? 0), $familias);
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function crear(array $datos): array
    {
        $ultimo = $this->familias->builder()->selectMax('orden', 'maximo')->where('deleted_at', null)->get()->getRowArray();

        $id = $this->familias->insert([
            'id_empresa' => $this->idEmpresa(),
            'nombre' => $this->texto($datos['nombre'] ?? null),
            'descripcion' => $this->texto($datos['descripcion'] ?? null) ?: null,
            'orden' => (int) ($ultimo['maximo'] ?? 0) + 1,
            'activo' => 1,
        ]);
        $this->verificar($id);

        return $this->aArray($this->buscar((int) $id), 0);
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function actualizar(int $id, array $datos): array
    {
        $familia = $this->buscar($id);

        $cambios = [];
        if (array_key_exists('nombre', $datos)) {
            $cambios['nombre'] = $this->texto($datos['nombre']);
        }
        if (array_key_exists('descripcion', $datos)) {
            $cambios['descripcion'] = $this->texto($datos['descripcion']) ?: null;
        }
        if (array_key_exists('activo', $datos)) {
            $cambios['activo'] = $datos['activo'] ? 1 : 0;
        }
        if (array_key_exists('orden', $datos)) {
            $cambios['orden'] = max(0, (int) $datos['orden']);
        }

        if ($cambios !== []) {
            // El validador del modelo exige estos campos aunque no cambien.
            $cambios += ['id_empresa' => $familia->id_empresa, 'nombre' => $familia->nombre];
            $this->verificar($this->familias->update($id, $cambios) ? $id : false);
        }

        return $this->aArray($this->buscar($id), $this->totalProductos($id));
    }

    /** Los productos de la familia no se borran: quedan "sin familia". */
    public function eliminar(int $id): void
    {
        $this->buscar($id);

        $this->productos->skipValidation(true)->where('id_familia', $id)->set(['id_familia' => null])->update();
        $this->productos->skipValidation(false);
        $this->familias->delete($id);
    }

    private function buscar(int $id): FamiliaProductoEntity
    {
        $familia = $this->familias->find($id);
        if ($familia === null) {
            throw new DomainException('No encontramos esa familia.');
        }

        return $familia;
    }

    private function totalProductos(int $idFamilia): int
    {
        return (int) $this->productos->where('id_familia', $idFamilia)->countAllResults();
    }

    /** @param int|string|false $resultado */
    private function verificar($resultado): void
    {
        if ($resultado === false || $resultado === 0) {
            $errores = $this->familias->errors();
            throw new DomainException($errores !== [] ? (string) reset($errores) : 'No se pudo guardar la familia.');
        }
    }

    private function texto(mixed $valor): string
    {
        return trim((string) ($valor ?? ''));
    }

    private function idEmpresa(): int
    {
        helper('vip2system');
        $id = id_empresa_sesion();
        if ($id === null) {
            throw new DomainException('No se pudo identificar tu empresa.');
        }

        return $id;
    }

    /** @return array<string, mixed> */
    private function aArray(FamiliaProductoEntity $familia, int $totalProductos): array
    {
        return [
            'id' => $familia->id,
            'nombre' => $familia->nombre,
            'descripcion' => $familia->descripcion,
            'orden' => $familia->orden,
            'activo' => $familia->activo,
            'total_productos' => $totalProductos,
        ];
    }
}
