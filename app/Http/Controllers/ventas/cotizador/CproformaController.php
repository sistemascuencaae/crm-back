<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\PermisosCotizacionTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Version 1.0
// Cotizador > Proformas (V04, ae/ventas/PROFORMAS): el historial de las proformas de los casos (ventas.cproforma), una proforma
// completa con sus productos (ventas.dproforma) y sus aportes (ventas.dproforma_aporte), y las versiones de la proforma de un caso
// (el ojito del requerimiento Proformar). Solo lectura: la proforma es una foto inmutable, la escribe
// ventas/cotizador/CotizarController@guardarCotizacion con ventas.fn_cproforma_registrar, y sus tablas no llevan trigger de auditoría
// (excepción del 26-oct). Los modelos Cproforma, Dproforma y DproformaAporte documentan sus columnas: no hay payload que armar.
// El negocio vive en ventas.fn_cproforma_* (PLANES/COTIZADOR/produccion_cotizaciones.sql): este controller solo valida la forma y
// traduce la respuesta. Como Todos los casos: el superusuario ve todas; el resto, las de los tipos de caso de su perfil.
// Costos, solo con Ver costos (ver_costos) en PROFORMAS; el Excel sigue con report (Imprimir). 27-oct: la comision del banco de
// la proforma con tarjeta (comision_banco y valor_comision) tambien es un costo: Ver, Proformas y el historial.
// Viene de ventas/CotizacionesController (Fase 9 del plan del cotizador, 11-nov-2026): mismos endpoints (familia de Proformar,
// excepción de D2), mismas funciones SQL y mismos mensajes; el trait de permisos pasó a app/Http/Traits.
class CproformaController extends Controller
{
    use PermisosCotizacionTrait;

    // Tope del Excel (tamanio 0), el mismo de Todos los casos.
    private const TOPE_EXCEL_PROFORMAS = 10000;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listProformas?pagina&tamanio&busqueda — todas las versiones, paginado en el servidor. Perfil sin tipos
    // de caso: no ve nada y la pantalla lo avisa (sin_tipos). tamanio 0 = el Excel (hasta 10.000). Sin costos en las filas.
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
                // 27-oct: las filas pasan por el filtro de costos (la comision del banco, si algun dia viniera).
                'registros' => $this->datosSegunPermisoCostos(json_decode(json_encode($registros), true)),
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
                'sin_tipos' => $sinTipos,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las proformas', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/getCotizacion/{id} — la proforma completa: cabecera, productos y aportes de cada producto (la abre
    // Cotizar para verla o editarla). Conserva el nombre de antes (familia de Proformar).
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

    // Version 1.0
    // GET /api/ventas/listProformasCaso/{casoId} — 27-oct: el historial de la proforma de un caso (el ojito Ver del
    // requerimiento Proformar): todas sus versiones, la mas nueva primero y con la vigente marcada
    // (ventas.fn_cproforma_caso_listar). Como getCotizacion, la proforma de un caso la ve quien trabaja el caso: sin filtro de
    // perfil. Sin costos en las filas.
    public function listProformasCaso($casoId)
    {
        try {
            if (!preg_match('/^[0-9]{1,9}$/', (string) $casoId) || (int) $casoId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Caso no válido', null));
            }

            $registros = DB::select('SELECT * FROM ventas.fn_cproforma_caso_listar(?)', [(int) $casoId]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                // 27-oct: como en Proformas, las filas pasan por el filtro de costos.
                'registros' => $this->datosSegunPermisoCostos(json_decode(json_encode($registros), true)),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las proformas del caso', $e->getMessage()));
        }
    }
}
