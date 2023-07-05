<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class AESUtil
{
    public static function encrypt($key, $plaintext): string
    {
        $method = 'AES-128-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        $ciphertext = openssl_encrypt($plaintext, $method, $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv.$ciphertext);
    }

    public static function decrypt($key, $ciphertext)
    {
        $method = 'AES-128-CBC';
        $ciphertext = base64_decode($ciphertext);
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($ciphertext, 0, $iv_length);
        $ciphertext = substr($ciphertext, $iv_length);

        return openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv);
    }
}