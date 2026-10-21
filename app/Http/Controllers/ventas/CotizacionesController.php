<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Proformas (ae/ventas/PROFORMAS): el historial de las proformas de los casos y una proforma completa.
// El negocio vive en ventas.fn_cproforma_* (PLANES/COTIZADOR/produccion_cotizaciones.sql): este
// controller solo valida la forma y traduce la respuesta. Solo lectura.
// Como Todos los casos: el superusuario ve todas; el resto, las de los tipos de caso de su perfil.
// Costos, solo con report en PROFORMAS.
class CotizacionesController extends Controller
{
    use PermisosCotizacionTrait;

    // Tope del Excel (tamanio 0), el mismo de Todos los casos.
    private const TOPE_EXCEL_PROFORMAS = 10000;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Historial: todas las versiones, paginado en el servidor (pagina, tamanio y busqueda). Perfil sin tipos de
    // caso: no ve nada y la pantalla lo avisa. tamanio 0 = el Excel (hasta 10.000). Sin costos en las filas.
    public function listProformas(Request $request)
    {
        try {
            $user = auth('api')->user();
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = (int) $request->query('tamanio', 10);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($tamanio <= 0 || $tamanio > self::TOPE_EXCEL_PROFORMAS) {
                $tamanio = self::TOPE_EXCEL_PROFORMAS;
            }

            $sinTipos = !$this->esSuperusuario()
                && !DB::table('crm.perfil_tipo_caso')->where('profile_id', $user->profile_id)->exists();

            if ($sinTipos) {
                $registros = [];
            } elseif ($busqueda !== '') {
                $registros = DB::select('SELECT * FROM ventas.fn_cproforma_buscar_paginacion(?, ?, ?, ?)', [$user->id, $pagina, $tamanio, $busqueda]);
            } else {
                $registros = DB::select('SELECT * FROM ventas.fn_cproforma_listar_paginacion(?, ?, ?)', [$user->id, $pagina, $tamanio]);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
                'sin_tipos' => $sinTipos,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las proformas', $e->getMessage()));
        }
    }

    // La cotizacion completa: cabecera, productos y aportes de cada producto.
    public function getCotizacion($id)
    {
        try {
            if (!ctype_digit((string) $id) || (int) $id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cotización no válida', null));
            }

            $fila = DB::selectOne('SELECT ventas.fn_cproforma_buscar(?) AS resultado', [(int) $id]);
            $respuesta = json_decode($fila->resultado, true);

            if (!$respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
            }

            // Una cotizacion ajena solo la abre el superusuario. La proforma de un caso (caso_id) la abre quien
            // trabaja el caso, para verla o editarla desde su requerimiento.
            if (!$this->esSuperusuario() && empty($respuesta['data']['caso_id'])
                && (int) $respuesta['data']['user_id'] !== (int) auth('api')->id()) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene acceso a esta cotización.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], $this->datosSegunPermisoCostos($respuesta['data'])));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la cotización', $e->getMessage()));
        }
    }
}
