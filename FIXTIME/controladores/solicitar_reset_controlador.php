<?php
require_once __DIR__ . '/../clases/PersonaRepositorio.php';
require_once __DIR__ . '/../clases/PasswordResetRepositorio.php';
require_once __DIR__ . '/../clases/Correo.php';

$app  = require __DIR__ . '/../config/app.php';
$base = rtrim($app['base_url'], '/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base . '/modules/login/olvide.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $base . '/modules/login/olvide.php?error=' . urlencode('Ingresá un email válido.'));
    exit;
}

$repoPersonas = new PersonaRepositorio();
$persona = $repoPersonas->buscarPorEmailExacto($email);

// Respuesta neutra: siempre decimos OK aunque no exista la persona
if (!$persona) {
    header('Location: ' . $base . '/modules/login/olvide.php?ok=1');
    exit;
}

// Invalidamos tokens previos y generamos uno nuevo (60 min)
$repoReset = new PasswordResetRepositorio();
$repoReset->invalidarTokensPersona((int)$persona['id']);
$minutos = 60;
$token   = $repoReset->crearToken((int)$persona['id'], $minutos);

// URL pública con el token (NO escapar)
$url = $base . '/modules/login/restablecer.php?token=' . urlencode($token);

// Log de depuración
error_log('[RESET URL ENVIADA] ' . $url);

// Render del correo
$correo  = new Correo();
$rutaTpl = __DIR__ . '/../plantillas/correo_reset.html';

// Pasar la URL cruda; el template debe usar {{URL}} en el href
$vars = [
    'NOMBRE'            => $persona['nombre'] ?? 'usuario',
    'URL'               => $url,
    'VENCIMIENTO_HORAS' => (string)$minutos,
    'ANIO'              => date('Y'),
    'CID_LOGO'          => 'logo-fixtime',
];
$html = $correo->renderPlantilla($rutaTpl, $vars);

// Enviar email (con logo si está)
$rutaLogo = __DIR__ . '/../publico/widoo.png';
try {
    $correo->enviarHtmlConLogo(
        $email,
        trim(($persona['nombre'] ?? '') . ' ' . ($persona['apellido'] ?? '')),
        'Restablecer contraseña — Fixtime',
        $html,
        'logo-fixtime',
        $rutaLogo
    );
} catch (Throwable $e) {
    // Podés loguear $e->getMessage() si querés
}

// Redirección neutra de vuelta al formulario de "Olvidé mi contraseña"
header('Location: ' . $base . '/modules/login/olvide.php?ok=1');
exit;
