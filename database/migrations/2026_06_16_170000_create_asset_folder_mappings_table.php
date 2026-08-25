<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_folder_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('folder')->unique();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('mapped')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_folder_mappings');
    }
};
