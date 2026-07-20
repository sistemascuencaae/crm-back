<?php

namespace App\Http\Controllers\varios;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\Http;

class ConsultaIdentidadExternoController extends Controller
{
    const URL_SRI = 'https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/obtenerPorNumerosRuc';
    const URL_ECUADOR_LEGAL = 'https://www.ecuadorlegalonline.com/modulo/consultar-cedula.php';

    // Esto es obligatorio para estos dos endpoints, sino no responden los endpoints
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    // Particulas que forman parte de un apellido compuesto (ej. "De La Rosa", "Del Pozo").
    const PARTICULAS_APELLIDO = ['de', 'del', 'la', 'las', 'los', 'san', 'santa', 'da', 'di', 'do', 'y', 'e'];

    // Acepta solo RUC's esta página del SRI.
    public function consultarRucSri($ruc)
    {
        try {
            $identificacion = trim($ruc);

            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT, // OBLIGATORIO: sin este header el SRI no responde
                'Accept' => 'application/json',
            ])->timeout(4) // aborta si el SRI no responde en 4 segundos
                ->get(self::URL_SRI, ['ruc' => $identificacion]);

            if (!$response->successful()) {
                return response()->json(RespuestaApi::returnResultado('error', 'El SRI no respondio correctamente', null));
            }

            $data = $response->json();

            if (empty($data) || !isset($data[0])) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron datos en el SRI', null));
            }

            // Devolvemos tal cual todo lo que entrega el SRI para ese RUC.
            $resultado = $data[0];

            // Separamos nombres y apellidos solo si es persona natural (una sociedad no tiene apellidos).
            if (($resultado['tipoContribuyente'] ?? null) === 'PERSONA NATURAL') {
                $partes = $this->separarNombreCompleto($resultado['razonSocial'] ?? '');
                $resultado['apellidos'] = $partes['apellidos'];
                $resultado['nombres'] = $partes['nombres'];
            } else {
                $resultado['apellidos'] = null;
                $resultado['nombres'] = null;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $resultado));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Al consultar SRI', $th->getMessage()));
        }
    }

    // Esta página de Ecuador Legal solo acepta cédulas
    public function consultarCedulaEcuadorLegal($cedula)
    {
        try {
            $identificacion = trim($cedula);

            $response = Http::asForm()->withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Referer' => 'https://www.ecuadorlegalonline.com/consultas/registro-civil/consultar-cedulas/',
                'X-Requested-With' => 'XMLHttpRequest',
                'Origin' => 'https://www.ecuadorlegalonline.com',
            ])->timeout(4) // aborta si Ecuador Legal no responde en 4 segundos
                ->post(self::URL_ECUADOR_LEGAL, ['name' => $identificacion, 'tipo' => 'I']);

            if (!$response->successful()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Ecuador Legal no respondio correctamente', null));
            }

            $nombre = $this->extraerNombreHtml($response->body());

            if (empty($nombre)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron datos en Ecuador Legal', null));
            }

            $partes = $this->separarNombreCompleto($nombre);

            $resultado = [
                'fuente' => 'ECUADOR LEGAL',
                'identificacion' => $identificacion,
                'nombre' => mb_strtoupper($nombre, 'UTF-8'),
                'apellidos' => $partes['apellidos'],
                'nombres' => $partes['nombres'],
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $resultado));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Al consultar Ecuador Legal', $th->getMessage()));
        }
    }

    // Extrae el nombre del HTML que devuelve Ecuador Legal dentro de las etiquetas <strong>
    private function extraerNombreHtml($html)
    {
        if (empty(trim($html))) {
            return null;
        }

        $dom = new \DOMDocument();
        // Silenciar warnings de HTML mal formado.
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Primero intentamos el strong dentro de #name0.
        $nodos = $xpath->query('//*[@id="name0"]//strong');
        if ($nodos->length === 0) {
            // Fallback: primer <strong> del fragmento.
            $nodos = $xpath->query('//strong');
        }

        if ($nodos->length > 0) {
            $texto = trim($nodos->item(0)->textContent);
            return $texto !== '' ? $texto : null;
        }

        return null;
    }

    // Separar nombre completo en apellidos y nombres
    private function separarNombreCompleto($nombreCompleto)
    {
        // Normalizamos espacios multiples y bordes.
        $nombreCompleto = trim(preg_replace('/\s+/', ' ', (string) $nombreCompleto));

        if ($nombreCompleto === '') {
            return ['apellidos' => null, 'nombres' => null];
        }

        $partes = explode(' ', $nombreCompleto);

        // Con 2 palabras o menos es ambiguo: la primera es apellido, el resto nombre.
        if (count($partes) <= 2) {
            $apellidos = array_shift($partes);
            $nombres = implode(' ', $partes);

            return [
                'apellidos' => $apellidos !== '' ? mb_strtoupper($apellidos, 'UTF-8') : null,
                'nombres' => $nombres !== '' ? mb_strtoupper($nombres, 'UTF-8') : null,
            ];
        }

        // Recorremos armando 2 "unidades de apellido"; cada unidad absorbe las
        // particulas que la anteceden y una palabra nucleo.
        $i = 0;
        $total = count($partes);
        $apellidosCompletados = 0;

        while ($i < $total && $apellidosCompletados < 2) {
            // Absorbemos particulas consecutivas.
            while ($i < $total && in_array(mb_strtolower($partes[$i]), self::PARTICULAS_APELLIDO, true)) {
                $i++;
            }
            // Absorbemos la palabra nucleo del apellido.
            if ($i < $total) {
                $i++;
            }
            $apellidosCompletados++;
        }

        $apellidos = implode(' ', array_slice($partes, 0, $i));
        $nombres = implode(' ', array_slice($partes, $i));

        return [
            'apellidos' => $apellidos !== '' ? mb_strtoupper($apellidos, 'UTF-8') : null,
            'nombres' => $nombres !== '' ? mb_strtoupper($nombres, 'UTF-8') : null,
        ];
    }
}
