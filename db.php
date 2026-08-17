<?php
$host = 'localhost';
$db   = 'jifc_analytics';
$user = 'root'; // Ajuste conforme seu ambiente
$pass = '';     // Ajuste conforme seu ambiente


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>