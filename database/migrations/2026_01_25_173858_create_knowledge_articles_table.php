<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создание таблицы статей базы знаний
     */
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id('article_id');
            $table->string('article_title', 255);
            $table->text('article_content');
            $table->string('article_category', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->foreignId('updated_by')->nullable()->constrained('users', 'user_id');
            $table->timestamps();
        });
    }

    /**
     * Откат миграции
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};
