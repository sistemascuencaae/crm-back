<?php

namespace App\Http\Traits;

trait FormatResponseTrait
{


    public function insertOk($data)
    {
        return response([
            'err' => false,
            'message' => 'Registrado correctamente',
            'data' => $data,
        ], 200);
    }

    public function insertErr()
    {
        return response([
            'err' => true,
            'message' => 'Error al guardar los datos',
            'data' => null,
        ], 500);
    }

    public function insertOkCustom($data, $message)
    {
        return response([
            'err' => false,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    public function insertErrCustom($data, $message)
    {
        return response([
            'err' => true,
            'message' => $message,
            'data' => $data,
        ], 500);
    }



    public function updateOk($data)
    {
        return response([
            'err' => false,
            'message' => 'Actualizado correctamente',
            'data' => $data,
        ], 200);
    }

    public function updateErr()
    {
        return response([
            'err' => true,
            'message' => 'Error al actualizar los datos',
            'data' => null,
        ], 500);
    }

    public function updateErrMsg($data)
    {
        return response([
            'err' => true,
            'message' => 'Error al actualizar los datos',
            'data' => $data,
        ], 500);
    }


    public function updateOkCustom($data, $message)
    {
        return response([
            'err' => false,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    public function updateErrCustom($data, $message)
    {
        return response([
            'err' => true,
            'message' => $message,
            'data' => $data,
        ], 500);
    }

    public function deleteOk($data)
    {
        return response([
            'err' => false,
            'message' => 'Eliminado correctamente',
            'data' => $data,
        ], 200);
    }

    public function deleteErr()
    {
        return response([
            'err' => true,
            'message' => 'Error al eliminar los datos',
            'data' => null,
        ], 500);
    }


    public function deleteErrCustom($data, $message)
    {
        return response([
            'err' => true,
            'message' => $message,
            'data' => $data,
        ], 400);
    }


    public function getOk($data)
    {
        return response([
            'err' => false,
            "status"=> 'success',
            'message' => 'Cargado correctamente',
            'data' => $data,
        ], 200);
    }
 
    public function getErr($message)
    {
        return response([
            'err' => true,
            'message' => $message,
            'data' => null,
        ], 500);
    }


    public function getOkCustom($data, $message)
    {
        return response([
            'err' => false,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    public function getErrCustom($data, $message)
    {
        return response([
            'err' => false,
            'message' => $message,
            'data' => $data,
        ], 500);
    }

    public function getOkPagination($data)
    {
        return response(array(
            'err' => false,
            'message' => 'Cargado correctamente',
            'pagination' => $data,
        ), 200);
    }
}
