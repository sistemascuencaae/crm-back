<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\ContadoParametro;
use App\Models\ventas\cotizador\ContadoRegla;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// Cotizador > Contado (V07, menú TARJETAS-CONTADO): las formas de pago de contado (ventas.contado_parametros) con sus reglas
// (ventas.contado_reglas), que se guardan juntas con un solo Guardar. Sin eliminar: formas y reglas se desactivan.
// El negocio vive en ventas.fn_contado_parametros_* y ventas.fn_contado_reglas_* (PLANES/COTIZADOR/
// produccion_contado_parametros.sql): este controller valida la forma, arma el jsonb con los campos de los modelos
// (ContadoParametro y ContadoRegla) y el bloque 'auditoria', y devuelve tal cual el {success, message} de la función. Viene de
// ventas/TarjetasContadoController (Fase 6 del plan del cotizador, 11-nov-2026), con los mismos mensajes y las mismas
// funciones SQL (las que confundían, con su nombre nuevo).
class ContadoParametrosController extends Controller
{
    use AuditoriaForenseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listContadoParametros — todas las formas de pago (activas e inactivas), cada una con sus reglas por
    // orden; la predeterminada primero y luego por nombre. Son pocas: sin paginar. Antes getFormasPagoContado.
    public function listContadoParametros()
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_contado_parametros_listar() AS resultado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las formas de pago de contado', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/guardarContadoParametros — guarda en un solo paso las formas de pago nuevas y las cambiadas, cada una
    // con sus reglas (todo o nada), y devuelve la lista como quedó. Antes guardarFormasPagoContado.
    public function guardarContadoParametros(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $formas = $request->input('formas');
            $payload = [
                'formas' => is_array($formas) ? $this->payloadFormas($formas) : null,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_contado_parametros_guardar_lote(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'formas' => $respuesta['formas'] ?? [],
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron guardar las formas de pago de contado', $e->getMessage()));
        }
    }

    // Solo los tipos (en el mismo orden de antes: el primer error es el mismo). Lo obligatorio y el negocio (con mensajes en
    // español) lo validan ventas.fn_contado_parametros_validaciones y ventas.fn_contado_reglas_validaciones.
    private function reglasRequest(): array
    {
        $reglas = [
            'formas' => 'nullable|array',
            'formas.*' => 'array',
            'formas.*.id' => 'nullable|integer',
            'formas.*.nombre' => 'nullable|string|max:255',
            'formas.*.descuento' => 'nullable|numeric',
            'formas.*.predeterminado' => 'nullable|boolean',
            'formas.*.activo' => 'nullable|boolean',
            'formas.*.reglas' => 'nullable|array',
            'formas.*.reglas.*' => 'array',
            'formas.*.reglas.*.id' => 'nullable|integer',
            'formas.*.reglas.*.orden' => 'nullable|integer',
            'formas.*.reglas.*.fecha_inicio' => 'nullable|string',
            'formas.*.reglas.*.fecha_fin' => 'nullable|string',
            'formas.*.reglas.*.descuento' => 'nullable|numeric',
            'formas.*.reglas.*.activo' => 'nullable|boolean',
        ];
        foreach (ContadoRegla::DIAS as $dia) {
            $reglas['formas.*.reglas.*.' . $dia] = 'nullable|boolean';
        }

        return $reglas;
    }

    // Cada forma de pago con los campos del modelo ContadoParametro y sus reglas. Sin id = nueva; con id = existente (la
    // función verifica que exista). predeterminado que no viene = false; activo que no viene = null (la función pone true).
    private function payloadFormas(array $formas): array
    {
        $campos = (new ContadoParametro())->getFillable();

        return array_map(function ($f) use ($campos) {
            $forma = ['id' => isset($f['id']) && $f['id'] !== '' ? (int) $f['id'] : null];
            foreach ($campos as $campo) {
                $forma[$campo] = $f[$campo] ?? null;
            }
            $forma['predeterminado'] = filter_var($forma['predeterminado'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $forma['activo'] = $this->booleanoONulo($forma['activo']);
            $forma['reglas'] = $this->payloadReglas($f['reglas'] ?? null);

            return $forma;
        }, array_values($formas));
    }

    // Sin la clave (o null) las reglas no se tocan; con arreglo, cada regla con los campos del modelo ContadoRegla: sin id =
    // nueva y con id = existente (la función verifica que sea de la forma de pago). El día que no viene cuenta como no marcado.
    private function payloadReglas($reglas): ?array
    {
        if (!is_array($reglas)) {
            return null;
        }

        $campos = (new ContadoRegla())->getFillable();

        return array_map(function ($r) use ($campos) {
            $regla = ['id' => isset($r['id']) && $r['id'] !== '' ? (int) $r['id'] : null];
            foreach ($campos as $campo) {
                $regla[$campo] = $r[$campo] ?? null;
            }
            $regla['activo'] = $this->booleanoONulo($regla['activo']);
            foreach (ContadoRegla::DIAS as $dia) {
                $regla[$dia] = filter_var($r[$dia] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            return $regla;
        }, array_values($reglas));
    }

    // null se queda null (la función pone el valor por defecto o conserva el actual).
    private function booleanoONulo($valor): ?bool
    {
        return ($valor === null || $valor === '') ? null : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }
}
