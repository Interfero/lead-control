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
        // Роли пользователей
        Schema::create('roles', function (Blueprint $table) {
            $table->id('role_id');
            $table->string('role_code', 50)->unique(); // master, developer, etc.
            $table->string('role_name', 100);          // Мастер, Разработчик
            $table->text('role_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Города
        Schema::create('cities', function (Blueprint $table) {
            $table->id('city_id');
            $table->string('city_name', 255)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Источники заказов
        Schema::create('sources', function (Blueprint $table) {
            $table->id('source_id');
            $table->string('source_name', 255)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Пользователи
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('user_name', 255);                    // ФИО
            $table->string('email', 255)->unique();              // Для входа
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->string('user_phone', 10)->nullable();        // Телефон
            $table->text('user_passport')->nullable();           // Паспортные данные
            $table->date('user_birth_date')->nullable();         // Дата рождения
            $table->date('user_hired_at')->nullable();           // Дата найма
            $table->date('user_fired_at')->nullable();           // Дата увольнения
            $table->text('user_note')->nullable();               // Комментарий
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        // Связь пользователей и ролей
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles', 'role_id')->onDelete('cascade');
            $table->primary(['user_id', 'role_id']);
            $table->timestamps();
        });

        // Связь пользователей и городов
        Schema::create('user_cities', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('city_id')->constrained('cities', 'city_id')->onDelete('cascade');
            $table->primary(['user_id', 'city_id']);
            $table->timestamps();
        });

        // Персоны (клиенты)
        Schema::create('persons', function (Blueprint $table) {
            $table->id('person_id');
            $table->string('person_name', 255);
            $table->integer('person_age')->nullable();
            $table->timestamps();
        });

        // Адреса
        Schema::create('addresses', function (Blueprint $table) {
            $table->id('address_id');
            $table->foreignId('person_id')->constrained('persons', 'person_id')->onDelete('cascade');
            $table->foreignId('city_id')->constrained('cities', 'city_id');
            $table->string('street', 255)->nullable();
            $table->string('house', 50)->nullable();
            $table->string('flat', 50)->nullable();
            $table->text('address_adds')->nullable();  // Комментарий к адресу
            $table->timestamps();
        });

        // Телефоны персон
        Schema::create('person_phones', function (Blueprint $table) {
            $table->id('phone_id');
            $table->foreignId('person_id')->constrained('persons', 'person_id')->onDelete('cascade');
            $table->char('phone_number', 10);        // Только цифры, без +7
            $table->string('phone_adds', 255)->nullable();  // Комментарий
            $table->timestamps();
            
            $table->index('phone_number');
        });

        // Заказы
        Schema::create('orders', function (Blueprint $table) {
            $table->id('order_id');
            $table->dateTime('datetime_order');      // Дата и время встречи
            $table->foreignId('address_id')->constrained('addresses', 'address_id');
            
            $table->enum('order_status', [
                'pending',           // Требуется обработка
                'callback',          // Прозвон
                'unassigned',        // Не назначено
                'on_way',            // В пути
                'in_progress',       // В работе
                'in_progress_sd',    // В работе СД (сервисная диагностика)
                'waiting_parts',     // Ожидание запчасти
                'waiting_payment',   // Ожидание оплаты
                'completed',         // Готов
                'cancelled_cc',      // Отмена КЦ
                'cancelled_city'     // Отмена Город
            ])->default('pending');
            
            $table->enum('order_type', [
                'new',       // Впервые
                'repeat',    // Повтор
                'warranty'   // Гарантия
            ])->default('new');
            
            $table->enum('order_core', [
                'core',      // Профильный
                'non_core'   // Непрофильный
            ])->default('core');
            
            $table->integer('amount_paid')->default(0);      // Оплачено клиентом
            $table->integer('amount_comp')->default(0);      // Стоимость комплектующих
            
            $table->foreignId('master_id')->nullable()->constrained('users', 'user_id');
            $table->foreignId('source_id')->nullable()->constrained('sources', 'source_id');
            
            $table->text('order_adds')->nullable();    // Описание проблемы
            $table->text('shift_adds')->nullable();    // Комментарии по переносам
            $table->text('city_adds')->nullable();     // Комментарий филиала
            
            $table->foreignId('order_created_by')->constrained('users', 'user_id');
            $table->timestamp('order_created_at')->useCurrent();
            $table->foreignId('order_closed_by')->nullable()->constrained('users', 'user_id');
            $table->timestamp('order_closed_at')->nullable();
            
            $table->index('datetime_order');
            $table->index('order_status');
        });

        // Связь заказов и персон
        Schema::create('order_persons', function (Blueprint $table) {
            $table->foreignId('order_id')->constrained('orders', 'order_id')->onDelete('cascade');
            $table->foreignId('person_id')->constrained('persons', 'person_id')->onDelete('cascade');
            $table->primary(['order_id', 'person_id']);
        });

        // Категории ДДС
        Schema::create('cfm_categories', function (Blueprint $table) {
            $table->id('cfm_cat_id');
            $table->string('cfm_cat_name', 255);
            
            $table->enum('cfm_cat_group', [
                'inflows',   // Поступление
                'outflows'   // Выбытие
            ]);
            
            $table->enum('cfm_cat_activities', [
                'operating',   // Операционная
                'investing',   // Инвестиционная
                'financing'    // Финансовая
            ]);
            
            $table->text('cfm_cat_adds')->nullable();  // Описание
            $table->boolean('is_auto')->default(false); // Автоматическое создание
            $table->boolean('is_visible')->default(true); // Видимость при создании
            $table->string('visible_for_roles', 255)->nullable(); // JSON или comma-separated
            $table->timestamps();
        });

        // Кассовые операции
        Schema::create('cfm_operations', function (Blueprint $table) {
            $table->id('cfm_id');
            $table->foreignId('city_id')->constrained('cities', 'city_id');
            $table->foreignId('cfm_cat_id')->constrained('cfm_categories', 'cfm_cat_id');
            $table->integer('amount_cfm')->default(0);
            $table->text('cfm_adds')->nullable();
            
            $table->foreignId('cfm_created_by')->constrained('users', 'user_id');
            $table->timestamp('cfm_created_at')->useCurrent();
            $table->foreignId('cfm_closed_by')->nullable()->constrained('users', 'user_id');
            $table->timestamp('cfm_closed_at')->nullable();
            
            // Связи для специфичных операций
            $table->foreignId('related_order_id')->nullable()->constrained('orders', 'order_id');
            $table->foreignId('related_city_id')->nullable()->constrained('cities', 'city_id'); // Для перемещений
            
            $table->timestamps();
        });

        // Маршруты (для рекламы)
        Schema::create('routes', function (Blueprint $table) {
            $table->id('route_id');
            $table->string('route_name', 255)->unique();
            $table->enum('route_type', ['city', 'private', 'mixed'])->default('city');
            $table->boolean('is_training')->default(false);
            $table->integer('route_boxes_count')->default(0);
            $table->integer('route_entrances_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('route_note', 255)->nullable();
            $table->timestamps();
        });

        // Макеты листовок
        Schema::create('route_makets', function (Blueprint $table) {
            $table->id('maket_id');
            $table->string('maket_name', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Действия по маршрутам
        Schema::create('route_actions', function (Blueprint $table) {
            $table->id('route_action_id');
            $table->foreignId('route_id')->constrained('routes', 'route_id');
            $table->date('route_action_date');
            $table->foreignId('maket_id')->nullable()->constrained('route_makets', 'maket_id');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users', 'user_id');
            
            $table->enum('route_action_status', [
                'planned',
                'to_issue',
                'issued',
                'in_progress',
                'done',
                'cancelled',
                'do_not_issue'
            ])->default('planned');
            
            $table->string('route_action_note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id');
            $table->timestamps();
        });

        // Собеседования
        Schema::create('interviews', function (Blueprint $table) {
            $table->id('interview_id');
            $table->foreignId('user_id')->constrained('users', 'user_id'); // Кандидат
            $table->dateTime('scheduled_at');
            $table->foreignId('manager_user_id')->nullable()->constrained('users', 'user_id');
            
            $table->enum('interview_status', [
                'booked',
                'came',
                'no_show',
                'unclear'
            ])->default('booked');
            
            $table->string('interview_note', 255)->nullable();
            $table->timestamps();
        });

        // График мастеров
        Schema::create('master_schedules', function (Blueprint $table) {
            $table->id('schedule_id');
            $table->foreignId('user_id')->constrained('users', 'user_id');
            $table->date('schedule_date');
            $table->boolean('is_working')->default(true);
            $table->string('schedule_note', 255)->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'schedule_date']);
        });

        // Документы (для файлов)
        Schema::create('documents', function (Blueprint $table) {
            $table->id('document_id');
            $table->string('documentable_type'); // Полиморфная связь
            $table->unsignedBigInteger('documentable_id');
            $table->string('document_category', 50); // contract, receipt, photo, etc.
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->string('file_mime', 100);
            $table->integer('file_size');
            $table->foreignId('uploaded_by')->constrained('users', 'user_id');
            $table->timestamps();
            
            $table->index(['documentable_type', 'documentable_id']);
        });

        // Password reset tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('master_schedules');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('route_actions');
        Schema::dropIfExists('route_makets');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('cfm_operations');
        Schema::dropIfExists('cfm_categories');
        Schema::dropIfExists('order_persons');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('person_phones');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('persons');
        Schema::dropIfExists('user_cities');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('roles');
    }
};
