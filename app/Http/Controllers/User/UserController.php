<?php

namespace App\Http\Controllers\User;

use App\Events\TableroEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\CasoController;
use App\Http\Resources\RespuestaApi;
use App\Models\Access;
use App\Models\configuracion\UsuarioCHorario;
use App\Models\crm\Almacen;
use App\Models\crm\Tablero;
use App\Models\crm\TableroUsuario;
use App\Models\openceo\Usuario;
use App\Models\User;
use App\Models\UsuarioAlmacen;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    // Mensaje amigable para cada excepción de crm.fn_usuario_dynamo_crear_o_actualizar y crm.fn_empleado_crear_o_actualizar
    private const MENSAJES_ERROR_DYNAMO = [
        'PERFIL_SIN_MENU_DYNAMO' => 'El perfil no tiene menú de Dynamo configurado. Configúrelo o desmarque "Crear en Dynamo".',
        'REQUIERE_PUNTO_VENTA' => 'Seleccione el punto de venta de Dynamo.',
        'REQUIERE_CONTRASENA' => 'Ingrese la contraseña.',
        'REQUIERE_ALIAS' => 'Ingrese el usuario alias.',
        'ALIAS_REPETIDO_EN_DYNAMO' => 'Hay más de un usuario con ese alias en Dynamo.',
        'ALIAS_DE_OTRA_PERSONA' => 'Este usu_alias es de otra persona en Dynamo.',
        'ALIAS_EN_USO_EN_DYNAMO' => 'Ese usu_alias ya lo tiene otro usuario en Dynamo.',
        'REQUIERE_ABREVIACION' => 'Ingrese la abreviación del empleado.',
        'ABREVIACION_DUPLICADA' => 'Esa abreviación ya la tiene otro empleado en Dynamo.',
        'CEDULA_INVALIDA' => 'La cédula no es válida.',
        'REQUIERE_USUARIO_DYNAMO' => 'No se pudo crear el empleado: falta el usuario de Dynamo.',
    ];

    // public function __construct()
    // {
    // $this->middleware('auth:api', [
    //     'except' =>
    //         [
    //             'allUsers'
    //         ]
    // ]);
    // }

    public function listAnalistas($tableroId)
    {
        $data = DB::select("SELECT 
                                        u.*,
                                        (u.usu_alias || ' - ' || u.surname || ' ' || u.name || ' - ' || dep.dep_nombre) AS user_dep,
                                        COALESCE(array_to_json(array_agg(ua.alm_id)), '[]'::json) AS agencias_ids
                                    FROM crm.tablero ta
                                        INNER JOIN crm.tablero_user tu ON tu.tab_id = ta.id
                                        INNER JOIN crm.users u ON u.id = tu.user_id
                                        INNER JOIN crm.departamento dep ON dep.id = u.dep_id
                                        LEFT JOIN crm.usuario_almacen ua ON ua.user_id = u.id
                                    WHERE ta.id = ?
                                    GROUP BY u.id, u.usu_alias, u.surname, u.name, dep.dep_nombre;", [$tableroId]);

        foreach ($data as &$item) {
            // Convertir el string JSON a array
            $item->agencias_ids = json_decode($item->agencias_ids, true);

            // Si viene [null] o null, convertirlo en array vacío
            if (!$item->agencias_ids || $item->agencias_ids === [null]) {
                $item->agencias_ids = [];
            }
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios analistas', $data));
    }

    public function listUsuariosActivos()
    {
        try {
            $usuarios = User::selectRaw("*, CONCAT(usu_alias, ' - ', name, ' ', surname) as full_name")
                ->orderBy("id", "asc")
                ->where('estado', true)
                ->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia')->get();

            // mapeado mapeo
            // return response()->json(RespuestaApi::returnResultado('success', 'Lista de usuarios activos', [
            //     "usuarios" => $usuarios->map(function ($usuario) {
            //         return [
            //             "id" => $usuario->id,
            //             "name" => $usuario->name
            //         ];
            //     }),
            // ]));

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listUsuariosActivos2()
    {
        try {
            $usuarios = User::where('estado', true)->orderBy("name", "asc")->get(["id", "usu_alias", "name", "surname"]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function allUsers()
    {
        try {
            $usuarios = User::orderBy("id", "asc")->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia', 'horario.chorario', 'usuario_crea_actualiza')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // Listado paginado de usuarios (grid de list-usuario), mismo estándar que el de clientes.
    // Sin busqueda -> crm.fn_usuario_listar_paginacion | con busqueda -> crm.fn_usuario_buscar_paginacion
    public function listarUsuarios(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($busqueda !== '') {
                $registros = DB::select('SELECT * FROM crm.fn_usuario_buscar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
            } else {
                $registros = DB::select('SELECT * FROM crm.fn_usuario_listar_paginacion(?, ?)', [$pagina, $tamanio]);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Usuarios listados con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'total_activos' => (int) ($registros[0]->total_activos ?? 0),
                'total_inactivos' => (int) ($registros[0]->total_inactivos ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los usuarios', $th->getMessage()));
        }
    }

    public function addUser(Request $request)
    {
        if (!$this->tienePermisoUsuarios('create')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para crear usuarios', ''));
        }

        // usu_alias en Dynamo es varchar(12)
        if (mb_strlen(trim((string) $request->input('usu_alias'))) > 12) {
            return response()->json(RespuestaApi::returnResultado('error', 'El usu_alias puede tener máximo 12 caracteres', ''));
        }

        try {
            // Solo si el formulario lo pide (en pantalla el check sale marcado); sin el dato = solo CRM.
            $crearDynamo = $request->boolean('crear_dynamo');

            // La cédula puede repetirse (2 o 3 usuarios por persona), pero tiene que ser válida.
            $cedula = trim((string) $request->input('cedula'));
            if (!DB::selectOne('SELECT crm.fn_validar_identificacion_ecuador(?, 1) AS valida', [$cedula])->valida) {
                return response()->json(RespuestaApi::returnResultado('error', 'La cédula no es válida', ''));
            }

            // Dónde existe el usu_alias (CRM y Dynamo, sin importar mayúsculas) y si es de otra persona
            $alias = json_decode(DB::selectOne('SELECT crm.fn_usuario_alias_verificar(?, ?) AS data', [$request->input('usu_alias'), $cedula])->data);

            if ($alias->otra_persona_crm || $alias->otra_persona_dynamo) {
                $dueno = $alias->otra_persona_crm ? $alias->nombre_crm : $alias->nombre_dynamo;
                return response()->json(RespuestaApi::returnResultado('error', 'Este usu_alias es de otra persona (' . $dueno . ')', ''));
            }

            if ($alias->existe_crm && (!$crearDynamo || $alias->existe_dynamo)) {
                $mensaje = $crearDynamo ? 'Este usu_alias ya existe en el CRM y en Dynamo' : 'Este usu_alias ya existe en el CRM';
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, ''));
            }

            // CRM y Dynamo en una sola transacción: si falla uno, no se guarda nada.
            $aviso = DB::transaction(function () use ($request, $crearDynamo, $alias, $cedula) {
                $avisos = [];
                $newUserData = null;

                if (!$alias->existe_crm) {
                    // SQL para sacar solo la columna emp_id del dynamo
                    // SELECT emp.emp_id
                    // ROM entidad ent
                    // JOIN empleado emp ON ent.ent_id = emp.ent_id
                    // WHERE ent.ent_identificacion LIKE '%0102281953%';

                    $emp_id = DB::table('public.entidad')
                        ->join('public.empleado', 'entidad.ent_id', '=', 'empleado.ent_id')
                        ->where('entidad.ent_identificacion', 'LIKE', '%' . $request->cedula . '%')
                        ->pluck('empleado.emp_id')
                        ->first();

                    if ($emp_id) {
                        $request->merge(['emp_id' => $emp_id]);
                    }

                    $request->merge(['id_usu_crea_actualiza' => auth()->id()]);

                    // Si no existe, crea el nuevo usuario
                    $newUserData = User::create($request->all());

                    // if ($request->input('bod_id')) {
                    //     $bodConsigUser = DB::selectOne("SELECT
                    //     b2.bod_id as bod_add FROM crm.users u INNER JOIN public.bodega b ON b.bod_id = u.bod_id
                    //     LEFT JOIN public.bodega b2 ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                    //     WHERE b.bod_id IS NOT null and u.id = ? order by 1 asc limit 1;", [$newUserData->id]);
                    //     $newUserData->bod_id_dos = $bodConsigUser->bod_add;
                    //     $newUserData->save();
                    // }

                    if ($request->input('bod_id')) {
                        $bodConsigUser = DB::selectOne("SELECT
                        b2.bod_id as bod_add, b3.bod_id as bod_add2 FROM crm.users u INNER JOIN public.bodega b ON b.bod_id = u.bod_id
                        LEFT JOIN public.bodega b2 ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                        LEFT JOIN public.bodega b3 ON CAST(b3.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 200)
                        WHERE b.bod_id IS NOT null and u.id = ? order by 1 asc limit 1;", [$newUserData->id]);
                        $newUserData->bod_id_dos = $bodConsigUser->bod_add;
                        $newUserData->bod_id_tres = $bodConsigUser->bod_add2;
                        $newUserData->save();
                    }

                    if ($newUserData->id) {
                        UsuarioCHorario::create([
                            "user_id" => $newUserData->id,
                            "chorario_id" => 3, // 3 es el ID del horario default en la tabla crm.chorario
                        ]);

                        // Permiso a la agencia elegida, igual que la pantalla "Permisos agencias"
                        UsuarioAlmacen::firstOrCreate([
                            'alm_id' => $newUserData->alm_id,
                            'user_id' => $newUserData->id,
                        ]);

                        // Tableros marcados en el formulario; sin la lista no se toca nada.
                        if ($request->has('tableros')) {
                            $this->sincronizarTableros($newUserData->id, $request->input('tableros'), false);
                        }
                    }
                }

                if (!$crearDynamo) {
                    return $alias->existe_dynamo
                        ? 'El usu_alias ya existe en Dynamo a nombre de ' . $alias->nombre_dynamo . '; allá no se tocó nada.'
                        : null;
                }

                // Los datos de Dynamo van en el bloque 'dynamo' para que User::create no los tome.
                $dynamo = json_decode(DB::selectOne('SELECT crm.fn_usuario_dynamo_crear_o_actualizar(?::jsonb) AS data', [json_encode([
                    'profile_id'     => $request->input('profile_id'),
                    'usu_alias'      => $request->input('usu_alias'),
                    'usu_nombre'     => $request->input('name'),
                    'usu_apellido'   => $request->input('surname'),
                    'usu_contrasena' => $request->input('password'), // tal cual, Dynamo no la cifra
                    'usu_tlf'        => $request->input('phone'),
                    'cedula'         => $cedula,
                    'pve_id'         => $request->input('dynamo.pve_id'),
                    'usu_ip'         => $request->input('dynamo.usu_ip'),
                    'cti_ids'        => $request->input('dynamo.cti_ids', []),
                ])])->data);

                if ($alias->existe_crm) {
                    $avisos[] = 'El usu_alias ya existía en el CRM (' . $alias->nombre_crm . '); se creó solo en Dynamo.';
                }

                if ($dynamo->accion === 'ACTUALIZADO') {
                    $avisos[] = 'El usu_alias ya existía en Dynamo (' . $alias->nombre_dynamo . '): se actualizaron sus datos, contraseña y comprobantes.';
                }

                if (!empty($dynamo->permisos_caja_quitados)) {
                    $avisos[] = 'Se le quitaron los permisos de caja en Dynamo (el perfil no es de cajera).';
                }

                if ($dynamo->requiere_empleado) {
                    $empleado = json_decode(DB::selectOne('SELECT crm.fn_empleado_crear_o_actualizar(?::jsonb) AS data', [json_encode([
                        'usu_id'          => $dynamo->usu_id,
                        'pve_id'          => $request->input('dynamo.pve_id'),
                        'alm_id'          => $request->input('alm_id'),
                        'cedula'          => $cedula,
                        'nombres'         => $request->input('name'),
                        'apellidos'       => $request->input('surname'),
                        'email'           => $request->input('email'),
                        'telefono'        => $request->input('phone'),
                        'emp_abreviacion' => $request->input('dynamo.emp_abreviacion'),
                    ])])->data);

                    if ($empleado->accion === 'SIN_EMPLEADO') {
                        $avisos[] = 'La persona ya es empleado del usuario ' . $empleado->usu_alias_empleado . ' en Dynamo; este usuario se creó sin empleado.';
                    } elseif ($empleado->accion === 'AMARRADO') {
                        $avisos[] = 'La persona ya era empleado (' . $empleado->emp_abreviacion . '): se le amarró este usuario y se reactivó.';
                    }

                    // crm.users.emp_id: con él CasoController encuentra al usuario del vendedor.
                    if ($newUserData) {
                        $newUserData->update(['emp_id' => $empleado->emp_id]);
                    } else {
                        User::whereRaw('UPPER(TRIM(usu_alias)) = UPPER(TRIM(?))', [$request->input('usu_alias')])
                            ->whereNull('emp_id')
                            ->update(['emp_id' => $empleado->emp_id]);
                    }
                }

                return $avisos ? implode(' ', $avisos) : null;
            });

            $usuarios = User::orderBy("id", "desc")->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia', 'horario.chorario', 'usuario_crea_actualiza')->get();

            // 'aviso' va aparte para que 'data' siga siendo la lista de usuarios.
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $usuarios) + ['aviso' => $aviso]);
        } catch (QueryException $e) {
            return $this->respuestaErrorConsulta($e);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Errores de BD del alta y la edición: códigos de las funciones de Dynamo, alias repetido y texto largo.
    private function respuestaErrorConsulta(QueryException $e)
    {
        foreach (self::MENSAJES_ERROR_DYNAMO as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, ''));
            }
        }

        // 23505: el índice único ux_users_usu_alias rechazó el alias (dos guardados del mismo alias a la vez)
        if ($e->getCode() === '23505' && strpos($e->getMessage(), 'ux_users_usu_alias') !== false) {
            return response()->json(RespuestaApi::returnResultado('error', 'Este usu_alias ya existe', ''));
        }

        // 22001: un texto es más largo de lo que permite su columna
        if ($e->getCode() === '22001') {
            return response()->json(RespuestaApi::returnResultado('error', 'Algún dato es más largo de lo que permite la base de datos', $e->getMessage()));
        }

        return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
    }

    // Catálogos del formulario para Dynamo: puntos de venta, comprobantes y perfiles casados.
    public function listCatalogosUsuarioDynamo()
    {
        try {
            $data = json_decode(DB::selectOne('SELECT crm.fn_usuario_dynamo_catalogos() AS data')->data);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Dónde existe el usu_alias (CRM y Dynamo) y, con ?cedula=, si es de otra persona; lo usa el aviso del formulario.
    public function verificarAliasUsuario(Request $request, $alias)
    {
        try {
            $data = json_decode(DB::selectOne('SELECT crm.fn_usuario_alias_verificar(?, ?) AS data', [$alias, $request->query('cedula')])->data);

            return response()->json(RespuestaApi::returnResultado('success', 'Se verificó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Precarga del alta y empleado de la persona en la edición: persona de Dynamo, su empleado y los usuarios del CRM con esa cédula.
    public function buscarUsuarioPorCedula($cedula)
    {
        if (!$this->tienePermisoUsuarios('create') && !$this->tienePermisoUsuarios('edit')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para crear ni editar usuarios', ''));
        }

        try {
            $data = json_decode(DB::selectOne('SELECT crm.fn_usuario_buscar_por_cedula(?) AS data', [$cedula])->data);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Lo que el usuario tiene hoy en Dynamo (punto de venta, comprobantes y empleado), buscado solo por su alias; null si no existe allá.
    public function buscarUsuarioDynamoPorAlias($alias)
    {
        if (!$this->tienePermisoUsuarios('edit')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para editar usuarios', ''));
        }

        try {
            $data = DB::selectOne('SELECT crm.fn_usuario_dynamo_buscar_por_alias(?) AS data', [$alias])->data;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data ? json_decode($data) : null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Abreviación sugerida para el empleado de Dynamo: iniciales de ?nombres= y ?apellidos=.
    public function sugerirAbreviacionEmpleado(Request $request)
    {
        try {
            $data = DB::selectOne('SELECT crm.fn_empleado_sugerir_abreviacion(?, ?) AS abreviacion', [$request->query('nombres'), $request->query('apellidos')])->abreviacion;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // ¿Esa abreviación ya la tiene otro empleado de Dynamo? Lo usa el aviso del formulario.
    public function verificarAbreviacionEmpleado($abreviacion)
    {
        try {
            $data = json_decode(DB::selectOne('SELECT crm.fn_empleado_verificar_abreviacion(?) AS data', [$abreviacion])->data);

            return response()->json(RespuestaApi::returnResultado('success', 'Se verificó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Tableros del usuario (crm.tablero_user): da los marcados y, con $quitar, quita los desmarcados.
    // Solo tableros activos (los inactivos no se muestran ni se tocan); los permisos se dan en la pantalla del tablero.
    private function sincronizarTableros($userId, $tabIds, $quitar)
    {
        $activos = Tablero::where('estado', true)->pluck('id')->all();
        $marcados = array_intersect($activos, array_map('intval', (array) $tabIds));
        $actuales = TableroUsuario::where('user_id', $userId)->pluck('tab_id')->all();

        foreach (array_diff($marcados, $actuales) as $tabId) {
            DB::table('crm.tablero_user')->insert(['user_id' => $userId, 'tab_id' => $tabId, 'permisos' => false]);
        }

        if ($quitar) {
            DB::table('crm.tablero_user')
                ->where('user_id', $userId)
                ->whereIn('tab_id', array_diff($activos, $marcados))
                ->delete();
        }
    }

    // Mismo permiso que valida el front: crm.access del menú USUARIOS para el perfil del usuario logueado.
    private function tienePermisoUsuarios($accion)
    {
        return Access::where('profile_id', auth()->user()->profile_id)
            ->where($accion, 1)
            ->whereHas('menu', function ($query) {
                $query->where('name', 'USUARIOS');
            })
            ->exists();
    }

    public function editUser(Request $request, $user_id)
    {
        if (!$this->tienePermisoUsuarios('edit')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para editar usuarios', ''));
        }

        // usu_alias en Dynamo es varchar(12)
        $alias = trim((string) $request->input('usu_alias'));
        if (mb_strlen($alias) > 12) {
            return response()->json(RespuestaApi::returnResultado('error', 'El usu_alias puede tener máximo 12 caracteres', ''));
        }

        try {
            $usuario = User::findOrFail($user_id);

            // La cédula puede repetirse, pero tiene que ser válida: las viejas mal escritas se corrigen al editar.
            $cedula = trim((string) $request->input('cedula'));
            if (!DB::selectOne('SELECT crm.fn_validar_identificacion_ecuador(?, 1) AS valida', [$cedula])->valida) {
                return response()->json(RespuestaApi::returnResultado('error', 'La cédula no es válida', ''));
            }

            // En Dynamo se busca solo por el alias ANTERIOR: por cédula podría tomar otra cuenta de la misma persona.
            $aliasAnterior = $usuario->usu_alias;
            $aliasCambio = mb_strtoupper($alias) !== mb_strtoupper(trim((string) $aliasAnterior));
            $filaDynamo = DB::selectOne('SELECT crm.fn_usuario_dynamo_buscar_por_alias(?) AS data', [$aliasAnterior])->data;
            $dynamoActual = $filaDynamo ? json_decode($filaDynamo) : null;
            $verificacion = json_decode(DB::selectOne('SELECT crm.fn_usuario_alias_verificar(?, ?) AS data', [$alias, $cedula])->data);

            if ($aliasCambio) {
                if ($verificacion->existe_crm) {
                    return response()->json(RespuestaApi::returnResultado('error', 'El usu_alias ya está en uso por otro usuario (' . $verificacion->nombre_crm . ')', ''));
                }
                if ($verificacion->otra_persona_dynamo) {
                    return response()->json(RespuestaApi::returnResultado('error', 'Este usu_alias es de otra persona (' . $verificacion->nombre_dynamo . ')', ''));
                }
                if ($dynamoActual && $verificacion->existe_dynamo) {
                    return response()->json(RespuestaApi::returnResultado('error', self::MENSAJES_ERROR_DYNAMO['ALIAS_EN_USO_EN_DYNAMO'], ''));
                }
            }

            $activo = $request->has('estado') ? $request->boolean('estado') : (bool) $usuario->estado;
            // Si no existe en Dynamo, solo se crea allá si lo piden (check de la pantalla).
            $crearDynamo = !$dynamoActual && $request->boolean('crear_dynamo');
            $perfilCasado = DB::table('crm.perfil_parametro')->where('profile_id', $request->input('profile_id'))->exists();
            // Igual que la pantalla de tableros: al superusuario, al propio usuario y al USUARIO GENERAL no se les quitan tableros.
            $tablerosSinQuitar = (int) $usuario->usu_tipo === 3 || (int) $usuario->id === (int) auth()->id()
                || strpos((string) $usuario->name, 'USUARIO GENERAL') !== false;

            // CRM y Dynamo en una sola transacción: si falla uno, no se guarda nada.
            $aviso = DB::transaction(function () use ($request, $usuario, $user_id, $alias, $aliasAnterior, $aliasCambio, $cedula, $dynamoActual, $verificacion, $activo, $crearDynamo, $perfilCasado, $tablerosSinQuitar) {
                $almAnterior = $usuario->alm_id;

                $request->merge(['usu_alias' => $alias, 'cedula' => $cedula, 'id_usu_crea_actualiza' => auth()->id()]);

                $usuario->update($request->all());

                // if ($request->input('bod_id')) {
                //     $bodConsigUser = DB::selectOne("SELECT
                //     b2.bod_id as bod_add FROM crm.users u INNER JOIN public.bodega b ON b.bod_id = u.bod_id
                //     LEFT JOIN public.bodega b2 ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                //     WHERE b.bod_id IS NOT null and u.id = ? order by 1 asc limit 1;", [$usuario->id]);
                //     $usuario->bod_id_dos = $bodConsigUser->bod_add;
                //     $usuario->save();
                // }

                if ($request->input('bod_id')) {
                    $bodConsigUser = DB::selectOne("SELECT
                    b2.bod_id as bod_add, b3.bod_id as bod_add2 FROM crm.users u INNER JOIN public.bodega b ON b.bod_id = u.bod_id
                    LEFT JOIN public.bodega b2 ON CAST(b2.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 100)
                    LEFT JOIN public.bodega b3 ON CAST(b3.bod_codigo AS INTEGER) = (CAST(b.bod_codigo AS INTEGER) + 200)
                    WHERE b.bod_id IS NOT null and u.id = ? order by 1 asc limit 1;", [$usuario->id]);
                    $usuario->bod_id_dos = $bodConsigUser->bod_add;
                    $usuario->bod_id_tres = $bodConsigUser->bod_add2;
                    $usuario->save();
                }

                if ($usuario->id) {
                    $existe = UsuarioCHorario::where('user_id', $user_id)->exists();

                    if (!$existe) {
                        UsuarioCHorario::create([
                            "user_id" => $usuario->id,
                            "chorario_id" => 3, // 3 es el ID del horario default en la tabla crm.chorario
                        ]);
                    }
                }

                // Cambió la agencia principal: se quita el permiso de la anterior y se da el de la nueva; las demás no se tocan.
                if (trim((string) $almAnterior) !== trim((string) $usuario->alm_id)) {
                    UsuarioAlmacen::where('user_id', $usuario->id)->where('alm_id', $almAnterior)->delete();

                    if ($usuario->alm_id) {
                        UsuarioAlmacen::firstOrCreate([
                            'alm_id' => $usuario->alm_id,
                            'user_id' => $usuario->id,
                        ]);
                    }
                }

                // Tableros: solo si el formulario manda la lista (sin ella no se tocan).
                if ($request->has('tableros')) {
                    $this->sincronizarTableros($usuario->id, $request->input('tableros'), !$tablerosSinQuitar);
                }

                if (!$dynamoActual && !$crearDynamo) {
                    return $aliasCambio && $verificacion->existe_dynamo
                        ? 'El usu_alias ya existe en Dynamo a nombre de ' . $verificacion->nombre_dynamo . '; allá no se tocó nada.'
                        : null;
                }

                // Perfil sin casamiento: en Dynamo solo el activo y el alias, que es la llave.
                if ($dynamoActual && !$perfilCasado) {
                    $usuDynamo = Usuario::whereRaw('UPPER(TRIM(usu_alias)) = UPPER(TRIM(?))', [$aliasAnterior])->orderBy('usu_id')->first();
                    $usuDynamo->update(['usu_activo' => $activo] + ($aliasCambio ? ['usu_alias' => mb_strtoupper($alias)] : []));

                    if (!$activo) {
                        DB::table('public.empleado')->where('usu_id', $usuDynamo->usu_id)->update(['emp_activo' => false]);
                    }

                    return 'El perfil no tiene menú de Dynamo configurado: allá solo se actualizó el estado' . ($aliasCambio ? ' y el usu_alias.' : '.');
                }

                $avisos = [];

                // Sin el bloque 'dynamo' se conservan el punto de venta y los comprobantes que ya tiene allá.
                $conBloque = is_array($request->input('dynamo'));
                $pveId = $request->input('dynamo.pve_id') ?? ($dynamoActual->pve_id ?? null);

                $dynamo = json_decode(DB::selectOne('SELECT crm.fn_usuario_dynamo_crear_o_actualizar(?::jsonb) AS data', [json_encode([
                    'profile_id'         => $request->input('profile_id'),
                    'usu_alias'          => $alias,
                    'usu_alias_anterior' => $aliasAnterior,
                    'usu_nombre'         => $request->input('name'),
                    'usu_apellido'       => $request->input('surname'),
                    'usu_contrasena'     => $request->input('password'), // solo si la escriben; tal cual, Dynamo no la cifra
                    'usu_tlf'            => $request->input('phone'),
                    'cedula'             => $cedula,
                    'usu_activo'         => $activo,
                    'pve_id'             => $pveId,
                    'usu_ip'             => $request->input('dynamo.usu_ip'),
                    'cti_ids'            => $conBloque ? $request->input('dynamo.cti_ids', []) : ($dynamoActual->cti_ids ?? []),
                ])])->data);

                if ($crearDynamo && $dynamo->accion === 'ACTUALIZADO') {
                    $avisos[] = 'El usu_alias ya existía en Dynamo (' . $verificacion->nombre_dynamo . '): se actualizaron sus datos, contraseña y comprobantes.';
                }

                if (!empty($dynamo->permisos_caja_quitados)) {
                    $avisos[] = 'Se le quitaron los permisos de caja en Dynamo (el perfil no es de cajera).';
                }

                // El empleado se crea o reactiva solo con el usuario activo; al desactivar ya lo apagó la función anterior.
                if ($dynamo->requiere_empleado && $activo) {
                    $empleado = json_decode(DB::selectOne('SELECT crm.fn_empleado_crear_o_actualizar(?::jsonb) AS data', [json_encode([
                        'usu_id'          => $dynamo->usu_id,
                        'pve_id'          => $pveId,
                        'alm_id'          => $request->input('alm_id'),
                        'cedula'          => $cedula,
                        'nombres'         => $request->input('name'),
                        'apellidos'       => $request->input('surname'),
                        'email'           => $request->input('email'),
                        'telefono'        => $request->input('phone'),
                        'emp_abreviacion' => $request->input('dynamo.emp_abreviacion'),
                        'emp_activo'      => true,
                    ])])->data);

                    if ($empleado->accion === 'SIN_EMPLEADO') {
                        $avisos[] = 'La persona ya es empleado del usuario ' . $empleado->usu_alias_empleado . ' en Dynamo; este usuario queda sin empleado.';
                    } elseif ($empleado->accion === 'AMARRADO') {
                        $avisos[] = 'La persona ya era empleado (' . $empleado->emp_abreviacion . '): se le amarró este usuario y se reactivó.';
                    } elseif ($empleado->accion === 'CREADO') {
                        $avisos[] = 'Se le creó el empleado en Dynamo (' . $empleado->emp_abreviacion . ').';
                    }

                    // crm.users.emp_id: con él CasoController encuentra al usuario del vendedor.
                    if (!$usuario->emp_id) {
                        $usuario->update(['emp_id' => $empleado->emp_id]);
                    }
                }

                return $avisos ? implode(' ', $avisos) : null;
            });

            $data = User::where('id', $usuario->id)
                ->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia', 'horario.chorario', 'usuario_crea_actualiza')
                ->first();

            // 'aviso' va aparte, igual que en el alta.
            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $data) + ['aviso' => $aviso]);
        } catch (QueryException $e) {
            return $this->respuestaErrorConsulta($e);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // Los usuarios no se eliminan, solo se desactivan, para no perder la trazabilidad en el CRM y en Dynamo.
    public function deleteUser($user_id)
    {
        return response()->json(RespuestaApi::returnResultado('error', 'Los usuarios no se eliminan: desactívelo desde Editar.', ''));

        // try {
        //     $usuario = User::findOrFail($user_id);

        //     $usuario->delete();

        //     return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $usuario));
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        // }
    }

    public function listUsuariosByTableroId($tablero_id)
    {
        try {
            // $usuarios = User::whereHas('tablero.usuario', function ($query) use ($tablero_id) {
            $usuarios = User::whereHas('tablero', function ($query) use ($tablero_id) {
                $query->where('tab_id', $tablero_id);
            })
                ->orderBy("id", "asc")
                ->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listUsuarioById($user_id)
    {
        try {
            $usuario = User::where('id', $user_id)->with('Departamento', 'perfil_analista', 'perfil', 'almacen', 'agencia')->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuario));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listAlmacenes()
    {
        try {
            $almacenes = Almacen::where('alm_activo', true)->orderBy('alm_nombre')->get();
            $bodegas = DB::select("SELECT * FROM public.bodega WHERE bod_activo = true
                        AND bod_nombre NOT LIKE '%CONSIG%';");

            $data = (object)[
                'almacenes' => $almacenes,
                'bodegas' => $bodegas
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editEnLineaUser(Request $request, $user_id)
    {
        try {
            $usuario = $request->all();

            $data = DB::transaction(function () use ($usuario, $user_id, $request) {

                $usuario = User::findOrFail($user_id);

                $usuario->update([
                    "en_linea" => $request->en_linea,
                ]);

                // // buscar todos mis casos en los que el usuario este
                // $query = DB::select(
                //     'SELECT caso.id FROM crm.users usuario 
                //             JOIN crm.caso caso 
                //                 ON caso.user_id = usuario.id
                //             WHERE usuario.id = ?',
                //     [$usuario->id]
                // );
                // foreach ($query as $key => $value) {
                //     $caso = new CasoController();
                //     $data = $caso->getCaso($value->id);
                //     broadcast(new TableroEvent($data));
                // }

                return User::where('id', $usuario->id)->first();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listUsers()
    {
        try {
            $usuarios = User::where('usu_alias', 'not like', 'USUARIOGENERAL%')
                ->orderBy("name", "asc")
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuarios));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listAlmacenes2()
    {
        try {
            $data = Almacen::where('alm_activo', true)->orderBy('alm_nombre')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
