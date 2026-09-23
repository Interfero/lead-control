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
        Schema::create('promoters', function (Blueprint $table) {
            $table->id('promoter_id');
            $table->foreignId('city_id')->constrained('cities', 'city_id');
            
            // Личные данные
            $table->string('promoter_name', 255);           // ФИО
            $table->string('promoter_phone', 10)->nullable(); // Телефон (без +7)
            $table->string('promoter_telegram', 100)->nullable();
            $table->integer('promoter_age')->nullable();
            $table->text('promoter_address')->nullable();
            
            // Реквизиты для оплаты
            $table->string('promoter_requisites', 255)->nullable(); // Номер карты/счёта
            $table->foreignId('bank_id')->nullable()->constrained('banks', 'bank_id')->nullOnDelete();
            
            // Статус
            $table->enum('promoter_status', ['active', 'fired'])->default('active');
            $table->date('hired_at')->nullable();
            $table->date('fired_at')->nullable();
            
            $table->text('promoter_comment')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('sources', 'source_id'); // Откуда узнал
            
            // Служебные
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->timestamps();
            
            $table->index('city_id');
            $table->index('promoter_status');
            $table->index('promoter_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promoters');
    }
};
