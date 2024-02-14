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
use App\Models\chat\ChatMensajeArchivo;
use App\Models\chat\ChatMensajeArchivos;
use App\Models\chat\ChatMensajes;
use App\Models\chat\ChatMiembrosGrupo;
use App\Models\chat\ChatRoom;
use App\Models\crm\Archivo;
use App\Models\crm\Galeria;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'listConversaciones', 'listarMensajes', 'getImagenesSmg'
        ]]);
    }

    public function usuariosParaChat()
    {
        try {
            $data = User::where('estado', true)->where('usu_tipo', '<>', 1)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
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
            //$dataMensajeCreado = $this->getMensaje($mensajeGuardado->id);
            $dataMensajes = $this->getMensajes($converId, $tipoConver, 15);
            $dataConverPrinc = $this->getConversacionesUser($user_id);
            broadcast(new RefreshChatConverEvent($dataConverPrinc, $user_id));
            broadcast(new EnviarMensajeEvent($dataMensajes, $converId, $tipoConver));
            if ($tipoConver === 'NORMAL') {
                $this->getConversacionesUser($userRecibeId->recibe);
            }
            if ($tipoConver === 'GRUPAL') {
                $miembros = DB::select("SELECT * FROM crm.chat_miembros_grupo WHERE chatgrupo_id = $converId");
                foreach ($miembros as $key => $value) {
                    $userMiembroId = $value->user_id;
                    $this->getConversacionesUser($userMiembroId);
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

            if ($tipoConver === 'NORMAL') {
                $userCreadorId = Auth::id();
                $currentDate = date('Y-m-d H:i:s');
                DB::update("UPDATE crm.chat_mensajes SET read_at= '$currentDate'
                WHERE id in (SELECT id from crm.chat_mensajes WHERE chatconve_id=$converId ORDER BY created_at DESC LIMIT 15)
                 and user_id <> $userCreadorId;");
            }
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
        $mensajes[] = [];
        if ($tipoConver == 'NORMAL') {
            $listaMensajes = ChatConversaciones::with(['mensajesNormal.archivosImg.img', 'mensajesNormal.archivo', 'mensajesNormal.user' => function ($query) {
                $query->select(['id', 'name', 'email']);
            }])->find($converId);
            $data = json_decode($listaMensajes, true);
            $mensajes = collect($data['mensajes_normal'])->sortByDesc('created_at')->values()->all();
        }
        if ($tipoConver == 'GRUPAL') {
            $listaMensajes = ChatGrupos::with(['mensajesGrupal.archivosImg.img', 'mensajesGrupal.archivo', 'mensajesGrupal.user' => function ($query) {
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
                    left join crm.users u1 on u1.id = cc2.user_uno_id
                    left join crm.users u2 on u2.id = cc2.user_dos_id
                    where cc2.id = cc.id limit 1) as username,
                    m.mensaje,
                    m.created_at,
                    m.read_at,
                    ROW_NUMBER() OVER (PARTITION BY m.chatconve_id ORDER BY m.created_at DESC) AS rn
                FROM crm.chat_mensajes m
                left join crm.chat_conversaciones cc on cc.id = m.chatconve_id
                left join crm.users u on u.id = m.user_id
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
                    m.read_at,
                    ROW_NUMBER() OVER (PARTITION BY m.chatgrupo_id ORDER BY m.created_at DESC) AS rn
                FROM crm.chat_mensajes m
                left JOIN crm.chat_miembros_grupo cgm ON m.chatgrupo_id = cgm.chatgrupo_id
                left JOIN crm.chat_grupos cg on cg.id = cgm.chatgrupo_id
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
                nm.created_at,
                nm.read_at
            FROM NumeredMessages nm
            WHERE nm.rn = 1 AND ? = ANY(nm.participantes);", [$userId, $userId, $userId]);

        broadcast(new RefreshChatConverEvent($data, $userId));
        return $data;
    }

    public function getConversacion($converId, $userId)
    {
        $data = DB::selectOne(" SELECT
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
                    m.created_at
                FROM crm.chat_mensajes m
                join crm.chat_conversaciones cc on cc.id = m.chatconve_id
                join crm.users u on u.id = m.user_id
                where cc.id = ? limit 1;", [$userId, $userId, $converId]);
        return $data;
    }


    public function getConversacionGrupal($grupoId)
    {
        $data = DB::selectOne("SELECT
            'GRUPAL' AS tipo_chat,
             cg.nombre_grupo AS nombre_chat,
             ARRAY(SELECT cmg2.user_id FROM crm.chat_miembros_grupo cmg2 WHERE cmg2.chatgrupo_id = cg.id) AS participantes,
             m.id AS id_mensaje,
             m.chatgrupo_id AS id_conversacion,
             m.user_id,
             cg.nombre_grupo AS username,
             m.mensaje,
             m.created_at
         FROM
             crm.chat_mensajes m
         JOIN
             crm.chat_grupos cg ON cg.id = m.chatgrupo_id
         JOIN
             crm.users u ON u.id = m.user_id
         WHERE
             cg.id = $grupoId
         LIMIT 1;");

        return $data;
    }



    public function iniciarChatNormal(Request $request)
    {
        try {
            $result = DB::transaction(function () use ($request) {
                $userCreadorId = Auth::id(); //user creador;
                $converData = $request->all();
                $newConver = ChatConversaciones::create($converData);
                $userDos = User::find($newConver->user_dos_id);
                $mensajeSaludo = ChatMensajes::create([
                    "chatconve_id" => $newConver->id,
                    "user_id" => $userCreadorId,
                    "mensaje" => 'Hola ' . ($userDos ? $userDos->name : '') . '!',
                ]);
                //notificacion usuario 1
                $this->getConversacionesUser($newConver->user_uno_id);
                //notificacion usuario 2
                $this->getConversacionesUser($newConver->user_dos_id);
                return $this->getConversacion($newConver->id, $userCreadorId);
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $result));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function iniciarChatGrupal(Request $request)
    {
        try {
            $result = DB::transaction(function () use ($request) {
                $userCreadorId = Auth::id(); //user creador;
                $nombreGrupo = $request->input('nombreGrupo');
                $dataUsuarios = $request->input('usuarios');
                $newGrupo = ChatGrupos::create([
                    "nombre_grupo" => $nombreGrupo
                ]);
                foreach ($dataUsuarios as $key => $user) {
                    $addMiembros = ChatMiembrosGrupo::create([
                        "user_id" => $user["id"],
                        "chatgrupo_id" => $newGrupo->id,
                    ]);
                }
                $newMsj = ChatMensajes::create([
                    "chatgrupo_id" => $newGrupo->id,
                    "user_id" => $userCreadorId,
                    "mensaje" => "Bienvenidos al grupo " . $newGrupo->nombre_grupo . " !"
                ]);
                broadcast(new EnviarMensajeEvent($newMsj, $newGrupo->id, 'GRUPAL'));
                $miembros = DB::select("SELECT * FROM crm.chat_miembros_grupo WHERE chatgrupo_id = $newGrupo->id");
                foreach ($miembros as $key => $value) {
                    $userMiembroId = $value->user_id;
                    $this->getConversacionesUser($userMiembroId);
                }
                return $this->getConversacionGrupal($newGrupo->id);
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $result));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar.', $th));
        }
    }

    public function addGaleriaArchivosChat(Request $request)
    {
        try {
            $converId = $request->input("converId");
            $tipoChat = $request->input("tipoChat");
            $data = DB::transaction(function () use ($request) {

                $archivos = $request->file("archivos");
                $nombreCarpeta = $request->input("nombreCarpeta");
                $converId = $request->input("converId");
                $tipoChat = $request->input("tipoChat");
                $msjDataJSON = json_decode($request->input("msg"));



                $nuevoMensajeImg = null;
                // $archivosGuardados = [];
                $contMSg = false;
                foreach ($archivos as $archivoData) {
                    // Fecha actual
                    $fechaActual = Carbon::now();
                    // Formatear la fecha en formato deseado
                    // $fechaFormateada = $fechaActual->format('Y-m-d H-i-s');
                    // Reemplazar los dos puntos por un guion medio (NO permite windows guardar con los : , por eso se le pone el - )
                    $fecha_actual = str_replace(':', '-', $fechaActual);
                    $nombreUnico = $fecha_actual . '-' . $archivoData->getClientOriginalName();
                    $extension = $archivoData->getClientOriginalExtension();
                    if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])) {
                        if ($contMSg === false) {
                            $nuevoMensajeImg = ChatMensajes::create([
                                "chatconve_id" => $msjDataJSON->chatconve_id,
                                "chatgrupo_id" => $msjDataJSON->chatgrupo_id,
                                "user_id" => $msjDataJSON->user_id,
                                "mensaje" => '',
                            ]);
                            $contMSg = true;
                        }
                        $path = Storage::disk('nas')->putFileAs("Chats/" . $nombreCarpeta . "/galerias", $archivoData, $nombreUnico); // crear una carpeta para chat

                        $nuevaImagen = Galeria::create([
                            "titulo" => 'Imagen Chat - ' . $converId, // poner el numero de chat o algo
                            "descripcion" => 'Imagen Chat - ' . $converId, // poner el numero de chat o algo
                            "imagen" => $path,
                            "tipo_gal_id" => 9, // 9 porque es tipo galeria 'tipo chat'
                            // "caso_id" => $caso_id,
                            // "sc_id" => 0,
                        ]);

                        $nuevaImagenMsg = ChatMensajeArchivos::create([
                            "mensaje_id" => $nuevoMensajeImg->id,
                            "galeria_id" => $nuevaImagen->id
                        ]);
                    } else {
                        $path = Storage::disk('nas')->putFileAs("Chats/" . $nombreCarpeta . "/archivos", $archivoData, $nombreUnico); // crear una carpeta para chat
                        $nuevoArchivo = Archivo::create([
                            "titulo" => $nombreUnico,
                            "observacion" => $msjDataJSON->mensaje ? $msjDataJSON->mensaje : 'Archivo Chat - ' . $converId, // poner el numero de chat o algo
                            "archivo" => $path,
                            "tipo" => 'Chat'
                        ]);

                        $nuevoMensajeFile = ChatMensajes::create([
                            "chatconve_id" => $msjDataJSON->chatconve_id,
                            "chatgrupo_id" => $msjDataJSON->chatgrupo_id,
                            "user_id" => $msjDataJSON->user_id,
                            "mensaje" => $archivoData->getClientOriginalName(),
                            "archivo_id" => $nuevoArchivo->id
                        ]);
                    }
                }


                if ($msjDataJSON->mensaje) {
                    $nuevoMensajeImg = ChatMensajes::create([
                        "chatconve_id" => $msjDataJSON->chatconve_id,
                        "chatgrupo_id" => $msjDataJSON->chatgrupo_id,
                        "user_id" => $msjDataJSON->user_id,
                        "mensaje" => $msjDataJSON->mensaje,
                    ]);
                }





                return (object)[
                    "nuevoMensajeImg" => $nuevoMensajeImg,
                    "converId" => $converId,
                    "tipoChat" => $tipoChat
                ];
            });
            // if($data->nuevoMensajeImg){
            //     $mensajeId = $data->nuevoMensajeImg->id;
            //     $dataNewMSj = $this->getMensaje($mensajeId);
            //     broadcast(new EnviarMensajeEvent($dataNewMSj, $data->converId, $data->tipoChat));
            // }else{
            $dataMensajes = $this->getMensajes($converId, $tipoChat, 15);
            broadcast(new EnviarMensajeEvent($dataMensajes, $data->converId, $data->tipoChat));
            //}


            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function getMensaje($msjId)
    {
        $data = ChatMensajes::with('user', 'archivosImg.img', 'archivosFile.archivos')->find($msjId);
        return $data;
    }

    public function listarMensajesNoLeidos()
    {
        try {
            $mensajesNoLeidos = DB::selectOne("SELECT count(mensaje) as cantidad  from crm.chat_mensajes cm
            where chatconve_id notnull and read_at isnull  order by 1 desc");
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $mensajesNoLeidos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}
