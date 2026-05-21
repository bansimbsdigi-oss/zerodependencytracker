<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) chdir(FCPATH);
require FCPATH . '../app/Config/Paths.php';
$paths = new Paths();
echo '<pre>Paths loaded OK<br>';
echo 'System: ' . $paths->systemDirectory . '<br>';
echo 'App: ' . $paths->appDirectory . '<br>';
require $paths->systemDirectory . '/Boot.php';
echo 'Boot loaded OK<br>';
echo '</pre>';
