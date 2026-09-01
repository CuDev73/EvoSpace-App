<?php
if (defined('CURSO_PICKER_CARGADO')) {
    return;
}
define('CURSO_PICKER_CARGADO', 1);

if (!isset($pdo)) {
    return;
}

$cursosPickerData = $pdo->query("
    SELECT c.id_curso, c.nombre, c.tipo, c.orden, c.cupo_maximo,
           (SELECT COUNT(*) FROM alumnos a WHERE a.id_curso = c.id_curso AND a.activo = 1) AS inscriptos
    FROM cursos c
    WHERE c.activo = 1
    ORDER BY c.tipo, c.orden
")->fetchAll(PDO::FETCH_ASSOC);

$cursosPickerHorarios = [];
$stmtHor = $pdo->query("
    SELECT h.id_curso, h.dia_semana, h.hora_inicio, h.hora_fin, u.usuario AS profe_nombre
    FROM horarios h
    LEFT JOIN profesores p ON h.id_profesor = p.id_profesor
    LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
    ORDER BY h.hora_inicio
");
while ($h = $stmtHor->fetch(PDO::FETCH_ASSOC)) {
    $dias = [];
    foreach (explode(',', $h['dia_semana']) as $d) {
        $mapa = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        if (isset($mapa[(int)trim($d)])) {
            $dias[] = $mapa[(int)trim($d)];
        }
    }
    $txt = implode(' ', $dias) . ' ' . substr($h['hora_inicio'], 0, 5) . '-' . substr($h['hora_fin'], 0, 5);
    if ($h['profe_nombre']) {
        $txt .= ' · ' . $h['profe_nombre'];
    }
    $cursosPickerHorarios[$h['id_curso']][] = $txt;
}
?>

<!-- Modal reutilizable: Seleccionar curso -->
<div class="modal fade" id="modalCursoPicker" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-evo text-white">
                <h5 class="modal-title"><i class="bi bi-book me-2"></i>Seleccionar curso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="cursoPickerBusqueda" class="form-control" placeholder="Buscar curso por nombre o nivel...">
                </div>
                <div id="cursoPickerLista" class="curso-picker-lista"></div>
            </div>
        </div>
    </div>
</div>

<style>
.curso-picker-lista .curso-picker-item {
  border-radius: 0.5rem !important;
  transition: border-color var(--evo-transition, 0.25s ease), background var(--evo-transition, 0.25s ease);
}
.curso-picker-lista .curso-picker-item:hover {
  border-color: var(--evo-primary, #c81015);
  background-color: rgba(200, 16, 21, 0.05);
}
</style>
<script>
const CURSOS_PICKER = <?= json_encode($cursosPickerData, JSON_UNESCAPED_UNICODE) ?>;
const CURSOS_PICKER_HOR = <?= json_encode($cursosPickerHorarios, JSON_UNESCAPED_UNICODE) ?>;
let _cursoPickerCtx = { hiddenId: null, labelId: null };

function cursorPickerEsc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function cursoPickerNombre(id) {
    const c = CURSOS_PICKER.find(x => x.id_curso == id);
    return c ? c.tipo + ' - ' + c.nombre : '';
}

function cursoPickerRender() {
    const q = (document.getElementById('cursoPickerBusqueda').value || '').toLowerCase().trim();
    const lista = document.getElementById('cursoPickerLista');
    const tipos = ['Acrotelas', 'Infantil', 'Superior'];
    let html = '';
    for (const t of tipos) {
        const items = CURSOS_PICKER.filter(c => c.tipo === t && (!q || (c.nombre + ' ' + c.tipo).toLowerCase().includes(q)));
        if (!items.length) continue;
        html += '<div class="d-flex align-items-center gap-2 mt-3 mb-2"><i class="bi bi-tag-fill text-danger"></i>'
            + '<strong class="text-danger text-uppercase small">' + cursorPickerEsc(t) + '</strong></div>';
        for (const c of items) {
            const lleno = c.cupo_maximo && c.inscriptos >= c.cupo_maximo;
            const hor = (CURSOS_PICKER_HOR[c.id_curso] || []);
            html += '<button type="button" class="list-group-item list-group-item-action w-100 text-start border mb-2 py-2 px-3 rounded shadow-sm curso-picker-item" onclick="cursoPickerElegir(' + c.id_curso + ')">'
                + '<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">'
                + '<strong>' + cursorPickerEsc(c.nombre) + '</strong>'
                + (c.cupo_maximo
                    ? '<span class="badge bg-' + (lleno ? 'danger' : 'success') + '">' + c.inscriptos + '/' + c.cupo_maximo + (lleno ? ' · Lleno' : '') + '</span>'
                    : '<span class="badge bg-info">' + c.inscriptos + ' insc.</span>')
                + '</div>'
                + (hor.length ? '<div class="small text-muted mt-1"><i class="bi bi-clock me-1"></i>' + hor.map(cursorPickerEsc).join(' · ') + '</div>' : '')
                + '</button>';
        }
    }
    lista.innerHTML = html || '<div class="text-center text-muted py-4">Sin resultados para la búsqueda.</div>';
}

function cursoPickerAbrir(hiddenId, labelId) {
    _cursoPickerCtx = { hiddenId: hiddenId || null, labelId: labelId || null };
    const bus = document.getElementById('cursoPickerBusqueda');
    if (bus) bus.value = '';
    cursoPickerRender();
    const modal = document.getElementById('modalCursoPicker');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
}

function cursoPickerElegir(id) {
    const c = CURSOS_PICKER.find(x => x.id_curso == id);
    if (!c) return;
    if (_cursoPickerCtx.hiddenId && document.getElementById(_cursoPickerCtx.hiddenId)) {
        document.getElementById(_cursoPickerCtx.hiddenId).value = id;
    }
    if (_cursoPickerCtx.labelId && document.getElementById(_cursoPickerCtx.labelId)) {
        document.getElementById(_cursoPickerCtx.labelId).textContent = c.tipo + ' - ' + c.nombre;
    }
    const modal = document.getElementById('modalCursoPicker');
    const inst = bootstrap.Modal.getInstance(modal);
    if (inst) inst.hide();
}

document.addEventListener('DOMContentLoaded', function() {
    const bus = document.getElementById('cursoPickerBusqueda');
    if (bus) bus.addEventListener('input', cursoPickerRender);
});
</script>