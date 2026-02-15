<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('institutions')
            ->whereIn('name', ['M-Pesa', 'GTBank', 'Zenith Bank', 'Equity Bank', 'Wise'])
            ->update(['is_active' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('institutions')
            ->whereIn('name', ['M-Pesa', 'GTBank', 'Zenith Bank', 'Equity Bank', 'Wise'])
            ->update(['is_active' => true]);
    }
};
