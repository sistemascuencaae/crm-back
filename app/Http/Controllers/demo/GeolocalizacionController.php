<?php

namespace App\Http\Controllers\demo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Proxy de geolocalización. El frontend nunca ve las API keys: todas las
 * llamadas a Geoapify (buscar / reverse / POIs) y a Google (Street View)
 * salen desde aquí. Mismo patrón que GoogleSheetsController.
 */
class GeolocalizacionController extends Controller
{
    private const GEOAPIFY = 'https://api.geoapify.com';
    // Los mapas se sirven desde otro host, no desde api.geoapify.com.
    private const GEOAPIFY_MAPS = 'https://maps.geoapify.com';
    private const TIMEOUT       = 10;

    /** Categorías por defecto para "qué hay cerca". */
    private const CATEGORIAS = 'commercial,catering,accommodation,education,healthcare,service,tourism,leisure';

    /**
     * Estilo del mapa vectorial de Geoapify para MapLibre.
     * GET /demo/demo/geo/estilo
     *
     * La clave viaja en la URL porque el navegador pide los tiles directo a
     * Geoapify; se sirve desde aquí para tenerla en un solo sitio (.env) y no
     * duplicada en environment.ts. Restringir la clave por dominio en el panel
     * de Geoapify es lo que evita que alguien más la use.
     */
    public function estilo()
    {
        try {
            $key = config('services.geoapify.key');

            if (empty($key)) {
                throw new \Exception('Falta GEOAPIFY_API_KEY en el .env del backend.');
            }

            $estilo = config('services.geoapify.map_style', 'osm-bright');

            return $this->ok([
                'styleUrl' => self::GEOAPIFY_MAPS . "/v1/styles/{$estilo}/style.json?apiKey={$key}",
                'estilo'   => $estilo,
            ]);

        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /**
     * Autocompletado mientras el usuario escribe la dirección.
     * GET /demo/demo/geo/autocomplete?text=av+cevallos
     */
    public function autocomplete(Request $request)
    {
        try {
            $request->validate($this->reglasBusqueda());

            $data = $this->geoapify('/v1/geocode/autocomplete', $this->paramsBusqueda($request, 8));

            return $this->ok(array_map([$this, 'normalizar'], $data['results'] ?? []));

        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /**
     * Búsqueda de dirección por texto libre.
     * GET /demo/demo/geo/buscar?text=Av+Cevallos+y+Mera,+Ambato
     */
    public function buscar(Request $request)
    {
        try {
            $request->validate($this->reglasBusqueda());

            $data = $this->geoapify('/v1/geocode/search', $this->paramsBusqueda($request, 10));

            return $this->ok(array_map([$this, 'normalizar'], $data['results'] ?? []));

        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /**
     * Reverse geocoding: de un punto del mapa a todos los datos de la dirección.
     * GET /demo/demo/geo/reverse?lat=-1.2491&lon=-78.6167
     */
    public function reverse(Request $request)
    {
        try {
            $request->validate([
                'lat' => 'required|numeric|between:-90,90',
                'lon' => 'required|numeric|between:-180,180',
            ]);

            $lat = (float) $request->input('lat');
            $lon = (float) $request->input('lon');

            // El mismo punto siempre devuelve lo mismo: se cachea 24 h para no
            // quemar la cuota diaria mientras se arrastra el marcador.
            $data = Cache::remember($this->claveCache('rev', $lat, $lon), 86400, function () use ($lat, $lon) {
                return $this->geoapify('/v1/geocode/reverse', [
                    'lat'    => $lat,
                    'lon'    => $lon,
                    'lang'   => 'es',
                    'limit'  => 1,
                    'format' => 'json',
                ]);
            });

            $resultados = $data['results'] ?? [];

            if (empty($resultados)) {
                return response()->json([
                    'status'  => 'success',
                    'data'    => null,
                    'message' => 'No hay ninguna dirección registrada en ese punto.',
                ]);
            }

            return $this->ok($this->normalizar($resultados[0]));

        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /**
     * Puntos de interés alrededor de una coordenada ("qué hay en esa ubicación").
     * GET /demo/demo/geo/cerca?lat=-1.2491&lon=-78.6167&radio=300
     */
    public function cerca(Request $request)
    {
        try {
            $request->validate([
                'lat'        => 'required|numeric|between:-90,90',
                'lon'        => 'required|numeric|between:-180,180',
                'radio'      => 'nullable|integer|between:50,5000',
                'categorias' => 'nullable|string',
            ]);

            $lat        = (float) $request->input('lat');
            $lon        = (float) $request->input('lon');
            $radio      = (int) $request->input('radio', 300);
            $categorias = $request->input('categorias', self::CATEGORIAS);

            $data = Cache::remember($this->claveCache("poi{$radio}", $lat, $lon), 86400, function () use ($lat, $lon, $radio, $categorias) {
                // /v2/places responde GeoJSON, no acepta format=json.
                return $this->geoapify('/v2/places', [
                    'categories' => $categorias,
                    'filter'     => "circle:{$lon},{$lat},{$radio}",
                    'bias'       => "proximity:{$lon},{$lat}",
                    'lang'       => 'es',
                    'limit'      => 25,
                ]);
            });

            $lugares = array_map(function ($feature) {
                $p = $feature['properties'] ?? [];
                return [
                    'nombre'     => $p['name'] ?? $p['address_line1'] ?? 'Sin nombre',
                    'categoria'  => $p['categories'][0] ?? null,
                    'categorias' => $p['categories'] ?? [],
                    'direccion'  => $p['address_line2'] ?? $p['formatted'] ?? null,
                    'distancia'  => isset($p['distance']) ? (int) $p['distance'] : null,
                    'lat'        => $p['lat'] ?? null,
                    'lon'        => $p['lon'] ?? null,
                ];
            }, $data['features'] ?? []);

            // Geoapify no ordena por distancia de forma garantizada.
            usort($lugares, fn ($a, $b) => ($a['distancia'] ?? PHP_INT_MAX) <=> ($b['distancia'] ?? PHP_INT_MAX));

            return $this->ok($lugares);

        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /**
     * Comprueba si hay vista de calle en un punto y devuelve cómo mostrarla.
     * GET /demo/demo/geo/streetview?lat=-1.2491&lon=-78.6167
     *
     * Sin GOOGLE_MAPS_KEY el módulo sigue funcionando: cae al deep link de
     * Google Maps, que no necesita credenciales pero abre una pestaña nueva.
     */
    public function streetview(Request $request)
    {
        try {
            $request->validate([
                'lat'     => 'required|numeric|between:-90,90',
                'lon'     => 'required|numeric|between:-180,180',
                'heading' => 'nullable|numeric',
            ]);

            $lat     = (float) $request->input('lat');
            $lon     = (float) $request->input('lon');
            $heading = (float) $request->input('heading', 0);
            $key     = config('services.google_maps.key');

            $deepLink = "https://www.google.com/maps/@?api=1&map_action=pano&viewpoint={$lat},{$lon}";

            if (empty($key)) {
                return $this->ok([
                    'disponible' => true,
                    'modo'       => 'link',
                    'deepLink'   => $deepLink,
                    'mensaje'    => 'Sin GOOGLE_MAPS_KEY: se abrirá Google Maps en una pestaña nueva.',
                ]);
            }

            // El endpoint metadata no se factura: sirve para saber si hay
            // panorama antes de mostrar el visor.
            $meta = Cache::remember($this->claveCache('sv', $lat, $lon), 86400, function () use ($lat, $lon, $key) {
                $r = Http::timeout(self::TIMEOUT)->get('https://maps.googleapis.com/maps/api/streetview/metadata', [
                    'location' => "{$lat},{$lon}",
                    'key'      => $key,
                ]);

                if (!$r->successful()) {
                    throw new \Exception('Google respondió ' . $r->status() . ': ' . $r->body());
                }

                return $r->json();
            });

            if (($meta['status'] ?? '') !== 'OK') {
                return $this->ok([
                    'disponible' => false,
                    'modo'       => 'ninguno',
                    'deepLink'   => $deepLink,
                    'mensaje'    => 'No hay vista de calle en este punto.',
                ]);
            }

            $embed = 'https://www.google.com/maps/embed/v1/streetview?' . http_build_query([
                'key'      => $key,
                'location' => "{$lat},{$lon}",
                'heading'  => $heading,
                'pitch'    => 0,
                'fov'      => 90,
            ]);

            return $this->ok([
                'disponible' => true,
                'modo'       => 'embed',
                'embedUrl'   => $embed,
                'deepLink'   => $deepLink,
                'fecha'      => $meta['date'] ?? null,
                'copyright'  => $meta['copyright'] ?? null,
                'panoId'     => $meta['pano_id'] ?? null,
            ]);

        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    // ---------------------------------------------------------------- helpers

    private function reglasBusqueda(): array
    {
        return [
            'text' => 'required|string|min:3',
            'lat'  => 'nullable|numeric|between:-90,90',
            'lon'  => 'nullable|numeric|between:-180,180',
            // rect de Geoapify: lon1,lat1,lon2,lat2
            'bbox' => ['nullable', 'string', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
            'tipo' => 'nullable|string|in:country,state,city,postcode,street,amenity,locality',
        ];
    }

    /**
     * Arma los parámetros de búsqueda de Geoapify.
     *
     * El bias de proximidad es lo que hace que la búsqueda sirva: sin él,
     * Geoapify ordena por relevancia en todo el país y Guayaquil se lleva
     * siempre los primeros puestos porque tiene mucho más dato en OSM.
     */
    private function paramsBusqueda(Request $request, int $limite): array
    {
        $params = [
            'text'   => $request->input('text'),
            'lang'   => 'es',
            'limit'  => $limite,
            'format' => 'json',
        ];

        // Con bbox se restringe duro al área visible del mapa; si no, al país.
        $params['filter'] = $request->filled('bbox')
            ? 'rect:' . $request->input('bbox')
            : 'countrycode:' . $this->pais();

        if ($request->filled('lat') && $request->filled('lon')) {
            $params['bias'] = 'proximity:' . $request->input('lon') . ',' . $request->input('lat');
        }

        // El front manda type=street cuando detecta una esquina ("A y B"):
        // ahí sabemos que se busca una calle y no un negocio.
        if ($request->filled('tipo')) {
            $params['type'] = $request->input('tipo');
        }

        return $params;
    }

    /** Llama a Geoapify añadiendo la apiKey y valida la respuesta. */
    private function geoapify(string $ruta, array $query): array
    {
        $key = config('services.geoapify.key');

        if (empty($key)) {
            throw new \Exception('Falta GEOAPIFY_API_KEY en el .env del backend. Crea una cuenta gratuita en geoapify.com y pega la clave.');
        }

        $response = Http::timeout(self::TIMEOUT)->get(self::GEOAPIFY . $ruta, $query + ['apiKey' => $key]);

        if (!$response->successful()) {
            $detalle = $response->json()['message'] ?? $response->body();
            throw new \Exception('Geoapify respondió ' . $response->status() . ': ' . $detalle);
        }

        return $response->json() ?? [];
    }

    /**
     * Deja los campos más usados a mano y conserva TODO lo demás en "crudo",
     * que es lo que el panel de la demo pinta campo por campo.
     */
    private function normalizar(array $r): array
    {
        return [
            'formatted'    => $r['formatted']     ?? null,
            'linea1'       => $r['address_line1'] ?? null,
            'linea2'       => $r['address_line2'] ?? null,
            'calle'        => $r['street']        ?? null,
            'numero'       => $r['housenumber']   ?? null,
            'barrio'       => $r['suburb']        ?? $r['district'] ?? null,
            'ciudad'       => $r['city']          ?? null,
            'canton'       => $r['county']        ?? null,
            'provincia'    => $r['state']         ?? null,
            'codigoPostal' => $r['postcode']      ?? null,
            'pais'         => $r['country']       ?? null,
            'lat'          => $r['lat']           ?? null,
            'lon'          => $r['lon']           ?? null,
            'tipo'         => $r['result_type']   ?? null,
            'categoria'    => $r['category']      ?? null,
            'confianza'    => $r['rank']['confidence'] ?? null,
            'placeId'      => $r['place_id']      ?? null,
            'crudo'        => $r,
        ];
    }

    /** Redondea a ~11 m para que el caché acierte al arrastrar el marcador. */
    private function claveCache(string $prefijo, float $lat, float $lon): string
    {
        return sprintf('geo:%s:%.4f:%.4f', $prefijo, $lat, $lon);
    }

    private function pais(): string
    {
        return config('services.geoapify.pais', 'ec');
    }

    private function ok($data)
    {
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    private function fail(\Exception $e)
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Datos inválidos: ' . implode(' ', $e->validator->errors()->all()),
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
