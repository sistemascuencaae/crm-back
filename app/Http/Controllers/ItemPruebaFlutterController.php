<?php

namespace App\Http\Controllers;

use App\Models\ItemPruebaFlutter;
use Illuminate\Http\Request;

/**
 * Pruebas de la app Flutter (temporal). Tabla: crm.pruebas_flutter
 * El item y su ubicación se registran con APIs separadas; la app identifica cada item por su uuid.
 */
class ItemPruebaFlutterController extends Controller
{
    // GET prueba-flutter/items
    public function listar()
    {
        return response()->json(
            ItemPruebaFlutter::whereNull('secuencia')->orderBy('id')->get()
        );
    }

    // POST prueba-flutter/items/sincronizar   {uuid, nombre}
    // Crea el item, o le cambia el nombre si el uuid ya existe
    public function sincronizar(Request $request)
    {
        $datos = $request->validate([
            'uuid'   => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
        ]);

        $item = ItemPruebaFlutter::updateOrCreate(
            ['uuid' => $datos['uuid']],
            ['nombre' => $datos['nombre']]
        );

        return response()->json($item, $item->wasRecentlyCreated ? 201 : 200);
    }

    // PUT prueba-flutter/items/{uuid}/ubicacion   {latitud, longitud}
    public function registrarUbicacion(Request $request, string $uuid)
    {
        $datos = $request->validate([
            'latitud'  => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
        ]);

        $item = ItemPruebaFlutter::where('uuid', $uuid)->first();

        if (!$item) {
            return response()->json(['mensaje' => 'El item no existe. Sincronízalo primero.'], 404);
        }

        // Las columnas son texto
        $item->update([
            'latitud'  => (string) $datos['latitud'],
            'longitud' => (string) $datos['longitud'],
        ]);

        return response()->json($item);
    }

    // DELETE prueba-flutter/items/{uuid}
    public function eliminar(string $uuid)
    {
        $eliminados = ItemPruebaFlutter::where('uuid', $uuid)->delete();

        if (!$eliminados) {
            return response()->json(['mensaje' => 'El item no existe.'], 404);
        }

        return response()->json(['mensaje' => 'Item eliminado.']);
    }


    // POST prueba-flutter/puntos   {puntos: [{uuid, secuencia, latitud, longitud, fecha_captura}, ...]}
    // Guarda varios puntos de una vez; si un uuid ya existe, no lo duplica
    public function guardarPuntos(Request $request)
    {
        $datos = $request->validate([
            'puntos'                 => 'required|array',
            'puntos.*.uuid'          => 'required|string|max:255',
            'puntos.*.secuencia'     => 'required|integer',
            'puntos.*.latitud'       => 'required|numeric|between:-90,90',
            'puntos.*.longitud'      => 'required|numeric|between:-180,180',
            'puntos.*.fecha_captura' => 'required|date',
        ]);

        foreach ($datos['puntos'] as $p) {
            ItemPruebaFlutter::updateOrCreate(
                ['uuid' => $p['uuid']],
                [
                    'nombre'        => 'punto ' . $p['secuencia'],
                    'secuencia'     => $p['secuencia'],
                    'latitud'       => (string) $p['latitud'],
                    'longitud'      => (string) $p['longitud'],
                    'fecha_captura' => $p['fecha_captura'],
                ]
            );
        }

        return response()->json(['guardados' => count($datos['puntos'])]);
    }
}
