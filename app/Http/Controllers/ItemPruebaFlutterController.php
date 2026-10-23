<?php

namespace App\Http\Controllers;

use App\Models\ItemPruebaFlutter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

    // POST prueba-flutter/puntos
    // {dispositivo, puntos: [{uuid, secuencia, latitud, longitud, precision, fecha_captura}, ...]}
    public function guardarPuntos(Request $request)
    {
        $datos = $request->validate([
            'dispositivo'            => 'required|string|max:255',
            'puntos'                 => 'required|array',
            'puntos.*.uuid'          => 'required|string|max:255',
            'puntos.*.secuencia'     => 'required|integer',
            'puntos.*.latitud'       => 'required|numeric|between:-90,90',
            'puntos.*.longitud'      => 'required|numeric|between:-180,180',
            'puntos.*.precision'     => 'nullable|numeric',
            'puntos.*.fecha_captura' => 'required|date',
        ]);

        foreach ($datos['puntos'] as $p) {
            ItemPruebaFlutter::updateOrCreate(
                ['uuid' => $p['uuid']],
                [
                    'nombre'        => 'punto ' . $p['secuencia'],
                    'dispositivo'   => $datos['dispositivo'],
                    'secuencia'     => $p['secuencia'],
                    'latitud'       => (string) $p['latitud'],
                    'longitud'      => (string) $p['longitud'],
                    'precision'     => $p['precision'] ?? null,
                    'fecha_captura' => $p['fecha_captura'],
                ]
            );
        }

        return response()->json(['guardados' => count($datos['puntos'])]);
    }

    // GET prueba-flutter/paradas
    // Paradas = huecos de más de 5 minutos entre dos puntos seguidos
    public function paradas()
    {
        $paradas = DB::select("
            WITH p AS (
                -- Puntos buenos (precisión de 30 m o mejor), cada teléfono por separado
                SELECT dispositivo,
                    latitud::float AS lat, longitud::float AS lng, fecha_captura,
                    LEAD(fecha_captura)   OVER w AS siguiente,
                    LEAD(latitud::float)  OVER w AS lat2,
                    LEAD(longitud::float) OVER w AS lng2
                FROM crm.pruebas_flutter
                WHERE secuencia IS NOT NULL
                AND (precision IS NULL OR precision <= 30)
                WINDOW w AS (PARTITION BY dispositivo ORDER BY secuencia)
            ),
            huecos AS (
                -- 1. Huecos de más de 5 min, saliendo a menos de 100 m del mismo lugar
                SELECT dispositivo, lat, lng, fecha_captura AS llego, siguiente AS salio
                FROM p
                WHERE siguiente - fecha_captura > interval '5 minutes'
                AND 111320 * sqrt(power(lat2 - lat, 2)
                                + power((lng2 - lng) * cos(radians(lat)), 2)) < 100
            ),
            marcados AS (
                -- 2. ¿Este hueco sigue la parada anterior? (menos de 5 min después y a menos de 100 m)
                SELECT *,
                    CASE WHEN llego - LAG(salio) OVER w2 < interval '5 minutes'
                            AND 111320 * sqrt(power(lat - LAG(lat) OVER w2, 2)
                                            + power((lng - LAG(lng) OVER w2) * cos(radians(lat)), 2)) < 100
                            THEN 0 ELSE 1 END AS nueva
                FROM huecos
                WINDOW w2 AS (PARTITION BY dispositivo ORDER BY llego)
            ),
            grupos AS (
                -- 3. Numera las paradas: los huecos que se continúan comparten número
                SELECT *, SUM(nueva) OVER (PARTITION BY dispositivo ORDER BY llego) AS parada
                FROM marcados
            )
            -- 4. Una fila por parada
            SELECT dispositivo,
                to_char(MIN(llego) - interval '5 hours', 'HH24:MI') AS llego,
                to_char(MAX(salio) - interval '5 hours', 'HH24:MI') AS salio,
                round(extract(epoch FROM MAX(salio) - MIN(llego)) / 60) AS minutos,
                AVG(lat) AS latitud,
                AVG(lng) AS longitud
            FROM grupos
            GROUP BY dispositivo, parada
            ORDER BY dispositivo, MIN(llego)
        ");

        return response()->json($paradas);
    }

    // GET prueba-flutter/puntos   Puntos de la ruta, en el orden en que se capturaron
    public function listarPuntos()
    {
        return response()->json(
            ItemPruebaFlutter::whereNotNull('secuencia')
                ->orderBy('dispositivo')
                ->orderBy('secuencia')
                ->get(['dispositivo', 'secuencia', 'latitud', 'longitud', 'precision', 'fecha_captura'])
        );
    }

    // POST prueba-flutter/mensajes   {titulo, mensaje}
    // Guarda el mensaje en Postgres y lo envía a todos los teléfonos (tema "cobradores")
    public function enviarMensaje(Request $request)
    {
        $datos = $request->validate([
            'titulo'  => 'required|string|max:255',
            'mensaje' => 'required|string',
        ]);

        // 1. Primero se guarda: aunque FCM falle, el mensaje no se pierde
        $id = DB::table('crm.pruebas_flutter_mensajes')->insertGetId([
            'titulo'  => $datos['titulo'],
            'mensaje' => $datos['mensaje'],
        ]);

        // 2. Luego se envía
        $enviado = $this->enviarPush($id, $datos['titulo'], $datos['mensaje']);

        DB::table('crm.pruebas_flutter_mensajes')->where('id', $id)->update(['enviado' => $enviado]);

        return response()->json(['id' => $id, 'enviado' => $enviado]);
    }

    // Envía una notificación al tema "cobradores" con la API HTTP v1 de FCM
    private function enviarPush(int $id, string $titulo, string $mensaje): bool
    {
        // Permiso temporal (dura 1 hora) usando la llave de la cuenta de servicio
        $token = $this->tokenFirebase();

        $proyecto = config('services.firebase.project_id');

        $respuesta = Http::withToken($token)->post(
            "https://fcm.googleapis.com/v1/projects/{$proyecto}/messages:send",
            [
                'message' => [
                    'topic'        => 'cobradores',
                    'notification' => ['title' => $titulo, 'body' => $mensaje],
                    'data'         => ['mensaje_id' => (string) $id], // para usarlo después en la app
                ],
            ]
        );

        return $respuesta->successful();
    }


    // Pide a Google un permiso temporal (dura 1 hora) firmado con la llave de la cuenta de servicio.
    // Se guarda en caché 55 minutos para no pedirlo en cada envío.
    private function tokenFirebase(): string
    {
        return Cache::remember('firebase_token', 55 * 60, function () {
            $llave = json_decode(
                file_get_contents(storage_path(config('services.firebase.credentials'))),
                true
            );
            $ahora = time();

            // 1. Arma una "solicitud firmada" con la llave privada
            $jwt = JWT::encode([
                'iss'   => $llave['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $ahora,
                'exp'   => $ahora + 3600,
            ], $llave['private_key'], 'RS256');

            // 2. Google la valida y devuelve el permiso temporal
            return Http::asForm()
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ])
                ->throw()               // si Google rechaza la llave, lanza el error
                ->json('access_token');
        });
    }


    // GET prueba-flutter/ruta?dispositivo=c80e8cf4&desde=2026-10-23 08:20&hasta=2026-10-23 08:59
    // Devuelve un GeoJSON con la ruta cruda (gris) y la ruta limpia (azul) para comparar
    public function ruta(Request $request)
    {
        $datos = $request->validate([
            'dispositivo' => 'required|string',
            'desde'       => 'required|date',
            'hasta'       => 'required|date',
        ]);

        // Las horas llegan en hora de Ecuador; en la tabla están en UTC (5 horas más)
        $puntos = DB::select("
            SELECT latitud::float  AS lat,
                longitud::float AS lng,
                precision,
                extract(epoch FROM fecha_captura) AS segundos
            FROM crm.pruebas_flutter
            WHERE dispositivo LIKE ?
            AND fecha_captura BETWEEN CAST(? AS timestamp) + interval '5 hours'
                                    AND CAST(? AS timestamp) + interval '5 hours'
            AND precision <= 50
            ORDER BY secuencia
        ", [$datos['dispositivo'] . '%', $datos['desde'], $datos['hasta']]);

        $sinSaltos  = $this->quitarSaltos($puntos);   // Paso 1
        $suavizados = $this->suavizar($sinSaltos);    // Paso 2

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => [
                $this->linea($puntos, '#999999'),      // cruda
                $this->linea($suavizados, '#0000ff'),  // limpia
            ],
        ]);
    }


    // a) Exige ir a más de 120 km/h desde el último punto bueno: imposible
    // b) "Va y vuelve": ir anterior → punto → siguiente es mucho más largo que
    //    ir directo de anterior a siguiente (como el punto 317 de la Luis Cordero)
    private function quitarSaltos(array $puntos): array
    {
        $buenos = [];

        foreach ($puntos as $i => $p) {
            $anterior  = end($buenos) ?: null;
            $siguiente = $puntos[$i + 1] ?? null;

            if ($anterior) {
                $segundos  = max(1, $p->segundos - $anterior->segundos);
                $kmPorHora = $this->metros($anterior, $p) / $segundos * 3.6;
                if ($kmPorHora > 120) {
                    continue;
                }
            }

            if ($anterior && $siguiente) {
                $directo = $this->metros($anterior, $siguiente);
                $desvio  = $this->metros($anterior, $p) + $this->metros($p, $siguiente);
                if ($desvio - $directo > 40 && $desvio > 1.8 * $directo) {
                    continue;
                }
            }

            $buenos[] = $p;
        }

        return $buenos;
    }


    // Combina dónde dice el GPS que estás con dónde deberías estar.
    // Un punto con mala precisión mueve poco la ruta; uno preciso la mueve mucho.
    private function suavizar(array $puntos): array
    {
        $resultado = [];
        $varianza  = -1;   // -1 = todavía no hay estimación

        foreach ($puntos as $i => $p) {
            $precision = max(1, (float) $p->precision);

            if ($varianza < 0) {
                // Primer punto: se toma tal cual
                $lat = $p->lat;
                $lng = $p->lng;
                $tiempo = $p->segundos;
                $varianza = $precision * $precision;
            } else {
                $segundos = max(1, $p->segundos - $tiempo);
                $tiempo = $p->segundos;

                // Velocidad real desde el punto anterior (mínimo 3 m/s).
                // Si vas rápido, la duda sobre dónde estás crece más rápido.
                $velocidad = max(3, $this->metros($puntos[$i - 1], $p) / $segundos);
                $varianza += $segundos * $velocidad * $velocidad;

                // Cuánto creerle al GPS: cerca de 0 = casi nada, cerca de 1 = todo
                $k = $varianza / ($varianza + $precision * $precision);

                $lat += $k * ($p->lat - $lat);
                $lng += $k * ($p->lng - $lng);
                $varianza = (1 - $k) * $varianza;
            }

            $resultado[] = (object) ['lat' => $lat, 'lng' => $lng];
        }

        return $resultado;
    }

    // Distancia aproximada en metros entre dos puntos
    private function metros($a, $b): float
    {
        $dLat = $b->lat - $a->lat;
        $dLng = ($b->lng - $a->lng) * cos(deg2rad($a->lat));
        return 111320 * sqrt($dLat * $dLat + $dLng * $dLng);
    }

    // Arma una línea GeoJSON de un color
    private function linea(array $puntos, string $color): array
    {
        return [
            'type'       => 'Feature',
            'properties' => ['stroke' => $color, 'stroke-width' => 3],
            'geometry'   => [
                'type'        => 'LineString',
                'coordinates' => array_map(fn($p) => [(float) $p->lng, (float) $p->lat], $puntos),
            ],
        ];
    }

    // GET prueba-flutter/visitas?dispositivo=c80e8cf4&lat=-2.9183&lng=-79.0551&radio=50
    // ¿Cuándo estuvo el teléfono dentro de un círculo? Una fila por visita
    public function visitas(Request $request)
    {
        $datos = $request->validate([
            'dispositivo' => 'required|string',
            'lat'         => 'required|numeric',
            'lng'         => 'required|numeric',
            'radio'       => 'nullable|numeric',
        ]);

        $visitas = DB::select("
            WITH p AS (
                SELECT secuencia, fecha_captura,
                    latitud::float AS lat, longitud::float AS lng,
                    LEAD(fecha_captura) OVER (ORDER BY secuencia) AS siguiente
                FROM crm.pruebas_flutter
                WHERE dispositivo LIKE ?
                AND (precision IS NULL OR precision <= 50)
            ),
            d AS (
                SELECT *,
                    111320 * sqrt(power(lat - ?, 2)
                                + power((lng - ?) * cos(radians(lat)), 2)) <= ? AS dentro
                FROM p
            ),
            m AS (
                SELECT *,
                    CASE WHEN dentro
                            AND NOT COALESCE(LAG(dentro) OVER (ORDER BY secuencia), false)
                            THEN 1 ELSE 0 END AS entra
                FROM d
            ),
            g AS (
                SELECT *, SUM(entra) OVER (ORDER BY secuencia) AS visita
                FROM m
            ),
            v AS (
                SELECT visita,
                    MIN(fecha_captura) AS llego,
                    bool_or(siguiente IS NULL) AS sigue_ahi,
                    CASE WHEN bool_or(siguiente IS NULL)
                            THEN now() AT TIME ZONE 'UTC'
                            ELSE MAX(siguiente)
                    END AS salio
                FROM g
                WHERE dentro
                GROUP BY visita
            )
            SELECT to_char(llego - interval '5 hours', 'DD/MM HH24:MI') AS llego,
                CASE WHEN sigue_ahi THEN 'sigue ahí'
                        ELSE to_char(salio - interval '5 hours', 'HH24:MI')
                END AS salio,
                round(extract(epoch FROM salio - llego) / 60) AS minutos
            FROM v
            WHERE salio - llego >= interval '1 minute'
            ORDER BY llego
        ", [
            $datos['dispositivo'] . '%',
            $datos['lat'],
            $datos['lng'],
            $datos['radio'] ?? 50,
        ]);

        return response()->json($visitas);
    }

}

