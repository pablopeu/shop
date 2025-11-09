<?php
/**
 * Encryption/Decryption utilities for sensitive data
 * Uses AES-256-CBC encryption via OpenSSL
 */

/**
 * Get or create encryption key
 * The key is stored in a file outside the webroot (or with restricted permissions)
 */
function get_encryption_key() {
    $key_file = __DIR__ . '/../.encryption_key';

    // If key doesn't exist, generate a new one
    if (!file_exists($key_file)) {
        // Generate a cryptographically secure random key (32 bytes = 256 bits)
        $key = bin2hex(random_bytes(32));

        // Save it with restricted permissions
        file_put_contents($key_file, $key);
        chmod($key_file, 0600); // Only owner can read/write

        error_log("Encryption: New encryption key generated");
    } else {
        $key = file_get_contents($key_file);
    }

    return $key;
}

/**
 * Encrypt a string
 * @param string $data The data to encrypt
 * @return string The encrypted data (base64 encoded with IV prepended)
 */
function encrypt_data($data) {
    if (empty($data)) {
        return '';
    }

    $key = hex2bin(get_encryption_key());
    $cipher = 'AES-256-CBC';

    // Generate a random IV (Initialization Vector)
    $iv_length = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($iv_length);

    // Encrypt the data
    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);

    if ($encrypted === false) {
        error_log("Encryption: Failed to encrypt data");
        return '';
    }

    // Prepend IV to encrypted data and encode as base64
    // Format: base64(IV + encrypted_data)
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt a string
 * @param string $encrypted_data The encrypted data (base64 encoded with IV prepended)
 * @return string The decrypted data
 */
function decrypt_data($encrypted_data) {
    if (empty($encrypted_data)) {
        return '';
    }

    $key = hex2bin(get_encryption_key());
    $cipher = 'AES-256-CBC';

    // Decode from base64
    $data = base64_decode($encrypted_data);

    if ($data === false) {
        error_log("Encryption: Failed to decode base64 data");
        return '';
    }

    // Extract IV from the beginning
    $iv_length = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);

    // Decrypt the data
    $decrypted = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);

    if ($decrypted === false) {
        error_log("Encryption: Failed to decrypt data");
        return '';
    }

    return $decrypted;
}

/**
 * Check if a value is encrypted (basic heuristic)
 * Encrypted values are base64 and longer than typical plaintext
 */
function is_encrypted($value) {
    if (empty($value)) {
        return false;
    }

    // Check if it's valid base64 and has reasonable length
    return strlen($value) > 40 && base64_encode(base64_decode($value, true)) === $value;
}
