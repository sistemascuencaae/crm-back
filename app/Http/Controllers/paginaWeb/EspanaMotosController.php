<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\EspanaMotosService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Pantalla España Motos (CRM): su configuración, sus pestañas de categorías y sus marcas.
 * Las imágenes del banner y de las tarjetas se editan con las rutas de galerías.
 * Validación ligera aquí; las reglas están en la base. Los errores de un campo llegan como data {campo: mensaje}.
 */
class EspanaMotosController extends Controller
{
    // Lo único que se acepta de cada petición
    private const CAMPOS = [
        'emo_publicada', 'emo_banner_visible',
        'emo_fondo_visible', 'emo_fondo_imagen', 'emo_fondo_titulo', 'emo_fondo_subtitulo',
        'emo_tarjetas_visible',
    ];
    private const CAMPOS_PESTANA         = ['emp_nombre', 'emp_categorias', 'emp_activo'];
    // El logo es el de la marca: se cambia en Marcas de la tienda
    private const CAMPOS_MARCA_CREAR     = ['mar_id', 'emm_activo'];
    private const CAMPOS_MARCA_MODIFICAR = ['emm_activo'];   // la marca no se cambia

    public function __construct(private EspanaMotosService $servicio)
    {
    }

    // GET pagina-web/espana-motos   → configuración + galerias {banner, tarjetas} + pestanas + marcas
    public function buscar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'España Motos', $this->servicio->buscar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la pantalla');
        }
    }

    // PUT pagina-web/espana-motos   (solo lo que cambia)
    public function modificar(Request $request)
    {
        try {
            $this->servicio->modificar($request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Pantalla guardada con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la pantalla');
        }
    }

    // GET pagina-web/espana-motos-marcas-erp?buscar=vim   → marcas del ERP para agregar
    public function marcasErp(Request $request)
    {
        try {
            $marcas = $this->servicio->marcasErp((string) $request->input('buscar', ''));

            return response()->json(RespuestaApi::returnResultado('success', 'Marcas', $marcas));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron buscar las marcas');
        }
    }

    // -----------------------------------------------------------------
    // Pestañas
    // -----------------------------------------------------------------

    // POST pagina-web/espana-motos-pestanas   {emp_nombre, emp_categorias: [416, 420], emp_activo}
    public function crearPestana(Request $request)
    {
        try {
            $empId = $this->servicio->crearPestana($request->only(self::CAMPOS_PESTANA) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Pestaña creada con éxito', ['emp_id' => $empId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear la pestaña');
        }
    }

    // PUT pagina-web/espana-motos-pestanas/{empId}   (solo lo que cambia)
    public function modificarPestana(Request $request, int $empId)
    {
        try {
            $this->servicio->modificarPestana($empId, $request->only(self::CAMPOS_PESTANA) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Pestaña guardada con éxito', ['emp_id' => $empId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la pestaña');
        }
    }

    // DELETE pagina-web/espana-motos-pestanas/{empId}   (a la papelera)
    public function eliminarPestana(int $empId)
    {
        try {
            $this->servicio->eliminarPestana($empId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Pestaña enviada a la papelera', ['emp_id' => $empId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la pestaña');
        }
    }

    // GET pagina-web/espana-motos-pestanas-papelera
    public function papeleraPestanas()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Papelera', $this->servicio->papeleraPestanas()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la papelera');
        }
    }

    // PUT pagina-web/espana-motos-pestanas/{empId}/restaurar
    public function restaurarPestana(int $empId)
    {
        try {
            $this->servicio->restaurarPestana($empId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Pestaña restaurada con éxito', ['emp_id' => $empId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la pestaña');
        }
    }

    // DELETE pagina-web/espana-motos-pestanas-papelera   → {cantidad}
    public function vaciarPestanas()
    {
        try {
            $cantidad = $this->servicio->vaciarPestanas(auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $cantidad]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    // PUT pagina-web/espana-motos-pestanas-orden   {ids: [3, 1, 2]} (todas, en el nuevo orden)
    public function ordenarPestanas(Request $request)
    {
        try {
            $this->servicio->ordenarPestanas(['ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------
    // Marcas
    // -----------------------------------------------------------------

    // POST pagina-web/espana-motos-marcas   {mar_id, emm_activo}
    public function crearMarca(Request $request)
    {
        try {
            $emmId = $this->servicio->crearMarca($request->only(self::CAMPOS_MARCA_CREAR) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Marca agregada con éxito', ['emm_id' => $emmId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al agregar la marca');
        }
    }

    // PUT pagina-web/espana-motos-marcas/{emmId}   {emm_activo} (la marca no se cambia)
    public function modificarMarca(Request $request, int $emmId)
    {
        try {
            $this->servicio->modificarMarca($emmId, $request->only(self::CAMPOS_MARCA_MODIFICAR) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Marca guardada con éxito', ['emm_id' => $emmId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la marca');
        }
    }

    // DELETE pagina-web/espana-motos-marcas/{emmId}   (a la papelera)
    public function eliminarMarca(int $emmId)
    {
        try {
            $this->servicio->eliminarMarca($emmId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Marca enviada a la papelera', ['emm_id' => $emmId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al quitar la marca');
        }
    }

    // GET pagina-web/espana-motos-marcas-papelera
    public function papeleraMarcas()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Papelera', $this->servicio->papeleraMarcas()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la papelera');
        }
    }

    // PUT pagina-web/espana-motos-marcas/{emmId}/restaurar
    public function restaurarMarca(int $emmId)
    {
        try {
            $this->servicio->restaurarMarca($emmId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Marca restaurada con éxito', ['emm_id' => $emmId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la marca');
        }
    }

    // DELETE pagina-web/espana-motos-marcas-papelera   → {cantidad}
    public function vaciarMarcas()
    {
        try {
            $cantidad = $this->servicio->vaciarMarcas(auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $cantidad]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    // PUT pagina-web/espana-motos-marcas-orden   {ids: [3, 1, 2]} (todas, en el nuevo orden)
    public function ordenarMarcas(Request $request)
    {
        try {
            $this->servicio->ordenarMarcas(['ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, EspanaMotosService::MENSAJES_ERROR);
    }
}
