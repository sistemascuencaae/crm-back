<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\EmailController;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\CPedidoProforma;
use Exception;

class PedidoMovilController extends Controller
{

    public function getPedidoById($cppId)
    {
        try {
            $data = CPedidoProforma::with('dpedidoProforma')->where('cpp_id', $cppId)->first();
            // echo json_encode($data);
            $email = "juanjgsj@gmail.com"; // $data->email pero como aqui no se va a llamar desde este metodo cuando se llame el metodo hay que porner el email del cliente
            $t = new EmailController();
            $t->send_email($email, $data);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
