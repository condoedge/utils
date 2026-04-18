<?php

namespace Condoedge\Utils\Services\LazyHierarchy;

class LazyHierarchyRegistry
{
    public function storeSource(string $sourceClass, array $store = []): string
    {
        $payload = json_encode([
            'type' => 'source',
            'class' => $sourceClass,
            'store' => $store,
        ]);

        $signature = substr(hash_hmac('sha256', $payload, config('app.key')), 0, 32);

        return base64_encode($payload) . '.' . $signature;
    }

    public function retrieveSource(string $signedId): ?array
    {
        $lastDot = strrpos($signedId, '.');

        if ($lastDot === false) {
            return null;
        }

        $payload = substr($signedId, 0, $lastDot);
        $providedSignature = substr($signedId, $lastDot + 1);
        $json = base64_decode($payload, true);

        if (!$json) {
            return null;
        }

        $expectedSignature = substr(hash_hmac('sha256', $json, config('app.key')), 0, 32);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        $data = json_decode($json, true);

        return ($data['type'] ?? null) === 'source' && !empty($data['class'])
            ? $data
            : null;
    }
}
