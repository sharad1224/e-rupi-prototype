<?php
// Simple PostgreSQL PDO connection (same style as your previous project).
$host = "localhost";
$port = "5432";
$dbname = "erupi_demo"; // Change only if your E-RUPI database has a different name.
$user = "postgres";
$password = "root";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    http_response_code(500);
    exit("Database connection failed. Check config/database.php and PostgreSQL.");
}
