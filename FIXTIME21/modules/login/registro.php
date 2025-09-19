<?php
require_once __DIR__ . '/../../clases/Sesion.php';
Sesion::iniciar();
if (!empty($_SESSION['uid'])) {
  $app = require __DIR__ . '/../../config/app.php';
  header('Location: ' . rtrim($app['base_url'], '/') . '/modules/cliente/');
  exit;
}
$ok    = $_GET['ok'] ?? null;
$error = $_GET['error'] ?? null;
$base  = rtrim((require __DIR__ . '/../../config/app.php')['base_url'], '/');
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
    :root { --bg:#0f172a; --card:#0b1220; --text:#e5e7eb; --muted:#94a3b8; --brand:#3b82f6; --brand-2:#2563eb; }
    html, body { height:auto; }
    *, *::before, *::after { box-sizing:border-box; }
    body{ margin:0; font-family:'Inter',sans-serif; background:var(--bg);
      display:flex; justify-content:center; align-items:flex-start; min-height:100svh; overflow-y:auto; color:var(--text); }
    #bg-lottie{position:fixed; inset:0; width:100%; height:100%; z-index:0; pointer-events:none; object-fit:cover; filter:brightness(0.25);}
    .overlay{position:fixed; inset:0; background:rgba(15,23,42,0.85); z-index:0;}
    .page-center{position:relative; z-index:1; width:100%; max-width:480px; padding:24px 16px 48px;}
    .card-full{background:var(--card); border-radius:18px; padding:30px 28px; box-shadow:0 8px 24px rgba(0,0,0,0.6); margin:12px auto;}
    .card-full h1{margin-bottom:15px; font-size:22px; font-weight:700; text-align:center;}
    .card-full h2{margin:18px 0 6px; font-size:16px; font-weight:600; text-align:left;}
    form{display:flex; flex-direction:column; gap:12px;}
    label{text-align:left; font-size:14px; color:var(--muted);}
    input, select, .chip-btn{
      padding:12px; border-radius:10px; border:1px solid rgba(148,163,184,.25);
      background:#0f172a; color:var(--text); width:100%;
    }
    input:focus, select:focus{outline:none; border-color:var(--brand); box-shadow:0 0 0 2px rgba(59,130,246,.4);}
    .row{display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:12px;}
    .row > *{min-width:0;}
    @media(max-width:480px){ .row{grid-template-columns:1fr;} }
    .btn{ margin-top:12px; padding:12px; border:none; border-radius:10px;
      background:linear-gradient(135deg,var(--brand),var(--brand-2)); color:#fff; font-weight:700; cursor:pointer; transition:transform .2s, box-shadow .2s; }
    .btn:hover{transform:translateY(-2px); box-shadow:0 6px 20px rgba(59,130,246,.45);}
    .links{margin-top:12px; text-align:center;}
    .links a{color:var(--brand); text-decoration:none; font-weight:600;}
    .sep{height:1px; background:#22314e; margin:6px 0 2px}
    /* Teléfonos dinámicos */
    .phones { display:flex; flex-direction:column; gap:10px; }
    .phone-item { display:flex; gap:8px; align-items:center; }
    .phone-item input { flex:1; }
    .chip-btn { width:auto; cursor:pointer; display:inline-flex; align-items:center; gap:6px; padding:10px 12px; }
    .chip-btn:hover { border-color:var(--brand); }
    .remove { border-color:#ef4444; color:#ef4444; }
    .remove:hover { box-shadow:0 0 0 2px rgba(239,68,68,.35); }
    /* Empresa: eliminado en registro (se gestiona en el panel) */
    small.muted{opacity:.8}
  </style>
</head>
<body>

<lottie-player id="bg-lottie"
  src="https://assets9.lottiefiles.com/packages/lf20_u4yrau.json"
  background="transparent" speed="1" loop autoplay></lottie-player>
<div class="overlay"></div>

<main class="page-center">
  <div class="card-full">
    <h1>Bienvenido a Fixtime 🚗🔧</h1>

    <form id="formRegistro" action="../../controladores/registro_controlador.php" method="post" novalidate>
      <!-- NUEVO: selector de tipo de registro -->
      <label for="tipo_cuenta">¿Cómo vas a usar Fixtime?</label>
    <select id="tipo_cuenta" name="es_empresarial" required>
      <option value="0">Solo personal</option>
      <option value="1">Personal + represento una empresa</option>
    </select>
      <small class="muted">La cuenta se crea a tu nombre. Si representás una empresa, podrás cargar sus datos luego en tu panel.</small>
      <div class="sep"></div>

      <h2>Datos personales</h2>
      <label for="nombre">Nombre</label>
      <input id="nombre" name="nombre" required>
      <label for="apellido">Apellido</label>
      <input id="apellido" name="apellido" required>
      <label for="dni">DNI</label>
      <input id="dni" name="dni" required inputmode="numeric" placeholder="Solo números">
      <label for="email">Correo</label>
      <input id="email" name="email" type="email" required>
      <label for="contrasenia">Contraseña</label>
      <input id="contrasenia" name="contrasenia" type="password" required placeholder="Mínimo 6, letras y números">

      <h2>Teléfonos</h2>
      <div class="phones" id="phones">
        <div class="phone-item">
          <input type="text" name="telefonos[]" placeholder="Ej: +54 9 11 5555-5555" required>
          <button type="button" class="chip-btn remove" aria-label="Quitar" style="display:none">Quitar</button>
        </div>
      </div>
      <button type="button" id="addPhone" class="chip-btn">➕ Agregar teléfono</button>

      <h2>Domicilio</h2>
      <div class="row">
        <div><label for="pais">País</label><input id="pais" name="pais" required></div>
        <div><label for="provincia">Provincia</label><input id="provincia" name="provincia" required></div>
      </div>
      <div class="row">
        <div><label for="localidad">Localidad</label><input id="localidad" name="localidad" required></div>
        <div><label for="barrio">Barrio</label><input id="barrio" name="barrio" required></div>
      </div>
      <label for="calle">Calle</label><input id="calle" name="calle" required>
      <div class="row">
        <div><label for="altura">Altura</label><input id="altura" name="altura" required inputmode="numeric"></div>
        <div><label for="piso">Piso (opcional)</label><input id="piso" name="piso"></div>
      </div>
      <label for="departamento">Dto. (opcional)</label><input id="departamento" name="departamento">

      <!-- Sección de empresa se carga luego en el panel -->
      <!-- compat -->
      <input type="hidden" name="correo" id="shadow_correo">
      <input type="hidden" name="mail" id="shadow_mail">
      <input type="hidden" name="contrasena" id="shadow_contrasena">
      <input type="hidden" name="password" id="shadow_password">

      <input type="hidden" name="action" value="registrar">
      <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

      <button class="btn" type="submit">Registrarme</button>
      <div class="links"><a href="index.php">Volver</a></div>
    </form>
  </div>
</main>

<script>
(function(){
  const $form = document.getElementById('formRegistro');
  const $phones = document.getElementById('phones');
  const $addPhone = document.getElementById('addPhone');
  
  function refreshRemoveVisibility(){
    const items = $phones.querySelectorAll('.phone-item');
    items.forEach((it) => {
      const btn = it.querySelector('.remove');
      if (!btn) return;
      btn.style.display = (items.length > 1) ? 'inline-flex' : 'none';
    });
  }
  $addPhone.addEventListener('click', () => {
    const item = document.createElement('div');
    item.className = 'phone-item';
    item.innerHTML = `<input type="text" name="telefonos[]" placeholder="Otro teléfono">
      <button type="button" class="chip-btn remove">Quitar</button>`;
    $phones.appendChild(item); refreshRemoveVisibility();
  });
  $phones.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove')) {
      e.target.closest('.phone-item').remove();
      refreshRemoveVisibility();
    }
  });
  refreshRemoveVisibility();

  $form.addEventListener('submit', function(e){
    const nombre = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const dni = document.getElementById('dni').value.trim();
    const email = document.getElementById('email').value.trim();
    const pass = document.getElementById('contrasenia').value.trim();

    if (!nombre || !apellido || !dni || !email || !pass) {
      e.preventDefault();
      Swal.fire({icon:'error',title:'Faltan datos',text:'Completá todos los campos obligatorios'});
      return;
    }

    // Si eligió empresa, exigir razón social y CUIT
    if ($tipo.value==='personal_y_empresarial') {
      const rz = document.getElementById('empresa_razon_social').value.trim();
      const cuit = document.getElementById('empresa_cuit').value.trim();
      if (!rz || !cuit) {
        e.preventDefault();
        Swal.fire({icon:'error',title:'Faltan datos de empresa',text:'Completá Razón social y CUIT.'});
        return;
      }
    }

    // copiar pass/mail alternativos
    document.getElementById('shadow_correo').value=email;
    document.getElementById('shadow_mail').value=email;
    document.getElementById('shadow_contrasena').value=pass;
    document.getElementById('shadow_password').value=pass;
  });

  <?php if ($ok): ?>
  Swal.fire({icon:'success',title:'Cuenta creada',text:'¡Ya podés iniciar sesión!',timer:2000,showConfirmButton:false})
    .then(()=>{ window.location.href="index.php"; });
  <?php endif; ?>
  <?php if ($error): ?>
  Swal.fire({icon:'error',title:'Error',text:'<?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?>'});
  <?php endif; ?>
})();
</script>
</body>
</html>
