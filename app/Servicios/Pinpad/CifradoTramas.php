<?php

namespace App\Servicios\Pinpad;

use phpseclib3\Crypt\DES;

/**
 * Replica EXACTA de cajapinpad.CifradoTramas (extraido del bytecode JVM).
 *
 * Algoritmo getHash():
 *   aleatorio = 16 chars hex (8 bytes random)
 *   bytes_data = hex2byte("EF12178E06711C05")     // DATA fija
 *   key_random = SecretKeySpec(hex2byte(aleatorio), "DES")
 *   key_fixed  = SecretKeySpec(hex2byte("BA0078E12733F411"), "DES")
 *   step1 = E(key_random, bytes_data)
 *   step2 = D(key_fixed,  step1)
 *   step3 = E(key_random, step2)
 *   return aleatorio + hex(step3)        // 32 chars hex
 *
 * Algoritmo validateHash(input32hex):
 *   first16 = input.substring(0,16)
 *   bytes_data2 = hex2byte("BA0078E12733F411")
 *   key_fixed2  = SecretKeySpec(hex2byte("EF12178E06711C05"), "DES")
 *   key_first   = SecretKeySpec(hex2byte(first16), "DES")
 *   step1 = E(key_fixed2, bytes_data2)
 *   step2 = D(key_first,  step1)
 *   step3 = E(key_fixed2, step2)
 *   return input == first16 + hex(step3)
 */
final class CifradoTramas
{
    // EN EL BYTECODE estos eran "LLAVE_IZQUIERDA" y "LLAVE_DERECHA",
    // pero en realidad uno es DATA fija y el otro es KEY fija.
    private const FIXED_DATA = 'EF12178E06711C05';   // 8 bytes plaintext
    private const FIXED_KEY  = 'BA0078E12733F411';   // 8 bytes DES key

    public static function getHash(): string
    {
        $rndHex = strtoupper(bin2hex(random_bytes(8))); // 16 chars hex
        $cipherText = self::edeWithRandomKey(
            self::FIXED_DATA,    // data
            $rndHex,             // outer key (random)
            self::FIXED_KEY      // inner key (fixed)
        );
        return $rndHex . $cipherText;
    }

    public static function validateHash(string $hash32): bool
    {
        if (strlen($hash32) !== 32) return false;
        $rnd = substr($hash32, 0, 16);
        $tag = substr($hash32, 16, 16);
        $expected = self::edeWithRandomKey(
            self::FIXED_DATA,
            $rnd,
            self::FIXED_KEY
        );
        return strcasecmp($tag, $expected) === 0;
    }

    /**
     * 3DES EDE-2 con la KEY EXTERNA derivada de un valor random.
     *   step1 = E(outerKey, data)
     *   step2 = D(innerKey, step1)
     *   step3 = E(outerKey, step2)
     */
    private static function edeWithRandomKey(string $dataHex, string $outerKeyHex, string $innerKeyHex): string
    {
        $data     = hex2bin($dataHex);
        $outerKey = hex2bin($outerKeyHex);
        $innerKey = hex2bin($innerKeyHex);

        if ($data === false || $outerKey === false || $innerKey === false) {
            throw new \InvalidArgumentException('CifradoTramas: hex invalido');
        }
        if (strlen($data) !== 8 || strlen($outerKey) !== 8 || strlen($innerKey) !== 8) {
            throw new \InvalidArgumentException('CifradoTramas: cada bloque debe ser 8 bytes (16 hex)');
        }

        $cipher = new DES('ecb');
        $cipher->disablePadding();

        $cipher->setKey($outerKey);
        $r = $cipher->encrypt($data);

        $cipher->setKey($innerKey);
        $r = $cipher->decrypt($r);

        $cipher->setKey($outerKey);
        $r = $cipher->encrypt($r);

        return strtoupper(bin2hex($r));
    }
}
