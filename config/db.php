<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => ($_ENV['DB_DSN'] ?? getenv('DB_DSN')) ?: 'mysql:host=' . (($_ENV['DB_HOST'] ?? getenv('DB_HOST')) ?: 'localhost') . ';dbname=' . (($_ENV['DB_NAME'] ?? getenv('DB_NAME')) ?: 'yii2basic'),
    'username' => ($_ENV['DB_USER'] ?? getenv('DB_USER')) ?: 'root',
    'password' => ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD')) ?: '',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
