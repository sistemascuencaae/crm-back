<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Cotizar (ae/ventas/COTIZAR): una cotizacion de contado o de credito con N productos.
// El negocio vive en ventas.fn_cotizar_* y fn_ccotizacion_registrar (PLANES/COTIZADOR): este
// controller solo valida la forma, arma el jsonb y traduce la respuesta. Calcular es la vista
// previa (no guarda); Guardar recalcula en el servidor y guarda la foto completa.
class CotizarController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Punto de venta y region del usuario de la sesion (solo informativos), politicas de
    // credito de clientes y valores por defecto (tasa e IVA).
    public function combosCotizar()
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_cotizar_combos(?) AS resultado', [(int) auth('api')->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los datos para cotizar', $e->getMessage()));
        }
    }

    // Calificaciones activas de la politica, la predeterminada primero.
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
            $validator = Validator::make($request->all(), $this->reglasCalculo(), $this->mensajesCalculo());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $fila = DB::selectOne('SELECT ventas.fn_cotizar_calcular(?::jsonb) AS resultado', [json_encode($this->payloadCalculo($request))]);
            $respuesta = json_decode($fila->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], $respuesta['data']));
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
            $validator = Validator::make($request->all(), $this->reglasCalculo(), $this->mensajesCalculo());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $fila = DB::selectOne('SELECT ventas.fn_ccotizacion_registrar(?::jsonb) AS resultado', [json_encode($this->payloadCalculo($request))]);
            $respuesta = json_decode($fila->resultado, true);

            if (!$respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
            }

            // Sin ningun producto calculado no se guarda nada.
            if (empty($respuesta['data']['ccotizacion_id'])) {
                return response()->json(RespuestaApi::returnResultado('error', 'Ningún producto se pudo calcular: la cotización no se guardó.', $respuesta['data']));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Cotización N° ' . $respuesta['data']['ccotizacion_id'] . ' guardada.', $respuesta['data']));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar la cotización', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------

    // Solo la forma; el negocio (politica, calificacion, vigencias...) lo valida ventas.fn_cotizar_calcular.
    private function reglasCalculo(): array
    {
        return [
            'tipo'             => 'required|in:CONTADO,CREDITO',
            'items'            => 'required|array|min:1',
            'items.*'          => 'array',
            'items.*.pro_id'   => 'required|integer',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.precio'   => 'nullable|numeric|gt:0',
            'pol_id'           => 'nullable|integer',
            'calificacion_id'  => 'nullable|integer',
            'entrada'          => 'nullable|numeric|min:0',
            'identificacion'   => ['nullable', 'regex:/^(\d{10}|\d{13})$/'],
        ];
    }

    // Mismos textos que la funcion, para que el front muestre un solo estilo de mensaje.
    private function mensajesCalculo(): array
    {
        return [
            'tipo.required'             => 'Elija la modalidad: CONTADO o CREDITO.',
            'tipo.in'                   => 'Elija la modalidad: CONTADO o CREDITO.',
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
            'calificacion_id.integer'   => 'La calificación no existe.',
            'entrada.numeric'           => 'La entrada debe ser un número mayor o igual a cero.',
            'entrada.min'               => 'La entrada debe ser un número mayor o igual a cero.',
            'identificacion.regex'      => 'La cédula o RUC del cliente debe tener 10 o 13 dígitos.',
        ];
    }

    // Ids como enteros; montos tal cual (la funcion los toma como numeric). Lo vacio va null.
    // Fecha, region, plazo, tasa e IVA no se reciben: la funcion usa la hora del servidor, el punto de
    // venta del usuario de la SESION (usuario_id del token), los pagos de la politica y los valores por defecto.
    private function payloadCalculo(Request $request): array
    {
        return [
            'tipo'            => $request->input('tipo'),
            'usuario_id'      => (int) auth('api')->id(),
            // precio: el negociado (unitario con IVA); sin el, el de lista. El rango lo valida la funcion.
            'items'           => array_map(fn($i) => [
                'pro_id'   => (int) $i['pro_id'],
                'cantidad' => $i['cantidad'],
                'precio'   => (isset($i['precio']) && trim((string) $i['precio']) !== '') ? $i['precio'] : null,
            ], array_values($request->input('items'))),
            'pol_id'          => $this->enteroONulo($request->input('pol_id')),
            'calificacion_id' => $this->enteroONulo($request->input('calificacion_id')),
            'entrada'         => $request->input('entrada'),
            'identificacion'  => trim((string) $request->input('identificacion')) === '' ? null : trim((string) $request->input('identificacion')),
        ];
    }

    private function enteroONulo($valor): ?int
    {
        return ($valor === null || trim((string) $valor) === '') ? null : (int) $valor;
    }
}
