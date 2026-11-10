<?php

declare(strict_types=1);

/**
 * Simpan di: app/Config/Webhooks.php
 *
 * Secret TIDAK boleh ditulis di sini. Simpan di .env, contoh:
 *
 *   webhook.xendit.token      = xxxxxxxx
 *   webhook.midtrans.serverKey = SB-Mid-server-xxxx
 *   webhook.generic.secret    = xxxxxxxx
 *
 * Mode verifikasi per provider:
 *  - token    : header berisi token statis (mis. Xendit x-callback-token)
 *  - hmac     : HMAC atas raw body (opsional + timestamp & prefix)
 *  - midtrans : SHA512(order_id + status_code + gross_amount + serverKey) dari body
 *  - callback : 'verify' => callable(IncomingRequest $request, array $cfg, string $body): bool
 *
 * Provider tanpa secret (kosong) otomatis DITOLAK (fail closed).
 * Cocokkan skema signature dengan dokumentasi terbaru masing-masing provider.
 */

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Webhooks extends BaseConfig
{
    /** Batas ukuran body (byte). */
    public int $maxBodyBytes = 1048576; // 1 MB

    /** @var array<string, array<string, mixed>> kunci = segmen URL: /webhook/{provider} */
    public array $providers = [];

    public function __construct()
    {
        parent::__construct();

        $this->providers = [
            'xendit' => [
                'enabled'    => true,
                'mode'       => 'token',
                'header'     => 'x-callback-token',
                'secret'     => (string) env('webhook.xendit.token', ''),
                'allowedIps' => [], // opsional, isi CIDR resmi dari provider
            ],

            'midtrans' => [
                'enabled'    => true,
                'mode'       => 'midtrans',
                'secret'     => (string) env('webhook.midtrans.serverKey', ''),
                'allowedIps' => [],
            ],

            // Contoh HMAC generik (marketplace/ekspedisi). Aktifkan bila dipakai.
            'generic' => [
                'enabled'         => false,
                'mode'            => 'hmac',
                'header'          => 'X-Signature',
                'secret'          => (string) env('webhook.generic.secret', ''),
                'algo'            => 'sha256',
                'encoding'        => 'hex',          // hex | base64
                'prefix'          => 'sha256=',      // '' bila tidak ada
                'timestampHeader' => 'X-Timestamp',  // null bila tidak ada
                'timestampUnit'   => 's',            // s | ms
                'tolerance'       => 300,            // detik
                'signedPayload'   => '{timestamp}.{body}',
                'allowedIps'      => [],
            ],
        ];
    }
}
