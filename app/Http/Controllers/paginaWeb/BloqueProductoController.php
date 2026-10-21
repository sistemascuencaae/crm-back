<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\BloqueProductoService;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Bloques destacados (CRM), los mismos para el inicio y para las páginas de marca.
 * Sin pma_id son los del inicio; con pma_id, los de esa página.
 * Validación ligera aquí; las reglas están en la base. Los errores de un campo llegan como data {campo: mensaje}.
 */
class BloqueProductoController extends Controller
{
    // Lo único que se acepta de un bloque (bpr_desde / bpr_hasta: "YYYY-MM-DDTHH:MM", hora de Ecuador, o null)
    private const CAMPOS = ['bpr_titulo', 'tpr_id', 'bpr_cantidad', 'bpr_activo', 'bpr_imagen_lado', 'bpr_desde', 'bpr_hasta'];

    public function __construct(private BloqueProductoService $servicio)
    {
    }

    // GET pagina-web/bloques-productos            → los del inicio
    // GET pagina-web/bloques-productos?pma_id=3   → los de esa página
    public function listar(Request $request)
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Bloques', $this->servicio->listar($this->pmaId($request))));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar los bloques');
        }
    }

    // POST pagina-web/bloques-productos   {pma_id (opcional), bpr_titulo, tpr_id, bpr_cantidad, bpr_imagen_lado, bpr_desde, bpr_hasta}
    public function crear(Request $request)
    {
        try {
            $bprId = $this->servicio->crear(
                $request->only(self::CAMPOS) + ['pma_id' => $request->input('pma_id'), 'usu_id' => auth()->id()]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque creado con éxito', ['bpr_id' => $bprId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear el bloque');
        }
    }

    // PUT pagina-web/bloques-productos/{bprId}   (solo lo que cambia)
    public function modificar(Request $request, int $bprId)
    {
        try {
            $this->servicio->modificar($bprId, $request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque guardado con éxito', ['bpr_id' => $bprId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el bloque');
        }
    }

    // DELETE pagina-web/bloques-productos/{bprId}   (con su galería e imágenes)
    public function eliminar(int $bprId)
    {
        try {
            $this->servicio->eliminar($bprId);

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque eliminado con éxito', ['bpr_id' => $bprId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar el bloque');
        }
    }

    // PUT pagina-web/bloques-productos-orden   {pma_id (opcional), ids: [3, 1, 2]} (todos los bloques de ese dueño)
    public function ordenar(Request $request)
    {
        try {
            $this->servicio->ordenar([
                'pma_id' => $request->input('pma_id'),
                'ids'    => $request->input('ids'),
                'usu_id' => auth()->id(),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------

    /** pma_id de la consulta: vacío = inicio */
    private function pmaId(Request $request): ?int
    {
        $pmaId = $request->input('pma_id');

        return ($pmaId === null || $pmaId === '') ? null : (int) $pmaId;
    }

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, BloqueProductoService::MENSAJES_ERROR);
    }
}