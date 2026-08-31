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
        Schema::table('guest_respondents', function (Blueprint $table) {
            $table->string('whatsapp_phone', 50)->nullable()->after('phone');
            $table->string('external_reference', 100)->nullable()->after('whatsapp_phone');
            $table->json('metadata')->nullable()->after('identity_hash');
            $table->boolean('status')->default(true)->after('metadata');
            $table->unsignedBigInteger('created_by')->nullable()->after('status');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');

            $table->foreign('created_by', 'fk_guest_respondents_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_guest_respondents_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            // Nullable-safe: evitan altas equivalentes accidentales por canal
            // sin impedir múltiples invitados sin ese dato (NULL no colisiona).
            $table->unique(['company_id', 'email'], 'uq_guest_respondents_company_email');
            $table->unique(['company_id', 'phone'], 'uq_guest_respondents_company_phone');
            $table->unique(['company_id', 'whatsapp_phone'], 'uq_guest_respondents_company_whatsapp');
            $table->unique(['company_id', 'external_reference'], 'uq_guest_respondents_company_external_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_respondents', function (Blueprint $table) {
            $table->dropUnique('uq_guest_respondents_company_email');
            $table->dropUnique('uq_guest_respondents_company_phone');
            $table->dropUnique('uq_guest_respondents_company_whatsapp');
            $table->dropUnique('uq_guest_respondents_company_external_ref');
            $table->dropForeign('fk_guest_respondents_created_by');
            $table->dropForeign('fk_guest_respondents_updated_by');
            $table->dropColumn(['whatsapp_phone', 'external_reference', 'metadata', 'status', 'created_by', 'updated_by']);
        });
    }
};
