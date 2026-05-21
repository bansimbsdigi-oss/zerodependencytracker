<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('FCPATH', __DIR__ . '/public/');
require __DIR__ . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';

// Boot CodeIgniter in Web mode to load environment
\CodeIgniter\Boot::bootWeb($paths);

try {
    echo "Connecting to DB...\n";
    $db = \Config\Database::connect();
    $db->initialize();
    echo "Connected successfully! Database name: " . $db->getDatabase() . "\n";
    
    $query = $db->query("SHOW TABLES");
    echo "Tables in database:\n";
    foreach ($query->getResultArray() as $row) {
        print_r($row);
    }
} catch (\Throwable $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
}
