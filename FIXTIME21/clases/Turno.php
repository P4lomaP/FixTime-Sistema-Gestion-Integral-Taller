<?php
class Turno {
    public int $idTurno;
    public string $estado;
    public int $automovilId;
    public string $motivo;
    public ?string $descripcion = null;

    public function __construct(int $id, string $estado, int $autoId, string $motivo, ?string $descripcion=null){
        $this->idTurno=$id; $this->estado=$estado; $this->automovilId=$autoId; $this->motivo=$motivo; $this->descripcion=$descripcion;
    }

    public function confirmarTurno(): void {}
    public function cancelarTurno(): void {}
    public function Reagendar(string $f, string $h): void {}
    public function EnviarConfirmacion(): void {}
    public function AgregarTurno(): void {}
}  
