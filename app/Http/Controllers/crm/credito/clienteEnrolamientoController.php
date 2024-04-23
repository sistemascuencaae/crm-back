<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\CasoController;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\credito\ClienteEnrolamiento;
use App\Models\crm\Galeria;
use App\Models\crm\RequerimientoCaso;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClienteEnrolamientoController extends Controller
{
    public function listEnrolamientosById($cli_id, $caso_id)
    {
        $log = new Funciones();
        try {
            $respuesta = ClienteEnrolamiento::where(function ($query) use ($cli_id, $caso_id) {
                $query->where('cli_id', $cli_id)
                    ->orWhere('caso_id', $caso_id);
            })
                ->with('caso.estadodos')
                ->orderBy('id', 'ASC')
                ->get();

            $log->logInfo(ClienteEnrolamientoController::class, 'Se listo con exito los enrolamientos del cliente: ' . $cli_id . ' , del caso #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $respuesta));
        } catch (Exception $e) {
            $log->logError(ClienteEnrolamientoController::class, 'Error al listar los enrolamientos del cliente ' . $cli_id . ' , del caso #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addClienteEnrolamiento(Request $request)
    {
        $log = new Funciones();
        try {
            if (!$request->has('casoId')) {
                return response()->json(RespuestaApi::returnResultado('error', 'El número de caso no existe', ''));
            }
            if (!$request->has('datosEnrolamiento')) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionó el objeto datosEnrolamiento', ''));
            }

            $data = DB::transaction(function () use ($request) {

                $estatusEnrolamiento = $request->input('statusEnrol');
                $cliId = $request->input('cliId');
                $datosEnrolamiento = json_decode($request->input('datosEnrolamiento'), true);

                $caso_id = $request->input('casoId');

                //START CODIGO EN CASO DE QUERER ACTUALIZAR UN CLIENTE ENROLAMIENTO

                // $clienteEnrolado = ClienteEnrolamiento::where('caso_id', $caso_id)->first();

                // if ($clienteEnrolado) {
                //     // Cliente ya existe, actualiza los datos
                //     $clienteEnrolado->fill($datosEnrolamiento);
                //     $clienteEnrolado->save();

                //     $imagenes = Galeria::where('caso_id', $caso_id)->where('equifax', true)->get();
                //     Galeria::where('caso_id', $caso_id)->where('equifax', true)->delete();
                //     //echo ('$request->input(reqCasoId): '.json_encode($request->input('reqCasoId')));
                //     $reqCaso = $this->actualizarReqCaso($request->input('reqCasoId'), $caso_id, $estatusEnrolamiento, $clienteEnrolado);
                //     //$reqCaso = RequerimientoCaso::find($request->input('reqCasoId'));
                //     //echo ('$imagenes: '.json_encode($imagenes));
                //     foreach ($imagenes as $img) {

                //         $ruta = $img['imagen'];

                //         if (Storage::disk('nas')->exists($ruta)) {
                //             Storage::disk('nas')->delete($ruta);
                //             // El archivo se ha eliminado exitosamente
                //         } else {
                //             // El archivo no existe en el sistema de archivos "nas"
                //         }
                //     }

                //     // También puedes actualizar las imágenes si es necesario
                //     if ($request->has('datosEnrolamiento') && isset($datosEnrolamiento['Images']) && !empty($datosEnrolamiento['Images'])) {
                //         foreach ($datosEnrolamiento['Images'] as $imagen) {
                //             $titulo = $imagen['ImageTypeName'];
                //             $descripcion = $imagen['ImageTypeName'];

                //             $imagenBase64 = $imagen['Image'];
                //             $imagenData = base64_decode($imagenBase64);

                //             if ($imagen['ImageTypeName'] === 'Video de Liveness') {
                //                 $nombre = $titulo . '.mp4';
                //             } else {
                //                 $nombre = $titulo . '.png';
                //             }

                //             $ruta = Storage::disk('nas')->put($caso_id . '/galerias/' . $nombre, $imagenData);
                //             file_put_contents($ruta, $imagenData);

                //             Galeria::create([
                //                 'caso_id' => $caso_id,
                //                 'titulo' => $titulo,
                //                 'descripcion' => $descripcion,
                //                 'imagen' => $caso_id . '/galerias/' . $nombre,
                //                 'tipo_gal_id' => 1,
                //                 'equifax' => true,
                //             ]);
                //         }
                //     }


                //     $clieEnrolado = ClienteEnrolamiento::where('id', $clienteEnrolado->id)
                //         ->with([
                //             'imagenes' => function ($query) {
                //                 $query->where('equifax', true);
                //             }
                //         ])->first();
                //     $casoController = new CasoController();

                //     $data = (object) [
                //         "caso" => $casoController->getCaso($caso_id),
                //         "clienteEnrolamiento" => $clieEnrolado,
                //         "reqCaso" => $reqCaso
                //     ];



                //     //return response()->json(RespuestaApi::returnResultado('success', 'Cliente enrolado actualizado.', $data));
                //     return $data;
                // }

                //END CODIGO EN CASO DE QUERER ACTUALIZAR UN CLIENTE ENROLAMIENTO

                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                if (!isset($datosEnrolamiento['Images']) || empty($datosEnrolamiento['Images'])) {
                    $data = (object) [
                        "error" => 'El objeto datosEnrolamiento no contiene imágenes'
                    ];
                    return $data; //response()->json(RespuestaApi::returnResultado('error', 'El objeto datosEnrolamiento no contiene imágenes', ''));
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
                    $fechaOriginal = $datosEnrolamiento['CreationDate'];
                    $fechaFormateada = str_replace([':', ' '], ['_', '_'], $fechaOriginal);
                    if ($parametro->nas == true) {
                        $ruta = Storage::disk('nas')->put("casos/" . $caso_id . '/galerias/' . $fechaFormateada . ' - ' . $nombre, $imagenData);
                    } else {
                        $ruta = Storage::disk('local')->put("casos/" . $caso_id . '/galerias/' . $fechaFormateada . ' - ' . $nombre, $imagenData);
                    }
                    file_put_contents($ruta, $imagenData);

                    Galeria::create([
                        'caso_id' => $caso_id,
                        'titulo' => $titulo,
                        'descripcion' => $descripcion,
                        'imagen' => 'casos/' . $caso_id . '/galerias/' . $fechaFormateada . ' - ' . $nombre,
                        'tipo_gal_id' => 1,
                        'equifax' => true,
                        'enrolamiento_id' => $datosEnrolamiento['Uid']
                    ]);
                }

                $enrolamientoId = $datosEnrolamiento['Uid'];
                unset($datosEnrolamiento['Images']);
                $datosEnrolamiento['Extras'] = json_encode($datosEnrolamiento['Extras']);
                $datosEnrolamiento['SignedDocuments'] = json_encode($datosEnrolamiento['SignedDocuments']);
                $datosEnrolamiento['Scores'] = json_encode($datosEnrolamiento['Scores']);
                $datosEnrolamiento['cli_id'] = $cliId;
                $clienteEnrolamiento = ClienteEnrolamiento::create($datosEnrolamiento);

                $clieEnrolado = ClienteEnrolamiento::where('id', $clienteEnrolamiento->id)
                    ->with([
                        'imagenes' => function ($query) use ($enrolamientoId) {
                            $query->where('equifax', true)->where('enrolamiento_id', $enrolamientoId);
                        }
                    ])->first();


                $casoController = new CasoController();
                $reqCaso = $this->actualizarReqCaso($request->input('reqCasoId'), $caso_id, $estatusEnrolamiento, $clienteEnrolamiento);
                //$reqCaso = RequerimientoCaso::find($request->input('reqCasoId'));
                $data = (object) [
                    "caso" => $casoController->getCaso($caso_id),
                    "clienteEnrolamiento" => $clieEnrolado,
                    "reqCaso" => $reqCaso
                ];

                $validEnrolCli = DB::selectOne('SELECT * from crm.temp_enrolamiento_cliente
                where cli_id = ? and req_caso_id = ? and  caso_id = ?', [$cliId, $request->input('reqCasoId'), $caso_id]);
                if ($validEnrolCli) {
                    DB::delete('DELETE FROM crm.temp_enrolamiento_cliente WHERE id = ?', [$validEnrolCli->id]);
                }
                return $data;
            });

            $log->logInfo(ClienteEnrolamientoController::class, 'Se creo con exito el Cliente Enrolamiento');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ClienteEnrolamientoController::class, 'Error al crear el Cliente Enrolamiento', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function addClienteEnrolByCliente(Request $request)
    // {
    //     try {




    //         if (!$request->has('casoId')) {
    //             return response()->json(RespuestaApi::returnResultado('error', 'El número de caso no existe', ''));
    //         }
    //         if (!$request->has('datosEnrolamiento')) {
    //             return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionó el objeto datosEnrolamiento', ''));
    //         }

    //         $reqCasoId = $request->input('reqCasoId');
    //         $casoId = $request->input('casoId');
    //         $cliId = $request->input('cliId');
    //         if ($reqCasoId && $casoId && $cliId) {
    //             $validEnrolCli = DB::selectOne('SELECT * from crm.temp_enrolamiento_cliente
    //             where cli_id = ? and req_caso_id = ? and  caso_id = ?', [$cliId, $reqCasoId, $casoId]);
    //             if (!$validEnrolCli) {
    //                 return response()->json(RespuestaApi::returnResultado('error', 'Error', 'Proceso terminado.'));
    //             }else{
    //                 $idProcesoEnrolamie = $validEnrolCli->id;
    //             }
    //         }else{
    //             return response()->json(RespuestaApi::returnResultado('error', 'Error', 'El los datos de enrolamiento no son validos.'));
    //         }





    //         $data = DB::transaction(function () use ($request) {

    //             $estatusEnrolamiento = $request->input('statusEnrol');
    //             $cliId = $request->input('cliId');
    //             $datosEnrolamiento = json_decode($request->input('datosEnrolamiento'), true);

    //             $caso_id = $request->input('casoId');

    //             //START CODIGO EN CASO DE QUERER ACTUALIZAR UN CLIENTE ENROLAMIENTO
    //             //END CODIGO EN CASO DE QUERER ACTUALIZAR UN CLIENTE ENROLAMIENTO

    //             if (!isset($datosEnrolamiento['Images']) || empty($datosEnrolamiento['Images'])) {
    //                 $data = (object) [
    //                     "error" => 'El objeto datosEnrolamiento no contiene imágenes'
    //                 ];
    //                 return $data; //response()->json(RespuestaApi::returnResultado('error', 'El objeto datosEnrolamiento no contiene imágenes', ''));
    //             }

    //             foreach ($datosEnrolamiento['Images'] as $imagen) {
    //                 $titulo = $imagen['ImageTypeName'];
    //                 $descripcion = $imagen['ImageTypeName'];

    //                 $imagenBase64 = $imagen['Image'];
    //                 $imagenData = base64_decode($imagenBase64);

    //                 if ($imagen['ImageTypeName'] === 'Video de Liveness') {
    //                     $nombre = $titulo . '.mp4';
    //                 } else {
    //                     $nombre = $titulo . '.png';
    //                 }
    //                 $fechaOriginal = $datosEnrolamiento['CreationDate'];
    //                 $fechaFormateada = str_replace([':', ' '], ['_', '_'], $fechaOriginal);
    //                 $ruta = Storage::disk('nas')->put($caso_id . '/galerias/' . $fechaFormateada . ' - ' . $nombre, $imagenData);
    //                 file_put_contents($ruta, $imagenData);

    //                 Galeria::create([
    //                     'caso_id' => $caso_id,
    //                     'titulo' => $titulo,
    //                     'descripcion' => $descripcion,
    //                     'imagen' => $caso_id . '/galerias/' . $fechaFormateada . ' - ' . $nombre,
    //                     'tipo_gal_id' => 1,
    //                     'equifax' => true,
    //                     'enrolamiento_id' => $datosEnrolamiento['Uid']
    //                 ]);
    //             }

    //             $enrolamientoId = $datosEnrolamiento['Uid'];
    //             unset($datosEnrolamiento['Images']);
    //             $datosEnrolamiento['Extras'] = json_encode($datosEnrolamiento['Extras']);
    //             $datosEnrolamiento['SignedDocuments'] = json_encode($datosEnrolamiento['SignedDocuments']);
    //             $datosEnrolamiento['Scores'] = json_encode($datosEnrolamiento['Scores']);
    //             $datosEnrolamiento['cli_id'] = $cliId;
    //             $clienteEnrolamiento = ClienteEnrolamiento::create($datosEnrolamiento);

    //             $clieEnrolado = ClienteEnrolamiento::where('id', $clienteEnrolamiento->id)
    //                 ->with([
    //                     'imagenes' => function ($query) use ($enrolamientoId) {
    //                         $query->where('equifax', true)->where('enrolamiento_id', $enrolamientoId);
    //                     }
    //                 ])->first();


    //             $casoController = new CasoController();
    //             $reqCaso = $this->actualizarReqCaso($request->input('reqCasoId'), $caso_id, $estatusEnrolamiento, $clienteEnrolamiento);
    //             //$reqCaso = RequerimientoCaso::find($request->input('reqCasoId'));
    //             $data = (object) [
    //                 "caso" => $casoController->getCaso($caso_id),
    //                 "clienteEnrolamiento" => $clieEnrolado,
    //                 "reqCaso" => $reqCaso
    //             ];

    //             return $data;
    //         });

    //         if($idProcesoEnrolamie){
    //             DB::delete('DELETE FROM crm.temp_enrolamiento_cliente WHERE id = ?', [$idProcesoEnrolamie]);
    //         }
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardaron los elementos con éxito', $data));
    //     } catch (\Throwable $th) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $th));
    //     }
    // }
    public function clienteEnroladoById($id)
    {
        $log = new Funciones();
        try {
            $enrolamiento = ClienteEnrolamiento::find($id);
            $enrolId = $enrolamiento['Uid'];
            $clienteEnrolado = ClienteEnrolamiento::where('id', $id)
                ->with([
                    'imagenes' => function ($query) use ($enrolId) {
                        $query->where('equifax', true)->where('enrolamiento_id', $enrolId);
                    }
                ])->first();

            if ($clienteEnrolado) {
                $log->logInfo(ClienteEnrolamientoController::class, 'Se listo con exito el enrolamiento con el ID: ' . $id);

                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $clienteEnrolado));
            } else {
                $log->logError(ClienteEnrolamientoController::class, 'Cliente no enrrolado con el ID: ' . $id);

                return response()->json(RespuestaApi::returnResultado('error', 'Cliente no enrrolado', $id));
            }

        } catch (Exception $e) {
            $log->logError(ClienteEnrolamientoController::class, 'Error al listar el enrolamiento con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function actualizarReqCaso($reqCasoId, $casoId, $statusEnrol, $clienteEnrolamiento)
    {
        $log = new Funciones();

        $casoCedulaCliente = DB::selectOne("SELECT cli.identificacion  from crm.caso ca
        inner join crm.cliente cli on cli.id = ca.cliente_id where ca.id = ?", [$casoId]);
        $enrolamientoCedulaCliente = DB::selectOne(
            'SELECT ce."IdNumber" from crm.cliente_enrolamiento ce
        left join crm.cliente cli on cli.id = ce.cli_id where ce.caso_id = ? and ce.id = ?',
            [$casoId, $clienteEnrolamiento->id]
        );

        //echo ('enrolamientoCedulaCliente: ' . json_encode($enrolamientoCedulaCliente));
        //echo ('$casoCedulaCliente: ' . json_encode($casoCedulaCliente));

        try {
            $reqCaso = RequerimientoCaso::find($reqCasoId);
            if ($reqCaso) {
                if ($statusEnrol == 'Proceso satisfactorio' && $casoCedulaCliente->identificacion == $enrolamientoCedulaCliente->IdNumber) {
                    $reqCaso->valor_boolean = true;
                } else {
                    $reqCaso->valor_boolean = false;
                }
                $reqCaso->marcado = true;
                $reqCaso->valor_int = $clienteEnrolamiento->id;
                $reqCaso->save();
            }

            $log->logInfo(ClienteEnrolamientoController::class, 'Se actualizo correctamente el requerimiento');

            return $reqCaso;
        } catch (\Throwable $e) {
            $log->logError(ClienteEnrolamientoController::class, 'Error al actualizar el requerimiento', $e);
            return $e;
        }
    }

    public function validarReqCasoCliente(Request $request)
    {
        $log = new Funciones();
        try {

            $reqCasoId = $request->input('reqCasoId');
            $casoId = $request->input('casoId');
            $cliId = $request->input('cliId');
            if ($reqCasoId && $casoId && $cliId) {
                $validEnrolCli = DB::selectOne('SELECT * from crm.temp_enrolamiento_cliente
                where cli_id = ? and req_caso_id = ? and  caso_id = ?', [$cliId, $reqCasoId, $casoId]);
                if (!$validEnrolCli) {
                    $log->logError(ClienteEnrolamientoController::class, 'Proceso terminado.');

                    return response()->json(RespuestaApi::returnResultado('error', 'Error', 'Proceso terminado.'));
                } else {
                    $log->logInfo(ClienteEnrolamientoController::class, 'Proceso completo');

                    return response()->json(RespuestaApi::returnResultado('success', 'Proceso completo', 'Proceso completo.'));
                }
            } else {
                $log->logError(ClienteEnrolamientoController::class, 'El los datos de enrolamiento no son validos.');

                return response()->json(RespuestaApi::returnResultado('error', 'Error', 'El los datos de enrolamiento no son validos.'));
            }
        } catch (Exception $e) {
            $log->logError(ClienteEnrolamientoController::class, 'Error al validar el requerimiento', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addArchivosFirmadosEnrolamiento(Request $request)
    {
        $log = new Funciones();
        try {
            $error = null;
            $exitoso = null;
            DB::transaction(function () use ($request, &$error, &$exitoso) {

                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                // Verificar si el objeto datosEnrolamiento se ha proporcionado
                if (!$request->has('datosEnrolamiento')) {
                    // return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionó el objeto datosEnrolamiento', ''));
                    $error = 'No se proporcionó el objeto datosEnrolamiento';
                    return null; // No se realiza la transacción
                }

                // Obtener el objeto datosEnrolamiento desde la solicitud
                $datosEnrolamientoJson = $request->input('datosEnrolamiento');
                $datosEnrolamiento = json_decode($datosEnrolamientoJson, true); // Decodificar en un array asociativo

                // Verificar si el objeto contiene el campo "SignedDocuments"
                if (!isset($datosEnrolamiento['SignedDocuments']) || empty($datosEnrolamiento['SignedDocuments'])) {
                    // return response()->json(RespuestaApi::returnResultado('error', 'No hay archivos para firmar', ''));
                    $error = 'No hay archivos para firmar';
                    return null; // No se realiza la transacción
                } else {

                    // Aqui borrar los archivos de la carpeta equifax y tambien de la BD
                    // Paso 1: Obtener los nombres de archivos en la carpeta NAS
                    $folderPath = $datosEnrolamiento['caso_id'] . "/equifax";
                    if ($parametro->nas == true) {
                        $archivosNAS = Storage::disk('nas')->files($folderPath);
                    } else {
                        $archivosNAS = Storage::disk('local')->files($folderPath);
                    }

                    // Paso 2: Buscar registros de archivos en la base de datos que coincidan con los nombres de archivos en la carpeta NAS
                    $archivosEnBD = Archivo::whereIn('archivo', $archivosNAS)->get();

                    if ($parametro->nas == true) {
                        // Pasos 3 y 4: Eliminar archivos de la carpeta NAS y registros de la base de datos
                        foreach ($archivosEnBD as $archivo) {
                            // Eliminar archivos de la carpeta NAS
                            Storage::disk('nas')->delete($archivo->archivo);

                            // Eliminar el registro de la base de datos
                            $archivo->delete();
                        }
                        // Aqui borrar los archivos de la carpeta equifax y tambien de la BD
                    } else {
                        // Pasos 3 y 4: Eliminar archivos de la carpeta NAS y registros de la base de datos
                        foreach ($archivosEnBD as $archivo) {
                            // Eliminar archivos de la carpeta NAS
                            Storage::disk('local')->delete($archivo->archivo);

                            // Eliminar el registro de la base de datos
                            $archivo->delete();
                        }
                        // Aqui borrar los archivos de la carpeta equifax y tambien de la BD
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

                        if ($parametro->nas == true) {
                            $ruta = Storage::disk('nas')->put("casos/" . $caso_id . '/equifax/' . $nombreArchivo, $archivoData);
                        } else {
                            $ruta = Storage::disk('local')->put("casos/" . $caso_id . '/equifax/' . $nombreArchivo, $archivoData);
                        }
                        file_put_contents($ruta, $archivoData);

                        Archivo::create([
                            "titulo" => $titulo,
                            "observacion" => $observacion,
                            "archivo" => $caso_id . '/equifax/' . $nombreArchivo,
                            "caso_id" => $caso_id,
                            "tipo" => 'equifax',
                        ]);
                    }

                    // Aqui borrar los archivos de la carpeta archivos_sin_firma y tambien de la BD
                    // Paso 1: Obtener los nombres de archivos en la carpeta NAS
                    $folderPath = $caso_id . "/archivos_sin_firma";
                    if ($parametro->nas == true) {
                        $archivosNAS = Storage::disk('nas')->files($folderPath);
                    } else {
                        $archivosNAS = Storage::disk('local')->files($folderPath);
                    }

                    // Paso 2: Buscar registros de archivos en la base de datos que coincidan con los nombres de archivos en la carpeta NAS
                    $archivosEnBD = Archivo::whereIn('archivo', $archivosNAS)->get();

                    if ($parametro->nas == true) {
                        // Pasos 3 y 4: Eliminar archivos de la carpeta NAS y registros de la base de datos
                        foreach ($archivosEnBD as $archivo) {
                            // Eliminar archivos de la carpeta NAS
                            Storage::disk('nas')->delete($archivo->archivo);

                            // Eliminar el registro de la base de datos
                            $archivo->delete();
                        }
                        // Aqui borrar los archivos de la carpeta archivos_sin_firma y tambien de la BD
                    } else {
                        // Pasos 3 y 4: Eliminar archivos de la carpeta NAS y registros de la base de datos
                        foreach ($archivosEnBD as $archivo) {
                            // Eliminar archivos de la carpeta NAS
                            Storage::disk('local')->delete($archivo->archivo);

                            // Eliminar el registro de la base de datos
                            $archivo->delete();
                        }
                        // Aqui borrar los archivos de la carpeta archivos_sin_firma y tambien de la BD
                    }

                    // return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', ''));
                    $exitoso = 'Se guardo con éxito';
                    return null; // Se realiza la transacción
                }
            });

            if ($error) {
                $log->logError(ClienteEnrolamientoController::class, $error);

                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                $log->logInfo(ClienteEnrolamientoController::class, 'Se guardaron con exito los archivos firmados del enrolamiento');

                return response()->json(RespuestaApi::returnResultado('success', $exitoso, ''));
            }
        } catch (Exception $e) {
            $log->logError(ClienteEnrolamientoController::class, 'Error al crear los archivos firmados del enrolamiento', $e);

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
