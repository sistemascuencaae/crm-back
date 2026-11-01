<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\ProductoTiendaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Productos de la tienda (CRM): la ficha técnica de cada producto, sus imágenes por sección y la plantilla
 * de características de cada categoría. Los datos del ERP (código, nombre, categoría, marca…) no se cambian aquí.
 * La imagen se sube antes con POST pagina-web/productos-tienda-imagenes-archivo, que devuelve su URL.
 * Validación ligera aquí; las reglas están en la base. Los errores de un campo llegan como data {campo: mensaje}
 * (en la ficha: valores.<car_id> y sueltas.<n>.nombre / sueltas.<n>.valor).
 */
class ProductoTiendaController extends Controller
{
    // Lo único que se acepta de cada petición
    private const CAMPOS_CARACTERISTICA_CREAR     = ['tpr_id', 'car_nombre', 'car_tipo', 'car_unidad'];
    private const CAMPOS_CARACTERISTICA_MODIFICAR = ['car_nombre', 'car_tipo', 'car_unidad'];   // la categoría no se cambia
    private const CAMPOS_FICHA                    = ['valores', 'sueltas'];
    private const CAMPOS_IMAGEN_CREAR             = ['pro_id', 'pim_seccion', 'pim_imagen', 'pim_texto', 'pim_visible', 'pim_desde', 'pim_hasta'];
    private const CAMPOS_IMAGEN_MODIFICAR         = ['pim_imagen', 'pim_texto', 'pim_visible', 'pim_desde', 'pim_hasta'];
    private const CAMPOS_PRODUCTO                 = ['pro_nombre_web', 'pro_observacion', 'pro_oferta_semana', 'pro_destacado'];

    public function __construct(private ProductoTiendaService $servicio)
    {
    }

    // -----------------------------------------------------------------
    // Productos
    // -----------------------------------------------------------------

    // GET pagina-web/productos-tienda-arbol?solo_publicados=1
    public function arbol(Request $request)
    {
        try {
            $arbol = $this->servicio->arbol($request->boolean('solo_publicados'));

            return response()->json(RespuestaApi::returnResultado('success', 'Categorías', $arbol));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron cargar las categorías');
        }
    }

    // GET pagina-web/productos-tienda?pagina=1&tamanio=20&busqueda=&tpr_id=416&solo_publicados=0&incompletos=0&oferta_semana=0&destacado=0
    //   → {registros, total, pagina, tamanio, publicados_actualizado}
    //   incompletos=1: solo a los que les falta contenido; oferta_semana=1 / destacado=1: solo los marcados así
    public function listar(Request $request)
    {
        $tprId = $request->input('tpr_id');

        try {
            $productos = $this->servicio->listar(
                (int) $request->input('pagina', 1),
                (int) $request->input('tamanio', 20),
                (string) $request->input('busqueda', ''),
                ($tprId === null || $tprId === '') ? null : (int) $tprId,
                $request->boolean('solo_publicados'),
                $request->boolean('incompletos'),
                $request->boolean('oferta_semana'),
                $request->boolean('destacado')
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Productos', $productos));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar los productos');
        }
    }

    // GET pagina-web/productos-tienda/{proId}   → {producto, ficha, imagenes, papelera_imagenes}
    public function buscar(int $proId)
    {
        try {
            $producto = $this->servicio->buscar($proId);

            if ($producto === null) {
                return response()->json(RespuestaApi::returnResultado('error', ProductoTiendaService::MENSAJES_ERROR['PRODUCTO_NO_EXISTE'], null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Producto', $producto));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar el producto');
        }
    }

    // PUT pagina-web/productos-tienda/{proId}/ficha   {valores: {12: "150", 13: "Rojo"}, sueltas: [{nombre, valor}]}
    // Un valor vacío borra ese dato; si llegan sueltas, reemplazan a las que había.
    public function guardarFicha(Request $request, int $proId)
    {
        try {
            $this->servicio->guardarFicha($proId, $request->only(self::CAMPOS_FICHA) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Ficha guardada con éxito', ['pro_id' => $proId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la ficha');
        }
    }

    // PUT pagina-web/productos-tienda/{proId}   {pro_nombre_web, pro_observacion, pro_oferta_semana, pro_destacado} (solo lo que cambia)
    // pro_nombre_web vacío = la tienda vuelve a usar el nombre del ERP; pro_observacion (la descripción) vacía = sin descripción
    public function modificar(Request $request, int $proId)
    {
        try {
            $this->servicio->modificar($proId, $request->only(self::CAMPOS_PRODUCTO) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Producto guardado con éxito', ['pro_id' => $proId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el producto');
        }
    }

    // -----------------------------------------------------------------
    // Imágenes
    // -----------------------------------------------------------------

    // POST pagina-web/productos-tienda-imagenes-archivo   multipart: archivo (solo WEBP, máx. 2 MB) + pro_id   → {url}
    // Solo sube el archivo; la URL se guarda después con crear o modificar.
    public function subirImagen(Request $request)
    {
        // mimes y mimetypes revisan el contenido real del archivo, no solo su nombre
        $validator = Validator::make($request->all(), [
            'pro_id'  => 'required|integer|min:1',
            'archivo' => 'required|file|mimes:webp|mimetypes:image/webp|max:2048',
        ], [
            'pro_id.*'          => 'Falta el producto.',
            'archivo.required'  => 'Elige una imagen.',
            'archivo.file'      => 'Elige una imagen.',
            'archivo.mimes'     => 'La imagen debe ser WEBP.',
            'archivo.mimetypes' => 'La imagen debe ser WEBP.',
            'archivo.max'       => 'La imagen no puede pesar más de 2 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        return app(ArchivoPaginaWebController::class)->guardar(
            $request->file('archivo'),
            'api/public/pagina-web/imagenes/',
            'Imagen subida',
            'productos/' . (int) $request->input('pro_id')
        );
    }

    // POST pagina-web/productos-tienda-imagenes
    //   {pro_id, pim_seccion (detalle | caratula | promocion), pim_imagen, pim_texto, pim_visible, pim_desde, pim_hasta}
    public function crearImagen(Request $request)
    {
        try {
            $pimId = $this->servicio->crearImagen($request->only(self::CAMPOS_IMAGEN_CREAR) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen agregada con éxito', ['pim_id' => $pimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al agregar la imagen');
        }
    }

    // PUT pagina-web/productos-tienda-imagenes/{pimId}   (solo lo que cambia; el producto y la sección no se cambian)
    public function modificarImagen(Request $request, int $pimId)
    {
        try {
            $this->servicio->modificarImagen($pimId, $request->only(self::CAMPOS_IMAGEN_MODIFICAR) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen guardada con éxito', ['pim_id' => $pimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la imagen');
        }
    }

    // DELETE pagina-web/productos-tienda-imagenes/{pimId}   (a la papelera)
    public function eliminarImagen(int $pimId)
    {
        try {
            $this->servicio->eliminarImagen($pimId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen enviada a la papelera', ['pim_id' => $pimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la imagen');
        }
    }

    // PUT pagina-web/productos-tienda-imagenes/{pimId}/restaurar
    public function restaurarImagen(int $pimId)
    {
        try {
            $this->servicio->restaurarImagen($pimId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen restaurada con éxito', ['pim_id' => $pimId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la imagen');
        }
    }

    // DELETE pagina-web/productos-tienda-imagenes-papelera?pro_id=8004906   → {cantidad} (borra también los archivos)
    public function vaciarImagenes(Request $request)
    {
        try {
            $cantidad = $this->servicio->vaciarImagenes((int) $request->input('pro_id'), auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $cantidad]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    // PUT pagina-web/productos-tienda-imagenes-orden   {pro_id, pim_seccion, ids: [7, 5, 6]} (todas las de la sección, en el nuevo orden)
    public function ordenarImagenes(Request $request)
    {
        try {
            $this->servicio->ordenarImagenes($request->only(['pro_id', 'pim_seccion', 'ids']) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------
    // Características (plantilla de la ficha por categoría)
    // -----------------------------------------------------------------

    // GET pagina-web/caracteristicas?tpr_id=416   → {plantilla, papelera}
    public function caracteristicas(Request $request)
    {
        try {
            $caracteristicas = $this->servicio->caracteristicas((int) $request->input('tpr_id'));

            return response()->json(RespuestaApi::returnResultado('success', 'Características', $caracteristicas));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron cargar las características');
        }
    }

    // POST pagina-web/caracteristicas   {tpr_id, car_nombre, car_tipo (texto | numero), car_unidad}
    public function crearCaracteristica(Request $request)
    {
        try {
            $carId = $this->servicio->crearCaracteristica($request->only(self::CAMPOS_CARACTERISTICA_CREAR) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Característica creada con éxito', ['car_id' => $carId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear la característica');
        }
    }

    // PUT pagina-web/caracteristicas/{carId}   (solo lo que cambia)
    public function modificarCaracteristica(Request $request, int $carId)
    {
        try {
            $this->servicio->modificarCaracteristica($carId, $request->only(self::CAMPOS_CARACTERISTICA_MODIFICAR) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Característica guardada con éxito', ['car_id' => $carId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la característica');
        }
    }

    // DELETE pagina-web/caracteristicas/{carId}   (a la papelera)
    public function eliminarCaracteristica(int $carId)
    {
        try {
            $this->servicio->eliminarCaracteristica($carId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Característica enviada a la papelera', ['car_id' => $carId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la característica');
        }
    }

    // PUT pagina-web/caracteristicas/{carId}/restaurar
    public function restaurarCaracteristica(int $carId)
    {
        try {
            $this->servicio->restaurarCaracteristica($carId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Característica restaurada con éxito', ['car_id' => $carId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la característica');
        }
    }

    // DELETE pagina-web/caracteristicas-papelera?tpr_id=416   → {cantidad} (borra también los valores de los productos)
    public function vaciarCaracteristicas(Request $request)
    {
        try {
            $cantidad = $this->servicio->vaciarCaracteristicas((int) $request->input('tpr_id'), auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $cantidad]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    // PUT pagina-web/caracteristicas-orden   {tpr_id, ids: [3, 1, 2]} (todas las de la categoría, en el nuevo orden)
    public function ordenarCaracteristicas(Request $request)
    {
        try {
            $this->servicio->ordenarCaracteristicas($request->only(['tpr_id', 'ids']) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, ProductoTiendaService::MENSAJES_ERROR);
    }
}
