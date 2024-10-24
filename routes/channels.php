<?php

use App\Models\chat\ChatConversaciones;
use App\Models\chat\ChatGrupos;
use App\Models\crm\Caso;
use App\Models\crm\Tablero;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Broadcast::channel('backoffice-activity', function () {
//     return Auth::check();
// });


Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});




Broadcast::channel('caso.comentarios.{id}', function ($user, $id) {

    $usuario = User::where("id", $user->id)->get();
    $comenTarea = Caso::where("id", $id)->get();

    if ($comenTarea[0] && $usuario[0]) {
        return true;
    } else {
        return false;
    }
});

Broadcast::channel('tablero.{id}', function ($user, $id) {
    $usuario = User::where("id", $user->id)->get();
    $tablero = Tablero::where("id", $id)->get();
    // echo('ingresamos al chanel'.json_encode($tablero));
    if ($tablero[0] && $usuario[0]) {
        return true;
    } else {
        return false;
    }
});

Broadcast::channel('conversacion.{id}.{tipo}', function ($user, $id, $tipo) {
    $usuario = User::where("id", $user->id)->first();
    if ($tipo === 'NORMAL') {
        $conversacionNormales = ChatConversaciones::find($id);
        if ($conversacionNormales && $usuario) {
            return true;
        } else {
            return false;
        }
    }
    if ($tipo === 'GRUPAL') {
        $conversacionGrupales = ChatGrupos::find($id);
        if ($conversacionGrupales && $usuario) {
            return true;
        } else {
            return false;
        }
    }
});

Broadcast::channel('chat.refresh.conver.{id}', function ($user, $id) {
    $usuario = User::where("id", $id)->first();
    if ($usuario) {
        return true;
    } else {
        return false;
    }
});



// Broadcast::channel('chat.room.{uniqd}', function ($user, $uniqd) {
//     echo('ingresamos al channel chat.romm');
//     $chatroom = ChatRoom::where("uniqd",$uniqd)->first();
//     if($chatroom->chat_group_id){
//         return true;
//     }else{
//         return (int) $user->id === (int) $chatroom->first_user || (int) $user->id === (int) $chatroom->second_user;
//     }
// });

// Broadcast::channel('chat.refresh.room.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

// Broadcast::channel('onlineusers', function($user){
//     return [
//         "id" => $user->id,
//         "full_name" => $user->name.' '.$user->surname,
//         "email" => $user->email,
//         "avatar" => $user->avatar ? env("APP_URL")."storage/".$user->avatar : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
//     ];
// });

// Broadcast::channel('tarea.comentarios.{id}', function ($user, $id) {
//     echo(json_encode($user));
//     $comenTarea = Tarea::where("id", $id)->get();
//     return $comenTarea;

//     return Tarea::where("id", $id)->get();
// });

// Broadcast::channel('crm.1', function ($user) {
//     echo('estamos en el canal: '.json_encode($user));
//     return $user;
// });






//-----------------------------------------------------------------------------------   C R M
// Broadcast::channel('crm', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });
