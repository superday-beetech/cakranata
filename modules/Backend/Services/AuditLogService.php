<?php

declare(strict_types=1);

namespace Modules\Backend\Services;

use Config\Database;
use Throwable;

class AuditLogService
{
    public function record(array $data): bool
    {
        try {
            $request = service('request');
            $user    = auth()->user();

            $encode = static function ($value): ?string {
                if ($value === null) {
                    return null;
                }

                $json = json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_PARTIAL_OUTPUT_ON_ERROR
                );

                return $json === false ? null : $json;
            };

            $db = Database::connect();

            $result = $db->table('audit_logs')->insert([
                'event'          => substr((string) ($data['event'] ?? 'system'), 0, 50),
                'module'         => substr((string) ($data['module'] ?? 'system'), 0, 100),
                'action'         => substr((string) ($data['action'] ?? 'unknown'), 0, 50),
                'user_id'        => $user?->id,
                'username'       => isset($data['username'])
                    ? substr((string) $data['username'], 0, 100)
                    : (isset($user->username)
                        ? substr((string) $user->username, 0, 100)
                        : null),
                'user_email'     => isset($data['user_email'])
                    ? substr((string) $data['user_email'], 0, 254)
                    : null,
                'entity_type'    => isset($data['entity_type'])
                    ? substr((string) $data['entity_type'], 0, 100)
                    : null,
                'entity_id'      => isset($data['entity_id'])
                    ? substr((string) $data['entity_id'], 0, 100)
                    : null,
                'description'    => $data['description'] ?? null,
                'old_values'     => $encode($data['old_values'] ?? null),
                'new_values'     => $encode($data['new_values'] ?? null),
                'ip_address'     => $request->getIPAddress(),
                'user_agent'     => substr(
                    (string) $request->getUserAgent(),
                    0,
                    2000
                ),
                'request_method' => substr($request->getMethod(), 0, 10),
                'request_uri'    => substr(
                    (string) $request->getUri()->getPath(),
                    0,
                    255
                ),
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            return $result;
        } catch (Throwable $e) {
            log_message(
                'error',
                'Audit Log gagal mencatat aktivitas: {error}',
                ['error' => $e->getMessage()]
            );

            return false;
        }
    }
}
