<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateUserProfilePhotos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'file_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        // $this->forge->addKey('user_id');
        $this->forge->addUniqueKey('user_id', 'uq_user_profile_photos_user_id');
        $this->forge->createTable('user_profile_photos', true);
    }

    public function down()
    {
        $this->forge->dropTable('user_profile_photos', true);
    }
}
