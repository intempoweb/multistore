<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // ERP keys
            $table->unsignedSmallInteger('ditta_cg18');
            $table->unsignedTinyInteger('tipocf_cg44');
            $table->unsignedInteger('clifor_cg44');

            // Main identity / web
            $table->unsignedInteger('codice_cg16')->nullable();
            $table->string('ragsoanag_cg16', 60);
            $table->string('partiva_cg16', 20)->nullable();

            $table->string('codfiscale_cg16', 20)->nullable();

            // Web contact person (ERP: COGNOMECONNWEB / NOMECONNWEB)
            $table->string('cognomeconnweb', 1)->nullable();
            $table->string('nomeconnweb', 1)->nullable();

            // Contacts
            $table->string('indemail_cg16', 128)->nullable();
            $table->string('email_pec_cg16', 128)->nullable();
            $table->string('tel1num_cg16', 24)->nullable();
            $table->string('tel2num_cg16', 24)->nullable();
            $table->string('faxnum_cg16', 24)->nullable();
            $table->string('cellnum_cg16', 24)->nullable();
            $table->string('indweb_cg16', 128)->nullable();

            // Invoicing flags (kept as-is from ERP)
            $table->string('indemailperfatt_cg16', 1)->nullable();

            // Addresses (legal)
            $table->string('indirizzo_cg16', 40)->nullable();
            $table->string('cap_cg16', 10)->nullable();
            $table->string('citta_cg16', 40)->nullable();
            $table->string('prov_cg16', 2)->nullable();

            // Addresses (shipping)
            $table->string('ragsocor_cg16', 60)->nullable();
            $table->string('indircor_cg16', 40)->nullable();
            $table->string('capcor_cg16', 10)->nullable();
            $table->string('cittacor_cg16', 40)->nullable();
            $table->string('provcor_cg16', 2)->nullable();

            // Payment / VAT
            $table->string('codpag_cg62', 6)->nullable();
            $table->string('descrizpag_cg62', 40)->nullable();

            // Agent + extra web fields from ERP view
            $table->string('agente_mg17', 40)->nullable();
            $table->string('ragsoanag_vwebdcg44', 60)->nullable();
            $table->string('indeemail_vwebdcg44', 128)->nullable();
            $table->string('codice_cg28', 4)->nullable();
            $table->string('descr_cg28', 40)->nullable();
            $table->decimal('perciva_cg28', 5, 2)->nullable();

            // Default ERP list price code
            $table->unsignedInteger('codlistinoded')->nullable();

            // IMPORTANT: ERP flag used to enable/disable customer visibility on web
            // In ERP this is CODRIFALF_MG19.
            $table->string('codrifalf_mg19', 12)->nullable();

            // Optional banking data
            // NOTE: ERP values can exceed UNSIGNED SMALLINT (max 65535). Example: CCCAB=70430.
            $table->unsignedInteger('ccabi_mg35')->nullable();
            $table->unsignedInteger('cccab_mg35')->nullable();
            $table->string('desbanca_cg12_cg13', 83)->nullable();
            $table->string('iban_mg35', 34)->nullable();

            // Misc
            $table->unsignedInteger('filtroestr')->nullable();

            // Derived/local flags
            // NOTE: ERP view ANAGRCLI_TOT returns only customers with CODRIFALF_MG19='PT'.
            // If later PT is removed in ERP, the customer will disappear from the view.
            // We keep the record locally and mark it inactive when it is not seen in a sync run.
            $table->boolean('is_active')->default(false);

            // ERP watermark from ANAGRCLI_TOT.LASTCHANGE (date in ERP)
            $table->date('erp_lastchange')->nullable();

            // When we last saw this customer in ERP during a sync run
            $table->dateTime('erp_last_seen_at')->nullable();

            $table->timestamps();

            // Constraints
            $table->unique(['ditta_cg18', 'tipocf_cg44', 'clifor_cg44'], 'uq_customers_erp_key');

            // Indexes
            $table->index(['ditta_cg18', 'clifor_cg44'], 'ix_customers_ditta_clifor');
            $table->index('ragsoanag_cg16', 'ix_customers_ragione_sociale');
            $table->index('codice_cg16', 'ix_customers_codice_cg16');
            $table->index('partiva_cg16', 'ix_customers_partiva');
            $table->index('codfiscale_cg16', 'ix_customers_codfiscale');
            $table->index('indemail_cg16', 'ix_customers_email');
            $table->index('codrifalf_mg19', 'ix_customers_codrifalf');
            $table->index(['ditta_cg18', 'is_active'], 'ix_customers_active_by_ditta');
            $table->index('erp_last_seen_at', 'ix_customers_last_seen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
