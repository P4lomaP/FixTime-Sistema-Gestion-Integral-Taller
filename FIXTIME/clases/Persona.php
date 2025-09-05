<?php
class Persona {
    public int $idPersona;
    public string $nombre;
    public string $apellido;
    public string $dni;
    public ?string $email;
    protected string $contraseniaHash;

    public function __construct(int $id, string $nombre, string $apellido, string $dni, ?string $email, string $hash){
        $this->idPersona=$id; $this->nombre=$nombre; $this->apellido=$apellido; $this->dni=$dni; $this->email=$email; $this->contraseniaHash=$hash;
    }
    public function iniciarSesion(string $pass): bool { return password_verify($pass, $this->contraseniaHash); }
    public function cerrarSesion(): void {}
    public function RecuperarContrasenia(): void {}
    public function EditarPerfil(string $n, string $a): void { $this->nombre=$n; $this->apellido=$a; }
    public function DarBajaPerfil(): void {}
}
