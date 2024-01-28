<?php

namespace App\Http\Controllers\chat;

use App\Events\SendMsgEvent;
use App\Events\ChatEvent;
use App\Events\RefreshChatRoomEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\chat\Chat;
use App\Models\chat\ChatGroup;
use App\Models\chat\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'list'
        ]]);
    }

    public function listChatsRooms($userId)
    {
        try {
            $data = $this->chatsUsuario($userId);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function list($uniqd)
    {
        try {
            $data = ChatGroup::with('chat.chatRoom.primerUser', 'chat.chatRoom.segundoUser')->where('uniqd', $uniqd)->first();
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function sendMessage(Request $request, $uniqd, $userId, $userDosId)
    {
        try {
            $chat = $request->all();
            $newMsg = Chat::create($chat);
            $data = DB::select("SELECT
                cr.uniqd,
                ch.user_id,
                us1.name as useruno,
                us2.name as userdos,
                ch.message,
                ch.created_at
                FROM crm.chat_groups cg
                INNER JOIN crm.chat_rooms cr ON cr.chat_group_id  = cg.id
                INNER JOIN crm.chats ch ON ch.chat_group_id = cg.id AND ch.chat_room_id = cr.id
                left join crm.users us1 on us1.id = cr.primer_user
                left join crm.users us2 on us2.id = cr.segundo_user
                WHERE cr.uniqd = ? order by ch.created_at asc", [$uniqd]);
            $chatsUserPrincipal = $this->chatsUsuario($userId);
            $chatsUserSecundario = $this->chatsUsuario($userDosId);
            broadcast(new SendMsgEvent($data, $uniqd));
            broadcast(new RefreshChatRoomEvent($chatsUserPrincipal, $userId));
            broadcast(new RefreshChatRoomEvent($chatsUserSecundario, $userDosId));
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }


    public function listarMensajes($uniqd)
    {
        try {
            $data = DB::select("SELECT
                cr.uniqd,
                COALESCE(us1.id, us2.id) as user_id,
                us1.name as useruno,
                us2.name as userdos,
                ch.message,
                ch.created_at
                FROM crm.chat_groups cg
                INNER JOIN crm.chat_rooms cr ON cr.chat_group_id  = cg.id
                INNER JOIN crm.chats ch ON ch.chat_group_id = cg.id AND ch.chat_room_id = cr.id
                left join crm.users us1 on us1.id = cr.primer_user
                left join crm.users us2 on us2.id = cr.segundo_user
                WHERE cr.uniqd = ? order by ch.created_at asc", [$uniqd]);
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }


    public function chatsUsuario($userId)
    {
        $data = DB::select("SELECT DISTINCT ON (cr.uniqd)
    ch.user_id AS escrito_user_id,
    ch.message,
    ch.created_at,
    cr.primer_user as user_uno_id,
    cr.segundo_user as user_dos_id,
    cg.uniqd as chatskey,
    u1.name as username_uno,
    u2.name as username_dos,
    cr.uniqd as roomkey,
    u2.avatar,
    cr.id as chat_room_id,
    cg.id as chat_group_id
FROM crm.chats ch
INNER JOIN crm.chat_groups cg ON cg.id = ch.chat_group_id
INNER JOIN crm.chat_rooms cr ON cr.id = ch.chat_room_id
INNER JOIN crm.users u1 ON u1.id = cr.primer_user
INNER JOIN crm.users u2 ON u2.id = cr.segundo_user
where cr.primer_user = ? or cr.segundo_user = ?
ORDER BY cr.uniqd, ch.created_at DESC;", [$userId, $userId]);
        return $data;
    }




    public function chatsUsuario1($userId)
    {
        $data = DB::select("SELECT
                DISTINCT ON (COALESCE(us2.name))
                cr.primer_user as user_id,
                cr.segundo_user as user_secudario_id,
                cg.uniqd as chatskey,
                us2.name as username,
                (select
                ch.message
                from crm.chat_rooms cr2
                inner join crm.chats ch on ch.chat_room_id = cr2.id
                where cr2.uniqd = cr.uniqd
                order by ch.created_at desc limit 1) as message,
                ch.created_at,
                cr.uniqd as roomkey,
                us2.avatar,
                cr.id as chat_room_id,
                cg.id as chat_group_id
                FROM crm.chat_groups cg
                left JOIN crm.chat_rooms cr ON cr.chat_group_id  = cg.id
                left JOIN crm.chats ch ON ch.chat_group_id = cg.id AND ch.chat_room_id = cr.id
                left join crm.users us1 on us1.id = cr.primer_user
                left join crm.users us2 on us2.id = cr.segundo_user
                where cr.primer_user = ? ORDER BY COALESCE(us2.name), ch.created_at desc;", [$userId]);
        return $data;
    }
}



// {
// "chats": [
// 	{
// 		"id" : 6,
// 		"chat_room_id" : 2,
// 		"chat_group_id" : 1,
// 		"message" : "hola 2 3",
// 		"galeria_id" : null,
// 		"archivo_id" : null,
// 		"read_at" : null,
// 		"created_at" : "2023-09-15T23:00:43.000Z",
// 		"updated_at" : "2023-09-15T23:00:43.000Z",
// 		"deleted_at" : null
// 	}
// ]}
