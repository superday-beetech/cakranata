<?php

declare(strict_types=1);

/**
 * Simpan di: app/Filters/WebhookSignatureFilter.php
 *
 * ============================================================
 * WEBHOOK SIGNATURE FILTER (fail closed)
 * ============================================================
 * Dipasang di group route /webhook/{provider}. Provider diambil dari URL
 * (atau argumen filter: 'webhook-signature:xendit').
 *
 * Pemeriksaan berurutan:
 *  1. Provider terdaftar & enabled          -> 404 bila tidak
 *  2. Method harus POST                     -> 405
 *  3. Secret ada di konfigurasi             -> 503 bila kosong (kesalahan konfigurasi)
 *  4. IP allowlist (opsional, CIDR)         -> 403
 *  5. Ukuran body <= maxBodyBytes           -> 413
 *  6. Verifikasi signature (hash_equals)    -> 401
 *
 * Catatan penting:
 *  - Signature dihitung atas RAW body, bukan hasil decode/encode ulang JSON.
 *  - Filter ini hanya membuktikan keaslian pengirim. Controller tetap WAJIB
 *    idempoten (simpan event/order id yang sudah diproses) karena provider
 *    melakukan retry, dan sebaiknya mengonfirmasi status lewat API provider.
 *  - Di belakang proxy/CDN, set Config\App::$proxyIPs agar getIPAddress() benar
 *    (relevan bila memakai allowedIps).
 *  - Batas ukuran body sebaiknya juga dipasang di web server
 *    (client_max_body_size / LimitRequestBody).
 *  - Kecualikan 'webhook/*' dari filter CSRF dan RBAC global.
 */

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Webhooks as WebhooksConfig;
use Throwable;

final class WebhookSignatureFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return $this->reject(400, 'bad_request', null, null);
        }

        try {
            return $this->verify($request, $arguments);
        } catch (Throwable $e) {
            log_message('critical', '[Webhook] fail-closed: ' . $e::class . ': ' . $e->getMessage());

            return $this->reject(503, 'internal_error', null, $request);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak dipakai.
    }

    // ============================================================
    // ALUR VERIFIKASI
    // ============================================================

    private function verify(IncomingRequest $request, $arguments): ?ResponseInterface
    {
        /** @var WebhooksConfig $cfg */
        $cfg      = config('Webhooks');
        $provider = $this->resolveProvider($request, $arguments);
        $pc       = $provider !== null ? ($cfg->providers[$provider] ?? null) : null;

        if ($pc === null || ($pc['enabled'] ?? true) === false) {
            return $this->reject(404, 'unknown_provider', $provider, $request);
        }

        if (strtoupper($request->getMethod()) !== 'POST') {
            return $this->reject(405, 'method_not_allowed', $provider, $request, ['Allow' => 'POST']);
        }

        $secret = (string) ($pc['secret'] ?? '');

        if ($secret === '') {
            log_message('critical', "[Webhook] secret provider '{$provider}' kosong; request ditolak.");

            return $this->reject(503, 'misconfigured', $provider, $request);
        }

        $allowedIps = (array) ($pc['allowedIps'] ?? []);

        if ($allowedIps !== [] && ! $this->ipAllowed($request->getIPAddress(), $allowedIps)) {
            return $this->reject(403, 'ip_not_allowed', $provider, $request);
        }

        $declared = (int) $request->getHeaderLine('Content-Length');
        $body     = (string) $request->getBody();

        if ($declared > $cfg->maxBodyBytes || strlen($body) > $cfg->maxBodyBytes) {
            return $this->reject(413, 'payload_too_large', $provider, $request);
        }

        if ($body === '') {
            return $this->reject(400, 'empty_body', $provider, $request);
        }

        $valid = match ($pc['mode'] ?? '') {
            'token'    => $this->verifyToken($request, $pc, $secret),
            'hmac'     => $this->verifyHmac($request, $pc, $secret, $body),
            'midtrans' => $this->verifyMidtrans($pc, $secret, $body),
            'callback' => $this->verifyCallback($request, $pc, $body),
            default    => false,
        };

        if (! $valid) {
            return $this->reject(401, 'invalid_signature', $provider, $request);
        }

        return null;
    }

    private function resolveProvider(IncomingRequest $request, $arguments): ?string
    {
        $candidate = is_array($arguments) && isset($arguments[0]) ? strtolower((string) $arguments[0]) : null;

        if ($candidate === null) {
            $segments = explode('/', trim(strtolower((string) $request->getPath()), '/'));

            if (($segments[0] ?? '') !== 'webhook' || count($segments) !== 2) {
                return null;
            }

            $candidate = $segments[1];
        }

        return preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $candidate) === 1 ? $candidate : null;
    }

    // ============================================================
    // MODE VERIFIKASI
    // ============================================================

    private function verifyToken(IncomingRequest $request, array $pc, string $secret): bool
    {
        $given = trim($request->getHeaderLine((string) ($pc['header'] ?? 'X-Callback-Token')));

        if ($given === '') {
            return false;
        }

        // Hash dulu agar perbandingan tidak membocorkan panjang token.
        return hash_equals(hash('sha256', $secret), hash('sha256', $given));
    }

    private function verifyHmac(IncomingRequest $request, array $pc, string $secret, string $body): bool
    {
        $algo = (string) ($pc['algo'] ?? 'sha256');

        if (! in_array($algo, hash_hmac_algos(), true)) {
            log_message('critical', "[Webhook] algoritma HMAC tidak dikenal: {$algo}");

            return false;
        }

        $given = trim($request->getHeaderLine((string) ($pc['header'] ?? 'X-Signature')));

        if ($given === '') {
            return false;
        }

        $prefix = (string) ($pc['prefix'] ?? '');

        if ($prefix !== '') {
            if (! str_starts_with($given, $prefix)) {
                return false;
            }

            $given = substr($given, strlen($prefix));
        }

        // Cegah replay lama via timestamp (bila provider mengirimnya).
        $timestamp       = '';
        $timestampHeader = $pc['timestampHeader'] ?? null;

        if (is_string($timestampHeader) && $timestampHeader !== '') {
            $timestamp = trim($request->getHeaderLine($timestampHeader));

            if (preg_match('/^\d{1,16}$/', $timestamp) !== 1) {
                return false;
            }

            $seconds = ($pc['timestampUnit'] ?? 's') === 'ms' ? ((int) $timestamp) / 1000 : (int) $timestamp;

            if (abs(time() - $seconds) > (int) ($pc['tolerance'] ?? 300)) {
                return false;
            }
        }

        $payload  = strtr((string) ($pc['signedPayload'] ?? '{body}'), [
            '{timestamp}' => $timestamp,
            '{body}'      => $body,
        ]);
        $raw      = hash_hmac($algo, $payload, $secret, true);
        $encoding = (string) ($pc['encoding'] ?? 'hex');

        if ($encoding === 'base64') {
            return hash_equals(base64_encode($raw), $given);
        }

        return hash_equals(bin2hex($raw), strtolower($given));
    }

    private function verifyMidtrans(array $pc, string $serverKey, string $body): bool
    {
        $data = json_decode($body, true);

        if (! is_array($data)) {
            return false;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! isset($data[$field]) || ! is_scalar($data[$field])) {
                return false;
            }
        }

        $expected = hash('sha512', $data['order_id'] . $data['status_code'] . $data['gross_amount'] . $serverKey);

        return hash_equals($expected, strtolower((string) $data['signature_key']));
    }

    private function verifyCallback(IncomingRequest $request, array $pc, string $body): bool
    {
        $callback = $pc['verify'] ?? null;

        if (! is_callable($callback)) {
            log_message('critical', '[Webhook] mode callback tanpa callable yang valid.');

            return false;
        }

        return $callback($request, $pc, $body) === true; // hanya true eksplisit yang lolos
    }

    // ============================================================
    // IP ALLOWLIST
    // ============================================================

    /** @param list<string> $cidrs */
    private function ipAllowed(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if ($this->ipInCidr($ip, (string) $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton((string) $subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        $bits    = $bits === null ? $maxBits : (int) $bits;

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $rest  = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }

    // ============================================================
    // RESPON
    // ============================================================

    /** @param array<string, string> $headers */
    private function reject(
        int $status,
        string $reason,
        ?string $provider,
        ?IncomingRequest $request,
        array $headers = []
    ): ResponseInterface {
        // Jangan log body maupun signature; hanya metadata.
        log_message('warning', sprintf(
            '[Webhook] reject status=%d reason=%s provider=%s ip=%s',
            $status,
            $reason,
            $provider ?? '-',
            $request?->getIPAddress() ?? '-'
        ));

        $response = service('response')
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON(['status' => false]); // pesan generik: jangan bocorkan penyebab

        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }

        return $response;
    }
}
