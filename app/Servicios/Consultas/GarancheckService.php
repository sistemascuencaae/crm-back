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

    // El proveedor se pone lento por rachas (se lo ha visto tardar minutos). Hasta aquí se espera;
    // pasado esto se da por caído y entra el plan B (Ecuador Legal / SRI). Ojo al subirlo: mientras
    // se espera, el corredor mira la pantalla y se ocupa un worker de PHP.
    private const TIMEOUT_SEGUNDOS = 180;

    // Subtabla del buró con la deuda histórica por fuente (SEPS / SICOM) y mes. De ahí salen
    // la fecha de corte y las instituciones de las políticas "... Hasta 12 Meses".
    private const TABLA_DEUDA_HISTORICA = 'recursivo deuda historica 3601';

    // Política -> columnas donde buscar el máximo. En "Vencido" son los TRAMOS DE MORA de
    // hasta 12 meses (no una ventana de tiempo: 0a1 significa vencido entre 0 y 1 mes).
    private const COLUMNAS_POLITICA = [
        'vencido' => ['vencido0a1', 'vencido1a2', 'vencido2a3', 'vencido3a6', 'vencido6a9', 'vencido9a12'],
        'demanda judicial' => ['demandaJudicial'],
        'cartera castigada' => ['carteraCastigada'],
    ];

    // POLÍTICAS CRÍTICAS (pedido del departamento de crédito, 22/Sep/2026).
    // Si alguna de estas sale RECHAZADA en la Evaluación de Fuentes, la evaluación entera
    // se marca rechazada aunque el proveedor la haya aprobado. Solo aplican a Fuentes.
    // Se comparan en minúsculas y sin tildes.
    private const POLITICAS_CRITICAS = [
        'edad',
        'juicios como demandado',
        'pago pendiente de pension alimenticia',
    ];

    // Rótulo del tramo, como lo muestra el proveedor en "Vencimiento:".
    private const ETIQUETAS_TRAMO = [
        'vencido0a1' => 'Vencido 0 a 1',
        'vencido1a2' => 'Vencido 1 a 2',
        'vencido2a3' => 'Vencido 2 a 3',
        'vencido3a6' => 'Vencido 3 a 6',
        'vencido6a9' => 'Vencido 6 a 9',
        'vencido9a12' => 'Vencido 9 a 12',
        'demandaJudicial' => 'Demanda Judicial',
        'carteraCastigada' => 'Cartera Castigada',
    ];

    // Va a la columna proveedor, a la búsqueda de caché y arma el nombre del parámetro.
    public function nombre(): string
    {
        return self::NOMBRE;
    }

    // Las dos evaluaciones listas para la pantalla de resultado. Al detalle del buró se le
    // agregan 'fechaCorte', 'vencimiento' e 'instituciones', que NO vienen en la evaluación:
    // hay que derivarlos de la subtabla de deuda histórica (así lo hace el portal).
    //
    // Regla, deducida comparando contra el portal: de los ÚLTIMOS 12 meses de la tabla se
    // busca el MAYOR valor entre las columnas de la política (en "Vencido", los seis tramos
    // de mora de hasta 12 meses). Ese valor da la fecha de corte, el tramo y las fuentes
    // (SEPS / SICOM) que lo componen. Si todo es 0, se toma el mes más reciente y no hay
    // instituciones. Las políticas "Mayor a 12 Meses" no llevan fecha, igual que en el portal.
    public function evaluacionParaPantalla(array $json): ?array
    {
        $evaluacion = $json['evaluacion'] ?? null;

        if (!is_array($evaluacion)) {
            return $evaluacion;
        }

        $evaluacion = $this->aplicarPoliticasCriticas($evaluacion);

        if (empty($evaluacion['evaluacionBuro']['detalleCalificacion'])) {
            return $evaluacion;
        }

        $meses = $this->mesesDeudaHistorica($json);

        if (!$meses) {
            return $evaluacion;
        }

        foreach ($evaluacion['evaluacionBuro']['detalleCalificacion'] as $i => $politica) {
            $columnas = $this->columnasDePolitica((string) ($politica['politica'] ?? ''));

            if ($columnas === null) {
                continue;
            }

            $corte = $this->corteDeLaPolitica($meses, $columnas);
            $evaluacion['evaluacionBuro']['detalleCalificacion'][$i]['fechaCorte'] = $corte['fecha'];
            $evaluacion['evaluacionBuro']['detalleCalificacion'][$i]['vencimiento'] = $corte['vencimiento'];
            $evaluacion['evaluacionBuro']['detalleCalificacion'][$i]['instituciones'] = $corte['instituciones'];
        }

        return $evaluacion;
    }

    // Regla del departamento de crédito: una POLÍTICA CRÍTICA rechazada tumba la Evaluación
    // de Fuentes completa. El veredicto del proveedor NO se pierde: queda en
    // 'resultadoProveedor', y en 'rechazoAlmespana' se deja qué políticas lo provocaron.
    private function aplicarPoliticasCriticas(array $evaluacion): array
    {
        $detalle = $evaluacion['evaluacionGeneral']['detalleCalificacion'] ?? [];

        if (!is_array($detalle) || !$detalle) {
            return $evaluacion;
        }

        $incumplidas = [];
        foreach ($detalle as $politica) {
            $nombre = (string) ($politica['politica'] ?? '');
            if (in_array($this->normalizar($nombre), self::POLITICAS_CRITICAS, true)
                && ($politica['resultadoPolitica'] ?? null) !== true) {
                $incumplidas[] = $nombre;
            }
        }

        $cabecera = $evaluacion['evaluacionGeneral']['cabecera'] ?? [];
        $evaluacion['evaluacionGeneral']['cabecera']['resultadoProveedor'] = $cabecera['resultado'] ?? null;
        $evaluacion['evaluacionGeneral']['rechazoAlmespana'] = $incumplidas;

        if ($incumplidas) {
            $evaluacion['evaluacionGeneral']['cabecera']['resultado'] = false;
        }

        return $evaluacion;
    }

    // Minúsculas y sin tildes, para que el nombre case aunque cambie el acento.
    private function normalizar(string $texto): string
    {
        $sinTildes = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        return trim(mb_strtolower($sinTildes !== false ? $sinTildes : $texto, 'UTF-8'));
    }

    // Filas de la deuda histórica agrupadas por mes, solo los últimos 12.
    private function mesesDeudaHistorica(array $json): array
    {
        $tabla = $json['buroCredito']['buroCreditoEquifax']['resultados'][self::TABLA_DEUDA_HISTORICA] ?? [];

        if (!is_array($tabla) || !$tabla) {
            return [];
        }

        $porMes = [];
        foreach ($tabla as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $fecha = substr((string) ($fila['fechaCorte'] ?? ''), 0, 10);
            if ($fecha === '') {
                continue;
            }
            $porMes[$fecha][] = $fila;
        }

        ksort($porMes);

        return array_slice($porMes, -12, null, true);
    }

    // 'Máximo Valor Total Vencido Hasta 12 Meses' -> los seis tramos de mora. null si no aplica.
    private function columnasDePolitica(string $politica): ?array
    {
        $texto = mb_strtolower($politica, 'UTF-8');

        if (strpos($texto, 'hasta 12 meses') === false) {
            return null;
        }

        foreach (self::COLUMNAS_POLITICA as $clave => $columnas) {
            if (strpos($texto, $clave) !== false) {
                return $columnas;
            }
        }

        return null;
    }

    // Mayor valor del período: devuelve su mes, su tramo y las fuentes que lo componen.
    private function corteDeLaPolitica(array $meses, array $columnas): array
    {
        $mejor = ['valor' => -1, 'fecha' => null, 'columna' => null];

        // De más reciente a más antiguo: con valores iguales (típico, todo en 0) gana el primero.
        foreach (array_reverse($meses, true) as $fecha => $filas) {
            foreach ($filas as $fila) {
                foreach ($columnas as $columna) {
                    $valor = (float) ($fila[$columna] ?? 0);
                    if ($valor > $mejor['valor']) {
                        $mejor = ['valor' => $valor, 'fecha' => $fecha, 'columna' => $columna];
                    }
                }
            }
        }

        // Las fuentes (SEPS / SICOM) que aportan a ese tramo en ese mes.
        $instituciones = [];
        foreach ($meses[$mejor['fecha']] ?? [] as $fila) {
            $valor = (float) ($fila[$mejor['columna']] ?? 0);
            if ($valor > 0) {
                $instituciones[] = [
                    'institucion' => trim((string) ($fila['opcion'] ?? '')),
                    'valor' => $valor,
                ];
            }
        }

        return [
            'fecha' => $mejor['fecha'],
            // El rótulo solo tiene sentido si hay algo que mostrar.
            'vencimiento' => $instituciones ? (self::ETIQUETAS_TRAMO[$mejor['columna']] ?? null) : null,
            'instituciones' => $instituciones,
        ];
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

        // Estándar del ERP para empresas: la razón social COMPLETA en apellidos y un punto en
        // nombres (es como la guardan el modal del CRM y el formulario STS). Antes se usaba
        // ConsultasService::partirRazonSocialEmpresa, que la partía mitad/mitad por palabras.
        $partes = [
            'apellidos' => $razonSocial !== '' ? mb_strtoupper($razonSocial, 'UTF-8') : null,
            'nombres' => $razonSocial !== '' ? '.' : null,
        ];

        [$provincia, $canton, $parroquia, $calle] = ConsultasService::partirDireccionSri((string) ($empresa['direccion'] ?? ''));
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
