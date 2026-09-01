<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$databaseDir = $projectRoot . '/db';
$databasePath = $databaseDir . '/products.db';
$schemaPath = $projectRoot . '/database/schema.sql';

if (file_exists($databasePath)) {
    fwrite(STDOUT, "Database already exists: {$databasePath}\n");
    exit(0);
}

if (!is_dir($databaseDir) && !mkdir($databaseDir, 0775, true) && !is_dir($databaseDir)) {
    fwrite(STDERR, "Could not create database directory.\n");
    exit(1);
}

$schema = file_get_contents($schemaPath);
if ($schema === false) {
    fwrite(STDERR, "Could not read database schema.\n");
    exit(1);
}

try {
    $database = new PDO('sqlite:' . $databasePath);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->exec($schema);
    fwrite(STDOUT, "Created portfolio-safe demo database.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Database initialization failed.\n");
    exit(1);
}

