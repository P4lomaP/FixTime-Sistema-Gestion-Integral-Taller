<?php
require __DIR__ . '/Database.php';
session_start();

/* helper: detectar si ya está hasheado (bcrypt/argon) */
if (!function_exists('is_password_hash_like')) {
  function is_password_hash_like(string $h): bool {
    return (bool)preg_match('/^\$2y\$\d{2}\$.{53}$/', $h) || str_starts_with($h, '$argon2');
  }
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni  = trim($_POST['dni'] ?? '');
    $pass = $_POST['contrasenia'] ?? '';

    if ($dni === '' || $pass === '') {
        $mensaje = 'Completá DNI y contraseña.';
    } else {
        $stmt = $mysqli->prepare("SELECT id, contrasenia, nombre, apellido FROM Personas WHERE dni = ? LIMIT 1");
        $stmt->bind_param('s', $dni);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$row = $res->fetch_assoc()) {
            $mensaje = 'Usuario no existe.';
        } else {
            $hashDb = $row['contrasenia'];

            if (is_password_hash_like($hashDb)) {
                if (!password_verify($pass, $hashDb)) {
                    $mensaje = 'Contraseña incorrecta.';
                } else {
                    if (password_needs_rehash($hashDb, PASSWORD_DEFAULT)) {
                        $nuevo = password_hash($pass, PASSWORD_DEFAULT);
                        $up = $mysqli->prepare("UPDATE Personas SET contrasenia=? WHERE id=?");
                        $up->bind_param('si', $nuevo, $row['id']);
                        $up->execute();
                    }
                    $_SESSION['uid'] = $row['id'];
                    $_SESSION['nombre'] = $row['nombre'];
                    $_SESSION['apellido'] = $row['apellido'];

                    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                    header("Location: $base/dashboard.php");
                    exit;
                }
            } else {
                if (!hash_equals($hashDb, $pass)) {
                    $mensaje = 'Contraseña incorrecta.';
                } else {
                    $nuevo = password_hash($pass, PASSWORD_DEFAULT);
                    $up = $mysqli->prepare("UPDATE Personas SET contrasenia=? WHERE id=?");
                    $up->bind_param('si', $nuevo, $row['id']);
                    $up->execute();

                    $_SESSION['uid'] = $row['id'];
                    $_SESSION['nombre'] = $row['nombre'];
                    $_SESSION['apellido'] = $row['apellido'];

                    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                    header("Location: $base/dashboard.php");
                    exit;
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Fixtime — Iniciar sesión</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<link rel="icon" type="image/png" href="widoo.png" sizes="32x32">
<link rel="shortcut icon" href="widoo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#0b1020; --bg-2:#0e162e; --panel:#0f1b34cc;
    --text:#e5edf9; --muted:#93a3c3; --accent:#5aa2ff; --accent-2:#9ec5ff; --warn:#ffd479;
    --glass-border: rgba(90,162,255,.18);
    --space: clamp(16px, 2.5vw, 28px);
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
    display:flex;
    background:
      radial-gradient(1100px 600px at -10% -10%, #1a2b57 0%, transparent 55%),
      radial-gradient(1000px 700px at 110% 120%, #122142 0%, transparent 60%),
      var(--bg);
    color:var(--text); font-family:'Inter','Montserrat',system-ui,Segoe UI,Roboto,Arial,sans-serif;
  }
  .shell{
    display:grid; grid-template-columns: 1.2fr 1fr; gap:28px;
    width:min(1100px,95vw); margin:auto; align-items:stretch
  }

  /* HERO IZQ */
  .hero{
    position:relative; overflow:hidden; border-radius:22px; padding:32px;
    background:linear-gradient(160deg, rgba(18,31,64,.9), rgba(10,18,42,.86));
    border:1px solid var(--glass-border); backdrop-filter: blur(10px);
    box-shadow:0 30px 60px rgba(0,0,0,.35);
  }
  .logo{ text-align:center; margin-bottom: clamp(14px, 2vw, 24px); }
  .logo img{
    width: clamp(150px, 28vw, 220px); max-width: 90%;
    filter: drop-shadow(0 8px 20px rgba(90,162,255,.55));
    animation: glow 4s ease-in-out infinite alternate;
  }
  @keyframes glow {
    from { filter: drop-shadow(0 8px 20px rgba(90,162,255,.3)); }
    to   { filter: drop-shadow(0 8px 28px rgba(90,162,255,.8)); }
  }
  .title{
    font-size: clamp(1.8rem, 3.2vw, 2.6rem); line-height:1.15; margin:0 0 10px; font-weight:800; text-align:center;
    background: linear-gradient(180deg,#ffffff,#a8c4ff 70%);
    -webkit-background-clip:text; background-clip:text; color:transparent;
  }
  .subtitle{color:var(--accent-2); margin:0 0 14px; text-align:center; font-size: clamp(0.98rem, 1.5vw, 1.05rem)}
  .features{display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:14px; margin-top:14px}
  .feat{
    display:flex; gap:12px; align-items:flex-start; padding:14px;
    background:rgba(255,255,255,.04); border:1px solid rgba(90,162,255,.14);
    border-radius:14px;
  }
  .feat svg{flex:0 0 26px}
  .feat h3{margin:0 0 4px; font-size:1.02rem}
  .feat p{margin:0; color:var(--muted); font-size:.95rem}
  .hero-footer{margin-top:14px; color:#8aa5d6; font-size:.9rem; text-align:center}

  /* FORM DER */
  .card{
    background:linear-gradient(160deg, rgba(19,33,66,.92), rgba(9,16,38,.86));
    border:1px solid var(--glass-border);
    backdrop-filter: blur(10px);
    padding: clamp(18px, 3.5vw, 28px);
    border-radius:22px;
    box-shadow:0 20px 50px rgba(0,0,0,.35);
    width:100%;
  }
  h1{margin:0 0 6px; font-weight:800; letter-spacing:.2px; font-size: clamp(1.2rem, 2.4vw, 1.6rem)}
  p.sub{margin:0 0 14px; color:var(--muted)}
  label{display:block; margin:12px 0 6px; color:var(--muted); font-size:.95rem}
  input{
    width:100%; padding:14px 12px; border-radius:12px;
    border:1px solid #24365f; background:var(--bg-2);
    color:var(--text); outline:none; font-size:16px; /* evita zoom en iOS */
    height:52px; /* tactil */
  }
  input:focus{border-color:var(--accent)}
  button{
    width:100%; padding:14px; height:52px; border-radius:12px; border:0;
    background:linear-gradient(180deg, var(--accent), #3f7fe6);
    color:#00143a; font-weight:800; margin-top:16px; cursor:pointer;
    box-shadow:0 10px 20px rgba(90,162,255,.22);
  }
  .msg{margin-top:10px; color:var(--warn)}
  .links{
    margin-top:22px; font-size:1.06rem; font-weight:600; color:var(--muted); text-align:center;
  }
  .links a{color:var(--accent-2); text-decoration:none}
  .links a:hover{text-decoration:underline}

  /* --- Responsive --- */
  @media (max-width: 980px){
    .shell{grid-template-columns: 1fr; gap:18px; width:min(920px, 94vw); padding-block:32px}
    .hero, .card{border-radius:18px}
    .features{grid-template-columns: 1fr 1fr}
  }
  @media (max-width: 640px){
    .shell{gap:16px; width: 94vw}
    .features{grid-template-columns: 1fr}
    .feat{padding:12px}
    .logo img{width: clamp(140px, 48vw, 200px)}
    .links{margin-top:26px; font-size:1.12rem}
    label{font-size:1rem}
  }
</style>
</head>
<body>
  <div class="shell">
    <!-- IZQUIERDA -->
    <section class="hero" aria-label="Resumen de Fixtime">
      <div class="logo">
        <img src="widoo.png" alt="Logo Widoo">
      </div>
      <h2 class="title">Fixtime</h2>
      <p class="subtitle">Organizá tu taller con precisión</p>

      <div class="features">
        <article class="feat">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6">
            <rect x="3" y="4" width="18" height="16" rx="2"/>
            <path d="M8 2v4M16 2v4M3 9h18"/>
          </svg>
          <div>
            <h3>Turnos inteligentes</h3>
            <p>Programá y confirmá citas fácilmente.</p>
          </div>
        </article>
        <article class="feat">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M16 3a5 5 0 00-4.9 6.1l-7.5 7.5a2.1 2.1 0 103 3l7.5-7.5A5 5 0 0016 3z"/>
          </svg>
          <div>
            <h3>Órdenes claras</h3>
            <p>Seguimiento de cada reparación y estado.</p>
          </div>
        </article>
        <article class="feat">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6">
            <rect x="3" y="4" width="18" height="14" rx="2"/>
            <path d="M7 8h10M7 12h6"/>
          </svg>
          <div>
            <h3>Facturación simple</h3>
            <p>Emití y consultá facturas al instante.</p>
          </div>
        </article>
        <article class="feat">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M12 8a4 4 0 100 8 4 4 0 000-8z"/>
            <path d="M3 13l2 0a7 7 0 001 2l-1 2 2 2 2-1a7 7 0 002 1l0 2h4l0-2a7 7 0 002-1l2 1 2-2-1-2a7 7 0 001-2l2 0v-4l-2 0a7 7 0 00-1-2l1-2-2-2-2 1a7 7 0 00-2-1l0-2h-4l0 2a7 7 0 00-2 1l-2-1-2 2 1 2a7 7 0 00-1 2l-2 0v4z"/>
          </svg>
          <div>
            <h3>Repuestos & stock</h3>
            <p>Control de inventario y precios de repuestos.</p>
          </div>
        </article>
      </div>

      <p class="hero-footer">Todo lo que tu taller necesita, sin vueltas.</p>
    </section>

    <!-- DERECHA -->
    <section class="card" aria-label="Formulario de acceso">
      <h1>Iniciar sesión</h1>
      <p class="sub">Ingresá con tu DNI y contraseña</p>

      <?php if($mensaje): ?><div class="msg" role="alert"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

      <form method="post" action="loogin.php" autocomplete="off">
        <label for="dni">DNI</label>
        <input id="dni" type="text" name="dni" required inputmode="numeric" autocomplete="username">

        <label for="pass">Contraseña</label>
        <input id="pass" type="password" name="contrasenia" required autocomplete="current-password">

        <button type="submit">Entrar</button>
      </form>

      <div class="links">
        <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
      </div>
      <div class="links">
        ¿No tenés cuenta? <a href="register.php">Registrate</a>
      </div>
    </section>
  </div>
</body>
</html>
