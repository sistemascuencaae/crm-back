<?php

namespace App\Http\Controllers\crm;

use App\Events\ComentariosEvent;
use App\Events\NotificacionesCrmEvent;
use App\Http\Controllers\Controller;
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
        //$userId = $request->input('user_id');
        $caso_id = $request->input('caso_id');
        $data = DB::select('select * from crm.comentarios where caso_id = ' . $caso_id.' order by 1 desc');
        //broadcast(new ComentariosEvent($data));
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }


    public function guardarComentario(Request $request)
    {

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
        $audit->save();
        // END Auditoria

        $data = DB::select('select * from crm.comentarios where caso_id = ' . $coment->caso_id.' order by 1 desc');

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
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }


    public function getNotificacion($descripcion, $tipo, $usuarioAccion, $casoId, $userId, $faseId, $user_name_actual)
    {
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

            $data = Notificaciones::with('caso', 'caso.user', 'caso.userCreador', 'caso.entidad', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo','caso.estadodos', 'tablero', 'user_destino')
            ->where('id', $noti->id)
            ->orderBy('id', 'DESC')->first();

            //     return $data;
            // } catch (\Throwable $th) {
            //     return null;
            // }
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
