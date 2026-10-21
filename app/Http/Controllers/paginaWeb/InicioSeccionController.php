<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\InicioSeccionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Secciones del inicio (CRM): el interruptor, la vigencia y el orden de cada sección y, en las que los usan,
 * su título, subtítulo y cantidad. Los errores de un campo llegan como data {campo: mensaje}.
 */
class InicioSeccionController extends Controller
{
    // Lo único que se acepta (subtítulo solo ofertas_flash; fechas: "YYYY-MM-DDTHH:MM", hora de Ecuador, o null)
    private const CAMPOS = ['ise_activo', 'ise_titulo', 'ise_subtitulo', 'ise_cantidad', 'ise_desde', 'ise_hasta'];

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

    // PUT pagina-web/inicio-secciones/{clave}   (solo lo que cambia de CAMPOS)
    public function modificar(Request $request, string $clave)
    {
        try {
            $this->servicio->modificar($clave, $request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Sección guardada con éxito', ['ise_clave' => $clave]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la sección');
        }
    }

    // PUT pagina-web/inicio-secciones-orden   {claves: ['banner_principal', 'mosaico', …]} (todas, en el nuevo orden)
    public function ordenar(Request $request)
    {
        try {
            $this->servicio->ordenar(['claves' => $request->input('claves'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, InicioSeccionService::MENSAJES_ERROR);
    }
}
