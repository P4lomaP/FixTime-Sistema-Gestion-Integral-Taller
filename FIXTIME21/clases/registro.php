<?php
require_once __DIR__ . '/PersonaRepositorio.php';

class Registro
{
    private PersonaRepositorio $repo;

    public function __construct()
    {
        $this->repo = new PersonaRepositorio();
    }

    
    public function registrarCliente(array $datos): array
    {
        $nombre = trim($datos['nombre'] ?? '');
        $apellido = trim($datos['apellido'] ?? '');
        $dni = preg_replace('/\D+/', '', $datos['dni'] ?? '');
        $email = trim($datos['email'] ?? '');
        $pass = (string)($datos['contrasenia'] ?? '');
        $pass2 = (string)($datos['contrasenia2'] ?? '');

        
        if ($nombre === '' || $apellido === '' || $dni === '' || $email === '' || $pass === '' || $pass2 === '') {
            return ['ok' => false, 'mensaje' => 'Completá todos los campos.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mensaje' => 'El email no es válido.'];
        }
        if (strlen($pass) < 6) {
            return ['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres.'];
        }
        if ($pass !== $pass2) {
            return ['ok' => false, 'mensaje' => 'Las contraseñas no coinciden.'];
        }
        if ($this->repo->existeDni($dni)) {
            return ['ok' => false, 'mensaje' => 'El DNI ya está registrado.'];
        }
        if ($this->repo->existeEmail($email)) {
            return ['ok' => false, 'mensaje' => 'El email ya está registrado.'];
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 11]);
        try {
            $personaId = $this->repo->crearClienteConEmail($nombre, $apellido, $dni, $email, $hash);
            return ['ok' => true, 'persona_id' => $personaId];
        } catch (Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo completar el registro.'];
        }
    }
}
