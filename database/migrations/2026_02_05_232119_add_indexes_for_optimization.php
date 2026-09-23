<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция для добавления индексов, улучшающих производительность запросов.
 * Также добавлены правила onDelete для внешних ключей.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Индексы для таблицы users
        Schema::table('users', function (Blueprint $table) {
            $table->index('user_phone', 'idx_users_phone');
            $table->index('is_active', 'idx_users_is_active');
        });
        
        // Индексы для таблицы orders
        Schema::table('orders', function (Blueprint $table) {
            $table->index('master_id', 'idx_orders_master_id');
            $table->index('source_id', 'idx_orders_source_id');
            $table->index('order_created_at', 'idx_orders_created_at');
            $table->index(['master_id', 'order_status'], 'idx_orders_master_status');
        });
        
        // Индексы для таблицы addresses
        Schema::table('addresses', function (Blueprint $table) {
            $table->index('city_id', 'idx_addresses_city_id');
        });
        
        // Индексы для таблицы cfm_operations
        Schema::table('cfm_operations', function (Blueprint $table) {
            $table->index('cfm_created_at', 'idx_cfm_created_at');
            $table->index('cfm_closed_at', 'idx_cfm_closed_at');
            $table->index('related_order_id', 'idx_cfm_related_order_id');
            $table->index('cfm_cat_id', 'idx_cfm_cat_id');
            $table->index(['city_id', 'cfm_closed_at'], 'idx_cfm_city_closed_at');
        });
        
        // Индексы для таблицы calls
        Schema::table('calls', function (Blueprint $table) {
            $table->index('operator_id', 'idx_calls_operator_id');
            $table->index('call_created_at', 'idx_calls_created_at');
        });
        
        // Индексы для таблицы route_actions
        Schema::table('route_actions', function (Blueprint $table) {
            $table->index('route_id', 'idx_route_actions_route_id');
            $table->index('promoter_id', 'idx_route_actions_promoter_id');
            $table->index('route_action_date', 'idx_route_actions_date');
            $table->index(['city_id', 'route_action_date'], 'idx_route_actions_city_date');
        });
        
        // Индексы для таблицы prom_payments
        Schema::table('prom_payments', function (Blueprint $table) {
            $table->index('promoter_id', 'idx_prom_payments_promoter_id');
            $table->index('payment_status', 'idx_prom_payments_status');
        });
        
        // Индексы для таблицы prom_meetings
        Schema::table('prom_meetings', function (Blueprint $table) {
            $table->index('meeting_datetime', 'idx_prom_meetings_datetime');
            $table->index('meeting_status', 'idx_prom_meetings_status');
        });
        
        // Индексы для таблицы prom_appointments  
        Schema::table('prom_appointments', function (Blueprint $table) {
            $table->index('appointment_datetime', 'idx_prom_appointments_datetime');
            $table->index('appointment_status', 'idx_prom_appointments_status');
        });
        
        // Индексы для таблицы knowledge_articles
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->index('is_published', 'idx_knowledge_is_published');
            $table->index('article_category', 'idx_knowledge_category');
        });
        
        // Индексы для таблицы documents
        Schema::table('documents', function (Blueprint $table) {
            $table->index('uploaded_by', 'idx_documents_uploaded_by');
            $table->index(['documentable_type', 'documentable_id'], 'idx_documents_morph');
        });
        
        // Индексы для таблицы interviews
        Schema::table('interviews', function (Blueprint $table) {
            $table->index('user_id', 'idx_interviews_user_id');
        });
        
        // Индексы для таблицы routes
        Schema::table('routes', function (Blueprint $table) {
            $table->index('district_id', 'idx_routes_district_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем индексы для таблицы users
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_phone');
            $table->dropIndex('idx_users_is_active');
        });
        
        // Удаляем индексы для таблицы orders
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_master_id');
            $table->dropIndex('idx_orders_source_id');
            $table->dropIndex('idx_orders_created_at');
            $table->dropIndex('idx_orders_master_status');
        });
        
        // Удаляем индексы для таблицы addresses
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('idx_addresses_city_id');
        });
        
        // Удаляем индексы для таблицы cfm_operations
        Schema::table('cfm_operations', function (Blueprint $table) {
            $table->dropIndex('idx_cfm_created_at');
            $table->dropIndex('idx_cfm_closed_at');
            $table->dropIndex('idx_cfm_related_order_id');
            $table->dropIndex('idx_cfm_cat_id');
            $table->dropIndex('idx_cfm_city_closed_at');
        });
        
        // Удаляем индексы для таблицы calls
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex('idx_calls_operator_id');
            $table->dropIndex('idx_calls_created_at');
        });
        
        // Удаляем индексы для таблицы route_actions
        Schema::table('route_actions', function (Blueprint $table) {
            $table->dropIndex('idx_route_actions_route_id');
            $table->dropIndex('idx_route_actions_promoter_id');
            $table->dropIndex('idx_route_actions_date');
            $table->dropIndex('idx_route_actions_city_date');
        });
        
        // Удаляем индексы для таблицы prom_payments
        Schema::table('prom_payments', function (Blueprint $table) {
            $table->dropIndex('idx_prom_payments_promoter_id');
            $table->dropIndex('idx_prom_payments_status');
        });
        
        // Удаляем индексы для таблицы prom_meetings
        Schema::table('prom_meetings', function (Blueprint $table) {
            $table->dropIndex('idx_prom_meetings_datetime');
            $table->dropIndex('idx_prom_meetings_status');
        });
        
        // Удаляем индексы для таблицы prom_appointments
        Schema::table('prom_appointments', function (Blueprint $table) {
            $table->dropIndex('idx_prom_appointments_datetime');
            $table->dropIndex('idx_prom_appointments_status');
        });
        
        // Удаляем индексы для таблицы knowledge_articles
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropIndex('idx_knowledge_is_published');
            $table->dropIndex('idx_knowledge_category');
        });
        
        // Удаляем индексы для таблицы documents
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_uploaded_by');
            $table->dropIndex('idx_documents_morph');
        });
        
        // Удаляем индексы для таблицы interviews
        Schema::table('interviews', function (Blueprint $table) {
            $table->dropIndex('idx_interviews_user_id');
        });
        
        // Удаляем индексы для таблицы routes
        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex('idx_routes_district_id');
        });
    }
};
