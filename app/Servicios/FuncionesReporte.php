<?php

namespace App\Servicios;


class FuncionesReporte
{

    function famChecbox($variable)
    {

        if ($variable == true) {
            return "X";
        } else {
            return "";
        }
    }

    function famRadioButton($variable)
    {
        $a = array('', '', '', '');
        switch ($variable) {
            case 'APTO':
                $a[0] = "X";
                break;
            case 'APTO EN OBSERVACION':
                $a[1] = "X";
                break;
            case 'APTO CON LIMITACIONES':
                $a[2] = "X";
                break;
            case 'NO APTO':
                $a[3] = "X";
                break;
            default:
                # code...
                break;
        }

        return $a;
    }




    function famChecboxSINO($variable1, $variable2)
    {

        $result = array(
            '','','',''
        );

        if ($variable1) {
           $result[0] = 'X';
        } else {
            $result[1] = 'X';
        }
        if ($variable2) {
            $result[2] = 'X';
        } else {
            $result[3] = 'X';
        }

        return $result;
    }
}
