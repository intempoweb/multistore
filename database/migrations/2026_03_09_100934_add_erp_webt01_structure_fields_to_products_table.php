<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Gerarchia merceologica ERP
            $table->string('fam_99', 20)->nullable()->after('codgrupfis_mg61')->index();
            $table->string('sfam_99', 20)->nullable()->after('fam_99')->index();
            $table->string('gruppo_99', 20)->nullable()->after('sfam_99')->index();
            $table->string('sgruppo_99', 20)->nullable()->after('gruppo_99')->index();
            $table->string('marca_mg64', 20)->nullable()->after('sgruppo_99')->index();

            // Opzioni / raggruppamenti ERP
            $table->string('opzionefam_webt01', 10)->nullable()->after('marca_mg64');
            $table->string('opzioneraggr_webt01', 10)->nullable()->after('opzionefam_webt01');

            $table->string('raggrupcat1_w51', 20)->nullable()->after('opzioneraggr_webt01')->index();
            $table->string('raggrupcat2_w52', 20)->nullable()->after('raggrupcat1_w51')->index();
            $table->string('raggrupcat3_w53', 20)->nullable()->after('raggrupcat2_w52')->index();
            $table->string('raggrupcat4_w54', 20)->nullable()->after('raggrupcat3_w53')->index();

            $table->string('codlinea_w55', 20)->nullable()->after('raggrupcat4_w54')->index();
            $table->string('codedizione_w56', 20)->nullable()->after('codlinea_w55')->index();
            $table->string('codcollezione_w57', 20)->nullable()->after('codedizione_w56')->index();
            $table->string('codbrand_w58', 20)->nullable()->after('codcollezione_w57')->index();
            $table->string('codfantasie_w59', 20)->nullable()->after('codbrand_w58')->index();
            $table->string('codassociazioneart_w60', 20)->nullable()->after('codfantasie_w59')->index();

            $table->string('raggrupassoc1_w61', 20)->nullable()->after('codassociazioneart_w60')->index();
            $table->string('raggrupassoc2_w62', 20)->nullable()->after('raggrupassoc1_w61')->index();
            $table->string('raggrupassoc3_w63', 20)->nullable()->after('raggrupassoc2_w62')->index();
            $table->string('raggrupassoc4_w64', 20)->nullable()->after('raggrupassoc3_w63')->index();

            // Catalogo / campagne
            $table->string('pagcatalogo_webt01', 20)->nullable()->after('raggrupassoc4_w64');

            $table->boolean('flgofferta_webt01')->nullable()->after('pagcatalogo_webt01')->index();
            $table->date('datainizofferta_webt01')->nullable()->after('flgofferta_webt01');
            $table->date('datafineofferta_webt01')->nullable()->after('datainizofferta_webt01');

            $table->boolean('flgpromo_webt01')->nullable()->after('datafineofferta_webt01')->index();
            $table->date('datainizpromo_webt01')->nullable()->after('flgpromo_webt01');
            $table->date('datafinepromo_webt01')->nullable()->after('datainizpromo_webt01');

            $table->boolean('flgnovita_webt01')->nullable()->after('datafinepromo_webt01')->index();
            $table->date('datainiznovita_webt01')->nullable()->after('flgnovita_webt01');
            $table->date('datafinenovita_webt01')->nullable()->after('datainiznovita_webt01');

            $table->boolean('flgcampagna_webt01')->nullable()->after('datafinenovita_webt01')->index();
            $table->date('datainizcampagna_webt01')->nullable()->after('flgcampagna_webt01');
            $table->date('datafinecampagna_webt01')->nullable()->after('datainizcampagna_webt01');

            // Visibilità / semaforo
            $table->decimal('qtamaxvisibile_webt01', 15, 3)->nullable()->after('datafinecampagna_webt01');
            $table->boolean('flgsemaforo_webt01')->nullable()->after('qtamaxvisibile_webt01')->index();
            $table->decimal('qtasemafverde_webt01', 15, 3)->nullable()->after('flgsemaforo_webt01');
            $table->decimal('qtasemafarancio_webt01', 15, 3)->nullable()->after('qtasemafverde_webt01');
            $table->decimal('qtasemafrosso_webt01', 15, 3)->nullable()->after('qtasemafarancio_webt01');

            // Dati packaging / misure
            $table->string('notedepprel_mg69', 255)->nullable()->after('qtasemafrosso_webt01');
            $table->string('codconfez_mg96', 20)->nullable()->after('notedepprel_mg69');
            $table->decimal('pzconf_mg68', 15, 3)->nullable()->after('codconfez_mg96');

            $table->decimal('pesocalc', 15, 4)->nullable()->after('pzconf_mg68');
            $table->string('umpeso_mg68', 10)->nullable()->after('pesocalc');
            $table->decimal('peson_mg68', 15, 4)->nullable()->after('umpeso_mg68');
            $table->decimal('pesol_mg68', 15, 4)->nullable()->after('peson_mg68');
            $table->decimal('massanetta_mg98', 15, 6)->nullable()->after('pesol_mg68');

            $table->decimal('largh_mg68', 15, 4)->nullable()->after('massanetta_mg98');
            $table->decimal('altez_mg68', 15, 4)->nullable()->after('largh_mg68');
            $table->decimal('prof_mg68', 15, 4)->nullable()->after('altez_mg68');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'fam_99',
                'sfam_99',
                'gruppo_99',
                'sgruppo_99',
                'marca_mg64',
                'opzionefam_webt01',
                'opzioneraggr_webt01',
                'raggrupcat1_w51',
                'raggrupcat2_w52',
                'raggrupcat3_w53',
                'raggrupcat4_w54',
                'codlinea_w55',
                'codedizione_w56',
                'codcollezione_w57',
                'codbrand_w58',
                'codfantasie_w59',
                'codassociazioneart_w60',
                'raggrupassoc1_w61',
                'raggrupassoc2_w62',
                'raggrupassoc3_w63',
                'raggrupassoc4_w64',
                'pagcatalogo_webt01',
                'flgofferta_webt01',
                'datainizofferta_webt01',
                'datafineofferta_webt01',
                'flgpromo_webt01',
                'datainizpromo_webt01',
                'datafinepromo_webt01',
                'flgnovita_webt01',
                'datainiznovita_webt01',
                'datafinenovita_webt01',
                'flgcampagna_webt01',
                'datainizcampagna_webt01',
                'datafinecampagna_webt01',
                'qtamaxvisibile_webt01',
                'flgsemaforo_webt01',
                'qtasemafverde_webt01',
                'qtasemafarancio_webt01',
                'qtasemafrosso_webt01',
                'notedepprel_mg69',
                'codconfez_mg96',
                'pzconf_mg68',
                'pesocalc',
                'umpeso_mg68',
                'peson_mg68',
                'pesol_mg68',
                'massanetta_mg98',
                'largh_mg68',
                'altez_mg68',
                'prof_mg68',
            ]);
        });
    }
};