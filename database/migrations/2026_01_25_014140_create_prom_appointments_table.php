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
        Schema::create('prom_appointments', function (Blueprint $table) {
            $table->id('appointment_id');
            
            // Основные поля
            $table->dateTime('appointment_datetime');       // Дата и время записи
            $table->foreignId('city_id')->constrained('cities', 'city_id');
            
            // Данные человека
            $table->boolean('is_interview')->default(false); // Это собеседование?
            $table->string('person_phone', 10)->nullable(); // Телефон (только если собеседование)
            $table->string('person_telegram', 100)->nullable(); // Телеграм (только если собеседование)
            $table->string('person_name', 255);             // Имя
            
            // Локация
            $table->foreignId('district_id')->nullable()->constrained('districts', 'district_id');
            
            // Работа
            $table->integer('leaflets_to_prepare')->default(0); // Листовок подготовить
            
            // Статус записи
            $table->enum('appointment_status', [
                'scheduled',    // Запись (запланировано)
                'attended',     // Встреча (пришёл)
                'refusal',      // Отказ
                'ignored'       // Игнор
            ])->default('scheduled');
            
            $table->text('appointment_comment')->nullable();
            
            // Служебные поля
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->timestamps();
            
            $table->index('appointment_datetime');
            $table->index('appointment_status');
            $table->index(['city_id', 'appointment_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prom_appointments');
    }
};
