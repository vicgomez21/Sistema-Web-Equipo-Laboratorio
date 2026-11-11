<?php
if (!isset($catalogoTitulo, $catalogoEntidad, $catalogoApi, $catalogoIdCampo, $catalogoLimiteNombre, $catalogoTieneActivo, $catalogoOpciones)) {
    http_response_code(500);
    exit;
}
$columnas = $catalogoTieneActivo ? 6 : 5;
?>
<div id="crudCatalogo"
     data-api="<?= htmlspecialchars($catalogoApi, ENT_QUOTES, 'UTF-8') ?>"
     data-entidad="<?= htmlspecialchars($catalogoEntidad, ENT_QUOTES, 'UTF-8') ?>"
     data-id-campo="<?= htmlspecialchars($catalogoIdCampo, ENT_QUOTES, 'UTF-8') ?>"
     data-tiene-activo="<?= $catalogoTieneActivo ? '1' : '0' ?>">
    <div class="page-header">
        <div>
            <h2><?= htmlspecialchars($catalogoTitulo) ?></h2>
            <p class="page-header__texto">Crea este catálogo antes de registrar los equipos.</p>
        </div>
        <button class="btn btn--primario" id="botonNuevo" type="button">
            + Nuevo <?= htmlspecialchars($catalogoEntidad) ?>
        </button>
    </div>

    <section class="card">
        <input class="buscador" id="buscador" type="search"
               placeholder="Buscar por nombre o descripción...">
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Equipos</th>
                        <?php if ($catalogoTieneActivo): ?><th>Estado</th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tablaCatalogo">
                    <tr><td colspan="<?= $columnas ?>" class="tabla-vacia">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <dialog id="dialogoCatalogo">
        <form id="formCatalogo" class="dialogo">
            <div class="dialogo__cabecera"><h3 id="tituloDialogo">Nuevo registro</h3></div>
            <div class="dialogo__cuerpo">
                <input type="hidden" id="catalogoId">
                <div class="form-grid">
                    <div class="campo campo--completo">
                        <label for="catalogoNombre">Nombre *</label>
                        <select id="catalogoNombre" required>
                            <option value="">Selecciona una opción</option>
                            <?php foreach ($catalogoOpciones as $opcion): ?>
                                <option value="<?= htmlspecialchars($opcion['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($opcion['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo campo--completo">
                        <label for="catalogoDescripcion">Descripción</label>
                        <textarea id="catalogoDescripcion" maxlength="150" rows="3" readonly
                                  aria-describedby="ayudaDescripcion"></textarea>
                        <small id="ayudaDescripcion" class="form-help">Se completa automáticamente según la opción seleccionada.</small>
                    </div>
                    <?php if ($catalogoTieneActivo): ?>
                    <div class="campo campo--check campo--completo">
                        <input id="catalogoActivo" type="checkbox" checked>
                        <label for="catalogoActivo">Categoría activa</label>
                    </div>
                    <?php endif; ?>
                </div>
                <p class="form-error" id="catalogoError" hidden></p>
            </div>
            <div class="dialogo__pie">
                <button class="btn btn--secundario" id="botonCancelar" type="button">Cancelar</button>
                <button class="btn btn--primario" type="submit">Guardar</button>
            </div>
        </form>
    </dialog>
</div>
<script type="application/json" id="catalogoOpciones"><?= json_encode(
    $catalogoOpciones,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
