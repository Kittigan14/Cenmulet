<?php
$db_path = __DIR__ . "/../database/cenmulet.sqlite";

$db_dir = dirname($db_path);
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0777, true);
}

try {
    $db = new PDO("sqlite:" . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA foreign_keys = ON;");
    
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "<br>DB Path: " . $db_path);
}
?>