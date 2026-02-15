<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support ALTER COLUMN for enums, so we need to recreate
        // For SQLite, we'll use a workaround by removing the check constraint
        if (DB::getDriverName() === 'sqlite') {
            // SQLite workaround: Create a new column, copy data, drop old, rename
            Schema::table('users', function (Blueprint $table) {
                $table->string('role_new')->default('user')->after('role');
            });
            
            DB::statement('UPDATE users SET role_new = role');
            
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
            
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('role_new', 'role');
            });
        } else {
            // MySQL/PostgreSQL: Modify enum
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'regulator') DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Revert is complex for SQLite, skip for now
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin') DEFAULT 'user'");
        }
    }
};
