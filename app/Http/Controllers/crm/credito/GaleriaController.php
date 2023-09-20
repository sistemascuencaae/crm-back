<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\Galeria;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GaleriaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addGaleria(Request $request)
    {
        try {
            if ($request->hasFile("imagen_file")) {
                $path = Storage::putFile("galerias", $request->file("imagen_file")); //se va a guardar dentro de la CARPETA CATEGORIAS
                $request->request->add(["imagen" => $path]); //Aqui obtenemos la ruta de la imagen en la que se encuentra
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

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $galeria));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listGaleriaByCasoId($caso_id)
    {
        try {
            $galerias = Galeria::orderBy("id", "asc")->where('caso_id', $caso_id)->get();

            // return response()->json([
            //     "imagenes" => $imagenes,]);

            // return response()->json([
            //     "imagenes" => $galerias->map(function ($galeria) {
            //         return [
            //             "id" => $galeria->id,
            //             "titulo" => $galeria->titulo,
            //             "descripcion" => $galeria->descripcion,
            //             // "imagen" => env("APP_URL") . "storage/app/public/" . $imagen->imagen,
            //             "imagen" => $galeria->imagen,
            //             "caso_id" => $galeria->caso_id,
            //             "tipo_gal_id" => $galeria->tipo_gal_id
            //         ];
            //     }),
            // ]);


            return response()->json(
                RespuestaApi::returnResultado(
                    'success',
                    'Se listo con éxito',
                    $galerias
                    // $galerias->map(function ($galeria) {
                    //     return [
                    //         "id" => $galeria->id,
                    //         "titulo" => $galeria->titulo,
                    //         "descripcion" => $galeria->descripcion,
                    //         "imagen" => $galeria->imagen,
                    //         "caso_id" => $galeria->caso_id,
                    //         "tipo_gal_id" => $galeria->tipo_gal_id,
                    //         "sc_id" => $galeria->sc_id,
                    //     ];
                    // })
                )
            );
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }

    }

    public function updateGaleria(Request $request, $id)
    {
        try {
            $galeria = Galeria::findOrFail($id);

            // Obtener el old_values (valor antiguo)
            $audit = new Audits();
            $valorAntiguo = $galeria;
            $audit->old_values = json_encode($valorAntiguo);

            if ($request->hasFile("imagen_file")) {
                if ($galeria->imagen) { //Aqui eliminamos la imagen anterior
                    Storage::delete($galeria->imagen); //Aqui pasa la rta de la imagen para eliminarlo
                }
                $path = Storage::putFile("galerias", $request->file("imagen_file")); //se va a guardar dentro de la CARPETA CATEGORIAS
                $request->request->add(["imagen" => $path]); //Aqui obtenemos la nueva ruta de la imagen al request
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

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $galeria));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteGaleria(Request $request, $id)
    {
        try {
            $galeria = Galeria::findOrFail($id);
            // Obtener el old_values (valor antiguo)
            $valorAntiguo = $galeria;

            $url = str_replace("storage", "public", $galeria->imagen); //Reemplazamos la palabra storage por public (ruta de nuestra img public/galerias/name_img)
            Storage::delete($url); //Mandamos a borrar la foto de nuestra carpeta storage

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

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $galeria));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listGaleriaBySolicitudCreditoId($sc_id)
    {
        try {
            $ultimaFoto = Galeria::where('sc_id', $sc_id)->latest('id')->first();

            return response()->json(
                RespuestaApi::returnResultado(
                    'success',
                    'Se listo con éxito',
                    $ultimaFoto
                )
            );
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addGaleriaEquifax(Request $request)
    {
        try {
            // Validar que se haya enviado una imagen en formato base64
            if (!$request->has("imagen_file")) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionó una imagen base64', ''));
            }

            // Obtener la imagen base64 desde la solicitud
            $imagenBase64 = $request->input("imagen_file");

            // Decodificar la imagen base64 y guardarla en el sistema de archivos
            $imagenData = base64_decode($imagenBase64);

            // Generar un nombre único para la imagen
            $nombreImagen = uniqid() . '.png'; // Puedes utilizar otro formato si es necesario

            // Guardar la imagen en la ruta especificada dentro de la carpeta "galerias" en storage
            $ruta = storage_path('app/public/galerias/' . $nombreImagen);
            file_put_contents($ruta, $imagenData);

            // Crear la entrada en la base de datos con la ruta de la imagen
            $galeria = Galeria::create([
                'caso_id' => $request->input('caso_id'),
                'titulo' => $request->input('titulo'),
                'descripcion' => $request->input('descripcion'),
                'imagen' => 'galerias/' . $nombreImagen,
                // Ruta relativa a la carpeta storage/app/public
                'tipo_galeria' => $request->input('tipo_galeria'),
                // Otros campos que puedas necesitar
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $galeria));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}