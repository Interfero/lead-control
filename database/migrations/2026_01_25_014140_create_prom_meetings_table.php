<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prom_meetings', function (Blueprint $table) {
            $table->id('meeting_id');
            
            // Основные поля
            $table->dateTime('meeting_datetime');           // Дата и время встречи
            $table->foreignId('city_id')->constrained('cities', 'city_id');
            
            // Данные человека (денормализовано для истории)
            $table->string('person_phone', 10)->nullable(); // Телефон
            $table->string('person_telegram', 100)->nullable(); // Телеграм
            $table->integer('person_age')->nullable();      // Возраст
            $table->string('person_name', 255);             // ФИО
            $table->text('person_address')->nullable();     // Адрес (текст)
            
            // Флаг собеседования
            $table->boolean('is_interview')->default(false); // Это собеседование?
            
            // Рабочие данные
            $table->foreignId('route_id')->nullable()->constrained('routes', 'route_id');
            $table->foreignId('maket_id')->nullable()->constrained('route_makets', 'maket_id');
            $table->integer('leaflets_issued')->default(0); // Листовок выдано
            $table->foreignId('source_id')->nullable()->constrained('sources', 'source_id'); // Откуда узнал о вакансии
            
            // Статус встречи
            $table->enum('meeting_status', [
                'in_progress',  // В работе
                'completed',    // Завершено
                'refusal',      // Отказ (отказался работать)
                'return',       // Возврат (вернул листовки)
                'not_returned', // Не сдал (потерялся с листовками)
                'ignored'       // Игнор (не отвечает)
            ])->default('in_progress');
            
            $table->text('meeting_comment')->nullable();
            
            // Связь с промоутером (если стал промоутером)
            $table->foreignId('promoter_id')->nullable()->constrained('promoters', 'promoter_id')->nullOnDelete();
            
            // Служебные поля
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->timestamps();
            
            $table->index('meeting_datetime');
            $table->index('meeting_status');
            $table->index('is_interview');
            $table->index('promoter_id');
            $table->index(['city_id', 'meeting_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prom_meetings');
    }
};
