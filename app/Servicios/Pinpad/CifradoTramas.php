<?php

namespace App\Servicios\Pinpad;

// Importamos la clase DES de phpseclib porque OpenSSL en PHP 8 no trae DES por
// defecto (lo movieron al "legacy provider"). phpseclib la implementa en PHP puro.
use phpseclib3\Crypt\DES;

/**
 * CifradoTramas — replica EXACTA del algoritmo de hash que usa la libreria
 * oficial Medianet (`cajapinpad.CifradoTramas` del JAR).
 *
 * Algoritmo (extraido del bytecode JVM):
 *   1) Generamos 16 chars hex aleatorios (= 8 bytes random).
 *   2) Esos 8 bytes random se usan como una KEY DES temporal.
 *   3) Tomamos un DATA FIJO ("EF12178E06711C05" en hex) y lo encriptamos:
 *        step1 = E(keyRandom, dataFixed)
 *   4) Desencriptamos el step1 con OTRA key fija ("BA0078E12733F411"):
 *        step2 = D(keyFixed, step1)
 *   5) Volvemos a encriptar con la keyRandom:
 *        step3 = E(keyRandom, step2)
 *   6) Concatenamos: hash = randomHex + hex(step3) = 32 chars hex en total.
 *
 * Esto es 3DES EDE (Encrypt-Decrypt-Encrypt) en modo ECB sin padding,
 * con DOS keys (en vez de tres como el 3DES "puro").
 */
final class CifradoTramas
{
    // DATA fija que va a ser encriptada. Esta hardcodeada en el bytecode oficial.
    // NO es una key — es el "plaintext" de 8 bytes que se cifra.
    private const FIXED_DATA = 'EF12178E06711C05';

    // KEY fija usada en el paso "decrypt" del medio del 3DES EDE.
    // Tambien hardcodeada en el bytecode oficial.
    private const FIXED_KEY  = 'BA0078E12733F411';

    /**
     * getHash() - genera un hash de 32 chars hex para anexar a una trama.
     *
     * Este hash es lo que el Pin Pad valida para asegurarse de que la trama
     * proviene de un cliente "autorizado" (que conoce las llaves embebidas).
     */
    public static function getHash(): string
    {
        // 1. random_bytes(8) => 8 bytes aleatorios criptograficamente seguros.
        //    bin2hex(...)    => convierte cada byte a 2 chars hex => 16 chars.
        //    Resultado ej: "A1B2C3D4E5F60718"
        $rndHex = strtoupper(bin2hex(random_bytes(8)));

        // 2. Aplicamos el algoritmo 3DES EDE-2 con:
        //      - DATA = FIXED_DATA  (lo que se cifra)
        //      - outerKey = $rndHex (el random como llave)
        //      - innerKey = FIXED_KEY
        $cipherText = self::edeWithRandomKey(
            self::FIXED_DATA,
            $rndHex,
            self::FIXED_KEY
        );

        // 3. Concatenamos: random + cifrado = 32 chars hex.
        //    El Pin Pad recibira esos 32 chars al final de la trama y podra
        //    rehacer el calculo (ya que tiene las mismas keys hardcodeadas)
        //    para verificar que el random produce ese cifrado.
        return $rndHex . $cipherText;
    }

    /**
     * validateHash() - verifica que un hash recibido sea valido.
     * Util para validar respuestas del Pin Pad.
     */
    public static function validateHash(string $hash32): bool
    {
        // Un hash valido tiene exactamente 32 chars hex.
        if (strlen($hash32) !== 32) return false;

        // Parte 1: los primeros 16 chars son el random
        $rnd = substr($hash32, 0, 16);
        // Parte 2: los siguientes 16 chars son el cifrado
        $tag = substr($hash32, 16, 16);

        // Recalculamos cual DEBERIA ser el cifrado dado ese random
        $expected = self::edeWithRandomKey(
            self::FIXED_DATA,
            $rnd,
            self::FIXED_KEY
        );

        // strcasecmp es comparacion case-insensitive (acepta hex en may/min)
        return strcasecmp($tag, $expected) === 0;
    }

    /**
     * Implementacion del 3DES EDE-2 (Encrypt-Decrypt-Encrypt con 2 keys).
     *   step1 = E(outerKey, data)
     *   step2 = D(innerKey, step1)
     *   step3 = E(outerKey, step2)
     *
     * Equivale a una "cifrado triple" pero solo usa 2 keys (la outerKey se
     * reutiliza). Es lo que hace el bytecode original.
     */
    private static function edeWithRandomKey(string $dataHex, string $outerKeyHex, string $innerKeyHex): string
    {
        // hex2bin convierte "A1B2" a los 2 bytes 0xA1 0xB2.
        // Necesitamos los datos como BYTES para alimentar al cifrador.
        $data     = hex2bin($dataHex);
        $outerKey = hex2bin($outerKeyHex);
        $innerKey = hex2bin($innerKeyHex);

        // Si alguno de los inputs no era hex valido, hex2bin devuelve false.
        if ($data === false || $outerKey === false || $innerKey === false) {
            throw new \InvalidArgumentException('CifradoTramas: hex invalido');
        }
        // DES trabaja con bloques de exactamente 8 bytes (64 bits).
        // Si los datos no miden eso, el algoritmo no funciona.
        if (strlen($data)  !== 8) {
            throw new \InvalidArgumentException('CifradoTramas: dato debe ser 8 bytes (16 hex)');
        }
        if (strlen($outerKey) !== 8 || strlen($innerKey) !== 8) {
            throw new \InvalidArgumentException('CifradoTramas: llaves DES deben ser 8 bytes');
        }

        // Creamos un cifrador DES en modo ECB (Electronic Code Book).
        // ECB es el modo mas simple: cada bloque se cifra independientemente.
        $cipher = new DES('ecb');
        // Sin padding: como nuestros datos miden exactamente 8 bytes (1 bloque
        // DES), no hace falta rellenar nada. Si dejaramos padding, la salida
        // mediria 16 bytes (1 bloque + padding) en vez de 8.
        $cipher->disablePadding();

        // -------------------------- step1 = E(outerKey, data)
        $cipher->setKey($outerKey);
        $r = $cipher->encrypt($data);

        // -------------------------- step2 = D(innerKey, step1)
        $cipher->setKey($innerKey);
        $r = $cipher->decrypt($r);

        // -------------------------- step3 = E(outerKey, step2)
        $cipher->setKey($outerKey);
        $r = $cipher->encrypt($r);

        // bin2hex convierte los 8 bytes resultado a 16 chars hex.
        // strtoupper porque el bytecode oficial devuelve mayusculas.
        return strtoupper(bin2hex($r));
    }
}
