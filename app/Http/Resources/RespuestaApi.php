<?php

namespace App\Http\Resources;


class RespuestaApi
{

    public static function returnResultado($estado, $message, $data)
    {
        
        switch ($estado) {
            case 'success':

                $result = array(
                    'code'      => 200,
                    'status'    => 'success',
                    'message'   =>  $message,
                    'data'     =>  $data
                );
                return $result; 

            case 'error':

                $result = array(
                    'code'      => 400,
                    'status'    => 'error',
                    'message'   => $message,
                    'data'     =>  $data
                );
                return $result;


            case 'exception':

                $result = array(
                    'code'      => 500,
                    'status'    => 'exception',
                    'message'   => $message,
                    'data'     =>  $data
                );
return $result;

            default:
            $result = array(
                'code'      => 500,
                'status'    => 'sin asignar error',
                'message'   => $message,
                'data'     =>  $data
            );
                break;
        }
    }
}
