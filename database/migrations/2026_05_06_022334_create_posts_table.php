<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('featured_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('audio_attachment_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('title', 512);
            $table->string('slug', 512)->unique();
            $table->longText('content_markdown');
            $table->longText('content_html_cached')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'published'])->default('draft')->index();
            $table->string('password')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('meta_title', 512)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image_url', 512)->nullable();
            $table->string('canonical_url', 512)->nullable();
            $table->boolean('noindex')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('posts'); }
};
