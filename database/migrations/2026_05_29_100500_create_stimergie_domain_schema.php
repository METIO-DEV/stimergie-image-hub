<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_object_key')->nullable();
            $table->text('legacy_logo_url')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('client_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->index();
            $table->string('status')->default('active')->index();
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'user_id']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id')->nullable()->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->nullable();
            $table->string('source_folder')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'slug']);
        });

        Schema::create('project_access_periods', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id')->nullable()->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
        });

        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id')->nullable()->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('orientation')->nullable()->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum')->nullable()->index();
            $table->string('storage_provider')->default('scaleway')->index();
            $table->string('object_key_original')->nullable();
            $table->string('object_key_web')->nullable();
            $table->string('object_key_thumb')->nullable();
            $table->string('object_key_hd')->nullable();
            $table->string('legacy_url')->nullable();
            $table->string('legacy_thumbnail_url')->nullable();
            $table->string('status')->default('pending_upload')->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'project_id', 'status']);
        });

        Schema::create('image_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('object_key');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->unique(['image_id', 'kind']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('image_tag', function (Blueprint $table) {
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['image_id', 'tag_id']);
        });

        Schema::create('image_client_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['image_id', 'client_id']);
        });

        Schema::create('shared_albums', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id')->nullable()->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('share_key')->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('shared_album_images', function (Blueprint $table) {
            $table->foreignId('shared_album_id')->constrained()->cascadeOnDelete();
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['shared_album_id', 'image_id']);
        });

        Schema::create('download_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id')->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('pending')->index();
            $table->boolean('is_hd')->default(false);
            $table->unsignedInteger('image_count')->default(0);
            $table->string('storage_provider')->nullable();
            $table->string('object_key')->nullable();
            $table->text('download_url')->nullable();
            $table->timestamp('download_url_expires_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_details')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source');
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('image_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_identifier');
            $table->string('status')->default('pending')->index();
            $table->text('error_details')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id')->nullable()->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('content_type')->default('resource')->index();
            $table->string('category')->nullable()->index();
            $table->string('featured_image_object_key')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('import_items');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('download_jobs');
        Schema::dropIfExists('shared_album_images');
        Schema::dropIfExists('shared_albums');
        Schema::dropIfExists('image_client_shares');
        Schema::dropIfExists('image_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('image_variants');
        Schema::dropIfExists('images');
        Schema::dropIfExists('project_access_periods');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('client_memberships');
        Schema::dropIfExists('clients');
    }
};
