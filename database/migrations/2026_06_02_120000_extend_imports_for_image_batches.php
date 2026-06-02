<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->unsignedInteger('uploaded_items')->default(0)->after('total_items');
            $table->unsignedInteger('duplicate_items')->default(0)->after('failed_items');
            $table->unsignedBigInteger('total_bytes')->default(0)->after('duplicate_items');
        });

        Schema::table('import_items', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('source_identifier');
            $table->string('relative_path')->nullable()->after('original_filename');
            $table->string('mime_type')->nullable()->after('relative_path');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->string('checksum')->nullable()->index()->after('size_bytes');
            $table->string('object_key_original')->nullable()->after('checksum');
            $table->unsignedInteger('attempts')->default(0)->after('object_key_original');
            $table->timestamp('processed_at')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('import_items', function (Blueprint $table) {
            $table->dropIndex(['checksum']);
            $table->dropColumn([
                'original_filename',
                'relative_path',
                'mime_type',
                'size_bytes',
                'checksum',
                'object_key_original',
                'attempts',
                'processed_at',
            ]);
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn([
                'uploaded_items',
                'duplicate_items',
                'total_bytes',
            ]);
        });
    }
};
