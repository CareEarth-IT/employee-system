require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$fixes = [
    '2026_07_09_120000_add_contract_fields_to_property_rental_incomes' => ['property_rental_incomes', 'contract_key'],
];

foreach ($fixes as $migration => [$table, $column]) {
    if (Schema::hasColumn($table, $column) && ! DB::table('migrations')->where('migration', $migration)->exists()) {
        $batch = ((int) DB::table('migrations')->max('batch')) + 1;
        DB::table('migrations')->insert(['migration' => $migration, 'batch' => $batch]);
        echo "reconciled: {$migration}\n";
    }
}

Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
