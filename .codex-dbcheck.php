<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try { $db = Illuminate\Support\Facades\DB::connection(); $row = $db->selectOne('select current_database() as name'); echo 'driver=' . $db->getDriverName() . ', database=' . $row->name . ', connected=yes' . PHP_EOL; } catch (Throwable $e) { echo 'unavailable: ' . get_class($e) . PHP_EOL; }
