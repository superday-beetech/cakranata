<?php

declare(strict_types=1);

namespace App\Services\Api;

use Closure;
use Throwable;

class ApiTransaction
{
    public function run(Closure $callback): mixed
    {
        $db = db_connect();

        $db->transBegin();

        try {
            $result = $callback($db);

            if ($db->transStatus() === false) {
                $db->transRollback();

                throw new \RuntimeException(
                    'Transaksi database gagal.'
                );
            }

            $db->transCommit();

            return $result;
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }
}
