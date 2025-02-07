<?php
require_once 'db.php';

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

if ($argc !== 3) {
    die("Usage: php create_admin.php <username> <password>\n");
}

$username = $argv[1];
$password = password_hash($argv[2], PASSWORD_DEFAULT);

$db = new DB();

try {
    $db->query(
        "INSERT INTO users (username, password) VALUES (?, ?)",
        [$username, $password]
    );
    echo "Admin user created successfully\n";
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "Error: Username already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
} 