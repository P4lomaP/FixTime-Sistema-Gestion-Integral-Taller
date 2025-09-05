<?php
require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::iniciar();
if (!empty($_SESSION['uid'])) {
  $app = require __DIR__ . '/../../config/app.php';
  header('Location: ' . rtrim($app['base_url'],'/') . '/modules/cliente/');
  exit;
}
$ok = $_GET['ok'] ?? null;
$error = $_GET['error'] ?? null;
$base = rtrim((require __DIR__ . '/../../config/app.php')['base_url'], '/');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Fixtime — Registro</title>
  <link rel="icon" type="image/png" href="<?= $base ?>/publico/widoo.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
  <style>
    :root {
      --bg: #0f172a;
      --panel: #111827;
      --card: #0b1220;
      --text: #e5e7eb;
      --muted: #94a3b8;
      --brand: #3b82f6;
      --brand-2: #2563eb;
    }
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      overflow: hidden;
      color: var(--text);
    }
    
    #bg-lottie {
      position: fixed;
      inset: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      pointer-events: none;
      object-fit: cover;
      filter: brightness(0.25);
    }
    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.85); 
      z-index: 0;
    }
    .page-center {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      padding: 16px;
    }
    .card-full {
      background: var(--card);
      border-radius: 18px;
      padding: 30px 28px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.6);
      text-align: center;
    }
    .card-full h1 {
      margin-bottom: 15px;
      font-size: 22px;
      font-weight: 700;
      color: var(--text);
    }
    .card-full h2 {
      margin: 15px 0;
      font-size: 18px;
      font-weight: 600;
      color: var(--text);
    }
    form { display: flex; flex-direction: column; gap: 12px; margin-top: 10px; }
    label { text-align: left; font-size: 14px; color: var(--muted); }
    input {
      padding: 12px;
      border-radius: 10px;
      border: 1px solid rgba(148,163,184,.25);
      background: #0f172a;
      color: var(--text);
    }
    input:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 2px rgba(59,130,246,.4);
    }
    .btn {
      margin-top: 12px;
      padding: 12px;
      border: none;
      border-radius: 10px;
      background: linear-gradient(135deg,var(--brand),var(--brand-2));
      color: #fff;
      font-weight: 700;
      cursor: pointer;
      transition: transform .2s, box-shadow .2s;
    }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.45); }
    .links { margin-top: 12px; }
    .links a { color: var(--brand); text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>


<lottie-player 
  id="bg-lottie"
  src="https://assets9.lottiefiles.com/packages/lf20_u4yrau.json"  <!-- ⚙️ Engranajes (puedes cambiar por ruedas si querés) -->
  background="transparent"
  speed="1"
  loop autoplay>
</lottie-player>
<div class="overlay"></div>

<main class="page-center">
  <div class="card-full">
    <h1>Bienvenido a Fixtime 🚗🔧</h1>
    <form id="formRegistro" action="../../controladores/registro_controlador.php" method="post" novalidate>
      <h2>Crear cuenta</h2>
      <label for="nombre">Nombre</label>
      <input id="nombre" name="nombre" required>
      <label for="apellido">Apellido</label>
      <input id="apellido" name="apellido" required>
      <label for="email">Correo</label>
      <input id="email" name="email" type="email" required>
      <label for="contrasenia">Contraseña</label>
      <input id="contrasenia" name="contrasenia" type="password" required>
      <button class="btn" type="submit">Registrarme</button>
      <div class="links"><a href="index.php">Volver</a></div>
    </form>
  </div>
</main>

<script>

<?php if ($ok): ?>
Swal.fire({icon:'success',title:'Cuenta creada',text:'¡Ya podés iniciar sesión!'});
<?php endif; ?>
<?php if ($error): ?>
Swal.fire({icon:'error',title:'Error',text:'<?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?>'});
<?php endif; ?>


document.getElementById('formRegistro').addEventListener('submit', function(e) {
  e.preventDefault();
  const nombre = document.getElementById('nombre').value.trim(),
        apellido = document.getElementById('apellido').value.trim(),
        email = document.getElementById('email').value.trim(),
        pass = document.getElementById('contrasenia').value.trim();
  if (!nombre || !apellido) return Swal.fire({icon:'error',title:'Faltan datos',text:'Nombre y apellido son obligatorios'});
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return Swal.fire({icon:'error',title:'Correo inválido',text:'Ingresá un correo válido'});
  if (!/(?=.*[A-Za-z])(?=.*\d).{6,}/.test(pass)) return Swal.fire({icon:'error',title:'Contraseña inválida',text:'Debe tener al menos 6 caracteres, con letras y números'});
  this.submit();
});
</script>
</body>
</html>
