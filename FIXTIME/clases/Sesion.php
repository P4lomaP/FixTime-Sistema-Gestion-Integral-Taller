<?php
class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function autenticar(array $persona): void
    {
        self::iniciar();
        $_SESSION['uid'] = $persona['id'];
        $_SESSION['nombre'] = $persona['nombre'];
        $_SESSION['apellido'] = $persona['apellido'];
    }

    public static function cerrar(): void
    {
        self::iniciar();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public static function requiereLogin(): void
    {
        self::iniciar();
        if (!isset($_SESSION['uid'])) {
            header("Location: ../index.php");
            exit;
        }
    }
}
