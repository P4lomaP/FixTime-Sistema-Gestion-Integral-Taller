<?php
class Conexion
{
    private static ?PDO $pdo = null;

    public static function obtener(): PDO
    {
        if (self::$pdo === null) {
            $conf = require __DIR__ . '/../config/config.php';
            $db = $conf['db'];
            $dsn = "mysql:host={$db['host']};dbname={$db['nombre']};charset={$db['charset']}";
            $opciones = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            self::$pdo = new PDO($dsn, $db['usuario'], $db['clave'], $opciones);
        }
        return self::$pdo;
    }
}
