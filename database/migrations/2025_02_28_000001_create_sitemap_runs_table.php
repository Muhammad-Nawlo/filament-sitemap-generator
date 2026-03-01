<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitemap_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('generated_at');
            $table->unsignedInteger('total_urls')->default(0);
            $table->unsignedInteger('static_urls')->default(0);
            $table->unsignedInteger('model_urls')->default(0);
            $table->unsignedInteger('file_size')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->unsignedInteger('crawled_urls')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitemap_runs');
    }
};
