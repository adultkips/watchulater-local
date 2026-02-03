<?php
// db/connect.php
$cfg = require __DIR__ . '/config.php';
$dsn = 'sqlite:' . $cfg['db_path'];

$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');