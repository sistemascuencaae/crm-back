<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\InicioSeccionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Secciones del inicio (CRM): el interruptor de cada sección y, en la grilla, su título y cantidad.
 * Los errores de un campo llegan como data {campo: mensaje}.
 */
class InicioSeccionController extends Controller
{
    // Lo único que se acepta (título y cantidad solo los usa la grilla)
    private const CAMPOS = ['ise_activo', 'ise_titulo', 'ise_cantidad'];

    public function __construct(private InicioSeccionService $servicio)
    {
    }

    // GET pagina-web/inicio-secciones
    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Secciones del inicio', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las secciones del inicio');
        }
    }

    // PUT pagina-web/inicio-secciones/{clave}   (solo lo que cambia: {ise_activo} o, en la grilla, {ise_titulo, ise_cantidad})
    public function modificar(Request $request, string $clave)
    {
        try {
            $this->servicio->modificar($clave, $request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Sección guardada con éxito', ['ise_clave' => $clave]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la sección');
        }
    }

    // -----------------------------------------------------------------

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, InicioSeccionService::MENSAJES_ERROR);
    }
}