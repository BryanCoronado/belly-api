<?php

namespace Belly\Flujos\Domain;

/**
 * Actualiza los pasos «catálogo» con el producto/familia actual de la base,
 * para que el bot no mande precios o fotos viejos.
 */
final class CatalogoVivo
{
    /**
     * @param array<string, mixed> $definicion
     * @return array<string, mixed>
     */
    public static function refrescar(array $definicion, int $idEmpresa): array
    {
        if ($idEmpresa <= 0 || ! is_array($definicion['nodos'] ?? null)) {
            return $definicion;
        }

        $nodos = [];
        foreach ($definicion['nodos'] as $nodo) {
            if (! is_array($nodo)) {
                continue;
            }
            if (($nodo['tipo'] ?? '') === TiposNodo::CATALOGO) {
                $nodo['datos'] = self::refrescarPaso((array) ($nodo['datos'] ?? []), $idEmpresa);
            }
            $nodos[] = $nodo;
        }
        $definicion['nodos'] = $nodos;

        return $definicion;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private static function refrescarPaso(array $datos, int $idEmpresa): array
    {
        $modo = (string) ($datos['modo'] ?? 'producto');
        $idProducto = (int) ($datos['id_producto'] ?? 0);
        $idFamilia = (int) ($datos['id_familia'] ?? 0);

        if ($modo === 'producto' && $idProducto > 0) {
            $producto = self::producto($idEmpresa, $idProducto);
            if ($producto === null) {
                return $datos + ['texto' => 'Ese producto ya no está disponible.', 'media' => 'ninguno', 'url' => ''];
            }
            $enlace = self::enlaceProducto($idEmpresa, $idProducto);
            $imagen = self::urlImagen($producto['imagen_url'] ?? null);

            return array_merge($datos, [
                'modo' => 'producto',
                'texto' => self::captionProducto($producto, $enlace),
                'media' => $imagen !== '' ? 'imagen' : 'ninguno',
                'url' => $imagen,
                'id_producto' => $idProducto,
                'id_familia' => (int) ($producto['id_familia'] ?? 0),
            ]);
        }

        if ($modo === 'familia' && $idFamilia > 0) {
            $familia = self::familia($idEmpresa, $idFamilia);
            if ($familia === null) {
                return $datos + ['texto' => 'Esa categoría ya no está disponible.', 'media' => 'ninguno', 'url' => ''];
            }
            $productos = self::productosDeFamilia($idEmpresa, $idFamilia);
            $enlace = self::enlaceTienda($idEmpresa);
            $imagen = self::urlImagen($familia['imagen_url'] ?? null)
                ?: self::urlImagen($productos[0]['imagen_url'] ?? null);

            return array_merge($datos, [
                'modo' => 'familia',
                'texto' => self::captionFamilia((string) $familia['nombre'], $productos, $enlace),
                'media' => $imagen !== '' ? 'imagen' : 'ninguno',
                'url' => $imagen,
                'id_familia' => $idFamilia,
                'id_producto' => 0,
            ]);
        }

        if ($modo === 'enlace') {
            $tienda = self::vitrina($idEmpresa);
            if ($tienda === null) {
                return $datos;
            }
            $enlace = self::enlaceTienda($idEmpresa);
            if ($enlace === '') {
                return $datos;
            }

            return array_merge($datos, [
                'modo' => 'enlace',
                'texto' => 'Mira nuestro catálogo *' . ($tienda['titulo'] ?: 'nuestra tienda') . "* 👇\n" . $enlace,
                'media' => 'ninguno',
                'url' => '',
                'id_producto' => 0,
                'id_familia' => 0,
            ]);
        }

        return $datos;
    }

    /** @return array<string, mixed>|null */
    private static function producto(int $idEmpresa, int $id): ?array
    {
        $fila = db_connect()->table('producto')
            ->where('id_empresa', $idEmpresa)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get(1)
            ->getRowArray();

        return is_array($fila) ? $fila : null;
    }

    /** @return array<string, mixed>|null */
    private static function familia(int $idEmpresa, int $id): ?array
    {
        $fila = db_connect()->table('familia_producto')
            ->where('id_empresa', $idEmpresa)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get(1)
            ->getRowArray();

        return is_array($fila) ? $fila : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function productosDeFamilia(int $idEmpresa, int $idFamilia): array
    {
        return db_connect()->table('producto')
            ->where('id_empresa', $idEmpresa)
            ->where('id_familia', $idFamilia)
            ->where('activo', 1)
            ->where('visible_catalogo', 1)
            ->where('deleted_at', null)
            ->orderBy('destacado', 'DESC')
            ->orderBy('orden', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->get(8)
            ->getResultArray();
    }

    /** @return array{titulo:string,slug:string}|null */
    private static function vitrina(int $idEmpresa): ?array
    {
        $fila = db_connect()->table('vitrina')
            ->select('titulo, slug, publicado')
            ->where('id_empresa', $idEmpresa)
            ->where('deleted_at', null)
            ->get(1)
            ->getRowArray();
        if (! is_array($fila) || (int) ($fila['publicado'] ?? 0) !== 1) {
            return null;
        }

        return ['titulo' => (string) ($fila['titulo'] ?? ''), 'slug' => (string) ($fila['slug'] ?? '')];
    }

    private static function enlaceTienda(int $idEmpresa): string
    {
        $tienda = self::vitrina($idEmpresa);
        if ($tienda === null || $tienda['slug'] === '') {
            return '';
        }
        $origen = rtrim((string) config(\Belly\Config\Plataforma::class)->urlTienda(), '/');
        if ($origen === '' && ENVIRONMENT === 'development') {
            $origen = 'http://localhost:5173';
        }
        if ($origen === '') {
            return '/c/' . $tienda['slug'];
        }

        return $origen . '/c/' . $tienda['slug'];
    }

    private static function enlaceProducto(int $idEmpresa, int $idProducto): string
    {
        $base = self::enlaceTienda($idEmpresa);

        return $base === '' ? '' : $base . '/p/' . $idProducto;
    }

    private static function urlImagen(mixed $valor): string
    {
        $url = trim((string) $valor);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        helper('vip2system');
        if (function_exists('media') && preg_match('#(?:^|/)uploads/(.+)$#', $url, $partes)) {
            return media()->construirUrlPublicaUploads($partes[1]);
        }
        $base = rtrim((string) (env('MEDIA_UPLOADS_BASE_URL') ?: base_url()), '/');

        return $base . '/' . ltrim($url, '/');
    }

    /** @param array<string, mixed> $producto */
    private static function captionProducto(array $producto, string $enlace): string
    {
        $nombre = trim((string) ($producto['nombre'] ?? ''));
        $tipo = (($producto['tipo'] ?? '') === 'servicio') ? 'servicio' : 'producto';
        $marca = trim((string) ($producto['marca'] ?? ''));
        $resumen = trim((string) ($producto['resumen'] ?? ''));
        if ($resumen === '') {
            $resumen = self::recortar((string) ($producto['descripcion'] ?? ''), 220);
        }
        $unidad = trim((string) ($producto['unidad'] ?? ''));
        $disponibilidad = (string) ($producto['disponibilidad'] ?? 'disponible');
        $precio = self::precioVisible($producto);
        $lineaPrecio = $precio . ($unidad !== '' ? ' / ' . $unidad : '');

        $lineas = [
            '*' . $nombre . '*',
            $tipo === 'servicio' ? '_Servicio_' : '',
            $marca !== '' ? 'Marca: ' . $marca : '',
            $lineaPrecio,
            $disponibilidad === 'agotado' ? '_Agotado por ahora_' : ($disponibilidad === 'pedido' ? '_Se pide a pedido_' : ''),
            $resumen,
            $enlace !== '' ? 'Ver más: ' . $enlace : '',
        ];

        return implode("\n", array_values(array_filter($lineas, static fn ($l) => trim((string) $l) !== '')));
    }

    /**
     * @param list<array<string, mixed>> $productos
     */
    private static function captionFamilia(string $nombre, array $productos, string $enlace): string
    {
        $filas = [];
        foreach (array_slice($productos, 0, 8) as $i => $producto) {
            $filas[] = '*' . ($i + 1) . '.* ' . ($producto['nombre'] ?? '') . ' — ' . self::precioVisible($producto);
        }

        $partes = ['*' . $nombre . '*', '', ...$filas];
        if ($enlace !== '') {
            $partes[] = "\nVer todo: " . $enlace;
        }

        return implode("\n", array_filter($partes, static fn ($l) => $l !== null));
    }

    /** @param array<string, mixed> $producto */
    private static function precioVisible(array $producto): string
    {
        $moneda = (string) ($producto['moneda'] ?? 'PEN');
        $oferta = $producto['precio_oferta'] ?? null;
        $normal = $producto['precio'] ?? null;
        if ($oferta !== null && $oferta !== '' && $normal !== null && $normal !== '' && (float) $oferta < (float) $normal) {
            return self::moneda((float) $oferta, $moneda) . '  ~' . self::moneda((float) $normal, $moneda) . '~';
        }
        if ($oferta !== null && $oferta !== '') {
            return self::moneda((float) $oferta, $moneda);
        }
        if ($normal !== null && $normal !== '') {
            return self::moneda((float) $normal, $moneda);
        }

        return 'A consultar';
    }

    private static function moneda(float $valor, string $moneda): string
    {
        return $moneda . ' ' . number_format($valor, 2, '.', '');
    }

    private static function recortar(string $texto, int $largo): string
    {
        $limpio = trim(preg_replace('/\s+/', ' ', $texto) ?? '');
        if ($limpio === '') {
            return '';
        }

        return mb_strlen($limpio) > $largo ? mb_substr($limpio, 0, $largo - 1) . '…' : $limpio;
    }
}
