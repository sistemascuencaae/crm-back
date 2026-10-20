<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresEncabezadoPie;
use App\Servicios\PaginaWeb\PaginaMarcaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Páginas de marca (CRM): crear una página para una marca del ERP, editar sus datos y secciones,
 * y sus bloques destacados. Las imágenes se editan con las rutas de galerías.
 * Validación ligera aquí; las reglas están en la base. Los errores de un campo llegan como data {campo: mensaje}.
 */
class PaginaMarcaController extends Controller
{
    // Lo único que se acepta de una página
    private const CAMPOS = [
        'pma_direccion', 'pma_titulo', 'pma_logo', 'pma_publicada',
        'pma_banner_visible', 'pma_bloques_visible',
        'pma_grilla_visible', 'pma_grilla_titulo', 'pma_grilla_cantidad',
        'pma_fotos_visible',
        'pma_contacto_visible', 'pma_contacto_titulo', 'pma_contacto_texto',
        'pma_contacto_boton_texto', 'pma_contacto_boton_enlace', 'pma_contacto_nueva_pestana',
    ];

    // Lo único que se acepta de un bloque
    private const CAMPOS_BLOQUE = ['pmb_titulo', 'tpr_id', 'pmb_cantidad', 'pmb_activo'];

    public function __construct(private PaginaMarcaService $servicio)
    {
    }

    // GET pagina-web/paginas-marca-marcas-erp?buscar=vim   → marcas del ERP para crear una página
    public function marcasErp(Request $request)
    {
        try {
            $marcas = $this->servicio->marcasErp((string) $request->input('buscar', ''));

            return response()->json(RespuestaApi::returnResultado('success', 'Marcas', $marcas));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron buscar las marcas');
        }
    }

    // GET pagina-web/paginas-marca
    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Páginas de marca', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las páginas de marca');
        }
    }

    // GET pagina-web/paginas-marca/{pmaId}   → página + galerias {banner, fotos} + bloques (cada uno con su galeria)
    public function buscar(int $pmaId)
    {
        try {
            $pagina = $this->servicio->buscar($pmaId);

            if ($pagina === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'La página no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Página de marca', $pagina));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la página');
        }
    }

    // POST pagina-web/paginas-marca   {mar_id}   → se crea oculta, con los textos por defecto
    public function crear(Request $request)
    {
        try {
            $pmaId = $this->servicio->crear(['mar_id' => $request->input('mar_id'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Página creada con éxito', ['pma_id' => $pmaId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear la página');
        }
    }

    // PUT pagina-web/paginas-marca/{pmaId}   (solo lo que cambia)
    public function modificar(Request $request, int $pmaId)
    {
        try {
            $this->servicio->modificar($pmaId, $request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Página guardada con éxito', ['pma_id' => $pmaId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la página');
        }
    }

    // DELETE pagina-web/paginas-marca/{pmaId}   (con sus bloques, galerías e imágenes)
    public function eliminar(int $pmaId)
    {
        try {
            $this->servicio->eliminar($pmaId);

            return response()->json(RespuestaApi::returnResultado('success', 'Página eliminada con éxito', ['pma_id' => $pmaId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la página');
        }
    }

    // POST pagina-web/paginas-marca/{pmaId}/bloques   {pmb_titulo, tpr_id, pmb_cantidad, pmb_activo}
    public function crearBloque(Request $request, int $pmaId)
    {
        try {
            $pmbId = $this->servicio->crearBloque(
                $request->only(self::CAMPOS_BLOQUE) + ['pma_id' => $pmaId, 'usu_id' => auth()->id()]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque creado con éxito', ['pmb_id' => $pmbId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear el bloque');
        }
    }

    // PUT pagina-web/paginas-marca-bloques/{pmbId}   (solo lo que cambia; tpr_id null quita la categoría)
    public function modificarBloque(Request $request, int $pmbId)
    {
        try {
            $this->servicio->modificarBloque($pmbId, $request->only(self::CAMPOS_BLOQUE) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque guardado con éxito', ['pmb_id' => $pmbId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el bloque');
        }
    }

    // DELETE pagina-web/paginas-marca-bloques/{pmbId}   (con su galería e imágenes)
    public function eliminarBloque(int $pmbId)
    {
        try {
            $this->servicio->eliminarBloque($pmbId);

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque eliminado con éxito', ['pmb_id' => $pmbId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar el bloque');
        }
    }

    // PUT pagina-web/paginas-marca/{pmaId}/bloques-orden   {ids: [3, 1, 2]} (todos los bloques, en el nuevo orden)
    public function ordenarBloques(Request $request, int $pmaId)
    {
        try {
            $this->servicio->ordenarBloques(['pma_id' => $pmaId, 'ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresEncabezadoPie::respuesta($e, $mensajeGenerico, PaginaMarcaService::MENSAJES_ERROR);
    }
}