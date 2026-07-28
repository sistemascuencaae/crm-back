<?php

namespace App\Http\Controllers\varios;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\ValidacionCedulaRucService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

            // Aunque el SRI falle, el front necesita saber si el RUC es de Natural o Jurídica para marcar el
            // Tipo Persona: se devuelve el deducido del propio número (dígito verificador) en el 'data' del
            // error. El front lo usa SIN darlo por consultado (no sella el throttle ni escribe nombres).
            $tipoSujetoLocal = ['tipo_sujeto' => ValidacionCedulaRucService::tipoSujetoPorIdentificacion($identificacion, 2)];

            if (!$response->successful()) {
                return response()->json(RespuestaApi::returnResultado('error', 'El SRI no respondio correctamente', $tipoSujetoLocal));
            }

            $data = $response->json();

            if (empty($data) || !isset($data[0])) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron datos en el SRI', $tipoSujetoLocal));
            }

            // Devolvemos tal cual todo lo que entrega el SRI para ese RUC.
            $resultado = $data[0];

            // El SRI nos dice si es persona o empresa en 'tipoContribuyente'.
            if (($resultado['tipoContribuyente'] ?? null) === 'PERSONA NATURAL') {
                // Persona: convencion ecuatoriana (2 apellidos + nombres).
                $partes = $this->separarNombreCompleto($resultado['razonSocial'] ?? '');
            } else {
                // Sociedad: no tiene apellidos, partimos la razon social en 2 mitades por palabras.
                $partes = $this->partirRazonSocialEmpresa($resultado['razonSocial'] ?? '');
            }
            $resultado['apellidos'] = $partes['apellidos'];
            $resultado['nombres'] = $partes['nombres'];

            // Tipo Persona resuelto AQUI (no en el front): el SRI es la fuente autoritativa. Si por lo que sea
            // no mandó 'tipoContribuyente', se cae al deducido del número. Con esto el modal de cliente marca
            // Natural/Jurídica solo, y de paso 'razonSocial' entra completa en "Nombre Empresa" (antes el front
            // decidía con el tipo aún en Natural y guardaba la razón social partida a la mitad).
            $tipoContribuyente = trim((string) ($resultado['tipoContribuyente'] ?? ''));
            $resultado['tipo_sujeto'] = $tipoContribuyente !== ''
                ? ($tipoContribuyente === 'PERSONA NATURAL' ? 'N' : 'J')
                : $tipoSujetoLocal['tipo_sujeto'];

            // Caché (log append-only: 1 fila por CADA consulta, con quién la hizo). Best-effort.
            $this->cachearConsultaIdentidad('SRI', $identificacion, $resultado, auth('api')->id());
            // Sella la fecha de última consulta en la entidad (si existe) YA, no al guardar: así el throttle
            // es exacto aunque el usuario consulte y cancele, y el caché no se llena de re-consultas.
            $this->marcarUltimaConsultaEntidad($identificacion);

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

            // Este endpoint solo acepta cédulas, y una persona jurídica NO tiene cédula: el Tipo Persona es
            // Natural pase lo que pase, así que viaja también cuando Ecuador Legal falla.
            $tipoSujetoLocal = ['tipo_sujeto' => 'N'];

            if (!$response->successful()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Ecuador Legal no respondio correctamente', $tipoSujetoLocal));
            }

            $nombre = $this->extraerNombreHtml($response->body());

            if (empty($nombre)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontraron datos en Ecuador Legal', $tipoSujetoLocal));
            }

            $partes = $this->separarNombreCompleto($nombre);

            $resultado = [
                'fuente' => 'ECUADOR LEGAL',
                'identificacion' => $identificacion,
                'nombre' => mb_strtoupper($nombre, 'UTF-8'),
                'apellidos' => $partes['apellidos'],
                'nombres' => $partes['nombres'],
                'tipo_sujeto' => 'N',
            ];

            // Caché (log append-only: 1 fila por CADA consulta, con quién la hizo). Best-effort.
            $this->cachearConsultaIdentidad('ECUADOR_LEGAL', $identificacion, $resultado, auth('api')->id());
            // Sella la fecha de última consulta en la entidad (si existe) YA, no al guardar: así el throttle
            // es exacto aunque el usuario consulte y cancele, y el caché no se llena de re-consultas.
            $this->marcarUltimaConsultaEntidad($identificacion);

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

    // Separar razon social en apellidos y nombres
    private function partirRazonSocialEmpresa($nombreCompleto)
    {
        // Normalizamos espacios multiples y bordes.
        $nombreCompleto = trim(preg_replace('/\s+/', ' ', (string) $nombreCompleto));

        if ($nombreCompleto === '') {
            return ['apellidos' => null, 'nombres' => null];
        }

        $partes = explode(' ', $nombreCompleto);

        // Una sola palabra: va toda en apellidos.
        if (count($partes) === 1) {
            return ['apellidos' => mb_strtoupper($partes[0], 'UTF-8'), 'nombres' => null];
        }

        // Punto de corte: primera mitad (redondeada hacia arriba) -> apellidos, resto -> nombres.
        $corte = (int) ceil(count($partes) / 2);
        $apellidos = implode(' ', array_slice($partes, 0, $corte));
        $nombres = implode(' ', array_slice($partes, $corte));

        return [
            'apellidos' => $apellidos !== '' ? mb_strtoupper($apellidos, 'UTF-8') : null,
            'nombres' => $nombres !== '' ? mb_strtoupper($nombres, 'UTF-8') : null,
        ];
    }

    // Caché de identidad: LOG APPEND-ONLY (un INSERT por CADA consulta a la fuente externa, no 1 fila por
    // persona). tipo_consulta = 'SRI' | 'ECUADOR_LEGAL'; usu_id = usuario del CRM que la disparó. Columnas
    // comunes planas (nombres, apellidos) + la respuesta COMPLETA en 'respuesta' jsonb (ahí caen los
    // objetos/arrays anidados de cada fuente). ?::jsonb evita el error text->jsonb. Best-effort: si falla
    // (p.ej. usu_id null con la columna NOT NULL), solo se loguea y NO rompe la consulta.
    private function cachearConsultaIdentidad(string $tipo, string $identificacion, array $resultado, ?int $usuId): void
    {
        try {
            DB::insert(
                'INSERT INTO crm.consulta_identidad
                   (tipo_consulta, identificacion, nombres, apellidos, respuesta, usu_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?::jsonb, ?, now(), now())',
                [
                    $tipo,
                    $identificacion,
                    $resultado['nombres'] ?? null,
                    $resultado['apellidos'] ?? null,
                    json_encode($resultado, JSON_UNESCAPED_UNICODE),
                    $usuId,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo cachear la consulta de identidad (' . $tipo . '): ' . $e->getMessage());
        }
    }

    // Sella entidad.ent_fecha_ultima_consulta_identidad = now() para la(s) entidad(es) que coinciden en los
    // primeros 10 dígitos (mismo criterio que el buscador cédula-vs-RUC). Es la fuente del throttle: al sellar
    // aquí (al consultar, no al guardar) el cálculo "¿re-consulto?" es exacto aunque el usuario cancele.
    // Si la identidad aún NO existe como entidad (alta nueva sin guardar), no actualiza nada (0 filas) y la
    // fecha se sella al crear la entidad (fn_entidad_crear). Best-effort: si falla, no rompe la consulta.
    private function marcarUltimaConsultaEntidad(string $identificacion): void
    {
        try {
            DB::update(
                'UPDATE entidad SET ent_fecha_ultima_consulta_identidad = now()
                 WHERE substring(TRIM(ent_identificacion) from 1 for 10) = substring(TRIM(?) from 1 for 10)',
                [$identificacion]
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo sellar la fecha de última consulta en entidad: ' . $e->getMessage());
        }
    }
}
