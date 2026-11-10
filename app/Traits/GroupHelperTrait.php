<?php

namespace App\Traits;

use Config\AuthGroups;

trait GroupHelperTrait
{
    /**
     * Ambil nama grup superadmin dari config AuthGroups
     */
    protected function getSuperadminGroup(): string
    {
        // Misal config-nya seperti:
        // 'superadmin' => ['title' => 'Super Admin']
        $groups = config(AuthGroups::class)->groups;

        return array_key_exists('superadmin', $groups) ? 'superadmin' : 'admin';
    }

    /**
     * Cek apakah user saat ini termasuk grup superadmin (dari config, bukan DB)
     */

    protected function isSuperadmin(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return service('accessControl')
            ->isSuperadmin((int) $user->id);
    }

    protected function isSuperadminss(): bool
    {
        if (!auth()->loggedIn()) {
            return false;
        }

        $user       = auth()->user();
        $superGroup = $this->getSuperadminGroup();

        return $user->inGroup($superGroup); // Shield akan tetap query DB, tapi nama group dijamin dari config
    }

    /**
     * Cek apakah user saat ini punya salah satu grup selain superadmin
     *
     * @param  string|array $groups
     * @return bool
     */
    protected function userInGroups($groups): bool
    {
        if (!auth()->loggedIn()) {
            return false;
        }

        $user = auth()->user();

        $groups = (array) $groups;

        // Jika salah satu grup adalah superadmin, lewati dari sini
        $superGroup = $this->getSuperadminGroup();
        $groups     = array_filter($groups, fn($g) => $g !== $superGroup);

        foreach ($groups as $group) {
            if ($user->inGroup($group)) {
                return true;
            }
        }

        return false;
    }
}
