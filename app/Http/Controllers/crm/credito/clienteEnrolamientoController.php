<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\credito\ClienteEnrolamiento;
use App\Models\crm\Galeria;
use App\Models\crm\RequerimientoCaso;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClienteEnrolamientoController extends Controller
{

    public function addClienteEnrolamiento(Request $request)
    {
        //echo ('$request: '.json_encode($request->input('statusEnrol')));
        //try {

        if (!$request->has('casoId')) {
            return response()->json(RespuestaApi::returnResultado('error', 'El número de caso no existe', ''));
        }


        $clienteEnrolado = ClienteEnrolamiento::where('caso_id', $request->input('casoId'))->first();
        if ($clienteEnrolado) {
            return response()->json(RespuestaApi::returnResultado('success', 'Cliente enrolado.', $clienteEnrolado));
        }
        //echo 'estamos qui';
        $estatusEnrolamiento = $request->input('statusEnrol');
        //echo ('$request: '.json_encode());

        // Verificar si el objeto datosEnrolamiento se ha proporcionado
        if (!$request->has('datosEnrolamiento')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionó el objeto datosEnrolamiento', ''));
        }

        // Obtener el objeto datosEnrolamiento desde la solicitud
        $datosEnrolamientoJson = $request->input('datosEnrolamiento');
        $datosEnrolamiento = json_decode($datosEnrolamientoJson, true); // Decodificar en un array asociativo


        // Verificar si el objeto contiene el campo "Images"
        if (!isset($datosEnrolamiento['Images']) || empty($datosEnrolamiento['Images'])) {
            return response()->json(RespuestaApi::returnResultado('error', 'El objeto datosEnrolamiento no contiene imágenes', ''));
        }

        // Obtener el valor de datosEnrolamiento
        $caso_id = $datosEnrolamiento['caso_id'];



        // Iterar a través de las imágenes y procesarlas
        foreach ($datosEnrolamiento['Images'] as $imagen) {

            $titulo = $imagen['ImageTypeName'];
            $descripcion = $imagen['ImageTypeName'];


            // Verificar si la imagen es un video de Liveness
            if ($imagen['ImageTypeName'] === 'Video de Liveness') {
                // Decodificar el video base64 y guardarlo en el sistema de archivos
                $videoBase64 = $imagen['Image'];
                $videoData = base64_decode($videoBase64);
                $nombreVideo = uniqid() . '.mp4';
                $ruta = Storage::disk('nas')->put($caso_id . '/galerias/' . $nombreVideo, $videoData);
                file_put_contents($ruta, $videoData);
            } else {
                // Si no es un video de Liveness, asumimos que es una imagen
                // Decodificar la imagen base64 y guardarla en el sistema de archivos
                $imagenBase64 = $imagen['Image'];
                $imagenData = base64_decode($imagenBase64);
                $nombreImagen = uniqid() . '.png'; // Puedes utilizar otro formato si es necesario
                $ruta = Storage::disk('nas')->put($caso_id . '/galerias/' . $nombreImagen, $imagenData);
                file_put_contents($ruta, $imagenData);
            }

            // Crear la entrada en la base de datos para Galeria y almacenar su ID
            Galeria::create([
                'caso_id' => $caso_id,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'imagen' => $caso_id . '/galerias/' . ($imagen['ImageTypeName'] === 'Video de Liveness' ? $nombreVideo : $nombreImagen),
                'tipo_gal_id' => 1,
                'equifax' => true,
            ]);
        }

        // Eliminar el array Images del objeto datosEnrolamiento
        unset($datosEnrolamiento['Images']);

        // Convertir otros arrays en cadenas JSON
        $datosEnrolamiento['Extras'] = json_encode($datosEnrolamiento['Extras']);
        $datosEnrolamiento['SignedDocuments'] = json_encode($datosEnrolamiento['SignedDocuments']);
        $datosEnrolamiento['Scores'] = json_encode($datosEnrolamiento['Scores']);

        // Crear ClienteEnrolamiento
        $clienteEnrolamiento = ClienteEnrolamiento::create($datosEnrolamiento);


        $this->actualizarReqCaso($request->input('reqCasoId'), $request->input('casoId'), $estatusEnrolamiento, $clienteEnrolamiento);
        return response()->json(RespuestaApi::returnResultado('success', 'Se guardaron los elementos con éxito', $clienteEnrolamiento));
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        // }
    }

    public function clienteEnroladoCasoId($casoId)
    {

        try {
            $clienteEnrolado = ClienteEnrolamiento::where('caso_id', $casoId)
                ->with([
                    'imagenes' => function ($query) {
                        $query->where('equifax', true);
                    }
                ])->first();

            if ($clienteEnrolado) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $clienteEnrolado));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Cliente no enrrolado', $casoId));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function actualizarReqCaso($reqCasoId, $casoId, $statusEnrol, $clienteEnrolamiento)
    {
        //try {
        $reqCaso = RequerimientoCaso::find($reqCasoId);
        if ($reqCaso) {
            if ($statusEnrol == 'Proceso satisfactorio') {
                $reqCaso->valor_boolean = true;
            } else {
                $reqCaso->valor_boolean = false;
            }
            //$reqCaso->marcado = $casoId;
            $reqCaso->valor_int = $clienteEnrolamiento->id;
            $reqCaso->save();
        }
    }

    public function addArchivosFirmadosEnrolamiento(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {

                // Verificar si el objeto datosEnrolamiento se ha proporcionado
                if (!$request->has('datosEnrolamiento')) {
                    return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionó el objeto datosEnrolamiento', ''));
                }

                // Obtener el objeto datosEnrolamiento desde la solicitud
                $datosEnrolamientoJson = $request->input('datosEnrolamiento');
                $datosEnrolamiento = json_decode($datosEnrolamientoJson, true); // Decodificar en un array asociativo


                // Verificar si el objeto contiene el campo "SignedDocuments"
                if (!isset($datosEnrolamiento['SignedDocuments']) || empty($datosEnrolamiento['SignedDocuments'])) {
                    return response()->json(RespuestaApi::returnResultado('error', 'El objeto datosEnrolamiento no contiene archivos firmados', ''));
                }

                // Obtener el valor de datosEnrolamiento
                $caso_id = $datosEnrolamiento['caso_id'];

                $contador = 1;
                foreach ($datosEnrolamiento['SignedDocuments'] as $archivo) {

                    // Decodificar el video base64 y guardarlo en el sistema de archivos
                    $archivoBase64 = $archivo;
                    $archivoData = base64_decode($archivoBase64);
                    //     $nombreArchivo = uniqid() . '.pdf'; //Genera un nombre ramdon
                    $nombreArchivo = 'firmado_' . $contador++ . '.pdf';

                    $titulo = $nombreArchivo;
                    $observacion = $nombreArchivo;

                    $ruta = Storage::disk('nas')->put($caso_id . '/equifax/' . $nombreArchivo, $archivoData);
                    file_put_contents($ruta, $archivoData);

                    Archivo::create([
                        "titulo" => $titulo,
                        "observacion" => $observacion,
                        "archivo" => $caso_id . '/equifax/' . $nombreArchivo,
                        "caso_id" => $caso_id,
                        "tipo" => 'equifax',
                    ]);
                }

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', ''));
            });
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // SI VALEN ESTOS METODOS, SOLO QUE YA SE UNIFICO EN UN SOLO METODO (addClienteEnrolamiento) , NO BORRAR

    // public function addClienteEnrolamiento(Request $request)
    // {
    //     try {
    //         $respuesta = ClienteEnrolamiento::create($request->all());

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $respuesta));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // public function addGaleriaEquifax(Request $request)
    // {
    //     try {
    //         // Validar que se haya enviado una imagen en formato base64
    //         if (!$request->has("imagen_file")) {
    //             return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionó una imagen base64', ''));
    //         }

    //         // Obtener la imagen base64 desde la solicitud
    //         $imagenBase64 = $request->input("imagen_file");

    //         // Decodificar la imagen base64 y guardarla en el sistema de archivos
    //         $imagenData = base64_decode($imagenBase64);

    //         // Generar un nombre único para la imagen
    //         $nombreImagen = uniqid() . '.png'; // Puedes utilizar otro formato si es necesario

    //         // Guardar la imagen en la ruta especificada dentro de la carpeta "galerias" en storage
    //         $ruta = storage_path('app/public/galerias/' . $nombreImagen);
    //         file_put_contents($ruta, $imagenData);

    //         // Crear la entrada en la base de datos con la ruta de la imagen
    //         $galeria = Galeria::create([
    //             'caso_id' => $request->input('caso_id'),
    //             'titulo' => $request->input('titulo'),
    //             'descripcion' => $request->input('descripcion'),
    //             'imagen' => 'galerias/' . $nombreImagen,
    //             // Ruta relativa a la carpeta storage/app/public
    //             'tipo_gal_id' => $request->input('tipo_gal_id'),
    //             'equifax' => $request->input('equifax'),
    //         ]);

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $galeria));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // public function addVideoEquifax(Request $request)
    // {
    //     try {
    //         // Validar que se haya enviado un video en formato base64
    //         if (!$request->has("video_file")) {
    //             return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionó un video base64', ''));
    //         }

    //         // Obtener el video base64 desde la solicitud
    //         $videoBase64 = $request->input("video_file");

    //         // Decodificar el video base64 y guardarlo en el sistema de archivos
    //         $videoData = base64_decode($videoBase64);

    //         // Generar un nombre único para el video con extensión MP4
    //         $nombreVideo = uniqid() . '.mp4';

    //         // Guardar el video en la ruta especificada dentro de la carpeta "galerias" en storage
    //         $ruta = storage_path('app/public/galerias/' . $nombreVideo);
    //         file_put_contents($ruta, $videoData);

    //         // Crear la entrada en la base de datos con la ruta del video
    //         $galeria = Galeria::create([
    //             'caso_id' => $request->input('caso_id'),
    //             'titulo' => $request->input('titulo'),
    //             'descripcion' => $request->input('descripcion'),
    //             'imagen' => 'galerias/' . $nombreVideo,
    //             // Ruta relativa a la carpeta storage/app/public
    //             'tipo_gal_id' => $request->input('tipo_gal_id'),
    //             'equifax' => $request->input('equifax'),
    //         ]);

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardó el video con éxito', $galeria));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }
}