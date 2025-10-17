<?php
$orden_data = null;
$orden_id = (int)($_GET['orden_id'] ?? 0);

// --- INICIO: LÓGICA PARA INCRUSTAR IMAGEN ---
$logo_base64 = '';
$ROOT_FOR_LOGO = dirname(__DIR__, 3);
$logo_path = $ROOT_FOR_LOGO . '/publico/widoo.png';

if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $logo_mime = mime_content_type($logo_path);
    $logo_base64 = 'data:' . $logo_mime . ';base64,' . base64_encode($logo_data);
}
// --- FIN: LÓGICA PARA INCRUSTAR IMAGEN ---

if ($orden_id > 0) {
    if (!isset($db)) {
        $ROOT_FOR_DB = dirname(__DIR__, 3);
        require_once $ROOT_FOR_DB . '/clases/Conexion.php';
        try {
            $db = Conexion::obtener();
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Throwable $e) {
            die("Error de conexión a la base de datos.");
        }
    }

    $sql = "SELECT
                o.id AS orden_id, o.fecha_ingreso, o.descripcion AS trabajo_solicitado, eo.descripcion AS estado_orden,
                a.anio AS vehiculo_anio, a.patente AS vehiculo_patente, a.color AS vehiculo_color, mo.descripcion AS vehiculo_modelo, ma.descripcion AS vehiculo_marca,
                p_cli.nombre AS cliente_nombre, p_cli.apellido AS cliente_apellido, p_cli.dni AS cliente_dni,
                (SELECT valor FROM Contacto_Persona WHERE Persona_id = p_cli.id AND Tipo_Contacto_id = 1 LIMIT 1) AS cliente_telefono,
                (SELECT valor FROM Contacto_Persona WHERE Persona_id = p_cli.id AND Tipo_Contacto_id = 2 LIMIT 1) AS cliente_email,
                p_mec.nombre AS mecanico_nombre, p_mec.apellido AS mecanico_apellido, mec.id AS mecanico_legajo
            FROM Ordenes_Reparaciones o
            JOIN Estados_Ordenes eo ON eo.id = o.EstadoOrdenReparacion_id
            JOIN Automoviles a ON a.id = o.Automovil_id
            JOIN Modelos_Automoviles mo ON mo.id = a.Modelo_Automovil_id
            JOIN Marcas_Automoviles ma ON ma.id = mo.Marca_Automvil_id
            JOIN Empleados mec ON mec.id = o.Empleado_id
            JOIN Personas p_mec ON p_mec.id = mec.Persona_id
            LEFT JOIN Turnos t ON t.id = o.Turnos_id
            LEFT JOIN Vehiculos_Personas vp ON vp.automoviles_id = a.id
            LEFT JOIN Personas p_cli ON p_cli.id = vp.Persona_id
            WHERE o.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$orden_id]);
    $orden_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

function e($value, $default = '---') {
    return htmlspecialchars(trim((string)$value) ?: $default, ENT_QUOTES, 'UTF-8');
}
?>
<div class="orden-wrapper">
    <style>
        .orden-wrapper { background: #ffffff; color: #0b1220; font-family: Inter, system-ui, sans-serif; font-size: 14px; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 800px; margin: 1rem auto; }
        .orden-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .orden-header .logo-info { display: flex; align-items: center; gap: 1rem; }
        .orden-header .logo-badge { width: 50px; height: 50px; border-radius: 10px; }
        .orden-header .taller-info { font-size: 0.8rem; color: #5b6b85; line-height: 1.4; }
        .orden-header .orden-numero { text-align: right; }
        .orden-header .orden-numero h3 { margin: 0 0 0.5rem 0; font-size: 1.5rem; color: #0b1220; }
        .orden-seccion { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; }
        .orden-seccion h4 { margin: 0 0 0.8rem 0; grid-column: 1 / -1; font-size: 0.9rem; text-transform: uppercase; color: #3b82f6; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;}
        .orden-seccion .dato { margin-bottom: 0.7rem; }
        .orden-seccion .dato strong { display: block; font-weight: 600; color: #5b6b85; font-size: 0.75rem; margin-bottom: 4px; }
        .orden-seccion .dato-linea { border-bottom: 1px dotted #cbd5e1; height: 20px; }
        .orden-seccion .checklist-item { display: flex; align-items: center; gap: 1rem; }
        .orden-seccion .checklist-item span { min-width: 140px; font-weight: 600; color: #334155; }
        .orden-seccion-full { grid-template-columns: 1fr; }
        .firmas { display: flex; justify-content: space-around; margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e2e8f0; }
        .firma-recuadro { text-align: center; width: 40%; }
        .firma-recuadro hr { border: 0; border-top: 1px solid #334155; }
        .firma-recuadro p { margin-top: 0.5rem; font-weight: 600; }
        .orden-footer { text-align: center; font-size: 0.8rem; color: #94a3b8; margin-top: 2rem; }

        @media print {
            @page { size: A4; margin: 0.8cm; }
            body { -webkit-print-color-adjust: exact; color-adjust: exact; }
            .orden-wrapper { margin: 0; padding: 0; border: none; box-shadow: none; border-radius: 0; font-size: 10px; width: 100%; max-width: 100%; }
            .orden-header, .orden-seccion { margin-bottom: 0.5rem; padding: 0.6rem; gap: 0.5rem; break-inside: avoid; }
            .orden-header h3 { font-size: 1.2rem; }
            .orden-seccion .dato { margin-bottom: 0.3rem; }
            .orden-seccion .checklist-item { gap: 0.5rem; }
            .firmas { margin-top: 1rem; padding-top: 0.8rem; break-inside: avoid; }
            .orden-footer { margin-top: 1rem; }
        }
    </style>

    <?php if ($orden_data): ?>
        <div class="orden-header">
            <div class="logo-info">
                <img src="<?= $logo_base64 ?>" alt="Logo" class="logo-badge">
                <div>
                    <strong>Fixtime</strong>
                    <div class="taller-info">
                        Taller Mecánico - Servicio Integral<br>
                        Formosa, Formosa, Argentina<br>
                        fixtimear@gmail.com
                    </div>
                </div>
            </div>
            <div class="orden-numero">
                <h3>Orden de Reparación</h3>
                <div>N° #<strong><?= e($orden_data['orden_id']) ?></strong></div>
                <div>Fecha Ingreso: <strong><?= e( (new DateTime($orden_data['fecha_ingreso']))->format('d/m/Y H:i') ) ?></strong></div>
                <div>Estado: <strong><?= e($orden_data['estado_orden']) ?></strong></div>
            </div>
        </div>

        <div class="orden-seccion">
            <h4>Vehículo</h4>
            <div class="dato"><strong>Marca / Modelo</strong> <?= e($orden_data['vehiculo_marca'] . ' ' . $orden_data['vehiculo_modelo']) ?></div>
            <div class="dato"><strong>Año</strong> <?= e($orden_data['vehiculo_anio']) ?></div>
            <div class="dato"><strong>Patente</strong> <?= e($orden_data['vehiculo_patente']) ?></div>
            <div class="dato"><strong>Color</strong> <?= e($orden_data['vehiculo_color']) ?></div>
        </div>

        <div class="orden-seccion">
            <h4>Cliente</h4>
            <div class="dato"><strong>Nombre</strong> <?= e($orden_data['cliente_nombre'] . ' ' . $orden_data['cliente_apellido']) ?></div>
            <div class="dato"><strong>DNI</strong> <?= e($orden_data['cliente_dni']) ?></div>
            <div class="dato"><strong>Email</strong> <?= e($orden_data['cliente_email']) ?></div>
            <div class="dato"><strong>Teléfono</strong> <?= e($orden_data['cliente_telefono']) ?></div>
        </div>

        <div class="orden-seccion orden-seccion-full">
            <h4>Trabajo Solicitado / Descripción</h4>
            <div class="dato"><?= e($orden_data['trabajo_solicitado']) ?></div>
        </div>
        
        <div class="orden-seccion">
            <h4>Mecánico Asignado</h4>
            <div class="dato"><strong>Nombre</strong> <?= e($orden_data['mecanico_apellido'] . ' ' . $orden_data['mecanico_nombre']) ?></div>
            <div class="dato"><strong>Legajo</strong> #<?= e($orden_data['mecanico_legajo']) ?></div>
        </div>

        <div class="orden-seccion">
            <h4 style="grid-column: 1 / 2;">Checklist de Recepción</h4>
            <h4 style="grid-column: 2 / 3;">Entrega</h4>

            <div class="dato">
                <div class="checklist-item"><span>Nivel de combustible</span> ☐ Bajo ☐ Medio ☐ Alto</div>
                <div class="checklist-item"><span>Rueda auxilio / Gato</span> ☐ Sí ☐ No</div>
                <div class="checklist-item"><span>Rayones / Golpes</span> ☐ Sí ☐ No</div>
                <strong>Observaciones</strong>
                <div class="dato-linea"></div>
            </div>

            <div class="dato">
                <strong>Fecha estimada</strong>
                <div class="dato-linea"></div>
                <strong>Garantía del servicio</strong>
                <div class="dato-linea">30 días / 1000 km</div>
                <strong>Observaciones</strong>
                <div class="dato-linea"></div>
            </div>
        </div>

        <div class="firmas">
            <div class="firma-recuadro"><hr><p>Cliente</p></div>
            <div class="firma-recuadro"><hr><p>Mecánico</p></div>
        </div>

        <div class="orden-footer">
            © <?= date('Y') ?> Fixtime - Taller Mecánico. Este documento no es una factura.
        </div>
    <?php else: ?>
        <p>Error: No se pudo cargar la información de la orden de reparación.</p>
    <?php endif; ?>
</div>