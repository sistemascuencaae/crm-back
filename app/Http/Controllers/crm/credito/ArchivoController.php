<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Audits;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class ArchivoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addArchivo(Request $request, $caso_id)
    {
        try {
            $file = $request->file("archivo");
            $titulo = $file->getClientOriginalName();

            // $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA archivos
            $path = Storage::disk('nas')->putFileAs($caso_id . "/archivos", $file, $titulo); // guarda en el nas con el nombre original del archivo


            $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta del archivo en la que se encuentra

            $archivo = Archivo::create([
                "titulo" => $titulo,
                "observacion" => $request->observacion,
                "archivo" => $path,
                "caso_id" => $request->caso_id
            ]);

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'created';
            $audit->auditable_type = Archivo::class;
            $audit->auditable_id = $archivo->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($archivo);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'addArchivo';
            $audit->save();
            // END Auditoria

            // $data = DB::select('select * from crm.archivos where caso_id =' . $request->caso_id);
            $archivos = Archivo::where('caso_id', $request->caso_id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $archivos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listArchivoByCasoId($caso_id)
    {
        try {
            $archivos = Archivo::orderBy("id", "desc")->where('caso_id', $caso_id)->get();

            return response()->json(
                RespuestaApi::returnResultado(
                    'success',
                    'Se listo con éxito',
                    $archivos->map(function ($archivo) {
                        return [
                            "id" => $archivo->id,
                            "titulo" => $archivo->titulo,
                            "observacion" => $archivo->observacion,
                            "tipo" => $archivo->tipo,
                            "archivo" => $archivo->archivo,
                            "caso_id" => $archivo->caso_id,
                            "created_at" => $archivo->created_at,
                            "updated_at" => $archivo->updated_at,
                        ];
                    }),
                )
            );
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editArchivo(Request $request, $id)
    {
        try {
            $archivo = Archivo::findOrFail($id);

            // Obtener el old_values (valor antiguo)
            $valorAntiguo = $archivo->observacion;

            // $file = $request->file("archivo");
            // $titulo = $file->getClientOriginalName();
            // if ($request->hasFile("archivo")) {
            //     if ($archivo->archivo) { //Aqui eliminamos la imagen anterior
            //         Storage::delete($archivo->archivo); //Aqui pasa la rta de la imagen para eliminarlo
            //     }
            //     $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA CATEGORIAS
            //     $request->request->add(["archivo" => $path]); //Aqui obtenemos la nueva ruta de la imagen al request
            // }

            $archivo->update([
                // "titulo" => $titulo,
                "observacion" => $request->observacion,
                // "archivo" => $path,
            ]);

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'updated';
            $audit->auditable_type = Archivo::class;
            $audit->auditable_id = $archivo->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode(['observacion' => $valorAntiguo]); // json_encode para convertir en string ese array
            $audit->new_values = json_encode(['observacion' => $archivo->observacion]); // json_encode para convertir en string ese array
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'editArchivo';
            $audit->save();
            // END Auditoria

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $archivo));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteArchivo(Request $request, $id)
    {
        try {
            $archivo = Archivo::findOrFail($id);
            // Obtener el old_values (valor antiguo)
            $valorAntiguo = $archivo;

            // $url = str_replace("storage", "public", $archivo->archivo); //Reemplazamos la palabra storage por public (ruta de nuestra img public/galerias/name_img)
            // Storage::delete($url); //Mandamos a borrar la foto de nuestra carpeta storage

            Storage::disk('nas')->delete($archivo->archivo); //Mandamos a borrar la foto de nuestra carpeta storage

            $archivo->delete();

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'deleted';
            $audit->auditable_type = Archivo::class;
            $audit->auditable_id = $archivo->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($valorAntiguo);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'deleteArchivo';
            $audit->save();
            // END Auditoria

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $archivo));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }

    }
}