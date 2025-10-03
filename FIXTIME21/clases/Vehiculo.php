<?php
class Vehiculo {
    public int $idAutomovil;
    public string $marca;
    public string $modelo;
    public string $color;
    public string $anio;
    public string $km;
    public string $descripcion;
    public function __construct(int $id, string $marca, string $modelo, string $color, string $anio, string $km, string $desc=''){
        $this->idAutomovil=$id; $this->marca=$marca; $this->modelo=$modelo; $this->color=$color; $this->anio=$anio; $this->km=$km; $this->descripcion=$desc;
    }
    public function actualizarDatos(string $color, string $km): void { $this->color=$color; $this->km=$km; }
    public function obtenerHistorial(): array { return []; }
}
