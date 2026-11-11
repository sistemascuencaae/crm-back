<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\Tarjeta;
use App\Models\ventas\cotizador\TarjetaBanco;
use App\Models\ventas\cotizador\TarjetaPlazo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// Cotizador > Tarjetas y comisiones (V08, menú TARJETAS): la comisión y el descuento predeterminado de cada banco de Dynamo
// (ventas.tarjeta_banco, llave ban_id), sus tarjetas (ventas.tarjeta) y los plazos de cada tarjeta (ventas.tarjeta_plazo), que se
// guardan juntos con un solo Guardar; la lista de bancos (la comparte Descuentos de plazos), los combos de las tarjetas (tipos y
// emisores) y la auditoría del banco. Sin eliminar: tarjetas y plazos se desactivan.
// El negocio vive en ventas.fn_tarjeta_banco_* y ventas.fn_tarjeta_* (PLANES/COTIZADOR/produccion_tarjetas.sql): este controller
// valida la forma, arma el jsonb con los campos de los modelos (TarjetaBanco, Tarjeta y TarjetaPlazo) y el bloque 'auditoria', y
// devuelve tal cual el {success, message} de la función. Viene de ventas/TarjetasController (Fase 7 del plan del cotizador,
// 11-nov-2026), con los mismos mensajes y las mismas funciones SQL; los combos, antes un solo endpoint (fn_tarjeta_catalogos_buscar,
// retirada), salen de ventas.fn_tipo_tarjeta_listar y ventas.fn_emisor_listar, y la auditoría tiene sus propias funciones.
class TarjetaBancoController extends Controller
{
    use AuditoriaForenseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listTarjetaBanco?pagina&tamanio&busqueda — los bancos activos y comerciales de public.banco, buscados
    // (nombre o ban_id exacto) y paginados, con cuántas tarjetas activas tiene cada uno. Antes listarBancosTarjetas.
    public function listTarjetaBanco(Request $request)
    {
        try {
            // Topes: tamanio 1..100 y pagina sin desbordar el OFFSET (integer) de la funcion.
            $pagina = min(max((int) $request->query('pagina', 1), 1), 1000000);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select(
                'SELECT * FROM ventas.fn_tarjeta_banco_listar_paginacion(?, ?, ?)',
                [$pagina, $tamanio, $busqueda]
            );

            foreach ($registros as $r) {
                $r->tarjetas_activas = (int) $r->tarjetas_activas;
            }

            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los bancos', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listTiposTarjetaBanco — el combo Tipo de los plazos: los tipos de tarjeta activos ({id, nombre}, por id).
    // Antes, la mitad 'tipos' de catalogosTarjetas.
    public function listTiposTarjetaBanco()
    {
        try {
            $registros = DB::select('SELECT * FROM ventas.fn_tipo_tarjeta_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los catálogos de tarjetas', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listEmisoresTarjetaBanco — el combo Emisor de las tarjetas: todos los emisores de public.emisor (con su
    // activo, su POS y su sistema de liquidación: la pantalla ofrece los activos y el que ya tiene la tarjeta). Antes, la mitad
    // 'emisores' de catalogosTarjetas.
    public function listEmisoresTarjetaBanco()
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_emisor_listar() AS resultado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los catálogos de tarjetas', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/getTarjetaBanco/{ban_id} — el banco con su comisión y su descuento, sus tarjetas con los plazos de cada una
    // y sus avisos (un descuento cerca de su comisión, el banco sin comisión). Antes getBancoTarjetas.
    public function getTarjetaBanco($ban_id)
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_tarjeta_banco_buscar(?) AS resultado, ventas.fn_tarjeta_descuento_avisos(?) AS avisos',
                [(int) $ban_id, (int) $ban_id]);

            if (!$fila || !$fila->resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'El banco no existe.', null));
            }

            $datos = json_decode($fila->resultado, true);
            $datos['avisos'] = json_decode($fila->avisos, true) ?? [];

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $datos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las tarjetas del banco', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/guardarTarjetaBanco/{ban_id} — guarda en un solo paso (todo o nada) la comisión y el descuento del banco y
    // sus tarjetas nuevas o cambiadas, cada una con sus plazos, y devuelve el banco como quedó con sus avisos. Antes
    // guardarBancoTarjetas.
    public function guardarTarjetaBanco(Request $request, $ban_id)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = ['ban_id' => (int) $ban_id];
            foreach ((new TarjetaBanco())->getFillable() as $campo) {
                $payload[$campo] = $this->valorCampo($campo, $request->input($campo));
            }
            $payload['tarjetas'] = $this->payloadTarjetas($request->input('tarjetas'));
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_tarjeta_banco_guardar(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                $datos = $respuesta['banco'] ?? [];
                $datos['avisos'] = $respuesta['avisos'] ?? [];

                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], $datos));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron guardar las tarjetas del banco', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/tarjetaBancoAuditoria?id&pagina&tamanio&busqueda — la auditoría de UN banco (id = ban_id): quién guardó
    // primero y por última vez y el historial paginado de lo guardado en esta pantalla (la foto del banco con sus tarjetas y
    // plazos; también los cambios directos en tarjeta_banco). Antes tarjetasAuditoria con tabla=tarjeta_banco.
    public function tarjetaBancoAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = min(max((int) $request->query('pagina', 1), 1), 1000000);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Banco no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_tarjeta_banco_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_tarjeta_banco_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

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
    // español) lo validan ventas.fn_tarjeta_banco_validaciones y, ya escrito, ventas.fn_tarjeta_descuento_comision_error.
    private function reglasRequest(): array
    {
        return [
            'comision_banco' => 'nullable|numeric',
            'descuento_empresa' => 'nullable|numeric',
            'tarjetas' => 'nullable|array',
            'tarjetas.*' => 'array',
            'tarjetas.*.id' => 'nullable|integer',
            'tarjetas.*.nombre' => 'nullable|string|max:255',
            'tarjetas.*.emi_id' => 'nullable|integer',
            'tarjetas.*.comision_banco' => 'nullable|numeric',
            'tarjetas.*.descuento_empresa' => 'nullable|numeric',
            'tarjetas.*.activo' => 'nullable|boolean',
            'tarjetas.*.plazos' => 'nullable|array',
            'tarjetas.*.plazos.*' => 'array',
            'tarjetas.*.plazos.*.id' => 'nullable|integer',
            'tarjetas.*.plazos.*.tipo_tarjeta_id' => 'nullable|integer',
            'tarjetas.*.plazos.*.plazo' => 'nullable|integer',
            'tarjetas.*.plazos.*.meses_gracia' => 'nullable|integer',
            'tarjetas.*.plazos.*.comision_banco' => 'nullable|numeric',
            'tarjetas.*.plazos.*.activo' => 'nullable|boolean',
        ];
    }

    // Cada tarjeta con los campos del modelo Tarjeta y sus plazos. Sin id = nueva; con id = existente (la función verifica que
    // sea de este banco). Sin arreglo, ninguna.
    private function payloadTarjetas($tarjetas): array
    {
        if (!is_array($tarjetas)) {
            return [];
        }

        $campos = (new Tarjeta())->getFillable();

        return array_map(function ($t) use ($campos) {
            $tarjeta = ['id' => $this->enteroONulo($t['id'] ?? null)];
            foreach ($campos as $campo) {
                $tarjeta[$campo] = $this->valorCampo($campo, $t[$campo] ?? null);
            }
            $tarjeta['plazos'] = $this->payloadPlazos($t['plazos'] ?? null);

            return $tarjeta;
        }, array_values($tarjetas));
    }

    // Sin la clave (o null) los plazos no se tocan; con arreglo, cada plazo con los campos del modelo TarjetaPlazo: sin id =
    // nuevo y con id = existente (la función verifica que sea de la tarjeta).
    private function payloadPlazos($plazos): ?array
    {
        if (!is_array($plazos)) {
            return null;
        }

        $campos = (new TarjetaPlazo())->getFillable();

        return array_map(function ($p) use ($campos) {
            $plazo = ['id' => $this->enteroONulo($p['id'] ?? null)];
            foreach ($campos as $campo) {
                $plazo[$campo] = $this->valorCampo($campo, $p[$campo] ?? null);
            }

            return $plazo;
        }, array_values($plazos));
    }

    // Cada valor como lo recibía la función antes de la Fase 7: el emisor y el tipo como entero (vacío = null), la comisión y el
    // descuento tal cual (vacío = null: hereda del nivel de arriba), activo como booleano (null = la función pone el valor por
    // defecto) y lo demás (el nombre, los meses y los meses de gracia) tal cual, para que la función los valide con sus mensajes.
    private function valorCampo(string $campo, $valor)
    {
        switch ($campo) {
            case 'emi_id':
            case 'tipo_tarjeta_id':
                return $this->enteroONulo($valor);
            case 'comision_banco':
            case 'descuento_empresa':
                return $this->numeroONulo($valor);
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

    // Vacío = sin valor (en tarjetas, hereda del nivel de arriba); el número va tal cual a la función.
    private function numeroONulo($valor)
    {
        return ($valor === null || trim((string) $valor) === '') ? null : $valor;
    }

    // null se queda null (la función pone el valor por defecto o conserva el actual).
    private function booleanoONulo($valor): ?bool
    {
        return ($valor === null || $valor === '') ? null : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }
}
