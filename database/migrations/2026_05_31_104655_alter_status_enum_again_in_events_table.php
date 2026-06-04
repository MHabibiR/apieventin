<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('open', 'closed', 'done', 'pending') DEFAULT 'open'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('open', 'close', 'pending') DEFAULT 'open'");
    }
};
