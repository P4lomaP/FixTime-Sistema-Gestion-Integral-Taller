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

    
    public function login(string $identificador, string $contrasenia): array
    {
        $persona = $this->repo->buscarPorIdentificador($identificador);
        if (!$persona) {
            return ['ok' => false, 'mensaje' => 'Usuario o contraseña inválidos.'];
        }

        $hash = $persona['contrasenia'];

        
        $esHash = strlen($hash) >= 60 && (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'));
        if ($esHash) {
            $verifica = password_verify($contrasenia, $hash);
        } else {
            $verifica = hash_equals($hash, $contrasenia);
        }

        if (!$verifica) {
            return ['ok' => false, 'mensaje' => 'Usuario o contraseña inválidos.'];
        }

        
        if (!$esHash || password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 11])) {
            $nuevo = password_hash($contrasenia, PASSWORD_BCRYPT, ['cost' => 11]);
            try {
                $this->repo->actualizarContraseniaHash((int)$persona['id'], $nuevo);
            } catch (Throwable $e) {
                
            }
        }

        Sesion::autenticar($persona);
        // === Empresa en sesión sin tocar DB ===
        require_once __DIR__ . '/EmpresaRepositorio.php';
        $repoE = new EmpresaRepositorio();
        if (empty($_SESSION['es_empresarial'])) {
            $uid = (int)($_SESSION['uid'] ?? 0);
            if ($uid && method_exists($repoE,'esPerfilEmpresarialPorDatos') && $repoE->esPerfilEmpresarialPorDatos($uid)) {
                $_SESSION['es_empresarial'] = 1;
            }
        }

        return ['ok' => true];
    }
}
