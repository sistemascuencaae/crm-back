<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\ActividadesFormulas;
use App\Models\crm\CondicionesFaseMover;
use App\Models\crm\CTipoResultadoCierre;
use App\Models\crm\Estados;
use App\Models\crm\Tablero;
use App\Models\crm\TableroUsuario;
use App\Models\crm\VistaMisCasos;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableroController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listAllTablerosWithFases()
    {
        $log = new Funciones();
        try {
            $tableros = Tablero::where('estado', true)->with('fase', 'estados')->orderBy('id', 'desc')->get();

            $log->logInfo(TableroController::class, 'Se listo con exito todos los tableros con sus fases');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar todos los tableros con sus fases', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function usuariosTablero($tabId)
    {
        try {
            $usuariosTablero = DB::select("SELECT u.* from crm.tablero_user tu
            left join crm.users u on u.id = tu.user_id
            where tu.tab_id = ?", [$tabId]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $usuariosTablero));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listByTablerosIdWithFases($tab_id)
    {
        $log = new Funciones();
        try {
            $tablero = Tablero::where('id', $tab_id)->where('estado', true)->with('fase.respuestas')->first();

            $log->logInfo(TableroController::class, 'Se listo con exito el tablero con el tab_id: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tablero));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar el tablero con el tab_id: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    //LISTA DE TODOS LOS TABLEROS
    public function listAll()
    {
        $log = new Funciones();
        try {
            $tableros = Tablero::with('tableroUsuario')->orderBy('id', 'desc')->get();

            $log->logInfo(TableroController::class, 'Se listo con exito todos los tableros');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar todos los tableros', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // start para superUsuario
    public function listAllTablerosActivos()
    {
        $log = new Funciones();
        try {
            $tableros = Tablero::with('tableroUsuario.usuario.departamento')->where('estado', true)->orderBy("id", "desc")->get();

            $log->logInfo(TableroController::class, 'Se listo con exito todos los tableros activos');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar todos los tableros activos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listAllTablerosInactivos()
    {
        $log = new Funciones();
        try {
            $tableros = Tablero::with('tableroUsuario.usuario.departamento')->where('estado', false)->orderBy("id", "desc")->get();

            $log->logInfo(TableroController::class, 'Se listo con exito todos los tableros inactivos');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar todos los tableros inactivos', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
    // end para superUsuario

    public function listTableroByUser($user_id)
    {
        $log = new Funciones();
        try {
            // $tableros = Tablero::where("tableroUsuario", $user_id)->with('tableroUsuario.usuario.departamento')->where('estado', true)->orderBy("id", "desc")->get();
            $tableros = Tablero::whereHas('tableroUsuario', function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            })->with('tableroUsuario.usuario.departamento')->where('estado', true)->orderBy('id', 'desc')->get();

            $log->logInfo(TableroController::class, 'Se listo con exito los tableros por user_id: ' . $user_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tableros));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar los tableros por user_id: ' . $user_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addTablero(Request $request)
    {
        $log = new Funciones();
        try {
            $tab = $request->all();

            $t = DB::transaction(function () use ($tab) {
                $tablero = Tablero::create($tab);
                for ($i = 0; $i < sizeof($tab['usuarios']); $i++) {
                    DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id, permisos) values (?, ?, ?)', [$tab['usuarios'][$i]['id'], $tablero['id'], $tab['usuarios'][$i]['permisos']]);
                }

                $condicion = CondicionesFaseMover::create([
                    "parametro" => '[]',
                ]);

                Estados::create([
                    "nombre" => 'PENDIENTE',
                    "estado" => true,
                    "tab_id" => $tablero->id,
                    "tipo_estado_id" => 1
                ]);

                Estados::create([
                    "nombre" => 'TERMINADO',
                    "estado" => true,
                    "tab_id" => $tablero->id,
                    "tipo_estado_id" => 1
                ]);

                DB::insert("INSERT INTO crm.fase
                (tab_id, nombre, descripcion, estado, orden, created_at, updated_at, generar_caso, color_id, fase_tipo, cnd_mover_id)
                VALUES(?, 'BANDEJA DE ENTRADA', 'SE CARGARAN TODOS LOS CASOS SIN ASIGNAR', true, 1, ?, ?, true, 22, 1, ?);", [$tablero->id, $tablero->created_at, $tablero->updated_at, $condicion->id]);

                DB::insert("INSERT INTO crm.users
                (name, estado, surname, usu_alias, email,
                password, created_at, updated_at, phone, fecha_nacimiento,
                address, usu_tipo_analista, dep_id, usu_tipo, tab_id, en_linea)
                VALUES('USUARIO GENERAL {$tablero->nombre} {$tablero->id}', true, 'USUARIO GENERAL {$tablero->nombre} {$tablero->id}', 'USUARIOGENERAL{$tablero->id}', 'usuariogeneral{$tablero->id}@gmail.com',
                '123456', '{$tablero->created_at}', '{$tablero->updated_at}', '9999999999', '{$tablero->created_at}',
                'USUARIO GENERAL', NULL, $tablero->dep_id, 1, $tablero->id,true);");

                // Insert de resultados de la Actividad cuando se crea el tablero Iniciado , Cerrado , Cerrado y Reagendado

                // DB::insert("INSERT INTO crm.ctipo_resultado_cierre
                // (estado, nombre, created_at, updated_at, tab_id)
                // VALUES(true, 'Iniciado', ?, ?, ?);", [$tablero->created_at, $tablero->updated_at, $tablero->id]);

                // DB::insert("INSERT INTO crm.ctipo_resultado_cierre
                // (estado, nombre, created_at, updated_at, tab_id)
                // VALUES(true, 'Cerrado', ?, ?, ?);", [$tablero->created_at, $tablero->updated_at, $tablero->id]);

                // DB::insert("INSERT INTO crm.ctipo_resultado_cierre
                // (estado, nombre, created_at, updated_at, tab_id)
                // VALUES(true, 'Cerrado y Reagendado', ?, ?, ?);", [$tablero->created_at, $tablero->updated_at, $tablero->id]);

                CTipoResultadoCierre::create([
                    "estado" => true,
                    "nombre" => 'Iniciado',
                    "tab_id" => $tablero->id
                ]);

                CTipoResultadoCierre::create([
                    "estado" => true,
                    "nombre" => 'Cerrado',
                    "tab_id" => $tablero->id
                ]);

                CTipoResultadoCierre::create([
                    "estado" => true,
                    "nombre" => 'Cerrado y Reagendado',
                    "tab_id" => $tablero->id
                ]);

                // CONSULTAS actividades
                $resultadoIniciado = CTipoResultadoCierre::where('tab_id', $tablero->id)->where('nombre', 'Iniciado')->first();
                $resultadoCerrado = CTipoResultadoCierre::where('tab_id', $tablero->id)->where('nombre', 'Cerrado')->first();
                $resultadoCerradoReagendado = CTipoResultadoCierre::where('tab_id', $tablero->id)->where('nombre', 'Cerrado y Reagendado')->first();

                // Insert de actividades_Formulas cuando se crea el tablero

                // // Iniciado , Cerrado , Cerrado
                // DB::insert("INSERT INTO crm.actividades_formulas
                // (tab_id, result_id_actual, result_id, result_id_proximo, created_at, updated_at)
                // VALUES(?, ?, ?, ?, ?, ?);",
                //     [$tablero->id, $resultadoIniciado->id, $resultadoCerrado->id, $resultadoCerrado->id, $tablero->created_at, $tablero->updated_at]
                // );

                // // Iniciado , Cerrado y Reagendado, Cerrado y Reagendado
                // DB::insert("INSERT INTO crm.actividades_formulas
                // (tab_id, result_id_actual, result_id, result_id_proximo, created_at, updated_at)
                // VALUES(?, ?, ?, ?, ?, ?);",
                //     [$tablero->id, $resultadoIniciado->id, $resultadoCerradoReagendado->id, $resultadoCerradoReagendado->id, $tablero->created_at, $tablero->updated_at]
                // );

                ActividadesFormulas::create([
                    "tab_id" => $tablero->id,
                    "result_id_actual" => $resultadoIniciado->id,
                    "result_id" => $resultadoCerrado->id,
                    "result_id_proximo" => $resultadoCerrado->id,
                ]);

                ActividadesFormulas::create([
                    "tab_id" => $tablero->id,
                    "result_id_actual" => $resultadoIniciado->id,
                    "result_id" => $resultadoCerradoReagendado->id,
                    "result_id_proximo" => $resultadoCerradoReagendado->id,
                ]);

                $usuGeneral = DB::select("SELECT * FROM crm.users WHERE name = 'USUARIO GENERAL {$tablero->nombre} {$tablero->id}'");

                DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id, permisos) values (?, ?, ?)', [$usuGeneral[0]->id, $tablero->id, true]);

                return $tablero;
            });

            $log->logInfo(TableroController::class, 'Se guardo con exito el tablero con el ID: ' . $t->id);

            // $dataRe = Tablero::with('tableroUsuario.usuario.departamento')->where('id', $t->id)->first();

            // Obtener el tablero con los usuarios y sus permisos
            $dataRe = Tablero::with([
                'tableroUsuario.usuario' => function ($query) {
                    $query->leftJoin('crm.tablero_user', 'users.id', '=', 'tablero_user.user_id');
                },
                'tableroUsuario.usuario.departamento'
            ])->where('id', $t->id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $dataRe));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al guardar un tablero', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editTablero(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $eliminados = $request->input('eliminados');
            $usuarios = $request->input('usuarios');
            $tablero = $request->all();

            $tab = DB::transaction(function () use ($tablero, $id, $eliminados, $usuarios) {
                // Actualiza la información del tablero
                Tablero::where('id', $id)
                    ->update([
                        'dep_id' => $tablero['dep_id'],
                        'nombre' => $tablero['nombre'],
                        'descripcion' => $tablero['descripcion'],
                        'estado' => $tablero['estado'],
                    ]);

                // Elimina usuarios según la información proporcionada
                for ($i = 0; $i < sizeof($eliminados); $i++) {
                    if ($id && $eliminados[$i]['id']) {
                        DB::delete("DELETE FROM crm.tablero_user WHERE tab_id = " . $id . " and user_id = " . $eliminados[$i]['id']);
                    }
                }

                // Agrega usuarios según la información proporcionada
                for ($i = 0; $i < sizeof($usuarios); $i++) {

                    DB::update(
                        "UPDATE crm.tablero_user SET user_id = ?, tab_id = ?, permisos = ?
                    WHERE tab_id = ? AND user_id = ?",
                        [
                            $usuarios[$i]['id'],
                            $id,
                            $usuarios[$i]['permisos'],
                            $id,
                            $usuarios[$i]['id']
                        ]
                    );


                    $tabl = TableroUsuario::where('tab_id', $id)->where('user_id', $usuarios[$i])->first();
                    if (!$tabl) {
                        DB::insert('INSERT INTO crm.tablero_user (user_id, tab_id, permisos) values (?, ?, ?)', [$usuarios[$i]['id'], $id, $usuarios[$i]['permisos']]);
                    }
                }

                return $tablero;
            });

            $log->logInfo(TableroController::class, 'Se actualizo con exito el tablero con el ID: ' . $id);

            // $dataRe = Tablero::with('tableroUsuario.usuario.departamento')->where('id', $tab['id'])->first();

            // Obtener el tablero con los usuarios y sus permisos
            $dataRe = Tablero::with([
                'tableroUsuario.usuario' => function ($query) {
                    $query->leftJoin('crm.tablero_user', 'users.id', '=', 'tablero_user.user_id');
                },
                'tableroUsuario.usuario.departamento'
            ])->where('id', $tab['id'])->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $dataRe));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al actualizar el tablero: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar el tablero', $e->getMessage()));
        }
    }

    public function listTableroMisCasos($user_id)
    {
        $log = new Funciones();
        try {
            $data = VistaMisCasos::where('id_usuario_miembro', $user_id)
            ->with([
                'miembros.usuario.departamento',
                'estadodos'
            ])->get();

            $log->logInfo(TableroController::class, 'Se listo con exito los casos para el tablero mis casos, con el user_id: ' . $user_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar los casos para el tablero mis casos, con el user_id: ' . $user_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editMiembrosByTableroId($id)
    {
        $log = new Funciones();
        try {
            $tablero = Tablero::where('id', $id)->with('tableroUsuario.usuario.departamento')->first();

            $log->logInfo(TableroController::class, 'Se listo con exito los miembros del tablero con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tablero));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar los miembros del tablero con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listTableroByDepId($dep_id)
    {
        $log = new Funciones();
        try {
            $data = Tablero::where('dep_id', $dep_id)->where('estado', true)->with('fase')->get();

            $log->logInfo(TableroController::class, 'Se listo con exito los tableros, por departamento ID: ' . $dep_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar los tableros, por departamento ID: ' . $dep_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function permisoTableroUsuario($tab_id, $user_id)
    {
        $log = new Funciones();
        try {
            $tablero = TableroUsuario::where('tab_id', $tab_id)->where('user_id', $user_id)->first();

            $log->logInfo(TableroController::class, 'Se listo con exito el permiso del usuario ID: ' . $user_id . ' y del tablero con ID: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tablero));
        } catch (Exception $e) {
            $log->logError(TableroController::class, 'Error al listar el permiso del usuario ID: ' . $user_id . ' y del tablero con ID: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }

    }

}
