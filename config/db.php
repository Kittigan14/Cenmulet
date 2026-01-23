<?php
try {
    $db = new PDO("sqlite:database/cenmulet.sqlite");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA foreign_keys = ON;");
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
