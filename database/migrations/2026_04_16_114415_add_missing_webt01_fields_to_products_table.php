<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'flgmodultime_webt01' => fn (Blueprint $table) => $table->boolean('flgmodultime_webt01')->nullable()->after('no_backorder'),
            'flgintempo_webt01' => fn (Blueprint $table) => $table->boolean('flgintempo_webt01')->nullable()->after('flgmodultime_webt01'),
            'flgstaging_webt01' => fn (Blueprint $table) => $table->boolean('flgstaging_webt01')->nullable()->after('flgintempo_webt01'),

            'gruattr01_w11' => fn (Blueprint $table) => $table->string('gruattr01_w11', 20)->nullable()->after('marca_mg64'),
            'gruattr02_w12' => fn (Blueprint $table) => $table->string('gruattr02_w12', 20)->nullable()->after('gruattr01_w11'),
            'gruattr03_w13' => fn (Blueprint $table) => $table->string('gruattr03_w13', 20)->nullable()->after('gruattr02_w12'),
            'gruattr04_w14' => fn (Blueprint $table) => $table->string('gruattr04_w14', 20)->nullable()->after('gruattr03_w13'),
            'gruattr05_w15' => fn (Blueprint $table) => $table->string('gruattr05_w15', 20)->nullable()->after('gruattr04_w14'),
            'gruattr06_w16' => fn (Blueprint $table) => $table->string('gruattr06_w16', 20)->nullable()->after('gruattr05_w15'),
            'gruattr07_w17' => fn (Blueprint $table) => $table->string('gruattr07_w17', 20)->nullable()->after('gruattr06_w16'),
            'gruattr08_w18' => fn (Blueprint $table) => $table->string('gruattr08_w18', 20)->nullable()->after('gruattr07_w17'),
            'gruattr09_w19' => fn (Blueprint $table) => $table->string('gruattr09_w19', 20)->nullable()->after('gruattr08_w18'),
            'gruattr10_w20' => fn (Blueprint $table) => $table->string('gruattr10_w20', 20)->nullable()->after('gruattr09_w19'),
            'gruattr11_w21' => fn (Blueprint $table) => $table->string('gruattr11_w21', 20)->nullable()->after('gruattr10_w20'),
            'gruattr12_w22' => fn (Blueprint $table) => $table->string('gruattr12_w22', 20)->nullable()->after('gruattr11_w21'),
            'gruattr13_w23' => fn (Blueprint $table) => $table->string('gruattr13_w23', 20)->nullable()->after('gruattr12_w22'),
            'gruattr14_w24' => fn (Blueprint $table) => $table->string('gruattr14_w24', 20)->nullable()->after('gruattr13_w23'),
            'gruattr15_w25' => fn (Blueprint $table) => $table->string('gruattr15_w25', 20)->nullable()->after('gruattr14_w24'),
            'gruattr16_w26' => fn (Blueprint $table) => $table->string('gruattr16_w26', 20)->nullable()->after('gruattr15_w25'),
            'gruattr17_w27' => fn (Blueprint $table) => $table->string('gruattr17_w27', 20)->nullable()->after('gruattr16_w26'),
            'gruattr18_w28' => fn (Blueprint $table) => $table->string('gruattr18_w28', 20)->nullable()->after('gruattr17_w27'),
            'gruattr19_w29' => fn (Blueprint $table) => $table->string('gruattr19_w29', 20)->nullable()->after('gruattr18_w28'),
            'gruattr20_w30' => fn (Blueprint $table) => $table->string('gruattr20_w30', 20)->nullable()->after('gruattr19_w29'),
            'gruattr21_w31' => fn (Blueprint $table) => $table->string('gruattr21_w31', 20)->nullable()->after('gruattr20_w30'),
            'gruattr22_w32' => fn (Blueprint $table) => $table->string('gruattr22_w32', 20)->nullable()->after('gruattr21_w31'),
            'gruattr23_w33' => fn (Blueprint $table) => $table->string('gruattr23_w33', 20)->nullable()->after('gruattr22_w32'),
            'gruattr24_w34' => fn (Blueprint $table) => $table->string('gruattr24_w34', 20)->nullable()->after('gruattr23_w33'),
            'gruattr25_w35' => fn (Blueprint $table) => $table->string('gruattr25_w35', 20)->nullable()->after('gruattr24_w34'),
            'gruattr26_w36' => fn (Blueprint $table) => $table->string('gruattr26_w36', 20)->nullable()->after('gruattr25_w35'),
            'gruattr27_w37' => fn (Blueprint $table) => $table->string('gruattr27_w37', 20)->nullable()->after('gruattr26_w36'),
            'gruattr28_w38' => fn (Blueprint $table) => $table->string('gruattr28_w38', 20)->nullable()->after('gruattr27_w37'),
            'gruattr29_w39' => fn (Blueprint $table) => $table->string('gruattr29_w39', 20)->nullable()->after('gruattr28_w38'),
            'gruattr30_w40' => fn (Blueprint $table) => $table->string('gruattr30_w40', 20)->nullable()->after('gruattr29_w39'),
            'gruattr31_w41' => fn (Blueprint $table) => $table->string('gruattr31_w41', 20)->nullable()->after('gruattr30_w40'),
            'gruattr32_w42' => fn (Blueprint $table) => $table->string('gruattr32_w42', 20)->nullable()->after('gruattr31_w41'),
            'gruattr33_w43' => fn (Blueprint $table) => $table->string('gruattr33_w43', 20)->nullable()->after('gruattr32_w42'),
            'gruattr34_w44' => fn (Blueprint $table) => $table->string('gruattr34_w44', 20)->nullable()->after('gruattr33_w43'),
            'gruattr35_w45' => fn (Blueprint $table) => $table->string('gruattr35_w45', 20)->nullable()->after('gruattr34_w44'),
            'gruattr36_w46' => fn (Blueprint $table) => $table->string('gruattr36_w46', 20)->nullable()->after('gruattr35_w45'),
            'gruattr37_w47' => fn (Blueprint $table) => $table->string('gruattr37_w47', 20)->nullable()->after('gruattr36_w46'),
            'gruattr38_w48' => fn (Blueprint $table) => $table->string('gruattr38_w48', 20)->nullable()->after('gruattr37_w47'),
            'gruattr39_w49' => fn (Blueprint $table) => $table->string('gruattr39_w49', 20)->nullable()->after('gruattr38_w48'),
            'gruattr40_w50' => fn (Blueprint $table) => $table->string('gruattr40_w50', 20)->nullable()->after('gruattr39_w49'),
        ];

        foreach ($columns as $column => $definition) {
            if (!Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        $columns = [
            'flgmodultime_webt01',
            'flgintempo_webt01',
            'flgstaging_webt01',
            'gruattr01_w11',
            'gruattr02_w12',
            'gruattr03_w13',
            'gruattr04_w14',
            'gruattr05_w15',
            'gruattr06_w16',
            'gruattr07_w17',
            'gruattr08_w18',
            'gruattr09_w19',
            'gruattr10_w20',
            'gruattr11_w21',
            'gruattr12_w22',
            'gruattr13_w23',
            'gruattr14_w24',
            'gruattr15_w25',
            'gruattr16_w26',
            'gruattr17_w27',
            'gruattr18_w28',
            'gruattr19_w29',
            'gruattr20_w30',
            'gruattr21_w31',
            'gruattr22_w32',
            'gruattr23_w33',
            'gruattr24_w34',
            'gruattr25_w35',
            'gruattr26_w36',
            'gruattr27_w37',
            'gruattr28_w38',
            'gruattr29_w39',
            'gruattr30_w40',
            'gruattr31_w41',
            'gruattr32_w42',
            'gruattr33_w43',
            'gruattr34_w44',
            'gruattr35_w45',
            'gruattr36_w46',
            'gruattr37_w47',
            'gruattr38_w48',
            'gruattr39_w49',
            'gruattr40_w50',
        ];

        foreach (array_reverse($columns) as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};