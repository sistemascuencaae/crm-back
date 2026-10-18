<?php

namespace App\Http\Controllers\configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\Notes;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addNotes(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                $note = Notes::create($request->all());

                $data = Notes::orderBy('id', 'desc')->get();

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $data->map(function ($item) use ($dateFields) {
                    // $this->formatoFechaItem($item, $dateFields);
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listNotes()
    {
        try {
            $notes = Notes::orderBy("id", "desc")->get();

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $notes->map(function ($item) use ($dateFields) {
                // $this->formatoFechaItem($item, $dateFields);
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notes));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editNotes(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $nota = Notes::findOrFail($id);

                $nota->update($request->all());

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                $funciones = new Funciones();
                $funciones->formatoFechaItem($nota, $dateFields);

                return $nota;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteNotes(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {

                $nota = Notes::findOrFail($id);

                $nota->delete();

                return $nota;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}