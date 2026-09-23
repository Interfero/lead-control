<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Явная таблица соответствия «мастер внешней CRM ↔ пользователь Lead Control».
 *
 * ТЗ Единого хаба: нельзя полагаться только на email или ФИО — нужна явная карта
 * CRM/KP master ID. Строки с link_type=manual считаются истиной и не перетираются
 * автоматическим линковщиком (команда hub:link-masters).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_master_links', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('kp_lead');
            $table->string('external_master_id', 64)->nullable();
            $table->string('external_master_name', 255)->nullable();
            // Нормализованное ФИО (нижний регистр, ё→е, «Фамилия Имя») — ключ поиска по имени
            $table->string('name_key', 255)->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('link_type', 16)->default('auto'); // auto | manual
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_master_id'], 'hub_master_links_source_ext_unique');
            $table->unique(['source', 'name_key', 'city_id'], 'hub_master_links_source_name_city_unique');
            $table->index(['user_id', 'source']);
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_master_links');
    }
};
