<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RespuestaApi2 extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($value)
    {
               // echo (json_encode($data));
               switch ($value->estado) {
                case 'success':
    
                    $result = array(
                        'code'      => 200,
                        'status'    => 'success',
                        'message'   =>  $value->mensaje,
                        'data'     =>  $value
                    );
                    return parent::toArray($result); 
    
                case 'error':
    
                    $result = array(
                        'code'      => 400,
                        'status'    => 'error',
                        'message'   => $value->mensaje,
                        'data'     =>  $value
                    );
                    return parent::toArray($result);
    
    
                case 'exception':
    
                    $result = array(
                        'code'      => 500,
                        'status'    => 'exception',
                        'message'   => $value->mensaje,
                        'data'     =>  $value
                    );
    
                    return parent::toArray($result);
                default:
                $result = array(
                    'code'      => 500,
                    'status'    => 'sin asignar error',
                    'message'   => $value->mensaje,
                    'data'     =>  $value
                );
                return parent::toArray($result);
            }
        //($value);
    }
}
