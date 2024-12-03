<?php

namespace App\Service;

class SecurityService
{
  private string $key;

  public function __construct(string $key)
  {
    $this->key = $key;
  }

  public function encryptAndEncode(string $data, string $method): string
  {
    $iv = random_bytes(openssl_cipher_iv_length($method));
    $encrypted = openssl_encrypt($data, $method, $this->key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
      throw new \RuntimeException('Encryption failed.');
    }
    $hmac = hash_hmac('sha256', $iv . $encrypted, $this->key, true);
    return base64_encode($hmac . $iv . $encrypted);
  }

  public function decodeAndDecrypt(string $data, string $method): string
  {
    $raw = base64_decode($data);
    $hmacLength = 32;
    $ivLength = openssl_cipher_iv_length($method);
    $hmac = substr($raw, 0, $hmacLength);
    $iv = substr($raw, $hmacLength, $ivLength);
    $encrypted = substr($raw, $hmacLength + $ivLength);

    if (!hash_equals($hmac, hash_hmac('sha256', $iv . $encrypted, $this->key, true))) {
      throw new \Exception('Data integrity check failed.');
    }

    $decrypted = openssl_decrypt($encrypted, $method, $this->key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
      throw new \RuntimeException('Decryption failed.');
    }
    return $decrypted;
  }
}
