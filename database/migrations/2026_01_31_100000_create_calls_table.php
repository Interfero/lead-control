<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создание таблицы звонков для интеграции с Mango Office
     */
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id('call_id');
            
            // Связь с персоной (если нашли по телефону)
            $table->unsignedBigInteger('person_id')->nullable();
            $table->foreign('person_id')->references('person_id')->on('persons')->nullOnDelete();
            
            // Опциональная связь с заказом
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('order_id')->on('orders')->nullOnDelete();
            
            // Оператор, обработавший звонок
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->foreign('operator_id')->references('user_id')->on('users')->nullOnDelete();
            
            // Данные звонка
            $table->string('phone', 20);                      // Номер телефона клиента (нормализованный)
            $table->string('direction', 10);                  // in / out
            $table->string('status', 50);                     // Статус из Mango (initiated, connected, result, etc.)
            $table->string('record_url', 500)->nullable();    // Ссылка на запись разговора
            $table->string('mango_call_id', 100)->nullable(); // ID звонка в Mango (для дедупликации/обновления)
            $table->string('internal_number', 20)->nullable(); // Внутренний номер оператора
            $table->integer('duration')->nullable();          // Длительность звонка в секундах
            
            // Таймстампы
            $table->timestamp('call_created_at')->useCurrent();
            $table->timestamp('call_updated_at')->useCurrent()->useCurrentOnUpdate();
            
            // Индексы для быстрого поиска
            $table->index('person_id');
            $table->index('phone');
            $table->index('mango_call_id');
            $table->index('call_created_at');
            $table->index(['direction', 'status']);
        });
    }

    /**
     * Откат миграции
     */
    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
