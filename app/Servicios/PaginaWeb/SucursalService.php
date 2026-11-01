<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orquestador de la página "Nuestras tiendas".
 * Llama a las funciones ecommerce.fn_sucursal_* (que validan con RAISE EXCEPTION y devuelven
 * id / nada / filas) y arma los datos que responden los controladores.
 * Cada escritura exitosa limpia la caché del listado público.
 */
class SucursalService
{
    public const CACHE_PUBLICAS = 'pagina_web.sucursales.publicas';
    private const CACHE_MINUTOS = 10;

    // Mensaje amigable para cada código que lanzan las funciones ecommerce.fn_sucursal_*
    public const MENSAJES_ERROR = [
        'REQUIERE_USUARIO'                 => 'No se identificó al usuario que realiza el cambio.',
        // Ciudades
        'CIUDAD_NOMBRE_REQUERIDO'          => 'El nombre de la ciudad es obligatorio.',
        'CIUDAD_NOMBRE_LARGO'              => 'El nombre de la ciudad no puede superar 80 caracteres.',
        'CIUDAD_DUPLICADA'                 => 'Ya existe una ciudad con ese nombre.',
        'CIUDAD_ACTIVO_INVALIDO'           => 'El estado de la ciudad debe ser verdadero o falso.',
        'CIUDAD_NO_EXISTE'                 => 'La ciudad no existe.',
        'CIUDAD_CON_SUCURSALES'            => 'No se puede eliminar: la ciudad tiene sucursales. Muévelas a otra ciudad o elimínalas primero.',
        // Sucursales
        'SUCURSAL_CIUDAD_REQUERIDA'        => 'Selecciona la ciudad de la sucursal.',
        'SUCURSAL_CIUDAD_NO_EXISTE'        => 'La ciudad seleccionada no existe.',
        'SUCURSAL_NOMBRE_REQUERIDO'        => 'El nombre de la sucursal es obligatorio.',
        'SUCURSAL_NOMBRE_LARGO'            => 'El nombre de la sucursal no puede superar 100 caracteres.',
        'SUCURSAL_DUPLICADA'               => 'Ya existe una sucursal con ese nombre en esta ciudad.',
        'SUCURSAL_DIRECCION_REQUERIDA'     => 'La dirección es obligatoria.',
        'SUCURSAL_DIRECCION_LARGA'         => 'La dirección no puede superar 250 caracteres.',
        'SUCURSAL_HORARIO_LARGO'           => 'El horario no puede superar 150 caracteres.',
        'SUCURSAL_TELEFONO_INVALIDO'       => 'El teléfono debe tener entre 7 y 20 caracteres: números, espacios, guiones, paréntesis o +.',
        'SUCURSAL_COORDENADAS_INCOMPLETAS' => 'Ingresa la latitud y la longitud juntas, o deja ambas vacías.',
        'SUCURSAL_COORDENADAS_INVERTIDAS'  => 'Parece que la latitud y la longitud están invertidas.',
        'SUCURSAL_LATITUD_INVALIDA'        => 'La latitud no es válida o está fuera de Ecuador.',
        'SUCURSAL_LONGITUD_INVALIDA'       => 'La longitud no es válida o está fuera de Ecuador.',
        'SUCURSAL_ACTIVO_INVALIDO'         => 'El estado de la sucursal debe ser verdadero o falso.',
        'SUCURSAL_NO_EXISTE'               => 'La sucursal no existe.',
        // Orden
        'ORDEN_INVALIDO'                   => 'La lista de orden no es válida.',
        'ORDEN_DESACTUALIZADO'             => 'La lista cambió mientras ordenabas. Recarga la página e inténtalo de nuevo.',
    ];

    // -----------------------------------------------------------------
    // Ciudades
    // -----------------------------------------------------------------

    public function crearCiudad(array $datos): int
    {
        $fila = DB::selectOne('SELECT ecommerce.fn_sucursal_ciudad_crear(?::jsonb) AS sci_id', [$this->json($datos)]);
        $this->limpiarCache();

        return (int) $fila->sci_id;
    }

    public function modificarCiudad(int $sciId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_sucursal_ciudad_modificar(?::integer, ?::jsonb)', [$sciId, $this->json($datos)]);
        $this->limpiarCache();
    }

    /** La manda a la papelera */
    public function eliminarCiudad(int $sciId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_sucursal_ciudad_eliminar(?::integer, ?::integer)', [$sciId, $usuId]);
        $this->limpiarCache();
    }

    /** La saca de la papelera, al final */
    public function restaurarCiudad(int $sciId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_sucursal_ciudad_restaurar(?::integer, ?::integer)', [$sciId, $usuId]);
        $this->limpiarCache();
    }

    public function ordenarCiudades(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_sucursal_ciudad_ordenar(?::jsonb)', [$this->json($datos)]);
        $this->limpiarCache();
    }

    // -----------------------------------------------------------------
    // Sucursales
    // -----------------------------------------------------------------

    public function crear(array $datos): int
    {
        $fila = DB::selectOne('SELECT ecommerce.fn_sucursal_crear(?::jsonb) AS suc_id', [$this->json($datos)]);
        $this->limpiarCache();

        return (int) $fila->suc_id;
    }

    public function modificar(int $sucId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_sucursal_modificar(?::integer, ?::jsonb)', [$sucId, $this->json($datos)]);
        $this->limpiarCache();
    }

    /** La manda a la papelera */
    public function eliminar(int $sucId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_sucursal_eliminar(?::integer, ?::integer)', [$sucId, $usuId]);
        $this->limpiarCache();
    }

    /** La saca de la papelera, al final de su ciudad */
    public function restaurar(int $sucId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_sucursal_restaurar(?::integer, ?::integer)', [$sucId, $usuId]);
        $this->limpiarCache();
    }

    // -----------------------------------------------------------------
    // Papelera (ciudades y sucursales)
    // -----------------------------------------------------------------

    /** [{tipo ('ciudad' | 'sucursal'), id, texto, detalle, eliminado_en, eliminado_usu_id, eliminado_por}] */
    public function papelera(): array
    {
        return PapeleraPaginaWeb::agregarEliminadoPor(DB::select('SELECT * FROM ecommerce.fn_sucursal_papelera()'), 'eliminado_usu_id');
    }

    /** Borra para siempre lo que está en la papelera. Devuelve cuántos borró (los que se veían en la papelera). */
    public function vaciar(int $usuId): int
    {
        $cantidad = (int) DB::selectOne('SELECT ecommerce.fn_sucursal_vaciar(?::integer) AS cantidad', [$usuId])->cantidad;
        $this->limpiarCache();

        return $cantidad;
    }

    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_sucursal_ordenar(?::jsonb)', [$this->json($datos)]);
        $this->limpiarCache();
    }

    // -----------------------------------------------------------------
    // Lectura
    // -----------------------------------------------------------------

    /**
     * CRM: todas las ciudades (aunque estén vacías o inactivas), cada una con sus sucursales.
     * Misma forma que antes: [{sci_id, sci_nombre, ..., sucursales: [{suc_id, ...}]}]
     */
    public function listarAdmin(): array
    {
        $ciudades   = DB::select('SELECT * FROM ecommerce.fn_sucursal_ciudad_listar()');
        $sucursales = collect(DB::select('SELECT * FROM ecommerce.fn_sucursal_listar()'))
            ->map(fn ($s) => $this->normalizarCoordenadas($s))
            ->groupBy('sci_id');

        return collect($ciudades)
            ->map(fn ($c) => (array) $c + [
                'sucursales' => $sucursales->get($c->sci_id, collect())->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Tienda: solo lo activo, agrupado por ciudad, guardado en caché.
     * Misma forma que antes: [{sci_id, sci_nombre, sucursales: [{suc_id, suc_nombre, ...}]}]
     */
    public function listarPublicas(): array
    {
        return Cache::remember(self::CACHE_PUBLICAS, now()->addMinutes(self::CACHE_MINUTOS), function () {
            $filas = DB::select('SELECT * FROM ecommerce.fn_sucursal_listar_publicas()');

            return collect($filas)
                ->groupBy('sci_id')
                ->map(fn ($grupo) => [
                    'sci_id'     => $grupo[0]->sci_id,
                    'sci_nombre' => $grupo[0]->sci_nombre,
                    'sucursales' => $grupo->map(fn ($s) => [
                        'suc_id'        => $s->suc_id,
                        'suc_nombre'    => $s->suc_nombre,
                        'suc_direccion' => $s->suc_direccion,
                        'suc_horario'   => $s->suc_horario,
                        'suc_telefono'  => $s->suc_telefono,
                        'suc_latitud'   => $s->suc_latitud === null ? null : (float) $s->suc_latitud,
                        'suc_longitud'  => $s->suc_longitud === null ? null : (float) $s->suc_longitud,
                    ])->values()->all(),
                ])
                ->values()
                ->all();
        });
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    private function limpiarCache(): void
    {
        Cache::forget(self::CACHE_PUBLICAS);
    }

    /** PostgreSQL devuelve numeric como texto: se convierte a número para el JSON. */
    private function normalizarCoordenadas(object $sucursal): array
    {
        $datos = (array) $sucursal;
        $datos['suc_latitud']  = $datos['suc_latitud']  === null ? null : (float) $datos['suc_latitud'];
        $datos['suc_longitud'] = $datos['suc_longitud'] === null ? null : (float) $datos['suc_longitud'];

        return $datos;
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}