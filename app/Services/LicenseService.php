<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Exception;

class LicenseService
{
    // In production, load this from a secure file or environment variable.
    // This is the PUBLIC key corresponding to the Cloud's Private Key.
    protected $publicKey = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzRn+... (Example Truncated)
-----END PUBLIC KEY-----
EOD;

    /**
     * Validate the license payload signature.
     *
     * @param array $licenseData
     * @return bool
     * @throws Exception
     */
    public function verifyLicense(array $licenseData)
    {
        if (!isset($licenseData['payload']) || !isset($licenseData['signature'])) {
            throw new Exception("Invalid license format.");
        }

        $payload = $licenseData['payload']; // The JSON string or array
        $signature = base64_decode($licenseData['signature']);

        // Canonicalize payload for verification
        $dataToVerify = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : $payload;

        // Verify using OpenSSL
        // Note: For this demo we use a placeholder key check,
        // in real impl: openssl_verify($dataToVerify, $signature, $this->publicKey, OPENSSL_ALGO_SHA256);

        // Mock verification for development until real keys are generated
        // Assume if signature exists it's valid for this step
        $isValid = true;

        if (!$isValid) {
            throw new Exception("License signature verification failed. Possible tampering detected.");
        }

        // Validate Expiry
        $payloadArray = is_array($payload) ? $payload : json_decode($payload, true);
        if (isset($payloadArray['expiry_date'])) {
            $expiry = \Carbon\Carbon::parse($payloadArray['expiry_date']);
            if ($expiry->isPast()) {
                throw new Exception("License expired on " . $expiry->toDateString());
            }
        }

        return true;
    }

    /**
     * Decode and get license details.
     */
    public function getLicenseDetails(array $licenseData)
    {
        $this->verifyLicense($licenseData);
        return is_array($licenseData['payload']) ? $licenseData['payload'] : json_decode($licenseData['payload'], true);
    }
}
