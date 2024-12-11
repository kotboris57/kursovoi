<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tracking_number = $_POST['tracking_number'];
    $status = $_POST['status'];
    $sender = $_POST['sender'];
    $recipient = $_POST['recipient'];

    $stmt = $pdo->prepare("INSERT INTO shipments (tracking_number, status, sender, recipient) VALUES (?, ?, ?, ?)");
    $stmt->execute([$tracking_number, $status, $sender, $recipient]);

    header('Location: dashboard.php');
}
?>

<form method="post" action="">
    Трек-номер: <input type="text" name="tracking_number" required><br>
    Статус: <input type="text" name="status" required><br>
    Отправитель: <input type="text" name="sender"><br>
    Получатель: <input type="text" name="recipient"><br>
    <button type="submit">Добавить</button>
</form>
