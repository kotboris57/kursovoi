<?php
$host = 'MySQL-8.0';    
$db = 'kursovoi';    
$user = 'root';              

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Ошибка подключения к базе данных: " . $e->getMessage());
    
    throw new PDOException("Ошибка подключения к базе данных.");
}
