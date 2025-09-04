<?php
require __DIR__ . '/Database.php';

$mensaje = '';
$ok = false;

// Leer token de la URL
$token = trim($_GET['token'] ?? '');

// Validar token
$reset = null;
if ($token !== '') {
    $sql = "SELECT pr.id, pr.persona_id, pr.expires_at, pr.used
            FROM PasswordResets pr
            WHERE pr.token = ?
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $reset = $res->fetch_assoc();
}

if (!$reset) {
    $mensaje = 'El enlace no es válido.';
} else {
    // Ver vencimiento y uso
    $expira = new DateTime($reset['expires_at']);
    $ahora  = new DateTime();
    if ((int)$reset['used'] === 1 || $ahora > $expira) {
        $mensaje = 'El enlace ya no es válido (usado o vencido).';
    } else {
        // Si llega POST, procesar nueva contraseña
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pass1 = $_POST['pass1'] ?? '';
            $pass2 = $_POST['pass2'] ?? '';
            if ($pass1 === '' || $pass2 === '') {
                $mensaje = 'Completá ambos campos.';
            } elseif ($pass1 !== $pass2) {
                $mensaje = 'Las contraseñas no coinciden.';
            } else {
                // Guardar nueva contraseña
                $hash = password_hash($pass1, PASSWORD_DEFAULT);

                $up1 = $mysqli->prepare("UPDATE Personas SET contrasenia=? WHERE id=?");
                $up1->bind_param('si', $hash, $reset['persona_id']);
                $up1->execute();

                // Marcar token como usado
                $up2 = $mysqli->prepare("UPDATE PasswordResets SET used=1 WHERE id=?");
                $up2->bind_param('i', $reset['id']);
                $up2->execute();

                $ok = true;
                $mensaje = 'Tu contraseña fue restablecida correctamente. Ya podés iniciar sesión.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Restablecer contraseña — Fixtime</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{--bg:#0b1020;--bg-2:#0e162e;--panel:#0f1b34cc;--text:#e5edf9;--muted:#93a3c3;--accent:#5aa2ff;--accent-2:#9ec5ff;--warn:#ffd479;--glass:rgba(90,162,255,.18)}
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
    <h1>Restablecer contraseña</h1>

    <?php if($mensaje): ?><div class="msg"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <?php if($reset && !$ok && $mensaje === ''): ?>
      <p class="sub">Ingresá tu nueva contraseña.</p>
      <form method="post" action="reset.php?token=<?= urlencode($token) ?>" autocomplete="off">
        <label for="p1">Nueva contraseña</label>
        <input id="p1" type="password" name="pass1" required>
        <label for="p2">Repetir contraseña</label>
        <input id="p2" type="password" name="pass2" required>
        <button type="submit">Guardar nueva contraseña</button>
      </form>
    <?php endif; ?>

    <div class="back"><a href="loogin.php">Volver al inicio de sesión</a></div>
  </div>
</body>
</html>
