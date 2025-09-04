<?php
require __DIR__ . '/Database.php';

session_start();
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $dni      = trim($_POST['dni'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass     = $_POST['contrasenia'] ?? '';

    if ($nombre === '' || $apellido === '' || $dni === '' || $email === '' || $pass === '') {
        $mensaje = 'Por favor, completá todos los campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'El correo no es válido.';
    } else {
        try {
            // Iniciamos transacción: si algo falla, nada queda a medias
            $mysqli->begin_transaction();

            // 1) Insertar persona
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO Personas (nombre, apellido, dni, contrasenia) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $nombre, $apellido, $dni, $hash);
            $stmt->execute();
            $persona_id = $stmt->insert_id;

            // 2) Buscar el id del tipo "Email" (no asumimos que sea 2)
            $qTipo = $mysqli->prepare("SELECT id FROM Tipos_Contactos WHERE descripcion = 'Email' LIMIT 1");
            $qTipo->execute();
            $rTipo = $qTipo->get_result();
            if (!$rowTipo = $rTipo->fetch_assoc()) {
                throw new Exception("No existe el tipo de contacto 'Email' en Tipos_Contactos.");
            }
            $tipo_email_id = (int)$rowTipo['id'];

            // 3) Insertar el email de la persona
            $insMail = $mysqli->prepare("INSERT INTO Contacto_Persona (Persona_id, Tipo_Contacto_id, valor) VALUES (?,?,?)");
            $insMail->bind_param('iis', $persona_id, $tipo_email_id, $email);
            $insMail->execute();

            // 4) (Opcional) Si querés, podés darle rol de Cliente automáticamente
            // $insCli = $mysqli->prepare("INSERT INTO Clientes (Persona_id) VALUES (?)");
            // $insCli->bind_param('i', $persona_id);
            // $insCli->execute();

            $mysqli->commit();
            $mensaje = '✅ Registro exitoso. Ya podés iniciar sesión.';
        } catch (Throwable $e) {
            $mysqli->rollback();
            // Si activaste el índice único, este mensaje aparece si el correo ya existe:
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'uq_tipo_valor')) {
                $mensaje = '❌ Ese correo ya está registrado.';
            } else {
                $mensaje = '❌ No se pudo registrar: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Registro — Fixtime</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet"> 
<style>
  :root{
    --bg: #0b1020; --bg-2:#0e162e; --panel:#0f1b34cc;
    --text:#e5edf9; --muted:#93a3c3; --accent:#5aa2ff;
    --accent-2:#9ec5ff; --warn:#ffd479; --glass:rgba(90,162,255,.18)
  }
  *{box-sizing:border-box}
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; gap:16px;
    background:
      radial-gradient(1200px 600px at 20% -20%, #18264c 0%, transparent 60%),
      radial-gradient(1000px 500px at 120% 110%, #122142 0%, transparent 55%),
      var(--bg);
    color:var(--text); font-family:'Inter','Montserrat',system-ui,Segoe UI,Roboto,Arial,sans-serif;
  }
  .card{
    background:linear-gradient(160deg, rgba(19,33,66,.92), rgba(9,16,38,.86));
    border:1px solid var(--glass);
    backdrop-filter: blur(10px);
    padding:28px; border-radius:22px; width:min(480px,92vw);
    box-shadow:0 20px 40px rgba(0,0,0,.35)
  }
  .logo{display:flex; justify-content:center; margin-bottom:16px}
  .logo img{width:140px; filter: drop-shadow(0 6px 16px rgba(90,162,255,.45))}
  h1{margin:0 0 6px; font-weight:800}
  p.sub{margin:0 0 16px; color:var(--muted)}
  label{display:block; margin:12px 0 8px; color:var(--muted); font-size:.95rem}
  input{
    width:100%; padding:13px 12px; border-radius:12px;
    border:1px solid #24365f; background:var(--bg-2); color:var(--text); outline:none;
  }
  input:focus{border-color:var(--accent)}
  button{
    width:100%; padding:13px; border-radius:12px; border:0;
    background:linear-gradient(180deg, var(--accent), #3f7fe6);
    color:#00143a; font-weight:800; letter-spacing:.3px; margin-top:16px; cursor:pointer;
    box-shadow:0 10px 20px rgba(90,162,255,.2);
  }
  .msg{margin-top:12px; color:var(--warn)}
  .links{margin-top:14px; font-size:.95rem; color:var(--muted); text-align:center}
  .links a{color:var(--accent-2); text-decoration:none}
  .links a:hover{text-decoration:underline}
</style>
</head>
<body>
  <div class="card">
    <div class="logo"><img src="widoo.png" alt="Widoo"></div>
    <h1>Crear cuenta</h1>
    <p class="sub">Completá tus datos para registrarte</p>

    <?php if($mensaje): ?><div class="msg"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <form method="post" action="register.php" autocomplete="off">
      <label>Nombre</label>
      <input type="text" name="nombre" required>

      <label>Apellido</label>
      <input type="text" name="apellido" required>

      <label>DNI</label>
      <input type="text" name="dni" required>

      <label>Correo electrónico</label>
      <input type="email" name="email" required>

      <label>Contraseña</label>
      <input type="password" name="contrasenia" required>

      <button type="submit">Registrarme</button>
    </form>

    <div class="links">
      ¿Ya tenés cuenta? <a href="loogin.php">Iniciar sesión</a>
    </div>
  </div>
</body>
</html>
