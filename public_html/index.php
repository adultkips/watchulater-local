<?php
require __DIR__ . '/bootstrap.php';
require_onboarded($pdo);

header('Location: roulettes.php');
exit;
