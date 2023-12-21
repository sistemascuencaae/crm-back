<?php

namespace App\Http\Resources\crm;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

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

      // Eliminar las propiedades originales si lo deseas
      foreach ($FechasTransformar as $nombreCampo) {
        unset($arrayUobjeto->$nombreCampo);
      }
    } catch (\Throwable $th) {
      throw $th;
    }
  }

}
