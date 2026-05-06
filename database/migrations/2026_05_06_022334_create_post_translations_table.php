<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('post_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title', 512);
            $table->string('slug', 512);
            $table->longText('content_markdown');
            $table->longText('content_html_cached')->nullable();
            $table->string('meta_title', 512)->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_ai_translated')->default(false);
            $table->timestamps();
            $table->unique(['post_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });
    }
    public function down(): void { Schema::dropIfExists('post_translations'); }
};
