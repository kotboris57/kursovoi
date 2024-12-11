<?php
require 'config.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM shipments WHERE id = ?");
$stmt->execute([$id]);

header('Location: dashboard.php');
?>
