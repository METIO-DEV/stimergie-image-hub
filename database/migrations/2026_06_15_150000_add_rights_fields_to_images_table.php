<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->date('rights_starts_at')->nullable()->after('status');
            $table->date('rights_ends_at')->nullable()->after('rights_starts_at')->index();
            $table->timestamp('rights_extension_requested_at')->nullable()->after('rights_ends_at');
            $table->foreignId('rights_extension_requested_by')
                ->nullable()
                ->after('rights_extension_requested_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropForeign(['rights_extension_requested_by']);
            $table->dropColumn([
                'rights_starts_at',
                'rights_ends_at',
                'rights_extension_requested_at',
                'rights_extension_requested_by',
            ]);
        });
    }
};
