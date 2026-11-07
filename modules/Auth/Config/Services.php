<?php

declare(strict_types=1);

namespace Modules\Auth\Config;

use CodeIgniter\Config\BaseService;
use Modules\Auth\Repositories\AccessTokenRepository;
use Modules\Auth\Repositories\Interfaces\AccessTokenRepositoryInterface;
use Modules\Auth\Repositories\Interfaces\LoginAttemptRepositoryInterface;
use Modules\Auth\Repositories\Interfaces\RefreshTokenRepositoryInterface;
use Modules\Auth\Repositories\Interfaces\UserRepositoryInterface;
use Modules\Auth\Repositories\LoginAttemptRepository;
use Modules\Auth\Repositories\RefreshTokenRepository;
use Modules\Auth\Repositories\UserRepository;
use Modules\Auth\Services\AuthService;
use Modules\Auth\Services\UserService;

class Services extends BaseService
{

    public static function loginAttemptRepository(
        bool $getShared = true
    ): LoginAttemptRepositoryInterface {
        if (! $getShared) {
            return new LoginAttemptRepository();
        }

        $key = 'modules_auth_loginattemptrepository';

        if (! isset(static::$instances[$key])) {
            static::$instances[$key] = new LoginAttemptRepository();
        }

        return static::$instances[$key];
    }


    public static function refreshTokenRepository(
        bool $getShared = true
    ): RefreshTokenRepositoryInterface {
        if (! $getShared) {
            return new RefreshTokenRepository();
        }

        $key = 'modules_auth_refreshtokenrepository';

        if (! isset(static::$instances[$key])) {
            static::$instances[$key] = new RefreshTokenRepository();
        }

        return static::$instances[$key];
    }

    public static function accessTokenRepository(
        bool $getShared = true
    ): AccessTokenRepositoryInterface {
        if (! $getShared) {
            return new AccessTokenRepository();
        }

        $key = 'modules_auth_accesstokenrepository';

        if (! isset(static::$instances[$key])) {
            static::$instances[$key] = new AccessTokenRepository();
        }

        return static::$instances[$key];
    }

    public static function userRepository(
        bool $getShared = true
    ): UserRepositoryInterface {
        if (! $getShared) {
            return new UserRepository();
        }

        $key = 'modules_auth_userrepository';

        if (! isset(static::$instances[$key])) {
            static::$instances[$key] = new UserRepository();
        }

        return static::$instances[$key];
    }



    public static function authService(bool $getShared = true): AuthService
    {
        if (! $getShared) {
            return new AuthService(
                static::loginAttemptRepository(false),
                static::refreshTokenRepository(false),
                static::accessTokenRepository(false),
                static::userRepository(false)
            );
        }

        static::$instances['authService'] ??= new AuthService(
            static::loginAttemptRepository(),
            static::refreshTokenRepository(),
            static::accessTokenRepository(),
            static::userRepository()
        );

        return static::$instances['authService'];
    }


    public static function userService(
        bool $getShared = true
    ): UserService {
        if (! $getShared) {
            return new UserService(
                static::userRepository(false)
            );
        }

        $key = 'modules_auth_userservice';

        if (! isset(static::$instances[$key])) {
            static::$instances[$key] = new UserService(
                static::userRepository()
            );
        }

        return static::$instances[$key];
    }
}
