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
        Schema::create('prom_payments', function (Blueprint $table) {
            $table->id('payment_id');
            
            // Основные поля
            $table->foreignId('city_id')->constrained('cities', 'city_id');
            $table->foreignId('promoter_id')->constrained('promoters', 'promoter_id');
            
            // Период работы (неделя)
            $table->date('week_start');                     // Начало недели
            $table->date('week_end');                       // Конец недели
            
            // Расчёт
            $table->integer('total_leaflets')->default(0); // Всего листовок за период
            $table->integer('rate_per_leaflet')->default(0); // Ставка за листовку (тг)
            $table->integer('amount_base')->default(0);    // Базовая сумма (листовки × ставка)
            $table->integer('amount_adjustment')->default(0); // Корректировки (+/-)
            $table->integer('amount_total')->default(0);   // Итого к оплате
            
            // Реквизиты для оплаты
            $table->string('payment_requisites', 255)->nullable(); // Номер карты/счёта
            $table->foreignId('payment_bank_id')->nullable()->constrained('banks', 'bank_id');
            
            $table->text('payment_comment')->nullable();
            
            // Статус оплаты
            $table->enum('payment_status', [
                'created',      // Сформировано
                'paid'          // Оплачено
            ])->default('created');
            
            // Связь с кассовой операцией
            $table->foreignId('cfm_operation_id')->nullable()->constrained('cfm_operations', 'cfm_id');
            
            // Служебные поля
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->timestamp('paid_at')->nullable();      // Дата проведения оплаты
            $table->timestamps();
            
            $table->index(['city_id', 'week_start']);
            $table->index(['promoter_id', 'week_start']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prom_payments');
    }
};
