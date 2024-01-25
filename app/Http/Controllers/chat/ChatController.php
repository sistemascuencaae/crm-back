<?php

namespace App\Http\Controllers\chat;

use App\Events\ChatEvent;
use App\Events\SendMsgEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\chat\Chat;
use App\Models\chat\ChatGroup;
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

    public function listarMensajes()
    {
        try {
            $data = DB::select("SELECT DISTINCT ON (u1.id, u2.id)
    u1.name as useruno,
    u2.name as userdos,
    u1.avatar as avataru1,
    u2.avatar as avataru2,
    ch.message,
    ch.created_at
FROM crm.chat_rooms cr
LEFT JOIN crm.users u1 ON u1.id = cr.primer_user
LEFT JOIN crm.users u2 ON u2.id = cr.segundo_user
INNER JOIN crm.chats ch ON ch.chat_room_id = cr.id
ORDER BY u1.id, u2.id, created_at DESC;",);
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

    public function sendMessage(Request $request, $uniqd)
    {
        try {
            $chat = $request->all();
            $newMsg = Chat::create($chat);
            $data = ChatGroup::with('chat.chatRoom.primerUser', 'chat.chatRoom.segundoUser')->where('uniqd', $uniqd)->first();

            broadcast(new SendMsgEvent($data));
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
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
