<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('blog_posts')
            ->whereIn('content_type', ['ensemble', 'article', 'actualite', 'actualites'])
            ->update(['content_type' => 'blog']);

        DB::table('blog_posts')
            ->whereIn('content_type', ['ressource', 'ressources', 'resources'])
            ->update(['content_type' => 'resource']);
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->where('content_type', 'blog')
            ->whereNull('client_id')
            ->update(['content_type' => 'ensemble']);
    }
};
