<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('ip_hash', 64);
            $table->char('country', 2)->nullable();
            $table->string('session_id', 64);
            $table->timestamp('viewed_at')->useCurrent();
            $table->index(['post_id', 'viewed_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('page_views'); }
};
