<?php
final class Sesion
{
    /** Inicia la sesión de forma segura (idempotente) */
    public static function iniciar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Evita que PHP intente abrir/crear sesión si ya hay headers enviados
            // (no imprime nada aquí para no romper headers)
            session_start();
        }
    }

    /** ¿Hay usuario autenticado? */
    public static function isLoggedIn(): bool
    {
        self::iniciar();
        return !empty($_SESSION['uid']);
    }

    /** Establece los datos mínimos del usuario logueado */
    public static function autenticar(array $persona): void
    {
        self::iniciar();
        $_SESSION['uid']      = $persona['id'];
        $_SESSION['nombre']   = $persona['nombre']   ?? '';
        $_SESSION['apellido'] = $persona['apellido'] ?? '';
    }

    /** Cierra sesión limpiamente */
    public static function cerrar(): void
    {
        self::iniciar();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Requiere sesión: si no hay usuario, redirige al login */
    public static function requiereLogin(): void
    {
        self::iniciar();
        if (empty($_SESSION['uid'])) {
            // Usa base_url para una ruta absoluta estable
            $app  = require __DIR__ . '/../config/app.php';
            $base = rtrim($app['base_url'] ?? '/', '/');

            self::redirect($base . '/modules/login/index.php');
        }
    }

    /** Redirige sin imprimir salida previa y termina */
    public static function redirect(string $url): never
    {
        // No echo/var_dump/espacios antes de este header
        header('Location: ' . $url);
        exit;
    }
}
