<?php
require_once __DIR__ . '/Persona.php';
require_once __DIR__ . '/Vehiculo.php';
require_once __DIR__ . '/Turno.php';

class Cliente extends Persona {
    /** @var Vehiculo[] */
    public array $listaVehiculos = [];

    public function registrarVehiculo(Vehiculo $v): void { $this->listaVehiculos[] = $v; }
    public function solicitarTurno(Turno $t): void {}
    public function realizarPago($pago): void {}
    public function recibirNotificacion(string $msg): void {}
    public function verHistorialReparacion(int $autoId): array { return []; }
}
