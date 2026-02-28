<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitemap_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('singleton')->default(1)->unique();
            $table->json('static_urls')->nullable();
            $table->json('models')->nullable();
            $table->string('default_change_frequency')->nullable();
            $table->decimal('default_priority', 3, 2)->nullable();
            $table->boolean('auto_generate_enabled')->default(false);
            $table->string('auto_generate_frequency', 20)->nullable(); // 'daily', 'hourly'
            $table->string('storage_path', 100)->default('public');
            $table->string('filename', 100)->default('sitemap.xml');
            $table->boolean('gzip_enabled')->default(false);
            $table->unsignedInteger('chunk_size')->default(1000);
            $table->boolean('large_site_mode')->default(false);
            $table->boolean('enable_index_sitemap')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitemap_settings');
    }
};
