<?php

namespace App\Servicios\Consultas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

// Proveedor GaranCheck / Plataforma 360°. TODO lo que sabe del JSON de este proveedor vive aquí;
// el controlador solo usa nombre() / consultar() / extraerDatosCliente(). Otro proveedor mañana =
// otra clase con estos 3 métodos y su parámetro CONSULTA-<NOMBRE>.
class GarancheckService
{
    private const NOMBRE = 'GARANCHECK';
    private const TIMEOUT_SEGUNDOS = 30;

    // Va a la columna proveedor, a la búsqueda de caché y arma el nombre del parámetro.
    public function nombre(): string
    {
        return self::NOMBRE;
    }

    // POST Basic Auth con el body del parámetro CONSULTA-GARANCHECK. Cualquier fallo lanza
    // RuntimeException con un mensaje para el usuario: red/timeout, HTTP no-2xx, o un error del
    // catálogo del proveedor ({codigo, mensaje}). El que llama no guarda nada si esto lanza.
    public function consultar(string $identificacion): array
    {
        $config = config('services.garancheck');
        $url = trim((string) ($config['url'] ?? ''));
        $user = (string) ($config['user'] ?? '');
        $pass = (string) ($config['pass'] ?? '');

        if ($url === '' || $user === '' || $pass === '') {
            throw new RuntimeException('Proveedor no configurado, comuníquese con el administrador.');
        }

        $body = $this->bodyConsulta($identificacion);

        try {
            $response = Http::withBasicAuth($user, $pass)
                ->acceptJson()
                ->timeout(self::TIMEOUT_SEGUNDOS)
                ->post($url, $body);
        } catch (Throwable $e) {
            throw new RuntimeException('El proveedor no respondió: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new RuntimeException('El proveedor respondió con error HTTP ' . $response->status());
        }

        $json = $response->json();
        if (!is_array($json) || empty($json)) {
            throw new RuntimeException('El proveedor devolvió una respuesta vacía o inválida.');
        }

        // Error del catálogo (códigos 1-8): viene {codigo, mensaje} en vez del reporte.
        if (isset($json['codigo']) && array_key_exists('mensaje', $json) && !isset($json['civil']) && !isset($json['sri'])) {
            $mensaje = trim((string) $json['mensaje']);
            throw new RuntimeException('Proveedor: ' . ($mensaje !== '' ? $mensaje : 'error código ' . $json['codigo']));
        }

        return $json;
    }

    // Datos mínimos para el alta/actualización del cliente en el CRM. $tipoSujeto ('N'/'J') lo
    // decide el número de identificación, no el JSON: un RUC de persona natural trae sus datos en
    // 'civil'; solo la sociedad los trae en 'sri'.
    public function extraerDatosCliente(array $json, string $tipoSujeto): array
    {
        $datos = $tipoSujeto === 'J' ? $this->datosJuridica($json) : $this->datosNatural($json);

        // Sin identidad no hay cliente: 'civil' (natural) o 'sri' (jurídica) vienen null o sin
        // nombres cuando la fuente está caída. Se lanza para que el que llama NO guarde nada.
        if ($datos['nombres'] === null && $datos['apellidos'] === null) {
            $rama = $tipoSujeto === 'J' ? 'sri' : 'civil';
            throw new RuntimeException("El proveedor no devolvió los datos de identidad ({$rama} sin datos); no se pudo registrar al cliente.");
        }

        return $datos;
    }

    // =====================================================================================
    // PRIVADOS
    // =====================================================================================

    private function bodyConsulta(string $identificacion): array
    {
        $parametro = DB::table('crm.parametro')->where('abreviacion', 'CONSULTA-' . self::NOMBRE)->first();
        $body = $parametro ? json_decode((string) $parametro->valor, true) : null;

        if (!is_array($body)) {
            throw new RuntimeException('No está configurado el parámetro CONSULTA-' . self::NOMBRE . ', comuníquese con el administrador.');
        }

        $body['identificacion'] = $identificacion;

        return $body;
    }

    // Persona natural: civil.general.nombres viene "APELLIDOS NOMBRES" en una sola cadena.
    // Dirección: el domicilio del Registro Civil tal cual lo manda el proveedor (personal.nombreCalle +
    // numeroCasa, aunque el número sea "S") → general.calle → la de clave más alta de
    // general.direcciones → contactos.direcciones → 'SN'.
    private function datosNatural(array $json): array
    {
        $general = $json['civil']['general'] ?? [];
        $personal = $json['civil']['personal'] ?? [];
        $contactos = $json['civil']['contactos'] ?? [];

        $partes = ConsultasService::separarNombreCompleto($general['nombres'] ?? '');
        $nombreComercial = trim(($partes['apellidos'] ?? '') . ' ' . ($partes['nombres'] ?? ''));

        $calle = trim(trim((string) ($personal['nombreCalle'] ?? '')) . ' ' . trim((string) ($personal['numeroCasa'] ?? '')));
        if ($calle === '') {
            $calle = $this->primerValor($general['calle'] ?? null, $general['direcciones'] ?? null, $contactos['direcciones'] ?? null);
        }

        return [
            'tipo_sujeto' => 'N',
            'nombres' => $partes['nombres'],
            'apellidos' => $partes['apellidos'],
            'nombre_comercial' => $nombreComercial !== '' ? $nombreComercial : null,
            'email' => $this->primerValor($general['correos'] ?? null, $contactos['correos'] ?? null) ?: null,
            'telefono' => $this->telefono($general['telefonos'] ?? null, $contactos['telefonos'] ?? null),
            'direccion' => $calle !== '' ? mb_strtoupper($calle, 'UTF-8') : 'SN',
            'provincia' => $general['provincia'] ?? null,
            'canton' => $general['canton'] ?? null,
            'parroquia' => $general['parroquia'] ?? null,
        ];
    }

    // Persona jurídica: razón social en sri.empresa; 'civil' es el representante legal y NO se usa.
    // sri.empresa.direccion viene "PROVINCIA / CANTON / PARROQUIA / CALLE".
    private function datosJuridica(array $json): array
    {
        $empresa = $json['sri']['empresa'] ?? [];
        $contactos = $json['sri']['contactos'] ?? [];

        $razonSocial = trim(preg_replace('/\s+/', ' ', (string) ($empresa['razonSocial'] ?? '')));
        $partes = ConsultasService::partirRazonSocialEmpresa($razonSocial);

        [$provincia, $canton, $parroquia, $calle] = $this->partirDireccionSri((string) ($empresa['direccion'] ?? ''));
        if ($calle === '') {
            $calle = $this->primerValor($contactos['direcciones'] ?? null);
        }

        return [
            'tipo_sujeto' => 'J',
            'nombres' => $partes['nombres'],
            'apellidos' => $partes['apellidos'],
            'nombre_comercial' => $razonSocial !== '' ? mb_strtoupper($razonSocial, 'UTF-8') : null,
            'email' => $this->primerValor($contactos['correos'] ?? null) ?: null,
            'telefono' => $this->telefono($contactos['telefonos'] ?? null),
            'direccion' => $calle !== '' ? mb_strtoupper($calle, 'UTF-8') : 'SN',
            'provincia' => $provincia,
            'canton' => $canton,
            'parroquia' => $parroquia,
        ];
    }

    // "PICHINCHA / QUITO / BELISARIO QUEVEDO / RUIZ DE LA CASTILLA N30-13 Y ANDAGOYA"
    // → [provincia, cantón, parroquia, calle]. Con menos de 4 tramos no hay geo: todo es calle.
    // El separador es " / " con espacios: una calle "S/N" no se parte.
    private function partirDireccionSri(string $direccion): array
    {
        $tramos = array_values(array_filter(array_map('trim', preg_split('#\s+/\s+#', $direccion)), 'strlen'));

        if (count($tramos) < 4) {
            return [null, null, null, trim($direccion)];
        }

        return [$tramos[0], $tramos[1], $tramos[2], implode(' / ', array_slice($tramos, 3))];
    }

    // Celular ecuatoriano (09 + 8 dígitos) de la fuente más reciente; si no hay, el primer número
    // no vacío; si no, 'SN'. Se guardan solo dígitos ("2-837506" → "2837506").
    private function telefono(...$fuentes): string
    {
        $candidatos = [];
        foreach ($fuentes as $fuente) {
            foreach ($this->valores($fuente) as $valor) {
                $digitos = preg_replace('/\D+/', '', (string) $valor);
                if ($digitos !== '') {
                    $candidatos[] = $digitos;
                }
            }
        }

        foreach ($candidatos as $numero) {
            if (preg_match('/^09\d{8}$/', $numero)) {
                return $numero;
            }
        }

        return $candidatos[0] ?? 'SN';
    }

    // Primer valor no vacío recorriendo las fuentes en orden (cada una: cadena, lista o mapa).
    private function primerValor(...$fuentes): string
    {
        foreach ($fuentes as $fuente) {
            foreach ($this->valores($fuente) as $valor) {
                $valor = trim((string) $valor);
                if ($valor !== '') {
                    return $valor;
                }
            }
        }

        return '';
    }

    // Los mapas de civil.general.* llevan claves numéricas ("7", "6", …) y la más alta es la más
    // reciente: se recorren de mayor a menor (las listas de contactos.* también, por uniformidad).
    private function valores($fuente): array
    {
        if (is_string($fuente) || is_numeric($fuente)) {
            return [$fuente];
        }
        if (!is_array($fuente)) {
            return [];
        }

        krsort($fuente, SORT_NUMERIC);

        return array_values(array_filter($fuente, 'is_scalar'));
    }
}
