<?php
// Database.php
// Ajusta las credenciales según tu XAMPP
$HOST = 'localhost';
$USER = 'root';
$PASS = '';
$DBNM = 'TallerMecanico2';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli = new mysqli($HOST, $USER, $PASS, $DBNM);
$mysqli->set_charset('utf8mb4');

// Helper para detectar si el valor en BD ya es un hash moderno
function is_password_hash_like(string $value): bool {
    return str_starts_with($value, '$2y$') || str_starts_with($value, '$argon2');
}
