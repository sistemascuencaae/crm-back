<?php

namespace App\Http\Resources\crm;

use Illuminate\Http\Resources\Json\JsonResource;

class Funciones
{

public static function fun_obtenerAlfanumericos($cadena) {
  // Expresión regular para encontrar caracteres no alfanuméricos
  $patron = '/[^a-zA-Z0-9]/';

  return preg_replace($patron, '', $cadena);
}

// // Ejemplo de uso
// $cadena = "¡Hola123, ¿cómo estás?";
// $alfanumericos = obtenerAlfanumericos($cadena);
// echo $alfanumericos;  // Devolverá "Hola123cómoestás"
}
