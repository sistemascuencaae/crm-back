<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\PaginaMarcaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Páginas de marca (CRM): crear una página para una marca del ERP y editar sus datos y secciones.
 * La plantilla es fija: los productos salen solos de sus marcas y las imágenes se editan con las rutas de galerías.
 * Validación ligera aquí; las reglas están en la base. Los errores de un campo llegan como data {campo: mensaje}.
 */
class PaginaMarcaController extends Controller
{
    // Lo único que se acepta de una página (el logo es el de la marca: se cambia en Marcas de la tienda)
    private const CAMPOS = [
        'pma_direccion', 'pma_titulo', 'pma_publicada',
        'pma_banner_visible', 'pma_fotos_visible',
        'pma_contacto_visible', 'pma_contacto_titulo', 'pma_contacto_texto',
        'pma_contacto_boton_texto', 'pma_contacto_boton_enlace', 'pma_contacto_nueva_pestana',
    ];

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

    // GET pagina-web/paginas-marca/{pmaId}   → página + galerias {banner, fotos}
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

    // DELETE pagina-web/paginas-marca/{pmaId}   (a la papelera)
    public function eliminar(int $pmaId)
    {
        try {
            $this->servicio->eliminar($pmaId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Página enviada a la papelera', ['pma_id' => $pmaId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la página');
        }
    }

    // GET pagina-web/paginas-marca-papelera
    public function papelera()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Papelera', $this->servicio->papelera()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la papelera');
        }
    }

    // PUT pagina-web/paginas-marca/{pmaId}/restaurar   (vuelve oculta)
    public function restaurar(int $pmaId)
    {
        try {
            $this->servicio->restaurar($pmaId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Página restaurada con éxito', ['pma_id' => $pmaId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la página');
        }
    }

    // DELETE pagina-web/paginas-marca-papelera   → {cantidad}
    public function vaciar()
    {
        try {
            $cantidad = $this->servicio->vaciar(auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $cantidad]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    // -----------------------------------------------------------------

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, PaginaMarcaService::MENSAJES_ERROR);
    }
}