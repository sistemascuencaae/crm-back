<?php

namespace App\Http\Resources\crm;

use App\Http\Resources\RespuestaApi;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\Auth;

class Funciones
{

  public static function fun_obtenerAlfanumericos($cadena)
  {
    // Expresión regular para encontrar caracteres no alfanuméricos
    $patron = '/[^a-zA-Z0-9]/';

    return preg_replace($patron, '', $cadena);
  }

  public function formatoFechaItem($arrayUobjeto, $FechasTransformar)
  {
    try {
      foreach ($FechasTransformar as $nombreCampo) {
        if (isset($arrayUobjeto->$nombreCampo)) {
          // Verificar si la propiedad existe en el objeto
          $campoFormateado = $nombreCampo . '_formateado';
          $arrayUobjeto->$campoFormateado = Carbon::parse($arrayUobjeto->$nombreCampo)->format('Y-m-d H:i:s');
        }
      }

      // NO LO ELIMINO SI NO TOCA CAMBIAR EN EL CODIGO DEL FRONT LOS CAMPOS DE FECHAS POR FECHAX_FORMATEADO

      // // Eliminar las propiedades originales si lo deseas
      // foreach ($FechasTransformar as $nombreCampo) {
      //   unset($arrayUobjeto->$nombreCampo);
      // }
    } catch (\Throwable $th) {
      throw $th;
    }
  }

  public function logInfo($controller, $msg)
  {
    $request = RequestFacade::instance();

    Log::info(
      'Controller: ' . $controller
      . ' | Ruta Funcion: ' . $request->fullUrl()
      . ' | Usuario: ' . Auth::id()
      . ' | IP Usuario:' . $request->ip()
      . ' | Mensaje: ' . $msg
    );
  }

  public function logError($controller, $msg, $exception = null)
  {
    $request = RequestFacade::instance();

    if ($exception) {
      Log::error(
        'Controller: ' . $controller
        . ' | Ruta Funcion: ' . $request->fullUrl()
        . ' | Usuario: ' . Auth::id()
        . ' | IP Usuario:' . $request->ip()
        . ' | Mensaje: ' . $msg
        . ' | Error: ' . $exception->getMessage()
        . ' | En la linea (' . $exception->getLine() . ')'
      );
    } else {
      Log::error(
        'Controller: ' . $controller
        . ' | Ruta Funcion: ' . $request->fullUrl()
        . ' | Usuario: ' . Auth::id()
        . ' | IP Usuario:' . $request->ip()
        . ' | Mensaje: ' . $msg
      );
    }
  }

  public function descargarTxt($data, $nombreArchivo)
  {
      try {
          // Establecer los encabezados de la respuesta para la descarga del archivo .txt
          $headers = [
              'Content-Type' => 'text/plain',
              'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
          ];

          // Crear la respuesta HTTP con el contenido del archivo adjunto
          return response($data, 200, $headers);
      } catch (Exception $e) {
          return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
      }
  }

}
