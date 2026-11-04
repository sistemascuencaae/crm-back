<?php

namespace App\Exceptions;

// 30-oct: cancelación de una ejecución del API Client: el usuario dejó la marca en Cache (api_client_cancelar_{uid}),
// el callback de progreso de cURL abortó la transferencia y el proxy la traduce a error_tipo 'cancelado'.
class ApiCanceladoException extends \RuntimeException
{
    public function __construct(string $mensaje = 'Petición cancelada por el usuario', ?\Throwable $anterior = null)
    {
        parent::__construct($mensaje, 0, $anterior);
    }
}
