<?php

return [
    /*
     * ============== PIN PAD FISICO (DESTINO TCP) ==============
     * Tu Laravel abre socket aqui y envia/recibe tramas.
     * El Pin Pad luego habla con el switch Medianet por su cuenta.
     */
    'ip'   => env('PINPAD_IP', '192.168.1.242'),
    'port' => (int) env('PINPAD_PORT', 6500),

    /*
     * ============== PARAMETROS DE RED (informativo / CP) ==============
     */
    'pinpad_mask'    => env('PINPAD_MSK', '255.255.255.0'),
    'pinpad_gateway' => env('PINPAD_GW',  '192.168.1.200'),

    /*
     * ============== HOST MEDIANET (informativo) ==============
     * IP del switch al que el Pin Pad sale internamente.
     * Tu Laravel NO lo alcanza ni lo necesita.
     */
    'host_medianet' => env('PINPAD_HOST', '10.10.3.200'),

    /*
     * ============== IDENTIFICADORES DEL COMERCIO ==============
     */
    'mid'          => env('PINPAD_MID', ''),
    'tid'          => env('PINPAD_TID', ''),
    'cid_terminal' => env('PINPAD_CID_TERMINAL', ''),

    /*
     * ============== TIMEOUT Y MODO ==============
     */
    'timeout_ms' => (int) env('PINPAD_TIMEOUT_MS', 30000),
    'modo'       => env('PINPAD_MODO', 'exclusivo'),

    /*
     * ============== CACHE DE REVERSO ==============
     * TTL en minutos. La libreria oficial mantiene la trama de reverso en
     * memoria mientras la app este abierta (sin TTL explicito). Manual
     * 4.1.4: "el sistema intentara reversar la ultima transaccion".
     *
     * Default 480 min (8 horas) = duracion de un turno tipico de caja.
     * Ajusta segun tus horarios:
     *   - turno simple 8h  → 480
     *   - turno doble 16h  → 960
     *   - 24/7             → 1440 (max razonable)
     *   - reverso solo automatico tras timeout → 5
     */
    'reverso_ttl_minutes' => (int) env('PINPAD_REVERSO_TTL_MINUTES', 480),
];
