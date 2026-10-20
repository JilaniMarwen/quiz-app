<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            // Null = no answer (time ran out).
            $table->foreignId('choice_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            // Rule 3: server-side timer reference.
            $table->timestamp('served_at', 3);
            $table->timestamp('answered_at', 3)->nullable();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
    }
};
