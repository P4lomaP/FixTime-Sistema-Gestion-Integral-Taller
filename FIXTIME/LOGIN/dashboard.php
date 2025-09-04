<?php
session_start();
if (!isset($_SESSION['uid'])) {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    header("Location: $base/loogin.php");
    exit;
}
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$apellido = $_SESSION['apellido'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel — Widoo</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet"> 
<style>
  :root{
    --bg: #0b1020;
    --bg-2:#0e162e;
    --panel:#0f1b34cc;
    --text:#e5edf9;
    --muted:#93a3c3;
    --accent:#5aa2ff;
    --accent-2:#9ec5ff;
  }
  *{box-sizing:border-box}
  body{
    margin:0; min-height:100vh; display:flex; flex-direction:column;
    align-items:center; justify-content:center; gap:16px;
    background:radial-gradient(1200px 600px at 20% -20%, #18264c 0%, transparent 60%),
               radial-gradient(1000px 500px at 120% 110%, #122142 0%, transparent 55%),
               var(--bg);
    color:var(--text); font-family:'Inter','Montserrat',system-ui,Segoe UI,Roboto,Arial,sans-serif;
  }
  .logo img{width:120px; filter: drop-shadow(0 4px 12px rgba(0,0,0,.5));}
  .card{
    background:linear-gradient(160deg, rgba(19,33,66,.92), rgba(9,16,38,.86));
    border:1px solid rgba(90,162,255,.18);
    backdrop-filter: blur(10px);
    padding:28px; border-radius:20px; width:min(640px,94vw);
    box-shadow:0 20px 40px rgba(0,0,0,.35);
    text-align:center;
  }
  h1{margin:6px 0 12px}
  p{margin:0 0 16px; color:var(--accent-2)}
  a.btn{
    display:inline-block; padding:12px 16px; border-radius:12px; text-decoration:none;
    background:linear-gradient(180deg, var(--accent), #3f7fe6); color:#00143a; font-weight:700;
    box-shadow:0 10px 20px rgba(90,162,255,.2);
  }
  .grid{
    margin-top:18px; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:14px;
  }
  .tile{
    background:rgba(255,255,255,.04); border:1px solid rgba(90,162,255,.14);
    border-radius:14px; padding:16px; text-align:left;
  }
  .tile h3{margin:0 0 6px; font-size:1.05rem}
  .tile p{margin:0; color:var(--muted)}
</style>
</head>
<body>
  <div class="logo">
    <img src="widoo.png" alt="Widoo Logo">
  </div>
  <div class="card">
    <h1>¡Hola, <?= htmlspecialchars($nombre . ' ' . $apellido) ?>!</h1>
    <p>Sesión iniciada correctamente.</p>

    <div class="grid">
      <div class="tile">
        <h3>Turnos</h3>
        <p>Gestioná turnos de clientes y vehículos.</p>
      </div>
      <div class="tile">
        <h3>Órdenes</h3>
        <p>Seguimiento de reparaciones y estados.</p>
      </div>
      <div class="tile">
        <h3>Facturación</h3>
        <p>Revisá facturas y tipos de pago.</p>
      </div>
    </div>

    <p style="margin-top:18px">
      <a class="btn" href="logout.php">Cerrar sesión</a>
    </p>
  </div>
</body>
</html>
