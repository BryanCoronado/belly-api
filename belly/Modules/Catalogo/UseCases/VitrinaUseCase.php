<?php

namespace Belly\Catalogo\UseCases;

use Belly\Catalogo\Entities\VitrinaEntity;
use Belly\Catalogo\Models\VitrinaModel;
use DomainException;

/**
 * Catálogo virtual de la empresa: un enlace público, un QR y la vitrina de productos.
 */
class VitrinaUseCase
{
    private const RESERVADOS = [
        'acceso', 'admin', 'agente', 'api', 'app', 'asesores', 'automatizacion',
        'belly', 'c', 'catalogo', 'chats', 'clientes', 'cuenta', 'dashboard',
        'equipo', 'envios', 'inicio', 'integraciones', 'lista-negra', 'login',
        'operador', 'perfil', 'plantillas', 'productos', 'registro', 'supervision',
        'whatsapp', 'www',
    ];

    private VitrinaModel $vitrinas;

    public function __construct(?VitrinaModel $vitrinas = null)
    {
        $this->vitrinas = $vitrinas ?? new VitrinaModel();
    }

    /** @return array<string, mixed> */
    public function deEmpresa(): array
    {
        return $this->presentarAdmin($this->vitrinas->first());
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    public function guardar(array $datos): array
    {
        $actual = $this->vitrinas->first();
        $fila = $this->normalizar($datos, $actual);

        if ($actual instanceof VitrinaEntity) {
            $this->verificar($this->vitrinas->update($actual->id, $fila + [
                'id_empresa' => $actual->id_empresa,
            ]) ? $actual->id : false);

            return $this->deEmpresa();
        }

        $fila['id_empresa'] = $this->idEmpresa();
        $id = $this->vitrinas->insert($fila);
        $this->verificar($id);

        return $this->deEmpresa();
    }

    /** @return array{bytes:string, mime:string} */
    public function logoBinario(): array
    {
        $vitrina = $this->vitrinas->first();
        $url = $vitrina instanceof VitrinaEntity ? trim((string) ($vitrina->logo_url ?? '')) : '';
        if ($url === '') {
            throw new DomainException('Aún no hay logo.');
        }

        if (! preg_match('#/uploads/(.+)$#', $url, $partes)) {
            throw new DomainException('No encontramos el archivo del logo.');
        }

        $ruta = WRITEPATH . 'uploads/' . str_replace(['\\', '..'], ['/', ''], $partes[1]);
        if (! is_file($ruta)) {
            throw new DomainException('No encontramos el archivo del logo.');
        }

        $mime = mime_content_type($ruta) ?: 'image/png';

        return ['bytes' => (string) file_get_contents($ruta), 'mime' => $mime];
    }

    /** @return array<string, mixed> */
    public function publico(string $slug): array
    {
        $vitrina = $this->buscarPublica($slug);
        $idEmpresa = (int) $vitrina['id_empresa'];
        $familias = $this->familiasPublicas($idEmpresa);
        $productos = $this->productosPublicos($idEmpresa, $familias);

        $familiasConProductos = [];
        foreach ($familias as $id => $familia) {
            $total = 0;
            foreach ($productos as $producto) {
                if ((int) $producto['id_familia'] === $id) {
                    $total++;
                }
            }
            if ($total > 0) {
                $familiasConProductos[] = [
                    'id' => $id,
                    'nombre' => $familia['nombre'],
                    'imagen_url' => $familia['imagen_url'],
                    'total' => $total,
                ];
            }
        }

        return [
            'titulo' => (string) $vitrina['titulo'],
            'eslogan' => $vitrina['eslogan'] !== null && $vitrina['eslogan'] !== '' ? (string) $vitrina['eslogan'] : null,
            'color' => $this->color($vitrina['color'] ?? null),
            'logo_url' => $vitrina['logo_url'] !== null && $vitrina['logo_url'] !== '' ? (string) $vitrina['logo_url'] : null,
            'whatsapp' => $this->digitosSuaves($vitrina['whatsapp'] ?? null),
            'mostrar_precios' => (int) ($vitrina['mostrar_precios'] ?? 1) === 1,
            'pagina' => $this->paginaDe($vitrina['pagina'] ?? null),
            'familias' => $familiasConProductos,
            'productos' => $productos,
        ];
    }

    /**
     * Título, texto y foto para la pestaña, Google y al pegar el enlace en WhatsApp.
     *
     * @return array{titulo:string,descripcion:string,imagen_url:?string,logo_url:?string,color:string,slug:string,url:string}
     */
    public function tarjeta(string $slug, ?int $idProducto = null): array
    {
        $vitrina = $this->buscarPublica($slug);
        $pagina = $this->paginaDe($vitrina['pagina'] ?? null);
        $seo = is_array($pagina['seo'] ?? null) ? $pagina['seo'] : [];
        $titulo = (string) $vitrina['titulo'];
        $descripcion = $this->corto($seo['descripcion'] ?? null, 180)
            ?: $this->corto($vitrina['eslogan'] ?? null, 180)
            ?: $this->corto($pagina['sobre'] ?? null, 180)
            ?: $this->corto($pagina['aviso'] ?? null, 180)
            ?: ('Catálogo de ' . $titulo);
        $logo = $this->urlPublica($vitrina['logo_url'] ?? null);
        $imagen = $this->urlPublica($seo['imagen_url'] ?? null)
            ?: $logo
            ?: $this->urlPublica($pagina['banners'][0]['imagen_url'] ?? null);

        if ($idProducto !== null && $idProducto > 0) {
            $producto = $this->productoPublicoDe((int) $vitrina['id_empresa'], $idProducto);
            if ($producto !== null) {
                $titulo = $producto['nombre'] . ' · ' . $titulo;
                $descripcion = $producto['resumen'] ?: $producto['descripcion'] ?: $descripcion;
                $imagen = $this->urlPublica($producto['imagen_url']) ?: $imagen;
            }
        }

        $limpio = (string) $vitrina['slug'];
        $ruta = '/c/' . $limpio . ($idProducto ? '/p/' . $idProducto : '');

        return [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'imagen_url' => $imagen,
            'logo_url' => $logo,
            'color' => $this->color($vitrina['color'] ?? null),
            'slug' => $limpio,
            'url' => $this->urlTienda($ruta),
        ];
    }

    /** @return array<string, mixed> */
    private function buscarPublica(string $slug): array
    {
        $limpio = $this->slug($slug);
        $fila = db_connect()->table('vitrina')
            ->where('slug', $limpio)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($fila === null) {
            throw new DomainException('No encontramos ese catálogo.');
        }
        if ((int) $fila['publicado'] !== 1) {
            throw new DomainException('Este catálogo aún no está publicado.');
        }

        return $fila;
    }

    /**
     * @return array<int, array{nombre:string, imagen_url:?string}>
     */
    private function familiasPublicas(int $idEmpresa): array
    {
        $filas = db_connect()->table('familia_producto')
            ->select('id, nombre, imagen_url')
            ->where('id_empresa', $idEmpresa)
            ->where('activo', 1)
            ->where('deleted_at', null)
            ->orderBy('orden', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->get()
            ->getResultArray();

        $familias = [];
        foreach ($filas as $fila) {
            $familias[(int) $fila['id']] = [
                'nombre' => (string) $fila['nombre'],
                'imagen_url' => $this->textoOpcional($fila['imagen_url'] ?? null),
            ];
        }

        return $familias;
    }

    /**
     * @param array<int, array{nombre:string, imagen_url:?string}> $familias
     * @return list<array<string, mixed>>
     */
    private function productosPublicos(int $idEmpresa, array $familias): array
    {
        $filas = db_connect()->table('producto')
            ->where('id_empresa', $idEmpresa)
            ->where('activo', 1)
            ->where('visible_catalogo', 1)
            ->where('deleted_at', null)
            ->orderBy('destacado', 'DESC')
            ->orderBy('orden', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->get()
            ->getResultArray();

        $salida = [];
        foreach ($filas as $fila) {
            $idFamilia = $fila['id_familia'] !== null ? (int) $fila['id_familia'] : null;
            $detalles = json_decode((string) ($fila['detalles'] ?? '[]'), true);
            $salida[] = [
                'id' => (int) $fila['id'],
                'id_familia' => $idFamilia,
                'familia' => $idFamilia !== null ? ($familias[$idFamilia]['nombre'] ?? null) : null,
                'nombre' => (string) $fila['nombre'],
                'resumen' => $this->textoOpcional($fila['resumen'] ?? null),
                'descripcion' => $this->textoOpcional($fila['descripcion'] ?? null),
                'marca' => $this->textoOpcional($fila['marca'] ?? null),
                'precio' => $fila['precio'] !== null && $fila['precio'] !== '' ? (float) $fila['precio'] : null,
                'precio_oferta' => $fila['precio_oferta'] !== null && $fila['precio_oferta'] !== '' ? (float) $fila['precio_oferta'] : null,
                'moneda' => (string) ($fila['moneda'] ?: 'PEN'),
                'unidad' => $this->textoOpcional($fila['unidad'] ?? null),
                'imagen_url' => $this->textoOpcional($fila['imagen_url'] ?? null),
                'imagenes' => ProductoUseCase::imagenes($fila['imagenes'] ?? null),
                'detalles' => is_array($detalles) ? $detalles : [],
                'variantes' => ProductoUseCase::variantes($fila['variantes'] ?? null),
                'etiqueta' => $this->textoOpcional($fila['etiqueta'] ?? null),
                'disponibilidad' => $this->disponibilidad($fila['disponibilidad'] ?? null),
                'destacado' => (int) ($fila['destacado'] ?? 0) === 1,
            ];
        }

        return $salida;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos, ?VitrinaEntity $actual): array
    {
        $titulo = trim((string) ($datos['titulo'] ?? $actual?->titulo ?? ''));
        if ($titulo === '') {
            throw new DomainException('Ponle un título al catálogo.');
        }

        $pedido = trim((string) ($datos['slug'] ?? ''));
        $slug = $this->slug($pedido !== '' ? $pedido : (string) ($actual?->slug ?? $titulo));
        $this->slugLibre($slug, $actual?->id);

        $eslogan = trim((string) ($datos['eslogan'] ?? $actual?->eslogan ?? ''));
        $logo = trim((string) ($datos['logo_url'] ?? $actual?->logo_url ?? ''));
        $whatsapp = $this->soloDigitos($datos['whatsapp'] ?? $actual?->whatsapp ?? null);

        return [
            'slug' => $slug,
            'titulo' => mb_substr($titulo, 0, 80),
            'eslogan' => $eslogan !== '' ? mb_substr($eslogan, 0, 160) : null,
            'color' => $this->color($datos['color'] ?? $actual?->color ?? null),
            'logo_url' => $logo !== '' ? $logo : null,
            'whatsapp' => $whatsapp,
            'mostrar_precios' => array_key_exists('mostrar_precios', $datos)
                ? ($datos['mostrar_precios'] ? 1 : 0)
                : (int) ($actual?->mostrar_precios ?? true),
            'publicado' => array_key_exists('publicado', $datos)
                ? ($datos['publicado'] ? 1 : 0)
                : (int) ($actual?->publicado ?? false),
            'pagina' => json_encode($this->paginaDe($datos['pagina'] ?? $actual?->pagina ?? null), JSON_UNESCAPED_UNICODE),
        ];
    }

    private function slugLibre(string $slug, ?int $excepto): void
    {
        $consulta = db_connect()->table('vitrina')
            ->where('slug', $slug)
            ->where('deleted_at', null);
        if ($excepto !== null) {
            $consulta->where('id !=', $excepto);
        }
        if ($consulta->countAllResults() > 0) {
            throw new DomainException('Ese nombre de enlace ya lo usa otro catálogo.');
        }
    }

    private function slug(string $valor): string
    {
        $texto = mb_strtolower(trim($valor));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
        ]);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
        $texto = trim($texto, '-');
        if (strlen($texto) < 3) {
            throw new DomainException('El nombre del enlace debe tener al menos 3 letras o números.');
        }
        if (strlen($texto) > 60) {
            $texto = rtrim(substr($texto, 0, 60), '-');
        }
        if (in_array($texto, self::RESERVADOS, true)) {
            throw new DomainException('Ese nombre de enlace no se puede usar.');
        }

        return $texto;
    }

    private function color(mixed $valor): string
    {
        $color = strtoupper(trim((string) $valor));
        if (preg_match('/^#[0-9A-F]{6}$/', $color) === 1) {
            return $color;
        }

        return '#0B1026';
    }

    private function soloDigitos(mixed $valor): ?string
    {
        $digitos = $this->digitosSuaves($valor);
        if ($digitos === null) {
            return null;
        }
        if (strlen($digitos) < 8 || strlen($digitos) > 15) {
            throw new DomainException('El WhatsApp del catálogo debe tener entre 8 y 15 dígitos, con código de país.');
        }

        return $digitos;
    }

    private function digitosSuaves(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) ($valor ?? '')) ?? '';

        return $digitos !== '' ? $digitos : null;
    }

    private function disponibilidad(mixed $valor): string
    {
        $opcion = (string) $valor;

        return in_array($opcion, ['disponible', 'agotado', 'pedido'], true) ? $opcion : 'disponible';
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto !== '' ? $texto : null;
    }

    /** @return array<string, mixed> */
    private function presentarAdmin(?VitrinaEntity $vitrina): array
    {
        $empresa = db_connect()->table('empresa')
            ->select('nombre')
            ->where('id', $this->idEmpresa())
            ->get()
            ->getRowArray();
        $nombreEmpresa = (string) ($empresa['nombre'] ?? '');
        $whatsappSugerido = $this->whatsappEmpresa();

        if (! $vitrina instanceof VitrinaEntity) {
            return [
                'existe' => false,
                'slug' => '',
                'titulo' => $nombreEmpresa,
                'eslogan' => '',
                'color' => '#0B1026',
                'logo_url' => null,
                'whatsapp' => $whatsappSugerido,
                'mostrar_precios' => true,
                'publicado' => false,
                'pagina' => $this->paginaDe(null),
                'enlace' => null,
            ];
        }

        return [
            'existe' => true,
            'slug' => (string) $vitrina->slug,
            'titulo' => (string) $vitrina->titulo,
            'eslogan' => (string) ($vitrina->eslogan ?? ''),
            'color' => $this->color($vitrina->color),
            'logo_url' => $vitrina->logo_url ? (string) $vitrina->logo_url : null,
            'whatsapp' => $vitrina->whatsapp ? (string) $vitrina->whatsapp : $whatsappSugerido,
            'mostrar_precios' => (bool) $vitrina->mostrar_precios,
            'publicado' => (bool) $vitrina->publicado,
            'pagina' => $this->paginaDe($vitrina->pagina),
            'enlace' => '/c/' . $vitrina->slug,
        ];
    }

    /** @return array<string, mixed> */
    private function paginaDe(mixed $valor): array
    {
        $crudo = $valor;
        if (is_string($valor) && $valor !== '') {
            $crudo = json_decode($valor, true);
        }
        if (! is_array($crudo)) {
            $crudo = [];
        }

        $banners = [];
        foreach (is_array($crudo['banners'] ?? null) ? $crudo['banners'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $imagen = $this->corto($item['imagen_url'] ?? null, 500);
            if ($imagen === '') {
                continue;
            }
            $banners[] = [
                'imagen_url' => $imagen,
                'estilo' => ($item['estilo'] ?? '') === 'completo' ? 'completo' : 'dividido',
                'etiqueta' => $this->corto($item['etiqueta'] ?? null, 40),
                'titulo' => $this->corto($item['titulo'] ?? null, 80),
                'texto' => $this->corto($item['texto'] ?? null, 160),
                'boton' => $this->corto($item['boton'] ?? null, 30),
                'id_familia' => $this->idOpcional($item['id_familia'] ?? null),
                'fondo' => $this->colorSuave($item['fondo'] ?? null, '#EEF0E5'),
                'burbuja' => $this->corto($item['burbuja'] ?? null, 20),
            ];
            if (count($banners) >= 6) {
                break;
            }
        }

        $promos = [];
        foreach (is_array($crudo['promos'] ?? null) ? $crudo['promos'] : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $titulo = $this->corto($item['titulo'] ?? null, 50);
            if ($titulo === '') {
                continue;
            }
            $promos[] = [
                'titulo' => $titulo,
                'texto' => $this->corto($item['texto'] ?? null, 80),
                'imagen_url' => $this->corto($item['imagen_url'] ?? null, 500),
                'fondo' => $this->colorSuave($item['fondo'] ?? null, '#FDECEA'),
                'id_familia' => $this->idOpcional($item['id_familia'] ?? null),
            ];
            if (count($promos) >= 3) {
                break;
            }
        }

        $oferta = is_array($crudo['oferta'] ?? null) ? $crudo['oferta'] : [];
        $termina = $this->corto($oferta['termina'] ?? null, 16);
        if ($termina !== '' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $termina) !== 1) {
            $termina = '';
        }

        $secciones = is_array($crudo['secciones'] ?? null) ? $crudo['secciones'] : [];

        return [
            'banners' => $banners,
            'promos' => $promos,
            'oferta' => [
                'activa' => (bool) ($oferta['activa'] ?? false),
                'titulo' => $this->corto($oferta['titulo'] ?? null, 60) ?: 'Ofertas de la semana',
                'termina' => $termina,
            ],
            'secciones' => [
                'categorias' => (bool) ($secciones['categorias'] ?? true),
                'ofertas' => (bool) ($secciones['ofertas'] ?? true),
                'destacados' => (bool) ($secciones['destacados'] ?? true),
                'nosotros' => (bool) ($secciones['nosotros'] ?? true),
            ],
            'aviso' => $this->corto($crudo['aviso'] ?? null, 180),
            'sobre' => $this->corto($crudo['sobre'] ?? null, 600),
            'horario' => $this->corto($crudo['horario'] ?? null, 160),
            'direccion' => $this->corto($crudo['direccion'] ?? null, 180),
            'entrega' => $this->corto($crudo['entrega'] ?? null, 180),
            'devoluciones' => $this->corto($crudo['devoluciones'] ?? null, 300),
            'instagram' => ltrim($this->corto($crudo['instagram'] ?? null, 80), '@'),
            'facebook' => $this->corto($crudo['facebook'] ?? null, 120),
            'tiktok' => ltrim($this->corto($crudo['tiktok'] ?? null, 80), '@'),
            'carrito' => array_key_exists('carrito', $crudo) ? (bool) $crudo['carrito'] : true,
            'bloques' => $this->bloquesDe($crudo['bloques'] ?? null),
            'seo' => $this->seoDe($crudo['seo'] ?? null),
        ];
    }

    /** @return array{descripcion:string,imagen_url:string} */
    private function seoDe(mixed $valor): array
    {
        $seo = is_array($valor) ? $valor : [];

        return [
            'descripcion' => $this->corto($seo['descripcion'] ?? null, 180),
            'imagen_url' => $this->corto($seo['imagen_url'] ?? null, 500),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function bloquesDe(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $lugares = [
            'cabecera', 'despues_carrusel', 'despues_promos', 'despues_categorias',
            'despues_ofertas', 'despues_destacados', 'despues_productos',
            'despues_beneficios', 'despues_nosotros', 'antes_footer', 'pie', 'ficha',
        ];
        $tipos = ['texto', 'imagen', 'boton', 'video', 'franja', 'columnas', 'espacio', 'html'];
        $bloques = [];

        foreach ($valor as $item) {
            if (! is_array($item)) {
                continue;
            }
            $html = $this->htmlLimpio($item['html'] ?? '', 30000);
            $css = $this->cssLimpio($item['css'] ?? '', 15000);
            $tipo = (string) ($item['tipo'] ?? ($html !== '' ? 'html' : 'texto'));
            if (! in_array($tipo, $tipos, true)) {
                $tipo = 'html';
            }
            if ($tipo === 'html' && $html === '' && $css === '') {
                continue;
            }
            $donde = (string) ($item['donde'] ?? 'despues_carrusel');
            if (! in_array($donde, $lugares, true)) {
                $donde = 'despues_carrusel';
            }
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['id'] ?? '')) ?? '';
            $bloques[] = [
                'id' => $id !== '' ? $id : ('b' . (count($bloques) + 1)),
                'nombre' => $this->corto($item['nombre'] ?? null, 40) ?: 'Bloque',
                'tipo' => $tipo,
                'html' => $html,
                'css' => $css,
                'donde' => $donde,
                'ancho' => ($item['ancho'] ?? '') === 'completo' ? 'completo' : 'caja',
                'activo' => array_key_exists('activo', $item) ? (bool) $item['activo'] : true,
                'datos' => $this->datosBloque($item['datos'] ?? null),
            ];
            if (count($bloques) >= 16) {
                break;
            }
        }

        return $bloques;
    }

    /** @return array<string, mixed> */
    private function datosBloque(mixed $valor): array
    {
        $datos = is_array($valor) ? $valor : [];
        $columnas = [];
        foreach (is_array($datos['columnas'] ?? null) ? $datos['columnas'] : [] as $columna) {
            if (! is_array($columna)) {
                continue;
            }
            $columnas[] = [
                'titulo' => $this->corto($columna['titulo'] ?? null, 40),
                'texto' => $this->corto($columna['texto'] ?? null, 160),
            ];
            if (count($columnas) >= 4) {
                break;
            }
        }

        return [
            'titulo' => $this->corto($datos['titulo'] ?? null, 80),
            'texto' => $this->corto($datos['texto'] ?? null, 600),
            'imagen_url' => $this->corto($datos['imagen_url'] ?? null, 500),
            'boton' => $this->corto($datos['boton'] ?? null, 40),
            'enlace' => $this->corto($datos['enlace'] ?? null, 300),
            'color' => $this->colorSuave($datos['color'] ?? null, '#0B1026'),
            'fondo' => $this->colorSuave($datos['fondo'] ?? null, '#FFFFFF'),
            'video' => $this->corto($datos['video'] ?? null, 300),
            'alto' => max(8, min(240, (int) ($datos['alto'] ?? 40))),
            'columnas' => $columnas,
        ];
    }

    private function htmlLimpio(mixed $valor, int $largo): string
    {
        $html = mb_substr((string) ($valor ?? ''), 0, $largo);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<\/?(?:html|head|body|link|meta|base|object|embed)\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;

        return $html;
    }

    private function cssLimpio(mixed $valor, int $largo): string
    {
        $css = mb_substr((string) ($valor ?? ''), 0, $largo);
        $css = preg_replace('/@import[^;{}]*;?/i', '', $css) ?? $css;
        $css = preg_replace('/expression\s*\(/i', '', $css) ?? $css;
        $css = preg_replace('/javascript\s*:/i', '', $css) ?? $css;
        $css = preg_replace('/-moz-binding/i', '', $css) ?? $css;
        $css = preg_replace('/behavior\s*:/i', '', $css) ?? $css;

        return $css;
    }

    private function corto(mixed $valor, int $largo): string
    {
        return mb_substr(trim((string) ($valor ?? '')), 0, $largo);
    }

    private function idOpcional(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    private function colorSuave(mixed $valor, string $porDefecto): string
    {
        $color = strtoupper(trim((string) $valor));

        return preg_match('/^#[0-9A-F]{6}$/', $color) === 1 ? $color : $porDefecto;
    }

    private function whatsappEmpresa(): ?string
    {
        $fila = db_connect()->table('app')
            ->select('telefono')
            ->where('id_empresa', $this->idEmpresa())
            ->where('canal', 'whatsapp')
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();
        $digitos = preg_replace('/\D+/', '', (string) ($fila['telefono'] ?? '')) ?? '';

        return $digitos !== '' ? $digitos : null;
    }

    /** @param int|string|false $resultado */
    private function verificar($resultado): void
    {
        if ($resultado === false || $resultado === 0) {
            $errores = $this->vitrinas->errors();
            throw new DomainException($errores !== [] ? (string) reset($errores) : 'No se pudo guardar el catálogo.');
        }
    }

    /**
     * @return array{nombre:string,resumen:string,descripcion:string,imagen_url:?string}|null
     */
    private function productoPublicoDe(int $idEmpresa, int $id): ?array
    {
        $fila = db_connect()->table('producto')
            ->select('nombre, resumen, descripcion, imagen_url')
            ->where('id_empresa', $idEmpresa)
            ->where('id', $id)
            ->where('activo', 1)
            ->where('visible_catalogo', 1)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($fila === null) {
            return null;
        }

        return [
            'nombre' => (string) $fila['nombre'],
            'resumen' => $this->corto($fila['resumen'] ?? null, 180),
            'descripcion' => $this->corto($fila['descripcion'] ?? null, 180),
            'imagen_url' => $this->textoOpcional($fila['imagen_url'] ?? null),
        ];
    }

    private function urlPublica(mixed $valor): ?string
    {
        $url = trim((string) $valor);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        $base = rtrim((string) (env('MEDIA_UPLOADS_BASE_URL') ?: base_url()), '/');

        return $base . '/' . ltrim($url, '/');
    }

    private function urlTienda(string $ruta): string
    {
        $origen = rtrim(config(\Belly\Config\Plataforma::class)->urlTienda(), '/');
        if ($origen === '' && ENVIRONMENT === 'development') {
            $origen = 'http://localhost:5173';
        }
        if ($origen === '') {
            return $ruta;
        }

        return $origen . $ruta;
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
}
