<?php

defined('ABSPATH') || exit;

class FortnoxKey
{

    public static function getPublicKey()
    {
        $publicKey = "-----BEGIN PUBLIC KEY-----\n";
        $publicKey .= "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA8cnR8+7WM8J3J2QKIfdJ\n";
        $publicKey .= "8nYe4yoxqQsW7JJkr4ssd64vf37hONmEFS02Gb7pzTD5isheo0xJSsPYe5PqH6JZ\n";
        $publicKey .= "yw3uy5NjrCuyc73eMaWZWAKlenapdU1J9hKobL5m5jOnLIYGXc2tnLeoS2AsPuT7\n";
        $publicKey .= "uwq0lXzK5n2choVqzO+SJL4eJiFZzx38nfCgc3gqXgAWhpeAew/hc9YUcAyQm6sw\n";
        $publicKey .= "et0j+JwsVI5V10RbEnqmGi9oFSfbAZHQQ3wbPwGzLCYqkXFX1JS4cGcvXjJBXP+f\n";
        $publicKey .= "gaQxrh81GmG+aRpAXhpM5Ew0qcsk/ua5ge4+YnrZbgzoKC/+Rwr/HFt0KTJExCDA\n";
        $publicKey .= "gQIDAQAB\n";
        $publicKey .= "-----END PUBLIC KEY-----\n";

        return $publicKey;
    }

}
