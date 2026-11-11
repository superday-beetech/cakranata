<?php

declare(strict_types=1);

if (! function_exists('log_ctx')) {
    /**
     * Log dengan interpolasi placeholder via vsprintf.
     *
     * Mendukung format:
     *   log_ctx('error', 'Endpoint {endpoint} gagal: {error}', [
     *       'endpoint' => '/api/users',
     *       'error'    => 'Connection refused',
     *   ]);
     *
     * @param string $level   emergency|alert|critical|error|warning|notice|info|debug
     * @param string $message Format string dengan {key}
     * @param array<string, mixed> $context Key-value context
     */
    function log_ctx(string $level, string $message, array $context = []): void
    {
        if ($context !== []) {
            $args = [];

            $formatted = preg_replace_callback(
                '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
                static function (array $m) use ($context, &$args): string {
                    $args[] = $context[$m[1]] ?? '(missing:' . $m[1] . ')';
                    return '%s';
                },
                $message
            );

            if (is_string($formatted) && $args !== []) {
                try {
                    $message = vsprintf($formatted, $args);
                } catch (\Throwable $e) {
                    $message .= ' [log format error: ' . $e->getMessage() . ']';
                }
            }
        }

        log_message($level, $message);
    }
}
