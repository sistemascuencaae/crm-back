<?php

namespace App\Http\Controllers\chat;

use App\Events\SendMsgEvent;
use App\Events\ChatEvent;
use App\Events\EnviarMensajeEvent;
use App\Events\RefreshChatConverEvent;
use App\Events\RefreshChatRoomEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\chat\Chat;
use App\Models\chat\ChatConversaciones;
use App\Models\chat\ChatGroup;
use App\Models\chat\ChatGrupos;
use App\Models\chat\ChatMensajes;
use App\Models\chat\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'listConversaciones', 'listarMensajes'
        ]]);
    }

    public function enviarMensaje(Request $request, $converId, $tipoConver)
    {
        try {
            $user_id = Auth::id();
            $userRecibeId = DB::selectOne("SELECT
                CASE
                 WHEN cc.user_uno_id = ? then cc.user_dos_id
                 WHEN cc.user_dos_id = ? THEN cc.user_uno_id
                 ELSE 0
                END AS recibe
                from crm.chat_conversaciones cc where cc.id = ?", [$user_id, $user_id, $converId]);
            $dataMensaje = $request->all();
            $mensajeGuardado = ChatMensajes::create($dataMensaje);
            $dataMensajeCreado = ChatMensajes::with('user')->find($mensajeGuardado->id);
            $dataConverPrinc = $this->getConversacionesUser($user_id);
            broadcast(new RefreshChatConverEvent($dataConverPrinc, $user_id));
            broadcast(new EnviarMensajeEvent($dataMensajeCreado, $converId, $tipoConver));
            if ($tipoConver === 'NORMAL') {
                $dataConverSecun = $this->getConversacionesUser($userRecibeId->recibe);
                broadcast(new RefreshChatConverEvent($dataConverSecun, $userRecibeId->recibe));
            }
            if ($tipoConver === 'GRUPAL') {
                $miembros = DB::select("SELECT * FROM crm.chat_miembros_grupo WHERE chatgrupo_id = $converId");
                foreach ($miembros as $key => $value) {
                    $userMiembroId = $value->user_id;
                    $dataConverSecun = $this->getConversacionesUser($userMiembroId);
                    broadcast(new RefreshChatConverEvent($dataConverSecun, $userMiembroId));
                }
            }
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', []));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al enviar mensaje.', $th));
        }
    }

    public function listarMensajes($converId, $tipoConver, $perPage)
    {
        try {
            $data = $this->getMensajes($converId, $tipoConver, $perPage);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function listConversaciones($userId)
    {
        try {
            $data = $this->getConversacionesUser($userId);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function getMensajes($converId, $tipoConver, $perPage)
    {
        $limit = 10 * $perPage;

        $mensajes[] = [];
        if ($tipoConver == 'NORMAL') {
            $listaMensajes = ChatConversaciones::with(['mensajesNormal.user' => function ($query) {
                $query->select(['id', 'name', 'email']);
            }])->find($converId);
            $data = json_decode($listaMensajes, true);
            $mensajes = collect($data['mensajes_normal'])->sortByDesc('created_at')->values()->all();
        }
        if ($tipoConver == 'GRUPAL') {
            $listaMensajes = ChatGrupos::with(['mensajesGrupal.user' => function ($query) {
                $query->select(['id', 'name', 'email']);
            }])->find($converId);
            $data = json_decode($listaMensajes, true);
            $mensajes = collect($data['mensajes_grupal'])->sortByDesc('created_at')->values()->all();
        }
        // Aplicar paginación
        $currentPage = Paginator::resolveCurrentPage('page');
        $pagedData = array_slice($mensajes, ($currentPage - 1) * $perPage, $perPage);
        $mensajesPaginados = new LengthAwarePaginator($pagedData, count($mensajes), $perPage);

        return $mensajesPaginados;
    }

    public function getConversacionesUser($userId)
    {
        $data = DB::select("WITH NumeredMessages AS (
                select
    	            'NORMAL' as tipo_chat,
                    'CHAT UNO A UNO' AS nombre_chat,
    	            ARRAY[cc.user_uno_id, cc.user_dos_id] AS participantes,
                    m.id as id_mensaje,
                    m.chatconve_id as id_conversacion,
                    m.user_id,
                    (select
                        CASE
                         WHEN cc2.user_uno_id = ? THEN u2.name
                         WHEN cc2.user_dos_id = ? THEN u1.name
                         ELSE 'Desconocido'
                        END AS remitente
                    from crm.chat_conversaciones cc2
                    inner join crm.users u1 on u1.id = cc2.user_uno_id
                    inner join crm.users u2 on u2.id = cc2.user_dos_id
                    where cc2.id = cc.id limit 1) as username,
                    m.mensaje,
                    m.created_at,
                    ROW_NUMBER() OVER (PARTITION BY m.chatconve_id ORDER BY m.created_at DESC) AS rn
                FROM crm.chat_mensajes m
                join crm.chat_conversaciones cc on cc.id = m.chatconve_id
                join crm.users u on u.id = m.user_id
                WHERE m.chatgrupo_id isnull
                UNION
                select
    	            'GRUPAL' as tipo_chat,
                    cg.nombre_grupo AS nombre_chat,
    	            ARRAY(SELECT user_id FROM crm.chat_miembros_grupo cmg WHERE cmg.chatgrupo_id = m.chatgrupo_id) AS participantes,
                    m.id as id_mensaje,
                    m.chatgrupo_id as id_conversacion,
                    m.user_id,
                    cg.nombre_grupo as username,
                    m.mensaje,
                    m.created_at,
                    ROW_NUMBER() OVER (PARTITION BY m.chatgrupo_id ORDER BY m.created_at DESC) AS rn
                FROM crm.chat_mensajes m
                JOIN crm.chat_miembros_grupo cgm ON m.chatgrupo_id = cgm.chatgrupo_id
                JOIN crm.chat_grupos cg on cg.id = cgm.chatgrupo_id
                WHERE m.chatconve_id isnull
            )
            select
	            nm.tipo_chat,
                nm.nombre_chat,
                nm.participantes,
                nm.id_mensaje,
                nm.id_conversacion,
                nm.user_id,
                nm.username,
                nm.mensaje,
                nm.created_at
            FROM NumeredMessages nm
            WHERE nm.rn = 1 AND ? = ANY(nm.participantes);", [$userId, $userId, $userId]);
        return $data;
    }
}
