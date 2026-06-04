<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_tag_analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('mode')->default('missing')->index();
            $table->unsignedInteger('total_images')->default(0);
            $table->unsignedInteger('processed_images')->default(0);
            $table->unsignedInteger('failed_images')->default(0);
            $table->foreignId('current_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('image_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_tag_analysis_runs');
    }
};
