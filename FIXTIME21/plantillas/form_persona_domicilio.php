<?php
/**
 * Partial compartido para alta/edición de Persona + Domicilio.
 * Espera variables previas:
 *   - $accion (string) URL a postear
 *   - $titulo (string)
 *   - $val (array) valores existentes: ['nombre','apellido','dni','email','telefono','pais','provincia','localidad','barrio','calle','altura','piso','departamento']
 *   - $extraCampos (string) HTML adicional (ej. cargo)
 *   - $submitLabel (string) texto del botón
 */
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
?>
<form method="post" action="<?= h($accion) ?>" class="kform" autocomplete="off">
  <h3 style="margin:0 0 12px"><?= h($titulo) ?></h3>
  <div class="row">
    <label>Nombre
      <input name="nombre" required value="<?= h($val['nombre'] ?? '') ?>" autocomplete="given-name">
    </label>
    <label>Apellido
      <input name="apellido" required value="<?= h($val['apellido'] ?? '') ?>" autocomplete="family-name">
    </label>
    <label>DNI
      <input name="dni" value="<?= h($val['dni'] ?? '') ?>" placeholder="Opcional">
    </label>
    <label>Email
      <input type="email" name="email" required value="<?= h($val['email'] ?? '') ?>" autocomplete="email">
    </label>
    <label>Teléfono
      <input name="telefono" value="<?= h($val['telefono'] ?? '') ?>" placeholder="Opcional">
    </label>
  </div>

  <fieldset style="border:1px solid var(--panel); border-radius:12px; padding:12px; margin:12px 0">
    <legend style="padding:0 6px; color:var(--muted)">Domicilio</legend>
    <div class="row">
      <label>País <input name="pais" value="<?= h($val['pais'] ?? '') ?>"></label>
      <label>Provincia <input name="provincia" value="<?= h($val['provincia'] ?? '') ?>"></label>
      <label>Localidad <input name="localidad" value="<?= h($val['localidad'] ?? '') ?>"></label>
      <label>Barrio <input name="barrio" value="<?= h($val['barrio'] ?? '') ?>"></label>
      <label>Calle <input name="calle" value="<?= h($val['calle'] ?? '') ?>"></label>
      <label>Altura <input name="altura" value="<?= h($val['altura'] ?? '') ?>"></label>
      <label>Piso <input name="piso" value="<?= h($val['piso'] ?? '') ?>"></label>
      <label>Departamento <input name="departamento" value="<?= h($val['departamento'] ?? '') ?>"></label>
    </div>
  </fieldset>

  <?php if (!empty($extraCampos)) echo $extraCampos; ?>

  <?php if (empty($val['id'])): ?>
    <label>Contraseña
      <input type="password" name="password" required minlength="6" autocomplete="new-password">
    </label>
  <?php endif; ?>

  <button class="btn" type="submit"><?= h($submitLabel ?? 'Guardar') ?></button>
</form>
<style>
.kform .row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
@media (max-width: 900px){ .kform .row{grid-template-columns:repeat(2,minmax(0,1fr));} }
@media (max-width: 560px){ .kform .row{grid-template-columns:1fr;} }
.kform input, .kform select{width:100%}
</style>
