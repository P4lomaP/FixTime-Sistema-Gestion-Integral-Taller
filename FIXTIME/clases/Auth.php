<?php
require_once __DIR__ . '/PersonaRepositorio.php';
require_once __DIR__ . '/Sesion.php';

class Auth
{
    private PersonaRepositorio $repo;

    public function __construct()
    {
        $this->repo = new PersonaRepositorio();
    }

    /**
     * $identificador: correo o DNI (cualquiera).
     */
    public function login(string $identificador, string $contrasenia): array
    {
        $persona = $this->repo->buscarPorIdentificador($identificador);
        if (!$persona) {
            return ['ok' => false, 'mensaje' => 'Usuario o contraseña inválidos.'];
        }

        $hash = $persona['contrasenia'];

        // Si el dato de la base no es un hash BCrypt/Argon, permitimos 1 login con texto plano
        $esHash = strlen($hash) >= 60 && (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'));
        if ($esHash) {
            $verifica = password_verify($contrasenia, $hash);
        } else {
            $verifica = hash_equals($hash, $contrasenia);
        }

        if (!$verifica) {
            return ['ok' => false, 'mensaje' => 'Usuario o contraseña inválidos.'];
        }

        // Rehash transparente si la contraseña aún no está hashed o el algoritmo cambió
        if (!$esHash || password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 11])) {
            $nuevo = password_hash($contrasenia, PASSWORD_BCRYPT, ['cost' => 11]);
            try {
                $this->repo->actualizarContraseniaHash((int)$persona['id'], $nuevo);
            } catch (Throwable $e) {
                // no interrumpe el login si falla la actualización
            }
        }

        Sesion::autenticar($persona);
        return ['ok' => true];
    }
}
