<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\CasoController;
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
        if (!$request->has('casoId')) {
            return response()->json(RespuestaApi::returnResultado('error', 'El número de caso no existe', ''));
        }
        if (!$request->has('datosEnrolamiento')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionó el objeto datosEnrolamiento', ''));
        }

        $datosEnrolamiento = json_decode($request->input('datosEnrolamiento'), true);

        $caso_id = $request->input('casoId');
        $clienteEnrolado = ClienteEnrolamiento::where('caso_id', $caso_id)->first();

        if ($clienteEnrolado) {
            // Cliente ya existe, actualiza los datos
            $clienteEnrolado->fill($datosEnrolamiento);
            $clienteEnrolado->save();
            $imagenes = Galeria::where('caso_id', $caso_id)->where('equifax', true)->get();
            Galeria::where('caso_id', $caso_id)->where('equifax', true)->delete();

            //echo ('$imagenes: '.json_encode($imagenes));
            foreach ($imagenes as $img) {

                $ruta = $img['imagen'];

                if (Storage::disk('nas')->exists($ruta)) {
                    Storage::disk('nas')->delete($ruta);
                    // El archivo se ha eliminado exitosamente
                } else {
                    // El archivo no existe en el sistema de archivos "nas"
                }
            }



            // También puedes actualizar las imágenes si es necesario
            if ($request->has('datosEnrolamiento') && isset($datosEnrolamiento['Images']) && !empty($datosEnrolamiento['Images'])) {
                foreach ($datosEnrolamiento['Images'] as $imagen) {
                    $titulo = $imagen['ImageTypeName'];
                    $descripcion = $imagen['ImageTypeName'];

                    $imagenBase64 = $imagen['Image'];
                    $imagenData = base64_decode($imagenBase64);

                    if ($imagen['ImageTypeName'] === 'Video de Liveness') {
                        $nombre = $titulo . '.mp4';
                    } else {
                        $nombre = $titulo . '.png';
                    }

                    $ruta = Storage::disk('nas')->put($caso_id . '/galerias/' . $nombre, $imagenData);
                    file_put_contents($ruta, $imagenData);

                    Galeria::create([
                        'caso_id' => $caso_id,
                        'titulo' => $titulo,
                        'descripcion' => $descripcion,
                        'imagen' => $caso_id . '/galerias/' . $nombre,
                        'tipo_gal_id' => 1,
                        'equifax' => true,
                    ]);
                }
            }


            $clieEnrolado = ClienteEnrolamiento::where('id', $clienteEnrolado->id)
                ->with([
                    'imagenes' => function ($query) {
                        $query->where('equifax', true);
                    }
                ])->first();
            $casoController = new CasoController();
            $data = (object) [
                "caso" => $casoController->getCaso($caso_id),
                "clienteEnrolamiento" => $clieEnrolado
            ];



            return response()->json(RespuestaApi::returnResultado('success', 'Cliente enrolado actualizado.', $data));
        }

        $estatusEnrolamiento = $request->input('statusEnrol');



        if (!isset($datosEnrolamiento['Images']) || empty($datosEnrolamiento['Images'])) {
            return response()->json(RespuestaApi::returnResultado('error', 'El objeto datosEnrolamiento no contiene imágenes', ''));
        }

        foreach ($datosEnrolamiento['Images'] as $imagen) {
            $titulo = $imagen['ImageTypeName'];
            $descripcion = $imagen['ImageTypeName'];

            $imagenBase64 = $imagen['Image'];
            $imagenData = base64_decode($imagenBase64);

            if ($imagen['ImageTypeName'] === 'Video de Liveness') {
                $nombre = $titulo . '.mp4';
            } else {
                $nombre = $titulo . '.png';
            }

            $ruta = Storage::disk('nas')->put($caso_id . '/galerias/' . $nombre, $imagenData);
            file_put_contents($ruta, $imagenData);

            Galeria::create([
                'caso_id' => $caso_id,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'imagen' => $caso_id . '/galerias/' . $nombre,
                'tipo_gal_id' => 1,
                'equifax' => true,
            ]);
        }

        unset($datosEnrolamiento['Images']);

        $datosEnrolamiento['Extras'] = json_encode($datosEnrolamiento['Extras']);
        $datosEnrolamiento['SignedDocuments'] = json_encode($datosEnrolamiento['SignedDocuments']);
        $datosEnrolamiento['Scores'] = json_encode($datosEnrolamiento['Scores']);

        $clienteEnrolamiento = ClienteEnrolamiento::create($datosEnrolamiento);
        $clieEnrolado = ClienteEnrolamiento::where('id', $clienteEnrolamiento->id)
            ->with([
                'imagenes' => function ($query) {
                    $query->where('equifax', true);
                }
            ])->first();
        $casoController = new CasoController();
        $data = (object) [
            "caso" => $casoController->getCaso($caso_id),
            "clienteEnrolamiento" => $clieEnrolado
        ];

        $this->actualizarReqCaso($request->input('reqCasoId'), $caso_id, $estatusEnrolamiento, $clienteEnrolamiento);

        return response()->json(RespuestaApi::returnResultado('success', 'Se guardaron los elementos con éxito', $data));
    }

    public function clienteEnroladoById($id)
    {
        try {
            $clienteEnrolado = ClienteEnrolamiento::where('id', $id)
                ->with([
                    'imagenes' => function ($query) {
                        $query->where('equifax', true)->where('deleted_at', null);
                    }
                ])->first();

            if ($clienteEnrolado) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $clienteEnrolado));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Cliente no enrrolado', $id));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function actualizarReqCaso($reqCasoId, $casoId, $statusEnrol, $clienteEnrolamiento)
    {
        $reqCaso = RequerimientoCaso::find($reqCasoId);
        if ($reqCaso) {
            if ($statusEnrol == 'Proceso satisfactorio') {
                $reqCaso->valor_boolean = true;
            } else {
                $reqCaso->valor_boolean = false;
            }
            $reqCaso->marcado = true;
            $reqCaso->valor_int = $clienteEnrolamiento->id;
            $reqCaso->save();
        }
        return $reqCaso;
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
                    // $nombreArchivo = 'firmado_' . $contador++ . '_' . $datosEnrolamiento['IdNumber'] . '_' . uniqid() . '.pdf';
                    $nombreArchivo = 'firmado_' . $contador++ . '_' . $datosEnrolamiento['IdNumber'] . '.pdf';

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
