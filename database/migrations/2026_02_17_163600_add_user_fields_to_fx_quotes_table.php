<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fx_quotes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->foreignId('institution_id')->nullable()->after('fx_provider_id')->constrained()->onDelete('cascade');
            $table->string('source_currency', 3)->nullable()->after('institution_id');
            $table->string('destination_currency', 3)->nullable()->after('source_currency');
            $table->decimal('source_amount', 18, 2)->nullable()->after('destination_currency');
            $table->decimal('destination_amount', 18, 2)->nullable()->after('source_amount');
            $table->decimal('spread', 8, 6)->nullable()->after('spread_percentage');
            $table->decimal('fee', 18, 2)->nullable()->after('spread');
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending')->after('fee');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('fx_quotes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['institution_id']);
            $table->dropColumn([
                'user_id',
                'institution_id', 
                'source_currency',
                'destination_currency',
                'source_amount',
                'destination_amount',
                'spread',
                'fee',
                'status',
            ]);
        });
    }
};
