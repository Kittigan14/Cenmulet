<?php
require_once "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("
        INSERT INTO users
        (fullname, username, password, tel, address, image, id_per)
        VALUES (:f, :u, :p, :t, :a, :i, :id)
    ");

    $stmt->execute([
        ':f' => $_POST['fullname'],
        ':u' => $_POST['username'],
        ':p' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        ':t' => $_POST['tel'],
        ':a' => $_POST['address'],
        ':i' => $_POST['image'],
        ':id' => $_POST['id_per']
    ]);

    header("Location: login.php");
}
?>
