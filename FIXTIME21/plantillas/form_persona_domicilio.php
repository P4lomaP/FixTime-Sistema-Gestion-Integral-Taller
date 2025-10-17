<?php
/**
 * Variables esperadas:
 * - $accion        (string) URL del controlador (POST)
 * - $titulo        (string) título del bloque
 * - $extraCampos   (string) HTML extra a inyectar (e.g., select Cargo o segmented)
 * - $submitLabel   (string) texto del botón submit
 */
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
?>
<h3 style="margin:0 0 12px"><?= h($titulo ?? 'Alta') ?></h3>

<form method="post" action="<?= h($accion) ?>" novalidate>
  <!-- honeypot anti bots (queda oculto por CSS inline) -->
  <div style="position:absolute;left:-5000px" aria-hidden="true">
    <label>Si ves este campo dejalo vacío
      <input type="text" name="hp">
    </label>
  </div>

  <div class="row">
    <label>Nombre
      <input name="nombre" required minlength="2" autocomplete="off" placeholder="Nombre">
    </label>
    <label>Apellido
      <input name="apellido" required minlength="2" autocomplete="off" placeholder="Apellido">
    </label>

    <label>DNI
      <input name="dni" required inputmode="numeric" pattern="\d{7,10}" title="Sólo números, 7 a 10 dígitos" placeholder="Ej: 30111222">
    </label>

    <label>Email
      <input name="email" type="email" placeholder="Opcional (usuario de contacto)">
    </label>

    <label>Teléfono
      <input name="telefono" placeholder="Opcional (con código de país)">
    </label>

    <label>Contraseña
      <input name="password" type="password" required minlength="6" autocomplete="new-password" placeholder="Mínimo 6 caracteres">
    </label>
  </div>

  <?php if (!empty($extraCampos)) echo $extraCampos; ?>

  <fieldset style="border:1px solid rgba(157,176,208,.16);border-radius:14px;padding:12px;margin-top:8px">
    <legend style="font-size:12px;color:var(--muted);padding:0 6px">Domicilio</legend>
    <div class="row">
      <label>País
        <input name="pais" placeholder="Ej: Argentina">
      </label>
      <label>Provincia
        <input name="provincia" placeholder="Ej: Buenos Aires">
      </label>
      <label>Localidad
        <input name="localidad" placeholder="Ej: La Plata">
      </label>
      <label>Barrio
        <input name="barrio" placeholder="Opcional">
      </label>
      <label>Calle
        <input name="calle" placeholder="Opcional">
      </label>
      <label>Altura
        <input name="altura" placeholder="Opcional">
      </label>
      <label>Piso
        <input name="piso" placeholder="Opcional">
      </label>
      <label>Departamento
        <input name="departamento" placeholder="Opcional">
      </label>
    </div>
  </fieldset>

  <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
    <button class="btn ghost" type="reset">Limpiar</button>
    <button class="btn" type="submit"><?= h($submitLabel ?? 'Guardar') ?></button>
  </div>

  <script>
  // Anti-doble envío + validaciones HTML5
  (function(){
    const form = document.currentScript.closest('form');
    if (!form) return;

    let sending = false;
    form.addEventListener('submit', (e)=>{
      // honeypot
      if (form.hp && form.hp.value.trim() !== '') { e.preventDefault(); return; }

      if (sending) { e.preventDefault(); return; }
      if (!form.checkValidity()) return;
      sending = true;

      const btn = form.querySelector('button[type="submit"]');
      if (btn){ btn.disabled = true; btn.textContent = 'Enviando…'; }
    });
  })();
  </script>
</form>
