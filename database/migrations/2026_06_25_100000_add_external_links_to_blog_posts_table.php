<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->json('external_links')->nullable()->after('featured_image_object_key');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('external_links');
        });
    }
};
