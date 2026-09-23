<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_policies', function (Blueprint $table) {
            $table->id('salary_policy_id');
            $table->unsignedBigInteger('city_id');
            $table->boolean('salary_enabled')->default(false);
            $table->unsignedInteger('salary_amount')->default(0);
            /** Первый расчётный месяц действия: YYYY-MM-01 */
            $table->date('effective_month');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('city_id')->references('city_id')->on('cities')->cascadeOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['city_id', 'effective_month']);
            $table->index(['city_id', 'effective_month']);
        });

        Schema::create('salary_calculations', function (Blueprint $table) {
            $table->id('salary_calculation_id');
            $table->unsignedBigInteger('city_id');
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            /** Расчётный месяц: YYYY-MM-01 */
            $table->date('period_month');
            $table->integer('incas_base')->default(0);
            $table->unsignedTinyInteger('rate_percent')->default(15);
            $table->unsignedInteger('commission_amount')->default(0);
            $table->unsignedInteger('salary_amount')->default(0);
            $table->unsignedInteger('accrued_amount')->default(0);
            $table->unsignedInteger('policy_version')->nullable();
            $table->unsignedInteger('calculation_version')->default(1);
            /** preliminary|fixed|needs_recalc|error */
            $table->string('status', 32)->default('preliminary');
            $table->string('error_message', 500)->nullable();
            $table->string('calc_code', 32)->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamp('recalc_requested_at')->nullable();
            $table->timestamps();

            $table->foreign('city_id')->references('city_id')->on('cities')->cascadeOnDelete();
            $table->foreign('recipient_user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->unique(['city_id', 'period_month']);
            $table->index(['recipient_user_id', 'period_month']);
            $table->index(['status', 'period_month']);
        });

        Schema::create('salary_audit_logs', function (Blueprint $table) {
            $table->id('salary_audit_id');
            $table->string('action', 64);
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('salary_calculation_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('reason', 1000)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
        });

        Schema::table('cfm_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('salary_calculation_id')->nullable()->after('related_user_id');
            $table->foreign('salary_calculation_id')
                ->references('salary_calculation_id')
                ->on('salary_calculations')
                ->nullOnDelete();
            $table->index('salary_calculation_id');
        });
    }

    public function down(): void
    {
        Schema::table('cfm_operations', function (Blueprint $table) {
            $table->dropForeign(['salary_calculation_id']);
            $table->dropColumn('salary_calculation_id');
        });
        Schema::dropIfExists('salary_audit_logs');
        Schema::dropIfExists('salary_calculations');
        Schema::dropIfExists('salary_policies');
    }
};
