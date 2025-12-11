<?php
$db = require __DIR__ . '/db.php';
// test database! Important not to run tests on production or development databases
$db['dsn'] = 'mysql:host=' . (($_ENV['DB_HOST'] ?? getenv('DB_HOST')) ?: 'localhost') . ';dbname=' . (($_ENV['DB_TEST_NAME'] ?? getenv('DB_TEST_NAME')) ?: 'yii2basic_test');

return $db;
