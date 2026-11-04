<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\RequerimientoCaso;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Cotizar (modal de Ventas > Cotizaciones, sin menu propio): una cotizacion de contado o de credito con N productos.
// 23-oct: la vista previa tambien acepta TARJETA y, en contado, la forma de pago (parametros_contado_id). 25-oct: la
// tarjeta es el banco (ban_id, public.banco desde el 26-oct) y el plazo de una de sus tarjetas (tarjeta_plazo_id) o, en
// un banco sin tarjetas, un plazo de la PREDETERMINADA (plazo_default_id = el concepto TARJETA de parametros_cotizador).
// 27-oct: guardar tambien (las 3 proformas: la de TARJETA y la de CONTADO con su forma de pago guardan la foto del pago).
// El negocio vive en ventas.fn_cotizar_* y fn_cproforma_registrar (PLANES/COTIZADOR): este
// controller solo valida la forma, arma el jsonb y traduce la respuesta. Calcular es la vista
// previa (no guarda); Guardar recalcula en el servidor y guarda la foto completa.
// Sin "Ver costos" (ver_costos) en PROFORMAS las respuestas van sin costos, aportes, CAME ni margenes.
class CotizarController extends Controller
{
    use PermisosCotizacionTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Punto de venta y region del usuario de la sesion (solo informativos), politicas de
    // credito de clientes y valores por defecto (tasa e IVA). Con caso_id (la proforma del caso) el
    // servidor dice si el caso puede proformar (mensaje_caso) y da solo las politicas de hasta su Plazo.
    public function combosCotizar(Request $request)
    {
        try {
            $casoId = $this->enteroONulo($request->query('caso_id'));
            $fila = DB::selectOne('SELECT ventas.fn_cotizar_combos(?, ?::integer) AS resultado', [(int) auth('api')->id(), $casoId]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los datos para cotizar', $e->getMessage()));
        }
    }

    // Modal "Buscar producto" de Cotizar: productos activos paginados, igual que el listado de clientes.
    // Sin busqueda -> ventas.fn_producto_cotizar_listar_paginacion | con busqueda -> _buscar_paginacion
    public function listarProductosCotizar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($busqueda !== '') {
                $registros = DB::select('SELECT * FROM ventas.fn_producto_cotizar_buscar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
            } else {
                $registros = DB::select('SELECT * FROM ventas.fn_producto_cotizar_listar_paginacion(?, ?)', [$pagina, $tamanio]);
            }

            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Productos listados con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los productos', $e->getMessage()));
        }
    }

    // Calificaciones activas de la politica, la predeterminada primero (el margen, solo con Ver costos).
    public function calificacionesPoliticaCotizar($polId)
    {
        try {
            if (!ctype_digit((string) $polId) || (int) $polId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Política no válida', null));
            }

            $registros = DB::select('SELECT * FROM ventas.fn_cotizar_calificaciones(?)', [(int) $polId]);

            // PDO entrega los numeric como texto: al JSON van como numeros.
            foreach ($registros as $r) {
                $r->margen_financiero = (float) $r->margen_financiero;
            }

            // Filas como arreglos para quitar el margen a quien no tiene Ver costos.
            $registros = $this->datosSegunPermisoCostos(json_decode(json_encode($registros), true));

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las calificaciones', $e->getMessage()));
        }
    }

    // Vista previa: precio de cada producto (una llamada por producto) y totales. No guarda.
    // Un producto con error queda marcado en su linea y no corta los demas.
    public function calcularCotizacion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasCalculo(false), $this->mensajesCalculo(false));

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $fila = DB::selectOne('SELECT ventas.fn_cotizar_calcular(?::jsonb) AS resultado', [json_encode($this->payloadCalculo($request, false))]);
            $respuesta = json_decode($fila->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], $this->datosSegunPermisoCostos($respuesta['data'])));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo calcular la cotización', $e->getMessage()));
        }
    }

    // Guardar cotizacion: el servidor recalcula con lo vigente y guarda la foto completa
    // (data.ccotizacion_id y data.cliente). Los productos con error no se guardan.
    public function guardarCotizacion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasCalculo(true), $this->mensajesCalculo(true));

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            // Proforma: viene del requerimiento Proformar de un caso.
            $casoId = $this->enteroONulo($request->input('caso_id'));
            $reqCasoId = $this->enteroONulo($request->input('req_caso_id'));
            if ($casoId !== null || $reqCasoId !== null) {
                return $this->guardarProforma($request, $casoId, $reqCasoId);
            }

            $fila = DB::selectOne('SELECT ventas.fn_cproforma_registrar(?::jsonb) AS resultado', [json_encode($this->payloadCalculo($request, true))]);
            $respuesta = json_decode($fila->resultado, true);

            if (!$respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
            }

            // Sin ningun producto calculado no se guarda nada.
            if (empty($respuesta['data']['ccotizacion_id'])) {
                return response()->json(RespuestaApi::returnResultado('error', 'Ningún producto se pudo calcular: la cotización no se guardó.', $this->datosSegunPermisoCostos($respuesta['data'])));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Cotización N° ' . $respuesta['data']['ccotizacion_id'] . ' guardada.', $this->datosSegunPermisoCostos($respuesta['data'])));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar la cotización', $e->getMessage()));
        }
    }

    // Proforma (requerimiento Proformar del caso): en una transaccion guarda la cotizacion con caso_id
    // (cliente y calificacion salen del caso), marca el requerimiento (marcado, valor_int = la ultima
    // version) y deja el registro en la bitacora del caso, como la solicitud credito vendedor. Cada
    // Guardar es una version nueva. Devuelve lo mismo que Guardar mas reqCaso (los del caso).
    private function guardarProforma(Request $request, ?int $casoId, ?int $reqCasoId)
    {
        $reqCaso = $reqCasoId !== null ? RequerimientoCaso::find($reqCasoId) : null;
        if (!$reqCaso || $casoId === null || (int) $reqCaso->caso_id !== $casoId || $reqCaso->tipo_campo !== 'proformar') {
            return response()->json(RespuestaApi::returnResultado('error', 'El requerimiento Proformar no es de este caso.', null));
        }

        return DB::transaction(function () use ($request, $reqCaso, $casoId) {
            $fila = DB::selectOne('SELECT ventas.fn_cproforma_registrar(?::jsonb) AS resultado', [json_encode($this->payloadCalculo($request, true))]);
            $respuesta = json_decode($fila->resultado, true);

            if (!$respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
            }

            if (empty($respuesta['data']['ccotizacion_id'])) {
                return response()->json(RespuestaApi::returnResultado('error', 'Ningún producto se pudo calcular: la proforma no se guardó.', $this->datosSegunPermisoCostos($respuesta['data'])));
            }

            $audit = new Audits();
            $audit->old_values = json_encode($reqCaso);

            $reqCaso->marcado = true;
            $reqCaso->valor_int = $respuesta['data']['ccotizacion_id'];
            $reqCaso->save();

            // Bitacora: accion 'editRequerimiento' + tipo_campo 'proformar' (muestra el mensaje resumido).
            $audit->user_id = auth('api')->id();
            $audit->event = 'updated';
            $audit->auditable_type = RequerimientoCaso::class;
            $audit->auditable_id = $reqCaso->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip();
            $audit->url = $request->fullUrl();
            $audit->new_values = json_encode($reqCaso);
            $audit->user_agent = $request->header('User-Agent');
            $audit->accion = 'editRequerimiento';
            $audit->caso_id = $casoId;
            $audit->save();

            $data = $this->datosSegunPermisoCostos($respuesta['data']);
            $data['reqCaso'] = RequerimientoCaso::where('caso_id', $casoId)->orderBy('id', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Proforma N° ' . $respuesta['data']['ccotizacion_id'] . ' guardada.', $data));
        });
    }

    // ------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------

    // Solo la forma; el negocio (politica, vigencias...) lo valida ventas.fn_cotizar_calcular.
    // 27-oct: calcular y guardar aceptan TARJETA (la proforma ya la guarda).
    private function reglasCalculo(bool $guardar): array
    {
        return [
            'tipo'             => 'required|in:CONTADO,CREDITO,TARJETA',
            'items'            => 'required|array|min:1',
            'items.*'          => 'array',
            'items.*.pro_id'   => 'required|integer',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.precio'   => 'nullable|numeric|gt:0',
            'pol_id'           => 'nullable|integer',
            'entrada'          => 'nullable|numeric|min:0',
            'identificacion'   => ['nullable', 'regex:/^(\d{10}|\d{13})$/'],
            'caso_id'          => 'nullable|integer',
            'req_caso_id'      => 'nullable|integer',
            'ban_id'           => 'nullable|integer',
            'tarjeta_plazo_id' => 'nullable|integer',
            'plazo_default_id' => 'nullable|integer',
            'parametros_contado_id' => 'nullable|integer',
        ];
    }

    // Mismos textos que la funcion, para que el front muestre un solo estilo de mensaje.
    private function mensajesCalculo(bool $guardar): array
    {
        $modalidad = 'Elija la modalidad: CONTADO, CREDITO o TARJETA.';

        return [
            'tipo.required'             => $modalidad,
            'tipo.in'                   => $modalidad,
            'items.required'            => 'Agregue al menos un producto.',
            'items.array'               => 'Agregue al menos un producto.',
            'items.min'                 => 'Agregue al menos un producto.',
            'items.*.array'             => 'Cada producto debe traer pro_id y cantidad.',
            'items.*.pro_id.required'   => 'Falta el producto en una de las líneas.',
            'items.*.pro_id.integer'    => 'El producto no es válido.',
            'items.*.cantidad.required' => 'Falta la cantidad en una de las líneas.',
            'items.*.cantidad.numeric'  => 'La cantidad debe ser mayor que cero.',
            'items.*.cantidad.gt'       => 'La cantidad debe ser mayor que cero.',
            'items.*.precio.numeric'    => 'El precio debe ser un número mayor que cero.',
            'items.*.precio.gt'         => 'El precio debe ser un número mayor que cero.',
            'pol_id.integer'            => 'La política no existe.',
            'entrada.numeric'           => 'La entrada debe ser un número mayor o igual a cero.',
            'entrada.min'               => 'La entrada debe ser un número mayor o igual a cero.',
            'identificacion.regex'      => 'La cédula o RUC del cliente debe tener 10 o 13 dígitos.',
            'caso_id.integer'           => 'El caso no existe.',
            'req_caso_id.integer'       => 'El requerimiento Proformar no es de este caso.',
            'ban_id.integer'           => 'El banco no existe.',
            'tarjeta_plazo_id.integer' => 'El plazo de la tarjeta no existe.',
            'plazo_default_id.integer' => 'El plazo de la tarjeta no existe.',
            'parametros_contado_id.integer' => 'La forma de pago no existe.',
        ];
    }

    // Ids como enteros; montos tal cual (la funcion los toma como numeric). Lo vacio va null.
    // Fecha, region, calificacion, plazo, tasa e IVA no se reciben: la funcion usa la hora del servidor, el punto de venta
    // del usuario de la SESION (token), la calificacion predeterminada, los pagos de la politica y los valores por defecto.
    // La tarjeta (banco y plazo) y la forma de pago de contado van en la vista previa y (27-oct) tambien al guardar.
    private function payloadCalculo(Request $request, bool $guardar): array
    {
        $payload = [
            'tipo'            => $request->input('tipo'),
            'usuario_id'      => (int) auth('api')->id(),
            // precio: el negociado (unitario con IVA); sin el, el de lista. El rango lo valida la funcion.
            'items'           => array_map(fn($i) => [
                'pro_id'   => (int) $i['pro_id'],
                'cantidad' => $i['cantidad'],
                'precio'   => (isset($i['precio']) && trim((string) $i['precio']) !== '') ? $i['precio'] : null,
            ], array_values($request->input('items'))),
            'pol_id'          => $this->enteroONulo($request->input('pol_id')),
            'entrada'         => $request->input('entrada'),
            'identificacion'  => trim((string) $request->input('identificacion')) === '' ? null : trim((string) $request->input('identificacion')),
            // Proforma: con el caso, la funcion toma su cliente y la calificacion de su Tipo Cliente.
            'caso_id'         => $this->enteroONulo($request->input('caso_id')),
            // 27-oct: tambien al guardar (la proforma guarda la tarjeta y la forma de pago).
            'ban_id'           => $this->enteroONulo($request->input('ban_id')),
            'tarjeta_plazo_id' => $this->enteroONulo($request->input('tarjeta_plazo_id')),
            'plazo_default_id' => $this->enteroONulo($request->input('plazo_default_id')),
            'parametros_contado_id' => $this->enteroONulo($request->input('parametros_contado_id')),
        ];

        return $payload;
    }

    private function enteroONulo($valor): ?int
    {
        return ($valor === null || trim((string) $valor) === '') ? null : (int) $valor;
    }
}
