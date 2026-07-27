<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['id_usuario'])) {
    header('Location: /evospace/index.php');
    exit;
}
verificarPermiso('cantina');

include '../../includes/header.php';
include '../../includes/navbar.php';

// Resumen diario (hoy)
$hoy = date('Y-m-d');
$stmt = $pdo->prepare("SELECT SUM(total) as total_hoy, 
                       SUM(CASE WHEN metodo_pago = 'Efectivo' THEN total ELSE 0 END) as efectivo,
                       SUM(CASE WHEN metodo_pago = 'Transferencia' THEN total ELSE 0 END) as transferencia,
                       SUM(CASE WHEN metodo_pago = 'Fiado' THEN total ELSE 0 END) as fiado,
                       COUNT(*) as cantidad
                       FROM ventas WHERE DATE(fecha) = ?");
$stmt->execute([$hoy]);
$resumenHoy = $stmt->fetch();

// Resumen semanal (últimos 7 días)
$semana = date('Y-m-d', strtotime('-6 days'));
$stmt = $pdo->prepare("SELECT SUM(total) as total_semana, 
                       SUM(CASE WHEN metodo_pago = 'Efectivo' THEN total ELSE 0 END) as efectivo,
                       SUM(CASE WHEN metodo_pago = 'Transferencia' THEN total ELSE 0 END) as transferencia,
                       SUM(CASE WHEN metodo_pago = 'Fiado' THEN total ELSE 0 END) as fiado,
                       COUNT(*) as cantidad
                       FROM ventas WHERE fecha >= ?");
$stmt->execute([$semana . ' 00:00:00']);
$resumenSemana = $stmt->fetch();

// Total general
$totalGeneral = $pdo->query("SELECT SUM(total) as total FROM ventas")->fetch()['total'] ?? 0;
?>

<div class="container mt-3">
    <h4><i class="bi bi-cash-stack"></i> Resumen de Caja</h4>

    <!-- Tarjetas de resumen -->
    <div class="row g-3 mt-3">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">Hoy (<?= date('d/m/Y') ?>)</div>
                <div class="card-body">
                    <p><strong>Total:</strong> Gs <?= number_format($resumenHoy['total_hoy'] ?? 0, 0, ',', '.') ?></p>
                    <p><strong>Efectivo:</strong> Gs <?= number_format($resumenHoy['efectivo'] ?? 0, 0, ',', '.') ?></p>
                    <p><strong>Transferencia:</strong> Gs <?= number_format($resumenHoy['transferencia'] ?? 0, 0, ',', '.') ?></p>
                    <p><strong>Fiado:</strong> Gs <?= number_format($resumenHoy['fiado'] ?? 0, 0, ',', '.') ?></p>
                    <p><strong>Cantidad ventas:</strong> <?= $resumenHoy['cantidad'] ?? 0 ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">Última semana</div>
                <div class="card-body">
                    <p><strong>Total:</strong> Gs <?= number_format($resumenSemana['total_semana'] ?? 0, 0, ',', '.') ?></p>
                    <p><strong>Efectivo:</strong> Gs <?= number_format($resumenSemana['efectivo'] ?? 0, 0, ',', '.') ?></p>
                    <p><strong>Transferencia:</strong> Gs <?= number_format($resumenSemana['transferencia'] ?? 0, 0, ',', '.') ?></p>
                    <p><strong>Fiado:</strong> Gs <?= number_format($resumenSemana['fiado'] ?? 0, 0, ',', '.') ?></p>
                    <p><strong>Cantidad ventas:</strong> <?= $resumenSemana['cantidad'] ?? 0 ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">Total General</div>
                <div class="card-body">
                    <p><strong>Total acumulado:</strong> Gs <?= number_format($totalGeneral, 0, ',', '.') ?></p>
                    <p class="text-muted small">Desde el inicio del sistema</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico simple -->
    <div class="card shadow mt-4">
        <div class="card-header bg-danger text-white">Ventas por día (últimos 7 días)</div>
        <div class="card-body">
            <canvas id="ventasChart" height="100"></canvas>
        </div>
    </div>

    <!-- Botón Volver -->
    <div class="d-flex gap-2 mt-4 pb-3">
        <a href="/evospace/secciones/cantina.php" class="btn btn-secondary flex-fill">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Obtener datos de los últimos 7 días
    fetch('obtener_ventas_semana.php')
        .then(res => res.json())
        .then(data => {
            const ctx = document.getElementById('ventasChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Total vendido (Gs)',
                        data: data.valores,
                        backgroundColor: '#c81015',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) { return 'Gs ' + value.toLocaleString(); }
                            }
                        }
                    }
                }
            });
        })
        .catch(err => console.error('Error cargando gráfico:', err));
});
</script>

<?php include '../../includes/footer.php'; ?>