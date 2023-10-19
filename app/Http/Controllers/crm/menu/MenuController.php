<?php

namespace App\Http\Controllers\crm\menu;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\menu\Menu;
use App\Models\crm\menu\MenuUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class MenuController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }
    // Aqui abria que listar por rol de usuario el menú (y obtener un unico menú y NO TODOS)
    //----------------------------------------------------------------


    // Aqui abria que listar por rol de usuario el menú (y obtener un unico menú y NO TODOS)
    public function listMenuUsuario(Request $request, $user_id)
    {
        try {
            $data = DB::transaction(function () use ($request, $user_id) {
                return MenuUsuario::where('user_id', $user_id)->with('menu.submenu.submenu.submenu')->get();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }



    //Lista todos los menus
    public function listMenu(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                return Menu::with('submenu.submenu')->get();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}