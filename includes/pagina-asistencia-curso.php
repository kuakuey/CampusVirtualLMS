<?php if ($esPropietario): ?>
<ul class="nav nav-pills gap-2 mb-4 flex-wrap">
    <li class="nav-item">
        <a class="nav-link <?= $vista === 'tomar' ? 'active' : '' ?>" href="<?= escapar(url_asistencia_curso($id, ['vista' => 'tomar', 'fecha' => $fecha])) ?>">
            <i class="bi bi-calendar-check me-1"></i> Tomar asistencia
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $vista === 'reportes' ? 'active' : '' ?>" href="<?= escapar(url_asistencia_curso($id, ['vista' => 'reportes'])) ?>">
            <i class="bi bi-bar-chart me-1"></i> Reportes
        </a>
    </li>
</ul>

<?php if ($vista !== 'reportes'): ?>
<div class="panel mb-4">
    <div class="panel-header"><h2>Generar listado</h2></div>
    <div class="panel-body">
        <form class="row g-3 align-items-end" method="get" action="<?= URL_APP ?>/curso.php">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="pestaña" value="asistencia">
            <input type="hidden" name="vista" value="tomar">
            <div class="col-md-6">
                <label class="form-label" for="fecha-asistencia">Fecha</label>
                <input type="date" name="fecha" id="fecha-asistencia" class="form-control" value="<?= escapar($fecha) ?>" required>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-people me-1"></i> Generar listado
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$estudiantesAsistencia): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p class="mb-0">Este curso no tiene estudiantes inscritos activos.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="mb-1">Lista del <?= formatear_fecha($fecha) ?></h2>
                    <p class="small text-muted mb-0">
                        <?= count($estudiantesAsistencia) ?> estudiante(s)
                        <?php if ($yaRegistrada): ?>
                            · <span class="text-success">Ya hay un registro; puedes actualizarlo.</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-success" data-marcar="present">Todos presentes</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-marcar="absent">Todos ausentes</button>
                </div>
            </div>
    <form method="post" id="form-asistencia" action="<?= escapar(url_asistencia_curso($id)) ?>">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar_asistencia">
        <input type="hidden" name="fecha" value="<?= escapar($fecha) ?>">
            <div class="panel-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 attendance-table">
                        <thead class="table-light">
                            <tr>
                                <th>Estudiante</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantesAsistencia as $st): ?>
                                <?php
                                $sid = (int) $st['id'];
                                $estadoActual = $asistenciasFecha[$sid] ?? 'present';
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?= renderizar_avatar_usuario($st, 36) ?>
                                            <div>
                                                <strong><?= escapar($st['name']) ?></strong>
                                                <div class="small text-muted"><?= escapar($st['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group attendance-status-group flex-wrap" role="group">
                                            <?php foreach (['present' => 'Presente', 'absent' => 'Ausente', 'late' => 'Tarde', 'excused' => 'Justificado'] as $valor => $etiqueta): ?>
                                                <input type="radio"
                                                       class="btn-check"
                                                       name="estado[<?= $sid ?>]"
                                                       id="est-<?= $sid ?>-<?= $valor ?>"
                                                       value="<?= $valor ?>"
                                                       <?= $estadoActual === $valor ? 'checked' : '' ?>>
                                                <label class="btn btn-outline-<?= $valor === 'present' ? 'success' : ($valor === 'absent' ? 'danger' : ($valor === 'late' ? 'warning' : 'info')) ?>" for="est-<?= $sid ?>-<?= $valor ?>">
                                                    <?= $etiqueta ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
            <div class="panel-body border-top d-flex justify-content-between flex-wrap gap-2">
                <?php if ($yaRegistrada): ?>
                <form method="post" action="<?= escapar(url_asistencia_curso($id)) ?>" onsubmit="return confirm('¿Eliminar el listado del <?= escapar(formatear_fecha($fecha)) ?>? Esta acción no se puede deshacer.');">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="eliminar_asistencia">
                    <input type="hidden" name="fecha" value="<?= escapar($fecha) ?>">
                    <button class="btn btn-outline-danger" type="submit">
                        <i class="bi bi-trash me-1"></i> Borrar listado
                    </button>
                </form>
                <?php else: ?>
                <span></span>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit" form="form-asistencia">
                    <i class="bi bi-save me-1"></i> Guardar asistencia
                </button>
            </div>
        </div>
    <script>
    document.querySelectorAll('[data-marcar]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var estado = boton.getAttribute('data-marcar');
            document.querySelectorAll('#form-asistencia input[type="radio"][value="' + estado + '"]').forEach(function (radio) {
                radio.checked = true;
            });
        });
    });
    </script>
<?php endif; ?>

<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-navy"><i class="bi bi-calendar3"></i></div>
            <div class="stat-value"><?= (int) $resumen['sesiones'] ?></div>
            <div class="stat-label">Sesiones</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-percent"></i></div>
            <div class="stat-value"><?= (int) $resumen['promedio'] ?>%</div>
            <div class="stat-label">Asistencia</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-person-check"></i></div>
            <div class="stat-value"><?= (int) $resumen['presentes'] ?></div>
            <div class="stat-label">Presentes</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-rose"><i class="bi bi-person-x"></i></div>
            <div class="stat-value"><?= (int) $resumen['ausentes'] ?></div>
            <div class="stat-label">Ausentes</div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Listados de asistencia</h2></div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th class="text-center">Presente</th>
                        <th class="text-center">Ausente</th>
                        <th class="text-center">Tarde</th>
                        <th class="text-center">Justificado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$reporteFechas): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay listados de asistencia registrados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($reporteFechas as $fila): ?>
                        <tr>
                            <td><?= formatear_fecha($fila['attendance_date']) ?></td>
                            <td class="text-center"><?= (int) $fila['presentes'] ?></td>
                            <td class="text-center"><?= (int) $fila['ausentes'] ?></td>
                            <td class="text-center"><?= (int) $fila['tardes'] ?></td>
                            <td class="text-center"><?= (int) $fila['justificados'] ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1 flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= escapar(url_asistencia_curso($id, ['vista' => 'tomar', 'fecha' => $fila['attendance_date']])) ?>">
                                        Ver lista
                                    </a>
                                    <form method="post" action="<?= escapar(url_asistencia_curso($id, ['vista' => 'reportes'])) ?>" class="d-inline" onsubmit="return confirm('¿Eliminar el listado del <?= escapar(formatear_fecha($fila['attendance_date'])) ?>? Esta acción no se puede deshacer.');">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar_asistencia">
                                        <input type="hidden" name="fecha" value="<?= escapar($fila['attendance_date']) ?>">
                                        <input type="hidden" name="volver_a" value="reportes">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-navy"><i class="bi bi-calendar3"></i></div>
            <div class="stat-value"><?= (int) $resumen['sesiones'] ?></div>
            <div class="stat-label">Sesiones</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-percent"></i></div>
            <div class="stat-value"><?= (int) $resumen['promedio'] ?>%</div>
            <div class="stat-label">Porcentaje de asistencia</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-teal"><i class="bi bi-person-check"></i></div>
            <div class="stat-value"><?= (int) $resumen['presentes'] ?></div>
            <div class="stat-label">Presentes</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-rose"><i class="bi bi-person-x"></i></div>
            <div class="stat-value"><?= (int) $resumen['ausentes'] ?></div>
            <div class="stat-label">Ausentes</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-clock-history"></i></div>
            <div class="stat-value"><?= (int) $resumen['tardes'] ?></div>
            <div class="stat-label">Tarde</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-icon icon-navy"><i class="bi bi-info-circle"></i></div>
            <div class="stat-value"><?= (int) $resumen['justificados'] ?></div>
            <div class="stat-label">Justificados</div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Tus asistencias en este curso</h2></div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$detallePropio): ?>
                        <tr><td colspan="2" class="text-center text-muted py-4">Aún no hay asistencias registradas en este curso.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($detallePropio as $fila): ?>
                        <tr>
                            <td><?= formatear_fecha($fila['attendance_date']) ?></td>
                            <td><?= insignia_asistencia($fila['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
