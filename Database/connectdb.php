<?php
function getDBConnection(): PDO {
   
    $dbPath = __DIR__ . '/../Database/database.sqlite';

    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('PRAGMA foreign_keys = ON;');

    $pdo->exec('PRAGMA busy_timeout = 5000;');

    $pdo->exec('PRAGMA journal_mode = WAL;');

    return $pdo;
}