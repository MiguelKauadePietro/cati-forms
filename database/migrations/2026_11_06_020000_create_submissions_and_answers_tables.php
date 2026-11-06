<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->restrictOnDelete();
            $table->string('contact_email', 254)->nullable();
            $table->timestamps();
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->text('text')->nullable();
            $table->text('justification')->nullable();
            $table->timestamps();
            $table->unique(['submission_id', 'question_id']);
        });

        Schema::create('answer_options', function (Blueprint $table) {
            $table->foreignId('answer_id')->constrained()->restrictOnDelete();
            $table->foreignId('question_option_id')->constrained()->restrictOnDelete();
            $table->primary(['answer_id', 'question_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_options');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('submissions');
    }
};
