<?php

namespace App\Libraries;

use App\Models\SettingModel;
use Config\Services;

class SettingLibrary
{
    protected $model;
    protected $cache;

    public function __construct()
    {
        $this->model = new SettingModel();
        $this->cache = Services::cache();
    }

    /**
     * Get setting value by name.
     *
     * @param  string $name
     * @param  mixed  $default
     * @param  string $type
     * @return mixed
     */
    public function get($name, $default = null, $type = false)
    {
        $settings = $this->getAll($type);

        if (isset($settings[$name])) {
            return $settings[$name];
        }

        return $default;
    }

    /**
     * Set setting value by name.
     */
    public function set($name, $value)
    {
        $row = $this->model->where('name', $name)->first();
        if ($row) {
            $this->model->update($row['id'], ['value' => $value]);

            // Hapus cache supaya next getAll() reload
            // $cacheKey = 'settings_' . $row['type'];
            // $this->cache->delete($cacheKey);

            return true;
        }

        return false;
    }
    public function getAll($type = null)
    {
        // Jika type diisi, filter berdasarkan type. Jika tidak, ambil semua setting aktif.
        $rows = $this->model->getActiveSettings($type);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['name']] = $row['value'];
        }

        return $settings;
    }
    /**
     * Get all active settings by type, return as associative array.
     */
    public function getAllCache($type = false)
    {
        // $cacheKey = 'settings_' . $type;
        // $cached   = $this->cache->get($cacheKey);

        // if ($cached !== null) {
        //     return $cached;
        // }

        // load dari DB
        $rows = $this->model->getActiveSettings($type);

        // ubah jadi array: name => value
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['name']] = $row['value'];
        }

        // simpan ke cache (TTL 600 detik = 10 menit, sesuaikan)
        // $this->cache->save($cacheKey, $settings, 600);

        return $settings;
    }

    /**
     * Clear all cached settings.
     */
    public function clearCache()
    {
        // Misalnya cache per type pakai prefix 'settings_'
        $types = ['general', 'information', 'pilihan', 'system', 'admin', 'email', 'other']; // tambahkan type lain sesuai kebutuhan

        foreach ($types as $type) {
            $cacheKey = 'settings_' . $type;
            $this->cache->delete($cacheKey);
        }

        return true;
    }
}
