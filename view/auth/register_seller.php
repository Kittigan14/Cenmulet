<?php
require_once "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("
        INSERT INTO sellers
        (fullname, store_name, username, password, tel)
        VALUES (:f, :s, :u, :p, :t)
    ");

    $stmt->execute([
        ':f' => $_POST['fullname'],
        ':s' => $_POST['store_name'],
        ':u' => $_POST['username'],
        ':p' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        ':t' => $_POST['tel']
    ]);

    header("Location: login.php");
}
?>
