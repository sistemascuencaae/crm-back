<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\Galeria;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class GaleriaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addGaleria(Request $request, $caso_id)
    {
        try {
            if ($request->hasFile("imagen_file")) {
                $imagen = $request->file("imagen_file");
                $titulo = $imagen->getClientOriginalName();

                $path = Storage::disk('nas')->putFileAs($caso_id . "/galerias", $imagen, $caso_id . '-' . $titulo);

                $request->request->add(["imagen" => $path]); // Aquí obtenemos la ruta de la imagen en la que se encuentra
            }

            $galeria = Galeria::create($request->all());

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'created';
            $audit->auditable_type = Galeria::class;
            $audit->auditable_id = $galeria->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($galeria);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'addGaleria';
            $audit->save();
            // END Auditoria

            $log = new Funciones();
            $log->logInfo(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Se creo con exito la imagen en el caso #', $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $galeria));
        } catch (Exception $e) {
            $log->logError(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Error al crear la imagen en el caso #', $caso_id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function listGaleriaByCasoId($caso_id)
    // {
    //     try {
    //         $galerias = Galeria::orderBy("id", "asc")->where('caso_id', $caso_id)->get();

    //         // return response()->json([
    //         //     "imagenes" => $imagenes,]);

    //         // return response()->json([
    //         //     "imagenes" => $galerias->map(function ($galeria) {
    //         //         return [
    //         //             "id" => $galeria->id,
    //         //             "titulo" => $galeria->titulo,
    //         //             "descripcion" => $galeria->descripcion,
    //         //             // "imagen" => env("APP_URL") . "storage/app/public/" . $imagen->imagen,
    //         //             "imagen" => $galeria->imagen,
    //         //             "caso_id" => $galeria->caso_id,
    //         //             "tipo_gal_id" => $galeria->tipo_gal_id
    //         //         ];
    //         //     }),
    //         // ]);


    //         return response()->json(
    //             RespuestaApi::returnResultado(
    //                 'success',
    //                 'Se listo con éxito',
    //                 $galerias
    //                 // $galerias->map(function ($galeria) {
    //                 //     return [
    //                 //         "id" => $galeria->id,
    //                 //         "titulo" => $galeria->titulo,
    //                 //         "descripcion" => $galeria->descripcion,
    //                 //         "imagen" => $galeria->imagen,
    //                 //         "caso_id" => $galeria->caso_id,
    //                 //         "tipo_gal_id" => $galeria->tipo_gal_id,
    //                 //         "sc_id" => $galeria->sc_id,
    //                 //     ];
    //                 // })
    //             )
    //         );
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }

    // }

    public function listGaleriaByCasoId(Request $request, $caso_id)
    {
        try {
            // Recupera las galerías relacionadas con el caso_id desde la base de datos
            $galerias = Galeria::where('caso_id', $caso_id)->get();

            $log = new Funciones();
            $log->logInfo(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Se listo con exito las imagenes del caso #', $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $galerias));
        } catch (Exception $e) {
            $log->logError(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Error al listar la imagenes del caso #', $caso_id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editGaleria(Request $request, $id)
    {
        try {
            $galeria = Galeria::findOrFail($id);

            // Obtener el old_values (valor antiguo)
            $audit = new Audits();
            $valorAntiguo = $galeria;
            $audit->old_values = json_encode($valorAntiguo);

            if ($request->hasFile("imagen_file")) {
                if ($galeria->imagen) {
                    // Eliminamos la imagen anterior del disco NAS
                    Storage::disk('nas')->delete($galeria->imagen);
                }

                // Obtener el nuevo archivo de imagen y su nombre original
                $nuevaImagen = $request->file("imagen_file");
                $titulo = $nuevaImagen->getClientOriginalName();

                // Guardar la nueva imagen en el disco NAS con su nombre original
                $path = Storage::disk('nas')->putFileAs($galeria->caso_id . "/galerias", $nuevaImagen, $galeria->caso_id . '-' . $titulo);

                $request->request->add(["imagen" => $path]); // Obtener la nueva ruta de la imagen en la solicitud
            }

            $galeria->update($request->all());

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit->user_id = Auth::id();
            $audit->event = 'updated';
            $audit->auditable_type = Galeria::class;
            $audit->auditable_id = $galeria->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->new_values = json_encode($galeria);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'editGaleria';
            $audit->save();
            // END Auditoria

            $log = new Funciones();
            $log->logInfo(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Se actualizo con exito la imagen, con el ID: ', $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $galeria));
        } catch (Exception $e) {
            $log->logError(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Error al actualizar la imagen, con el ID: ', $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteGaleria(Request $request, $id)
    {
        try {
            $galeria = Galeria::findOrFail($id);
            // Obtener el old_values (valor antiguo)
            $valorAntiguo = $galeria;

            // $url = str_replace("storage", "public", $galeria->imagen); //Reemplazamos la palabra storage por public (ruta de nuestra img public/galerias/name_img)
            // Storage::delete($url); //Mandamos a borrar la foto de nuestra carpeta storage

            Storage::disk('nas')->delete($galeria->imagen); //Mandamos a borrar la foto de nuestra carpeta storage

            $galeria->delete();

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'deleted';
            $audit->auditable_type = Galeria::class;
            $audit->auditable_id = $galeria->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($valorAntiguo);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'deleteGaleria';
            $audit->save();
            // END Auditoria

            $log = new Funciones();
            $log->logInfo(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Se elimino con exito la imagen, con el ID: ', $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $galeria));
        } catch (Exception $e) {
            $log->logError(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Error al eliminar la imagen, con el ID: ', $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listGaleriaBySolicitudCreditoId(Request $request, $sc_id)
    {
        try {
            $ultimaFoto = Galeria::where('sc_id', $sc_id)->latest('id')->first();

            $log = new Funciones();
            $log->logInfo(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Se listo con exito la ultima foto de la solicitud de credito, con el ID: ', $sc_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $ultimaFoto));
        } catch (Exception $e) {
            $log->logError(GaleriaController::class, $request->fullUrl(), Auth::id(), $request->ip(), 'Error al listar la ultima imagen de la solicitud de credito, con el ID: ', $sc_id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}