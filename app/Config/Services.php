<?php

namespace Config;

use App\Libraries\SettingLibrary;
use App\Services\AccessControlService;
use App\Services\AuthorizationService;
use CodeIgniter\Config\BaseService;
use Modules\Core\Services\Storage\FileHandler;
use Modules\Core\Services\Storage\ImageHandler;
use Modules\Core\Services\Storage\StorageFacade;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
    public static function app_settings($getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('app_settings');
        }

        return new SettingLibrary();
    }


    public static function routeRegistryAccess(
        bool $getShared = true
    ): \App\Services\RouteRegistryAccessService {
        if ($getShared) {
            return static::getSharedInstance('routeRegistryAccess');
        }

        return new \App\Services\RouteRegistryAccessService(
            null,
            static::accessControl()
        );
    }

    public static function accessControl(
        bool $getShared = true
    ): \App\Services\AccessControlService {
        if ($getShared) {
            return static::getSharedInstance('accessControl');
        }

        return new \App\Services\AccessControlService();
    }

    public static function storage(bool $getShared = true): StorageFacade
    {
        if ($getShared) {
            return static::getSharedInstance('storage');
        }

        return new StorageFacade();
    }


    public static function fileHandler(bool $getShared = true): FileHandler
    {
        if ($getShared) {
            return static::getSharedInstance('fileHandler');
        }

        return new FileHandler();
    }

    public static function imageHandler(bool $getShared = true): ImageHandler
    {
        if ($getShared) {
            return static::getSharedInstance('imageHandler');
        }

        return new ImageHandler();
    }
}
