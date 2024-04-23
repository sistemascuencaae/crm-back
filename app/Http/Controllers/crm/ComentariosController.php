<?php

namespace App\Http\Controllers\crm;

use App\Events\ComentariosEvent;
use App\Events\NotificacionesCrmEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\Caso;
use App\Models\crm\Comentarios;
use App\Models\crm\Notificaciones;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ComentariosController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function listaComentarios(Request $request)
    {
        $log = new Funciones();

        //$userId = $request->input('user_id');
        $caso_id = $request->input('caso_id');

        try {
            $data = DB::select('select * from crm.comentarios where caso_id = ' . $caso_id . ' order by 1 desc');
            //broadcast(new ComentariosEvent($data));

            $log->logInfo(ComentariosController::class, 'Se listo con exito los comentarios del caso #' . $caso_id);

            return response()->json([
                "res" => 200,
                "data" => $data
            ]);

        } catch (Exception $e) {
            $log->logError(ComentariosController::class, 'Error al listar los comentarios del caso #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function guardarComentario(Request $request)
    {
        $log = new Funciones();

        try {
            $coment = Comentarios::create($request->all());

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'created';
            $audit->auditable_type = Comentarios::class;
            $audit->auditable_id = $coment->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($coment);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'addComentario';
            $audit->caso_id = $coment->caso_id;
            $audit->save();
            // END Auditoria

            $data = DB::select('select * from crm.comentarios where caso_id = ' . $coment->caso_id . ' order by 1 desc');

            $casoEnProceso = Caso::find($coment->caso_id);
            $userLogin = auth('api')->user();
            $noti = $this->getNotificacion(
                'comento el caso #',
                'Comentario',
                $userLogin->name,
                $casoEnProceso->id,
                $casoEnProceso->user_id,
                $casoEnProceso->fas_id,
                $casoEnProceso->user->name
            );

            if ($noti) {
                broadcast(new NotificacionesCrmEvent($noti));
            }

            broadcast(new ComentariosEvent($data));

            $log->logInfo(ComentariosController::class, 'Se guardo con exito el comentario');

            return response()->json([
                "res" => 200,
                "data" => $data
            ]);

        } catch (Exception $e) {
            $log->logError(ComentariosController::class, 'Error al guardar el comentario', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function getNotificacion($descripcion, $tipo, $usuarioAccion, $casoId, $userId, $faseId, $user_name_actual)
    {
        $log = new Funciones();

        try {
            $tabDepa = DB::select('SELECT t.id as tab_id, d.id as dep_id FROM crm.tablero t inner join crm.fase f on f.tab_id = t.id
            inner join crm.departamento d on d.id = t.dep_id
            where f.id = ? limit 1;', [$faseId]);

            $noti = Notificaciones::create([
                "titulo" => 'CRM NOTIFICACION',
                "descripcion" => $descripcion,
                "estado" => true,
                "color" => '#5DADE2',
                "caso_id" => $casoId,
                "dep_id" => sizeof($tabDepa) > 0 ? $tabDepa[0]->dep_id : null,
                "tipo" => $tipo,
                "usuario_accion" => $usuarioAccion,
                "usuario_destino_id" => $userId,
                "tab_id" => sizeof($tabDepa) > 0 ? $tabDepa[0]->tab_id : null,
            ]);

            $data = Notificaciones::with('caso', 'caso.user', 'caso.userCreador', 'caso.clienteCrm', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'caso.estadodos', 'tablero', 'user_destino')
                ->where('id', $noti->id)
                ->orderBy('id', 'DESC')->first();

            //     return $data;
            // } catch (\Throwable $th) {
            //     return null;
            // }

            $log->logInfo(ComentariosController::class, 'Se listo con exito la notificacion');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ComentariosController::class, 'Error al listar la notificacion', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
