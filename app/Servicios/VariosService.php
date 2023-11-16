<?php

namespace App\Servicios;

use DateTime;

class VariosService
{



    function vempresas()
    {
        return array(
            "ALMACENES ESPANA" => 1,
            "PROYLECMA" => 2,
        );
    }

    function vrucempresa()
    {
        return array(
            "0190386465001" => 1,
            "0190478459001" => 2,
        );
    }


    function vlistaLateralidad()
    {
        return array(
            "IZQUIERDO" => 1,
            "DERECHO" => 2,
            "AMBIDIESTRO" => 3,
        );
    }

    function vtipoSangre()
    {
        return array(
            "A+" => 1,
            "O+" => 2,
            "B+" => 3,
            "AB+" => 4,
            "A-" => 5,
            "0-" => 6,
            "B-" => 7,
            "AB-" => 8,
        );
    }


    function vsexoLista()
    {
        return array(
            "MASCULINO" => 1,
            "FEMENINO" => 2,
        );
    }

    function edad($fecha_nacimiento)
    {
        $nacimiento = new DateTime($fecha_nacimiento);
        $ahora = new DateTime(date("Y-m-d"));
        $diferencia = $ahora->diff($nacimiento);
        return $diferencia->format("%y");
    }
    function vreligion($religion, $otraReligion)
    {
        $resul = array('','','','','','');
        ($religion == 1)?$resul[0] = 'X':'';
        ($religion == 2)?$resul[1] = 'X':'';
        ($religion == 3)?$resul[2] = 'X':'';
        ($religion == 4)?$resul[3] = 'X':'';
        ($religion == 5)?$resul[4] = $otraReligion:'';
        return $resul;
    }


    function arraySize4($valor)
    {
        $resul = array('','','','','');
        ($valor == 1)?$resul[0] = 'X':'';
        ($valor == 2)?$resul[1] = 'X':'';
        ($valor == 3)?$resul[2] = 'X':'';
        ($valor == 4)?$resul[3] = 'X':'';
        ($valor == 5)?$resul[4] = 'X':'';
        return $resul;
    }

    function arraySize2($valor)
    {
        $resul = array('','');
        ($valor == true)?$resul[0] = 'X':'';
        ($valor == false)?$resul[1] = 'X':'';
        return $resul;
    }

    function vtiposDiscapacidad($valor, $valor2)
    {
       if($valor == 1){
        return $valor2;
       }
       if($valor == 2){
        return "COGNITIVO";
       }
       if($valor == 3){
        return "FISICA";
       }
    }

    function nAptitudMedica($valor)
    {
        $resul = array('','','','');
       if($valor == 'APTO'){
        ($valor == true)?$resul[0] = 'X':'';
       }
       if($valor == 'APTO EN OBSERVACION'){
        ($valor == true)?$resul[1] = 'X':'';
       }
       if($valor == 'APTO CON LIMITACIONES'){
        ($valor == true)?$resul[2] = 'X':'';
       }
       if($valor == 'NO APTO'){
        ($valor == true)?$resul[3] = 'X':'';
       }

       return $resul;
    }














}
