<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('blog_posts')
            ->where('content_type', 'ensemble')
            ->update(['content_type' => 'blog']);
    }

    public function down(): void
    {
        DB::table('blog_posts')
            ->where('content_type', 'blog')
            ->whereNull('client_id')
            ->update(['content_type' => 'ensemble']);
    }
};
