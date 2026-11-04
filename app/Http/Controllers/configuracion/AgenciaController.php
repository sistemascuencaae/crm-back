<?php

namespace App\Http\Controllers\configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\Agencia;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgenciaController extends Controller
{
    // 30-oct: agregar, editar y eliminar van por crm.fn_agencia_* (validaciones, mensajes y auditoría forense en la
    // función, como Clientes Dynamo); aquí se arma el jsonb y se traduce el código que levanta la función. El grid
    // de Configuración → Agencias se lista paginado (crm.fn_agencia_listar_paginacion) y el combo de calificadores
    // sale de crm.fn_agencia_proveedores_listar. Los listados viejos de los combos (usuarios, soporte) no cambian.
    private const CAMPOS_PAYLOAD = ['codigo', 'nombre', 'estado', 'id_zona_agencia', 'consulta_proveedor', 'codigo_tienda_proveedor'];

    // Mensaje amigable para cada código que levantan crm.fn_agencia_validaciones / crm.fn_agencia_eliminar.
    private const MENSAJES_ERROR = [
        'REQUIERE_CODIGO' => 'El código es obligatorio.',
        'CODIGO_DUPLICADO' => 'Ya existe una agencia con ese código.',
        'REQUIERE_NOMBRE' => 'El nombre es obligatorio.',
        'REQUIERE_ZONA' => 'Debe seleccionar una zona.',
        'ZONA_NO_EXISTE' => 'La zona seleccionada no existe.',
        'ESTADO_INVALIDO' => 'El estado no es válido.',
        'PROVEEDOR_INVALIDO' => 'El calificador de consulta no es válido.',
        'REQUIERE_TIENDA' => 'El código de tienda es obligatorio con Yupana.',
        'AGENCIA_NO_EXISTE' => 'La agencia no existe.',
        'AGENCIA_EN_USO' => 'La agencia tiene usuarios o casos asociados: desactívela en lugar de eliminarla.',
    ];

    public function __construct() {}

    public function listAllAgencias()
    {
        try {
            $data = Agencia::selectRaw("*, (CASE WHEN crm.agencia.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
                ->with('zona_agencia')
                ->orderBy('nombre', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listAgenciasActivas()
    {
        try {
            $agencias = Agencia::where('estado', true)->with('zona_agencia')->orderBy('nombre', 'asc')->get();
            $bodegas = DB::select("SELECT * FROM public.bodega WHERE bod_activo = true
                        AND bod_nombre NOT LIKE '%CONSIG%';");

            $data = (object)[
                'almacenes' => $agencias,
                'bodegas' => $bodegas
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // 30-oct: el grid de Configuración → Agencias, paginado y con búsqueda (código, nombre, zona, calificador o
    // código de tienda). Responde {registros, total, pagina, tamanio} como el listado de Clientes Dynamo.
    public function listAgenciasPaginacion(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM crm.fn_agencia_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las agencias', $e->getMessage()));
        }
    }

    // 30-oct: los calificadores que puede elegir una agencia (codigo, nombre, usa_codigo_tienda), una sola fuente con
    // la validación de la función.
    public function listProveedoresConsulta()
    {
        try {
            $data = DB::select('SELECT * FROM crm.fn_agencia_proveedores_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los calificadores', $e->getMessage()));
        }
    }

    // QUITADO 30-oct: ahora va por crm.fn_agencia_crear (reactivar = descomentar).
    // public function addAgencia(Request $request)
    // {
    //     try {
    //         $data = DB::transaction(function () use ($request) {
    //
    //             Agencia::create($request->all());
    //
    //             $data = Agencia::selectRaw("*, (CASE WHEN crm.agencia.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
    //                 ->with('zona_agencia')
    //                 ->orderBy('nombre', 'asc')->get();
    //
    //             return $data;
    //         });
    //
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // 30-oct: crm.fn_agencia_crear valida (código, nombre, zona, calificador y código de tienda), inserta y deja la
    // auditoría forense. Devuelve la agencia guardada (crm.fn_agencia_buscar); el grid se recarga.
    public function addAgencia(Request $request)
    {
        try {
            $payload = $request->only(self::CAMPOS_PAYLOAD);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_agencia_crear(?::jsonb) AS id', [json_encode($payload)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $this->buscar((int) $resultado->id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar la agencia');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar la agencia', $e->getMessage()));
        }
    }

    // QUITADO 30-oct: ahora va por crm.fn_agencia_modificar (reactivar = descomentar).
    // public function editAgencia(Request $request, $id)
    // {
    //     try {
    //         $data = DB::transaction(function () use ($request, $id) {
    //             $agencia = Agencia::findOrFail($id);
    //
    //             $agencia->update($request->all());
    //
    //             $data = Agencia::selectRaw("*, (CASE WHEN crm.agencia.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
    //                 ->with('zona_agencia')
    //                 ->orderBy('nombre', 'asc')->get();
    //
    //             return $data;
    //         });
    //
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
    //     }
    // }

    public function editAgencia(Request $request, $id)
    {
        try {
            $payload = $request->only(self::CAMPOS_PAYLOAD);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne('SELECT crm.fn_agencia_modificar(?::jsonb) AS id', [json_encode($payload)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $this->buscar((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al actualizar la agencia');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar la agencia', $e->getMessage()));
        }
    }

    // QUITADO 30-oct: ahora va por crm.fn_agencia_eliminar (reactivar = descomentar).
    // public function deleteAgencia(Request $request, $id)
    // {
    //     try {
    //         $data = DB::transaction(function () use ($request, $id) {
    //
    //             $agencia = Agencia::findOrFail($id);
    //
    //             $agencia->delete();
    //
    //             return $agencia;
    //         });
    //
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // 30-oct: crm.fn_agencia_eliminar se niega si la agencia tiene usuarios o casos (AGENCIA_EN_USO).
    public function deleteAgencia(Request $request, $id)
    {
        try {
            $agencia = $this->buscar((int) $id);

            DB::selectOne('SELECT crm.fn_agencia_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $agencia));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar la agencia');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar la agencia', $e->getMessage()));
        }
    }

    public function listAgenciasActivas2()
    {
        try {
            $data = Agencia::where('estado', true)->with('zona_agencia')->orderBy('nombre', 'asc')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // La agencia como la devuelve crm.fn_agencia_buscar (con zona_nombre y proveedor_nombre), o null si no existe.
    private function buscar(int $id): ?array
    {
        $fila = DB::selectOne('SELECT crm.fn_agencia_buscar(?) AS datos', [$id]);

        return $fila && $fila->datos ? json_decode($fila->datos, true) : null;
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico (como ClienteController).
    private function respuestaError(QueryException $e, string $generico)
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }

    // Contexto forense que las funciones guardan en auditoria.logs_cambios (módulo AGENCIAS).
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }
}
