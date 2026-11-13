<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\TarjetaPlazoDescuento;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// Cotizador > Descuentos de plazos (V09, menú DESCUENTOS-PLAZOS): el descuento fijo y las promociones (con fecha y hora) de los
// plazos de las tarjetas de cada banco de Dynamo (ventas.tarjeta_plazo_descuento). La pantalla lista las tarjetas del banco con sus
// plazos (el default, el fijo, las promociones, lo que rige hoy y la comisión que les pone el tope), guarda juntos los plazos
// cambiados de todo el banco (todo o nada) y abre la auditoría del banco. Sin eliminar: lo guardado se desactiva. La lista de
// bancos es la de Tarjetas y comisiones (TarjetaBancoController@listTarjetaBanco).
// El negocio vive en ventas.fn_tarjeta_plazo_descuento_* (PLANES/COTIZADOR/produccion_tarjetas.sql): este controller valida la
// forma, arma el jsonb con los campos del modelo TarjetaPlazoDescuento y el bloque 'auditoria', y devuelve tal cual el
// {success, message} de la función. Viene de ventas/TarjetasController (Fase 8 del plan del cotizador, 11-nov-2026), con los
// mismos mensajes y las mismas funciones SQL: el guardado es fn_tarjeta_plazo_descuento_guardar_lote (antes _guardar) y la
// auditoría tiene sus propias funciones (antes fn_tarjeta_auditoria_* con la tabla).
class TarjetaPlazoDescuentoController extends Controller
{
    use AuditoriaForenseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listTarjetaPlazoDescuento/{ban_id} — las tarjetas del banco con sus plazos: de cada plazo su default, su
    // descuento fijo, sus promociones (la más nueva primero), el descuento que rige ahora y la comisión que le toca; y los avisos
    // del banco. Antes getDescuentosPlazos.
    public function listTarjetaPlazoDescuento($ban_id)
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_tarjeta_plazo_descuento_buscar(?) AS resultado', [(int) $ban_id]);

            if (!$fila || !$fila->resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'El banco no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los descuentos de los plazos', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/guardarTarjetaPlazoDescuento/{ban_id} — guarda en un solo paso (todo o nada) los descuentos de los plazos
    // cambiados del banco ({plazos: [{tarjeta_plazo_id, fijo, promociones}]}) y devuelve las tarjetas del banco como quedaron.
    // Antes guardarDescuentosPlazos.
    public function guardarTarjetaPlazoDescuento(Request $request, $ban_id)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = [
                'ban_id' => (int) $ban_id,
                'plazos' => $this->payloadPlazos($request->input('plazos')),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_tarjeta_plazo_descuento_guardar_lote(?::jsonb) AS resultado',
                [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], $respuesta['descuentos'] ?? null));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron guardar los descuentos de los plazos', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/tarjetaPlazoDescuentoAuditoria?id&pagina&tamanio&busqueda — la auditoría de UN banco (id = ban_id): quién
    // guardó primero y por última vez y el historial paginado de lo guardado en esta pantalla (la foto de los descuentos de los
    // plazos del banco). Antes tarjetasAuditoria con tabla=tarjeta_plazo_descuento.
    public function tarjetaPlazoDescuentoAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = min(max((int) $request->query('pagina', 1), 1), 1000000);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Banco no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_tarjeta_plazo_descuento_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_tarjeta_plazo_descuento_auditoria_paginacion(?, ?, ?, ?)',
                [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del banco', $e->getMessage()));
        }
    }

    // Solo los tipos (en el mismo orden de antes: el primer error es el mismo). Lo obligatorio y el negocio (con mensajes en
    // español) lo validan ventas.fn_tarjeta_plazo_descuento_validaciones y, ya escrito, ventas.fn_tarjeta_descuento_comision_error.
    private function reglasRequest(): array
    {
        return [
            'plazos' => 'nullable|array',
            'plazos.*' => 'array',
            'plazos.*.tarjeta_plazo_id' => 'nullable|integer',
            'plazos.*.fijo' => 'nullable|numeric',
            'plazos.*.promociones' => 'nullable|array',
            'plazos.*.promociones.*' => 'array',
            'plazos.*.promociones.*.id' => 'nullable|integer',
            'plazos.*.promociones.*.descuento_empresa' => 'nullable|numeric',
            'plazos.*.promociones.*.fecha_inicio' => 'nullable|string',
            'plazos.*.promociones.*.fecha_fin' => 'nullable|string',
            'plazos.*.promociones.*.activo' => 'nullable|boolean',
        ];
    }

    // Cada plazo cambiado: su id, su descuento fijo (vacío = sin fijo) y sus promociones. Sin arreglo, ninguno.
    private function payloadPlazos($plazos): array
    {
        if (!is_array($plazos)) {
            return [];
        }

        return array_map(function ($p) {
            return [
                'tarjeta_plazo_id' => $this->enteroONulo($p['tarjeta_plazo_id'] ?? null),
                'fijo' => $this->numeroONulo($p['fijo'] ?? null),
                'promociones' => $this->payloadPromociones($p['promociones'] ?? null),
            ];
        }, array_values($plazos));
    }

    // Sin la clave (o null) las promociones del plazo no se tocan; con arreglo, cada promoción con los campos del modelo
    // TarjetaPlazoDescuento: sin id = nueva y con id = existente (la función verifica que sea de ese plazo).
    private function payloadPromociones($promociones): ?array
    {
        if (!is_array($promociones)) {
            return null;
        }

        $campos = (new TarjetaPlazoDescuento())->getFillable();

        return array_map(function ($r) use ($campos) {
            $promocion = ['id' => $this->enteroONulo($r['id'] ?? null)];
            foreach ($campos as $campo) {
                $promocion[$campo] = $this->valorCampo($campo, $r[$campo] ?? null);
            }

            return $promocion;
        }, array_values($promociones));
    }

    // Cada valor como lo recibía la función antes de la Fase 8: activo como booleano (null = la función pone true) y lo demás (el
    // descuento y las fechas) tal cual, para que la función los valide con sus mensajes.
    private function valorCampo(string $campo, $valor)
    {
        switch ($campo) {
            case 'activo':
                return $this->booleanoONulo($valor);
            default:
                return $valor;
        }
    }

    private function enteroONulo($valor): ?int
    {
        return ($valor === null || trim((string) $valor) === '') ? null : (int) $valor;
    }

    // Vacío = sin descuento fijo; el número va tal cual a la función.
    private function numeroONulo($valor)
    {
        return ($valor === null || trim((string) $valor) === '') ? null : $valor;
    }

    // null se queda null (la función pone el valor por defecto).
    private function booleanoONulo($valor): ?bool
    {
        return ($valor === null || $valor === '') ? null : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }
}
