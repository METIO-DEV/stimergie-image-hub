<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_transfer_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('mode')->default('batch-copy');
            $table->unsignedInteger('total_folders')->default(0);
            $table->unsignedInteger('processed_folders')->default(0);
            $table->unsignedInteger('failed_folders')->default(0);
            $table->string('current_folder')->nullable();
            $table->json('folders')->nullable();
            $table->json('completed_folders')->nullable();
            $table->json('failed_folder_details')->nullable();
            $table->text('log_file')->nullable();
            $table->unsignedInteger('process_id')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfer_jobs');
    }
};
