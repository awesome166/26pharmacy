<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

class LicenseService
{
    public function signPayload(array $payload): array
    {
        $canonical = $this->canonicalJson($payload);
        $privateKey = config('sync.license_private_key');

        if ($privateKey) {
            $signature = '';
            if (!openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign license.');
            }
            $algorithm = 'RS256';
        } else {
            if (app()->environment('production') && !config('sync.license_allow_hmac', false)) {
                throw new RuntimeException('Production license signing requires an RSA private key.');
            }
            $secret = (string) config('sync.license_hmac_secret');
            if ($secret === '') {
                throw new RuntimeException('License signing is not configured.');
            }
            $signature = hash_hmac('sha256', $canonical, $secret, true);
            $algorithm = 'HS256';
        }

        return [
            'payload' => $payload,
            'algorithm' => $algorithm,
            'signature' => base64_encode($signature),
        ];
    }

    public function verifyLicense(array $licenseData, array $expected = []): bool
    {
        $payload = $licenseData['payload'] ?? null;
        $encodedSignature = $licenseData['signature'] ?? null;
        $algorithm = $licenseData['algorithm'] ?? 'RS256';

        if (!is_array($payload) || !is_string($encodedSignature)) {
            throw new RuntimeException('Invalid license format.');
        }

        $signature = base64_decode($encodedSignature, true);
        if ($signature === false) {
            throw new RuntimeException('Invalid license signature encoding.');
        }

        $canonical = $this->canonicalJson($payload);
        if ($algorithm === 'RS256') {
            $publicKey = config('sync.license_public_key');
            $valid = $publicKey && openssl_verify($canonical, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        } elseif ($algorithm === 'HS256') {
            if (app()->environment('production') && !config('sync.license_allow_hmac', false)) {
                throw new RuntimeException('Shared-secret licenses are disabled in production.');
            }
            $secret = (string) config('sync.license_hmac_secret');
            $valid = $secret !== '' && hash_equals(hash_hmac('sha256', $canonical, $secret, true), $signature);
        } else {
            throw new RuntimeException('Unsupported license signature algorithm.');
        }

        if (!$valid) {
            throw new RuntimeException('License signature verification failed.');
        }

        $expiry = $payload['expires_at'] ?? $payload['expiry_date'] ?? null;
        $allowGrace = (bool) ($expected['allow_grace'] ?? false);
        $graceHours = $allowGrace ? max(0, (int) config('sync.license_grace_hours', 0)) : 0;
        if (!$expiry || CarbonImmutable::parse($expiry)->addHours($graceHours)->isPast()) {
            throw new RuntimeException('License has expired.');
        }

        if (isset($payload['not_before']) && CarbonImmutable::parse($payload['not_before'])->isFuture()) {
            throw new RuntimeException('License is not active yet.');
        }

        foreach (['account_id', 'branch_id', 'device_id'] as $claim) {
            if (isset($expected[$claim]) && (string) ($payload[$claim] ?? '') !== (string) $expected[$claim]) {
                throw new RuntimeException("License {$claim} does not match this installation.");
            }
        }

        if (isset($expected['feature']) && !in_array($expected['feature'], $payload['features'] ?? [], true)) {
            throw new RuntimeException("Feature {$expected['feature']} is not licensed.");
        }

        return true;
    }

    public function getLicenseDetails(array $licenseData): array
    {
        $this->verifyLicense($licenseData);
        return $licenseData['payload'];
    }

    private function canonicalJson(array $payload): string
    {
        ksort($payload);
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
