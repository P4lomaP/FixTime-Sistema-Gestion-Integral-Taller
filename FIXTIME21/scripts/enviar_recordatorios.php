<?php
declare(strict_types=1);

require_once __DIR__ . '/../clases/Conexion.php';
require_once __DIR__ . '/../clases/TurnoRepositorio.php';
require_once __DIR__ . '/../clases/Correo.php';

$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');

$repoTurnos = new TurnoRepositorio();
$turnos = $repoTurnos->turnosParaRecordatorio();

if (!$turnos) {
    error_log("[RECORDATORIOS] No hay turnos para enviar.");
    exit;
}

$correo = new Correo();

foreach ($turnos as $t) {
    $nombre = trim(($t['nombre'] ?? '') . ' ' . ($t['apellido'] ?? ''));
    $fecha  = date('d/m/Y H:i', strtotime($t['fecha_turno'] . ' ' . $t['hora_turno']));

    $html = "
        <h2>Recordatorio de turno</h2>
        <p>Hola <b>{$nombre}</b>,</p>
        <p>Te recordamos que tenés un turno programado para el <b>{$fecha}</b> en Fixtime.</p>
        <p>Si no podés asistir, por favor contactanos para reprogramarlo.</p>
        <br>
        <p>Muchas gracias,<br>El equipo de Fixtime</p>
    ";

    try {
        $correo->enviarHtmlConLogo(
            $t['email'],
            $nombre,
            'Recordatorio de turno — Fixtime',
            $html
        );

        // marcar como enviado para no duplicar
        $repoTurnos->marcarRecordatorioEnviado((int)$t['id']);

        error_log("[RECORDATORIOS] Enviado a {$t['email']} (turno {$t['id']})");
    } catch (Throwable $e) {
        error_log("[RECORDATORIOS] Error al enviar a {$t['email']}: " . $e->getMessage());
    }
}
