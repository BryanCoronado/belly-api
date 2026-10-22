<?php

namespace Belly\Catalogo\UseCases;

use Belly\Catalogo\Entities\ProductoEntity;
use Belly\Catalogo\Models\FamiliaProductoModel;
use Belly\Catalogo\Models\ProductoModel;
use DomainException;

/**
 * Productos o servicios de la empresa. Lo común (precio, unidad, imagen) va en campos fijos
 * y lo propio de cada negocio (talla, marca, duración...) en una lista libre de "detalles".
 */
class ProductoUseCase
{
    private const MAX_DETALLES = 20;
    private const LIMITE_LISTADO = 500;

    private ProductoModel $productos;
    private FamiliaProductoModel $familias;

    public function __construct(?ProductoModel $productos = null, ?FamiliaProductoModel $familias = null)
    {
        $this->productos = $productos ?? new ProductoModel();
        $this->familias = $familias ?? new FamiliaProductoModel();
    }

    /**
     * @param array{q?:mixed, id_familia?:mixed, activo?:mixed} $filtros id_familia = "sin" para los que no tienen familia
     * @return list<array<string, mixed>>
     */
    public function listar(array $filtros = []): array
    {
        $consulta = $this->productos->orderBy('nombre', 'ASC');

        $busqueda = trim((string) ($filtros['q'] ?? ''));
        if ($busqueda !== '') {
            $consulta->groupStart()
                ->like('nombre', $busqueda)
                ->orLike('codigo', $busqueda)
                ->orLike('descripcion', $busqueda)
                ->groupEnd();
        }

        $familia = $filtros['id_familia'] ?? null;
        if ($familia === 'sin') {
            $consulta->where('id_familia', null);
        } elseif ($familia !== null && $familia !== '' && (int) $familia > 0) {
            $consulta->where('id_familia', (int) $familia);
        }

        $activo = $filtros['activo'] ?? null;
        if ($activo !== null && $activo !== '') {
            $consulta->where('activo', filter_var($activo, FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
        }

        $productos = $consulta->findAll(self::LIMITE_LISTADO);
        $nombresFamilia = $this->nombresFamilia();

        return array_map(fn (ProductoEntity $p): array => $this->aArray($p, $nombresFamilia), $productos);
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        return $this->aArray($this->buscar($id), $this->nombresFamilia());
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function crear(array $datos): array
    {
        $fila = $this->normalizar($datos) + [
            'id_empresa' => $this->idEmpresa(),
            'activo' => array_key_exists('activo', $datos) ? ($datos['activo'] ? 1 : 0) : 1,
        ];
        $id = $this->productos->insert($fila);
        $this->verificar($id);

        return $this->obtener((int) $id);
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function actualizar(int $id, array $datos): array
    {
        $producto = $this->buscar($id);

        $cambios = $this->normalizar($datos + [
            'nombre' => $producto->nombre,
            'moneda' => $producto->moneda,
        ]);
        if (array_key_exists('activo', $datos)) {
            $cambios['activo'] = $datos['activo'] ? 1 : 0;
        }
        $cambios['id_empresa'] = $producto->id_empresa;

        $this->verificar($this->productos->update($id, $cambios) ? $id : false);

        return $this->obtener($id);
    }

    /** @return array<string, mixed> */
    public function cambiarEstado(int $id, bool $activo): array
    {
        return $this->actualizar($id, ['activo' => $activo]);
    }

    public function eliminar(int $id): void
    {
        $this->buscar($id);
        $this->productos->delete($id);
    }

    private function buscar(int $id): ProductoEntity
    {
        $producto = $this->productos->find($id);
        if ($producto === null) {
            throw new DomainException('No encontramos ese producto.');
        }

        return $producto;
    }

    /**
     * Deja los datos listos para guardar. Solo toma las claves recibidas.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        $fila = [];

        if (array_key_exists('nombre', $datos)) {
            $fila['nombre'] = trim((string) $datos['nombre']);
        }
        foreach (['descripcion', 'codigo', 'unidad', 'imagen_url'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $texto = trim((string) ($datos[$campo] ?? ''));
                $fila[$campo] = $texto !== '' ? $texto : null;
            }
        }
        if (array_key_exists('precio', $datos)) {
            $fila['precio'] = $this->precio($datos['precio']);
        }
        if (array_key_exists('moneda', $datos)) {
            $fila['moneda'] = strtoupper(trim((string) $datos['moneda'])) ?: 'PEN';
        }
        if (array_key_exists('id_familia', $datos)) {
            $fila['id_familia'] = $this->familia($datos['id_familia']);
        }
        if (array_key_exists('detalles', $datos)) {
            $fila['detalles'] = json_encode($this->detalles($datos['detalles']), JSON_UNESCAPED_UNICODE);
        }

        return $fila;
    }

    private function precio(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $texto = str_replace(',', '.', trim((string) $valor));
        if (! is_numeric($texto)) {
            throw new DomainException('El precio debe ser un número.');
        }

        return number_format((float) $texto, 2, '.', '');
    }

    private function familia(mixed $valor): ?int
    {
        if ($valor === null || $valor === '' || (int) $valor === 0) {
            return null;
        }
        if ($this->familias->find((int) $valor) === null) {
            throw new DomainException('Esa familia ya no existe.');
        }

        return (int) $valor;
    }

    /** @return list<array{nombre:string, valor:string}> */
    private function detalles(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $detalles = [];
        foreach ($valor as $item) {
            $nombre = mb_substr(trim((string) ($item['nombre'] ?? '')), 0, 60);
            $contenido = mb_substr(trim((string) ($item['valor'] ?? '')), 0, 255);
            if ($nombre !== '' && $contenido !== '') {
                $detalles[] = ['nombre' => $nombre, 'valor' => $contenido];
            }
        }

        return array_slice($detalles, 0, self::MAX_DETALLES);
    }

    /** @return array<int, string> */
    private function nombresFamilia(): array
    {
        $nombres = [];
        foreach ($this->familias->findAll() as $familia) {
            $nombres[$familia->id] = $familia->nombre;
        }

        return $nombres;
    }

    /** @param int|string|false $resultado */
    private function verificar($resultado): void
    {
        if ($resultado === false || $resultado === 0) {
            $errores = $this->productos->errors();
            throw new DomainException($errores !== [] ? (string) reset($errores) : 'No se pudo guardar el producto.');
        }
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

    /**
     * @param array<int, string> $nombresFamilia
     * @return array<string, mixed>
     */
    private function aArray(ProductoEntity $producto, array $nombresFamilia): array
    {
        return [
            'id' => $producto->id,
            'id_familia' => $producto->id_familia,
            'familia' => $producto->id_familia !== null ? ($nombresFamilia[$producto->id_familia] ?? null) : null,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'codigo' => $producto->codigo,
            'precio' => $producto->precio,
            'moneda' => $producto->moneda,
            'unidad' => $producto->unidad,
            'imagen_url' => $producto->imagen_url,
            'detalles' => is_array($producto->detalles) ? $producto->detalles : [],
            'activo' => $producto->activo,
        ];
    }
}
