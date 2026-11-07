<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use InvalidArgumentException;

final class AuthenticationService
{
    /**
     * Normalisasi identitas login.
     *
     * @return array{email?: string, username?: string}
     */
    public function resolveIdentity(string $identity): array
    {
        $identity = trim($identity);

        if ($identity === '') {
            throw new InvalidArgumentException(
                'Email atau username wajib diisi.'
            );
        }

        if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
            return ['email' => mb_strtolower($identity)];
        }

        return ['username' => mb_strtolower($identity)];
    }
}
