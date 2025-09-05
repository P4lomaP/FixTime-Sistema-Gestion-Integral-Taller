<?php
class Turno {
    public int $idTurno;
    public string $fecha;
    public string $hora;
    public string $estado;
    public int $automovilId;
    public function __construct(int $id, string $fecha, string $hora, string $estado, int $autoId){
        $this->idTurno=$id; $this->fecha=$fecha; $this->hora=$hora; $this->estado=$estado; $this->automovilId=$autoId;
    }
    public function confirmarTurno(): void {}
    public function cancelarTurno(): void {}
    public function Reagendar(string $f, string $h): void { $this->fecha=$f; $this->hora=$h; }
    public function EnviarConfirmacion(): void {}
    public function AgregarTurno(): void {}
}
