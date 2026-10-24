<?php

namespace App\Http\Controllers\ventas;

use App\Models\Access;

// Permisos del cotizador que aplica el servidor: el superusuario (usu_tipo 3) ve todas las cotizaciones
// y el resto solo las suyas; costos, aportes, CAME, markup y margenes solo con "Ver costos" (ver_costos) en
// PROFORMAS (21-oct: antes era report, que en Perfiles se llama Imprimir y ahora queda solo para el Excel).
trait PermisosCotizacionTrait
{
    private function esSuperusuario(): bool
    {
        return (int) (auth('api')->user()->usu_tipo ?? 0) === 3;
    }

    // crm.access del menu PROFORMAS (Ver costos) para el perfil del usuario: vale en Cotizar, en la proforma del
    // caso, en el historial y en Ver.
    private function puedeVerCostosCotizacion(): bool
    {
        $usuario = auth('api')->user();

        return $usuario !== null && Access::where('profile_id', $usuario->profile_id)
            ->where('ver_costos', 1)
            ->whereHas('menu', function ($query) {
                $query->where('name', 'PROFORMAS');
            })
            ->exists();
    }

    // Claves que se quitan en cualquier nivel: items, unitario, credito y calificacion de calcular/guardar;
    // cabecera, detalles y aportes de una cotizacion guardada (alli el markup va en columnas sueltas).
    private function clavesCostoCotizacion(): array
    {
        return [
            'costo', 'origen_costo', 'aportes', 'total_aportes', 'came', 'markup',
            'margen_financiero', 'margen_financiero_general', 'incremento',
            'pcotizador_id', 'concepto_markup', 'origen_markup', 'cmarkup_id', 'cabecera_markup',
            'dmarkup_id', 'orden_markup', 'vigencia_inicio_markup', 'vigencia_fin_markup',
            'valor_markup', 'valor_markup_lista', 'minimo_negociacion', 'maximo_negociacion',
        ];
    }

    // Con Ver costos, la respuesta tal cual; sin el, sin esas claves (se quitan, no van en null).
    private function datosSegunPermisoCostos($datos)
    {
        if (!is_array($datos) || $this->puedeVerCostosCotizacion()) {
            return $datos;
        }

        return $this->quitarClavesCosto($datos, array_flip($this->clavesCostoCotizacion()));
    }

    private function quitarClavesCosto(array $datos, array $claves): array
    {
        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && isset($claves[$clave])) {
                unset($datos[$clave]);
            } elseif (is_array($valor)) {
                $datos[$clave] = $this->quitarClavesCosto($valor, $claves);
            } elseif ($clave === 'error' && is_string($valor)) {
                // El error de fn_calcular_precio_json trae "(tipo=..., costo=..., markup=...)": va sin eso.
                $datos[$clave] = preg_replace('/\s*\(tipo=[^)]*\)/', '', $valor);
            }
        }

        return $datos;
    }
}
