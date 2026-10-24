<?php
// Create a new random fixture database; never accept an existing database name.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = getenv('NIL_MYSQL_HOST') ?: '127.0.0.1';
if (!in_array($host, array('127.0.0.1', 'localhost'), true)) throw new RuntimeException('Only a local disposable database service is allowed.');
for ($attempt = 0; $attempt < 60; $attempt++) {
    try { $db = new mysqli($host, getenv('NIL_MYSQL_USER') ?: 'root', getenv('NIL_MYSQL_PASSWORD') ?: '', '', (int)(getenv('NIL_MYSQL_PORT') ?: 3306)); break; }
    catch (mysqli_sql_exception $e) { if ($attempt === 59) throw $e; usleep(500000); }
}
$name = 'nil_manager_test_' . bin2hex(random_bytes(6));
$db->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo $name;
