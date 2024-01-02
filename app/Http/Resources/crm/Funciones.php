<?php

namespace App\Http\Resources\crm;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Funciones
{

  public static function fun_obtenerAlfanumericos($cadena)
  {
    // Expresión regular para encontrar caracteres no alfanuméricos
    $patron = '/[^a-zA-Z0-9]/';

    return preg_replace($patron, '', $cadena);
  }

  // // Ejemplo de uso
// $cadena = "¡Hola123, ¿cómo estás?";
// $alfanumericos = obtenerAlfanumericos($cadena);
// echo $alfanumericos;  // Devolverá "Hola123cómoestás"



  // $arrayUobjeto: Este parámetro representa el objeto (o array) que contiene las propiedades a formatear.
  // $FechasTransformar: Este es un array que contiene los nombres de los campos que representan fechas en el objeto.
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

  public function logInfo($controller, $rutaFuncion, $user_id, $user_ip, $msg, $id_objeto = null)
    { // id_objeto es, por ejemplo un tablero.id, caso.id, usuario.id    

        // ejemplo de como llenar la funcion

        // $controller = TableroController::class;
        // $rutaFuncion = $request->fullUrl(); // Obtén la URL de la solicitud actual
        // $user_id = Auth::id();
        // $user_ip = $request->ip();

        Log::info('Controller: ' . $controller . ' | Ruta Funcion: ' . $rutaFuncion . ' | Usuario: ' . $user_id . ' | IP Usuario:' . $user_ip . ' | Mensaje: ' . $msg . $id_objeto);
    }

    public function logError($controller, $rutaFuncion, $user_id, $user_ip, $msg, $id_objeto = null, $exception)
    { // $exeption es el objeto de error que lanza los try catch

        // ejemplo de como llenar la funcion

        // $controller = TableroController::class;
        // $rutaFuncion = $request->fullUrl(); // Obtén la URL de la solicitud actual
        // $user_id = Auth::id();
        // $user_ip = $request->ip();

        Log::error('Controller: ' . $controller . ' | Ruta Funcion: ' . $rutaFuncion . ' | Usuario: ' . $user_id . ' | IP Usuario:' . $user_ip . ' | Mensaje: ' . $msg . $id_objeto . ' | ' . $exception->getMessage());
    }

}
