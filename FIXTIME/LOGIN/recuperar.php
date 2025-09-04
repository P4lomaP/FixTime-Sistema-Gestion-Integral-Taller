<?php
require __DIR__ . '/Database.php';

// ── Carga PHPMailer manual ──
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';
require __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// URL base para armar el link
$BASE_URL = 'https://922634f692e5.ngrok-free.app/FIXTIME/LOGIN'; // <-- poné tu IP y carpeta reales
// Función para enviar email
function sendResetEmail(string $to, string $link, string $toName = 'Usuario'): bool {
    $mail = new PHPMailer(true);
    try {
        // Configuración SMTP (Gmail)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sebaxcabre692@gmail.com';       // <-- tu Gmail
        $mail->Password   = 'ueiabjfivjmipuwq';      // <-- contraseña de aplicación (16 caracteres)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Remitente y destinatario
        $mail->setFrom('TU_CORREO@gmail.com', 'Fixtime');
        $mail->addAddress($to, $toName);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = 'Fixtime — Recuperar contraseña';
        $mail->Body    = '
          <div style="font-family:system-ui,Segoe UI,Arial,sans-serif;font-size:15px;color:#0b1020">
            <p>Hola,</p>
            <p>Para restablecer tu contraseña hacé clic en el siguiente enlace (vence en 1 hora):</p>
            <p><a href="'.$link.'" style="color:#1a73e8">'.$link.'</a></p>
            <p>Si no solicitaste esto, ignorá este correo.</p>
            <p>— Fixtime</p>
          </div>';
        $mail->AltBody = "Para restablecer tu contraseña (vence en 1 hora):\n$link\n\nSi no fuiste vos, ignorá este correo.";

        return $mail->send();
    } catch (Exception $e) {
        // Podés loguear el error si querés: error_log($e->getMessage());
        return false;
    }
}

$mensaje = '';
$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $mensaje = 'Por favor, escribí tu email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'El correo no es válido.';
    } else {
        // Buscar persona por email
        $sql = "
            SELECT p.id AS persona_id, CONCAT(p.nombre,' ',p.apellido) AS nombre
            FROM Contacto_Persona cp
            INNER JOIN Personas p ON p.id = cp.Persona_id
            INNER JOIN Tipos_Contactos tc ON tc.id = cp.Tipo_Contacto_id
            WHERE tc.descripcion = 'Email' AND cp.valor = ?
            LIMIT 1
        ";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();

        $personaId = null; 
        $personaNombre = 'Usuario';
        if ($row = $res->fetch_assoc()) {
            $personaId = (int)$row['persona_id'];
            $personaNombre = $row['nombre'] ?: 'Usuario';
        }

        // Siempre mostramos lo mismo (para no revelar si existe o no)
        $enviado = true;
        $mensaje = 'Si el correo existe, te enviamos un enlace para restablecer la contraseña. Revisá tu bandeja.';

        // Si existe, generar token y enviar
        if ($personaId) {
            $token  = bin2hex(random_bytes(32));
            $expira = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            $ins = $mysqli->prepare("INSERT INTO PasswordResets (persona_id, token, expires_at) VALUES (?,?,?)");
            $ins->bind_param('iss', $personaId, $token, $expira);
            $ins->execute();

            $link = $BASE_URL . '/reset.php?token=' . urlencode($token);

            sendResetEmail($email, $link, $personaNombre);

            // En localhost mostramos el link por si el correo no llega
            if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
                echo '<div style="margin:12px 0;color:#9ec5ff">Link DEV: <a href="'.$link.'" target="_blank">'.$link.'</a></div>';
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recuperar contraseña — Fixtime</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0b1020;--bg-2:#0e162e;--text:#e5edf9;--muted:#93a3c3;--accent:#5aa2ff;--accent-2:#9ec5ff;--warn:#ffd479;--glass:rgba(90,162,255,.18)}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:
    radial-gradient(1100px 600px at -10% -10%, #1a2b57 0%, transparent 55%),
    radial-gradient(1000px 700px at 110% 120%, #122142 0%, transparent 60%),
    var(--bg);color:var(--text);font-family:'Inter','Montserrat',system-ui,Segoe UI,Roboto,Arial,sans-serif}
  .card{background:linear-gradient(160deg, rgba(19,33,66,.92), rgba(9,16,38,.86));border:1px solid var(--glass);
    backdrop-filter: blur(10px);padding:28px;border-radius:22px;box-shadow:0 20px 50px rgba(0,0,0,.35);width:min(460px,92vw)}
  h1{margin:0 0 6px;font-weight:800}
  p.sub{margin:0 0 16px;color:var(--muted)}
  label{display:block;margin:12px 0 6px;color:var(--muted)}
  input{width:100%;padding:13px 12px;border-radius:12px;border:1px solid #24365f;background:var(--bg-2);color:var(--text)}
  input:focus{border-color:var(--accent)}
  button{width:100%;padding:13px;border-radius:12px;border:0;background:linear-gradient(180deg, var(--accent), #3f7fe6);
    color:#00143a;font-weight:800;margin-top:16px;cursor:pointer;box-shadow:0 10px 20px rgba(90,162,255,.22)}
  .msg{margin-top:12px;color:var(--warn)}
  .back{margin-top:16px;text-align:center}
  .back a{color:var(--accent-2);text-decoration:none}
  .back a:hover{text-decoration:underline}
</style>
</head>
<body>
  <div class="card">
    <h1>Recuperar contraseña</h1>
    <p class="sub">Ingresá tu correo y te enviaremos un enlace.</p>

    <?php if($mensaje): ?><div class="msg"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <?php if(!$enviado): ?>
    <form method="post" action="recuperar.php" autocomplete="off">
      <label for="email">Correo electrónico</label>
      <input id="email" type="email" name="email" required>
      <button type="submit">Enviar enlace</button>
    </form>
    <?php endif; ?>

    <div class="back"><a href="loogin.php">Volver al inicio de sesión</a></div>
  </div>
</body>
</html>
