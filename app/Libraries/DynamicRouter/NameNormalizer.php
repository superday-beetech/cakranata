<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

/**
 * Satu-satunya sumber aturan kebab-case untuk URL (modul, controller, method).
 * Dipakai Routes.php dan RouteResolver agar prefix web, API, dan registry konsisten.
 */
final class NameNormalizer
{
    /** "UserProfile" -> "user-profile", "HTTPServer" -> "http-server". */
    public static function kebab(string $name): string
    {
        $name = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $name) ?? $name;
        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name) ?? $name;

        return strtolower($name);
    }

    /** Sama seperti kebab(), tetapi membuang akhiran "Controller" (untuk nama modul). */
    public static function moduleKebab(string $name): string
    {
        return self::kebab(preg_replace('/Controller$/', '', $name) ?? $name);
    }
}
