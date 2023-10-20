<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class MenuController extends Controller
{
    use FormatResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function list()
    {
        $data = Menu::all();

        return response() ->json([
            'code' => 200,
            'status'=> 'success',
            'data' => $data
        ]);
    }

    public function findById($id){
        $entity = Menu::find($id);
        if (is_object($entity)) {
            $data = array(
                'code' => 200,
                'status' => 'success',
                'family' => $entity,
            );
        } else {
            $data = array(
                'code' => 404,
                'status' => 'error',
                'message' => 'Error: Familia no existe',
            );
        }
        return response()->json($data, $data['code']);
    }





}
