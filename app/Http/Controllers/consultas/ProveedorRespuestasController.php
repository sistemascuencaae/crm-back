<?php

namespace App\Http\Controllers\consultas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

// 29-oct (pedido del usuario): la pantalla de respuestas de proveedores (demo). Solo lee crm.proveedor_respuestas: cuántas
// consultas buenas y malas hubo por proveedor en un rango de fechas, la lista de cada una y su detalle (el error real que
// está detrás del "Cód. N" que ve el usuario en Nuevo Caso y en el formulario STS).
class ProveedorRespuestasController extends Controller
{
    private const TAMANIOS = [25, 50, 100];
    private const DIAS_MAXIMOS = 366;

    // Version 1.0
    // POST /api/consultas/respuestas-proveedor/resumen {desde, hasta} — por proveedor y resultado: cantidad y porcentaje
    // (crm.fn_proveedor_respuestas_resumen, días en hora de Ecuador).
    public function resumen(Request $request)
    {
        try {
            $error = $this->validarRango($request);
            if ($error !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $error, null));
            }

            $filas = DB::select('SELECT proveedor, resultado, cantidad, porcentaje FROM crm.fn_proveedor_respuestas_resumen(?::date, ?::date, NULL)', [
                $request->input('desde'),
                $request->input('hasta'),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', array_map(fn($f) => [
                'proveedor' => $f->proveedor,
                'resultado' => $f->resultado,
                'cantidad' => (int) $f->cantidad,
                'porcentaje' => (float) $f->porcentaje,
            ], $filas)));
        } catch (Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el resumen.', null));
        }
    }

    // Version 1.0
    // POST /api/consultas/respuestas-proveedor/listar {desde, hasta, proveedor?, resultado?, buscar?, pagina, tamanio} — las
    // consultas, de la más nueva a la más vieja, sin la respuesta cruda (va en el detalle). buscar = cédula/RUC o usuario.
    public function listar(Request $request)
    {
        try {
            $error = $this->validarRango($request);
            if ($error !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $error, null));
            }

            $tamanio = (int) $request->input('tamanio', 50);
            $tamanio = in_array($tamanio, self::TAMANIOS, true) ? $tamanio : 50;
            $pagina = max(1, (int) $request->input('pagina', 1));

            $consulta = $this->base()
                ->whereRaw("r.created_at >= (?::date::timestamp AT TIME ZONE 'America/Guayaquil')", [$request->input('desde')])
                ->whereRaw("r.created_at < ((?::date + 1)::timestamp AT TIME ZONE 'America/Guayaquil')", [$request->input('hasta')]);

            if ($request->filled('proveedor')) {
                $consulta->where('r.proveedor', (string) $request->input('proveedor'));
            }
            if ($request->filled('resultado')) {
                $consulta->where('r.resultado', (string) $request->input('resultado'));
            }
            $buscar = trim((string) $request->input('buscar', ''));
            if ($buscar !== '') {
                $patron = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtoupper($buscar)) . '%';
                $consulta->where(function ($q) use ($buscar, $patron) {
                    $q->where('r.identificacion', 'like', trim($buscar) . '%')
                        ->orWhereRaw("upper(coalesce(u.name, '') || ' ' || coalesce(u.surname, '')) LIKE ?", [$patron])
                        ->orWhereRaw('upper(coalesce(u.usu_alias, \'\')) LIKE ?', [$patron])
                        ->orWhereRaw('upper(coalesce(r.corredor, \'\')) LIKE ?', [$patron]);
                });
            }

            $total = (clone $consulta)->count();
            $registros = $consulta->orderByDesc('r.id')
                ->offset(($pagina - 1) * $tamanio)
                ->limit($tamanio)
                ->get()
                ->map(fn($f) => $this->fila($f));

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'registros' => $registros,
                'total' => $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las consultas.', null));
        }
    }

    // Version 1.0
    // GET /api/consultas/respuestas-proveedor/{id} — una consulta con todo: el mensaje técnico, la respuesta cruda del
    // proveedor (solo en los errores) y el cliente. Es la búsqueda por el "Cód. N" que reporta el usuario.
    public function ver($id)
    {
        try {
            if (!ctype_digit((string) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El código debe ser un número.', null));
            }

            $f = $this->base()
                ->leftJoin('public.cliente as c', 'c.cli_id', '=', 'r.cli_id')
                ->addSelect('r.respuesta', 'c.ent_nombre_comercial as cliente_nombre')
                ->where('r.id', (int) $id)
                ->first();

            if (!$f) {
                return response()->json(RespuestaApi::returnResultado('error', 'No existe una consulta con el código ' . $id . '.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $this->fila($f) + [
                'mensaje' => $f->mensaje,
                'respuesta' => $f->respuesta !== null ? json_decode($f->respuesta, true) : null,
                'cliente_nombre' => $f->cliente_nombre,
            ]));
        } catch (Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la consulta.', null));
        }
    }

    private function base()
    {
        return DB::table('crm.proveedor_respuestas as r')
            ->leftJoin('crm.users as u', 'u.id', '=', 'r.usu_id')
            ->select(
                'r.id', 'r.proveedor', 'r.canal', 'r.identificacion', 'r.cli_id', 'r.usu_id', 'r.corredor', 'r.resultado',
                'r.http_status', 'r.duracion_ms', 'r.consulta_id', 'r.mensaje',
                DB::raw("to_char(r.created_at AT TIME ZONE 'America/Guayaquil', 'YYYY-MM-DD HH24:MI:SS') as fecha"),
                DB::raw("nullif(trim(coalesce(u.name, '') || ' ' || coalesce(u.surname, '')), '') as usuario"),
                DB::raw('r.respuesta IS NOT NULL as con_respuesta')
            );
    }

    private function fila($f): array
    {
        return [
            'id' => (int) $f->id,
            'fecha' => $f->fecha,
            'proveedor' => $f->proveedor,
            'canal' => $f->canal,
            'identificacion' => $f->identificacion,
            'cli_id' => $f->cli_id !== null ? (int) $f->cli_id : null,
            'usu_id' => $f->usu_id !== null ? (int) $f->usu_id : null,
            'usuario' => $f->usuario,
            'corredor' => $f->corredor,
            'resultado' => $f->resultado,
            'http_status' => $f->http_status !== null ? (int) $f->http_status : null,
            'duracion_ms' => $f->duracion_ms !== null ? (int) $f->duracion_ms : null,
            'consulta_id' => $f->consulta_id !== null ? (int) $f->consulta_id : null,
            'con_respuesta' => (bool) $f->con_respuesta,
        ];
    }

    // Fechas obligatorias, desde <= hasta y a lo más un año.
    private function validarRango(Request $request): ?string
    {
        $validator = Validator::make($request->all(), [
            'desde' => 'required|date_format:Y-m-d',
            'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde',
        ], [
            'desde.required' => 'Elija la fecha desde.',
            'desde.date_format' => 'La fecha desde no es válida.',
            'hasta.required' => 'Elija la fecha hasta.',
            'hasta.date_format' => 'La fecha hasta no es válida.',
            'hasta.after_or_equal' => 'La fecha hasta no puede ser menor que la fecha desde.',
        ]);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        $dias = (new \DateTime($request->input('desde')))->diff(new \DateTime($request->input('hasta')))->days;

        return $dias > self::DIAS_MAXIMOS ? 'El rango de fechas no puede pasar de un año.' : null;
    }
}
