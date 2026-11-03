<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\FamiliaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Familias de productos (CRM): agrupar SKU que son el mismo producto con distintas variantes, con papelera.
 * Las listas van paginadas (busqueda, pagina, tamanio: 20 por defecto, máximo 100; con el total).
 * Validación ligera aquí; las reglas están en la base. Los errores de un campo llegan como data {campo: mensaje}.
 */
class FamiliaController extends Controller
{
    // Lo único que se acepta
    private const CAMPOS_CREAR     = ['pro_id', 'fam_nombre', 'car_id_1', 'car_id_2'];
    private const CAMPOS_MODIFICAR = ['fam_nombre', 'car_id_1', 'car_id_2', 'pro_id_principal'];

    public function __construct(private FamiliaService $servicio)
    {
    }

    // GET pagina-web/productos-tienda-familias?busqueda=&pagina=1&tamanio=20   → {registros, total}
    public function listar(Request $request)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Familias', $this->servicio->listar(...$this->paginacion($request))));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las familias');
        }
    }

    // GET pagina-web/productos-tienda-familias/{famId}   → {familia, productos} (cada SKU con su precio si está publicado)
    public function buscar(int $famId)
    {
        try {
            $familia = $this->servicio->buscar($famId);

            if ($familia === null) {
                return response()->json(['code' => 404] + RespuestaApi::returnResultado('error', ErroresPaginaWeb::MENSAJES['FAMILIA_NO_EXISTE'], null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Familia', $familia));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la familia');
        }
    }

    // POST pagina-web/productos-tienda-familias   {pro_id, fam_nombre, car_id_1, car_id_2 (opcional)}  → {fam_id}
    public function crear(Request $request)
    {
        try {
            $famId = $this->servicio->crear($request->only(self::CAMPOS_CREAR) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Familia creada con éxito', ['fam_id' => $famId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear la familia');
        }
    }

    // PUT pagina-web/productos-tienda-familias/{famId}   (solo lo que cambia: fam_nombre, car_id_1, car_id_2 (null la quita), pro_id_principal)
    public function modificar(Request $request, int $famId)
    {
        try {
            $this->servicio->modificar($famId, $request->only(self::CAMPOS_MODIFICAR) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Familia guardada con éxito', ['fam_id' => $famId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la familia');
        }
    }

    // POST pagina-web/productos-tienda-familias/{famId}/productos   {pro_id}
    public function agregarProducto(Request $request, int $famId)
    {
        try {
            $this->servicio->agregarProducto($famId, (int) $request->input('pro_id'), auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Producto agregado a la familia', ['fam_id' => $famId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al agregar el producto');
        }
    }

    // DELETE pagina-web/productos-tienda-familias/{famId}/productos/{proId}   (si era el principal, pasa al siguiente; el último no se quita)
    public function quitarProducto(int $famId, int $proId)
    {
        try {
            $this->servicio->quitarProducto($famId, $proId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Producto quitado de la familia', ['fam_id' => $famId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al quitar el producto');
        }
    }

    // GET pagina-web/productos-tienda-familias/{famId}/candidatos?busqueda=&pagina=1&tamanio=20   → {registros, total} (con precio)
    public function candidatos(Request $request, int $famId)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Productos', $this->servicio->candidatos($famId, ...$this->paginacion($request))));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron buscar los productos');
        }
    }

    // DELETE pagina-web/productos-tienda-familias/{famId}   (a la papelera; sus SKU quedan libres)
    public function eliminar(int $famId)
    {
        try {
            $this->servicio->eliminar($famId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Familia enviada a la papelera', ['fam_id' => $famId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la familia');
        }
    }

    // PUT pagina-web/productos-tienda-familias/{famId}/restaurar   → {fam_id, quedaron_en_otra} (SKU que ya estaban en otra familia)
    public function restaurar(int $famId)
    {
        try {
            $quedaron = $this->servicio->restaurar($famId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Familia restaurada con éxito', ['fam_id' => $famId, 'quedaron_en_otra' => $quedaron]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la familia');
        }
    }

    // GET pagina-web/productos-tienda-familias-papelera?busqueda=&pagina=1&tamanio=20   → {registros, total} (con eliminado_por)
    public function papelera(Request $request)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Papelera', $this->servicio->papelera(...$this->paginacion($request))));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la papelera');
        }
    }

    // DELETE pagina-web/productos-tienda-familias-papelera   → {cantidad}
    public function vaciar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $this->servicio->vaciar(auth()->id())]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    // GET pagina-web/productos-tienda/{proId}/familia   → {fam_id, fam_nombre, es_principal, aviso} o null
    public function deProducto(int $proId)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Familia del producto', $this->servicio->deProducto($proId)));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la familia del producto');
        }
    }

    // -----------------------------------------------------------------

    /** [busqueda, pagina, tamanio]: 20 por defecto, entre 1 y 100 */
    private function paginacion(Request $request): array
    {
        return [
            trim((string) $request->input('busqueda', '')),
            max((int) $request->input('pagina', 1), 1),
            min(max((int) $request->input('tamanio', 20), 1), 100),
        ];
    }

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico);
    }
}
