<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('analytics');

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);

if (($_GET['export'] ?? '') === 'csv') {
    $rows = $db->query("SELECT p.product_code, p.name, COUNT(pv.id) AS verifications, COALESCE(MAX(pv.status), 'No scans') AS status FROM products p LEFT JOIN product_verifications pv ON pv.product_id = p.id GROUP BY p.id, p.product_code, p.name")->fetchAll(PDO::FETCH_NUM);
    export_csv('verification-report.csv', ['Product Code', 'Product', 'Verifications', 'Status'], $rows);
}

$stats = [
    'products' => (int) $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'genuine' => (int) $db->query("SELECT COUNT(*) FROM product_verifications WHERE status = 'Genuine Product'")->fetchColumn(),
    'counterfeit' => (int) $db->query("SELECT COUNT(*) FROM product_verifications WHERE status IN ('Counterfeit Product','Invalid QR Code')")->fetchColumn(),
    'fraud' => (int) $db->query("SELECT COUNT(*) FROM fraud_logs WHERE risk_level IN ('Medium','High')")->fetchColumn(),
];
$daily = $db->query("SELECT DATE(verification_date) AS label, COUNT(*) AS total FROM product_verifications GROUP BY DATE(verification_date) ORDER BY label DESC LIMIT 14")->fetchAll(PDO::FETCH_ASSOC);
$hotspots = $db->query("SELECT COALESCE(NULLIF(city, ''), 'Unknown') AS label, COUNT(*) AS total FROM product_verifications GROUP BY COALESCE(NULLIF(city, ''), 'Unknown') ORDER BY total DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
$stages = $db->query("SELECT lifecycle_status AS label, COUNT(*) AS total FROM products GROUP BY lifecycle_status")->fetchAll(PDO::FETCH_ASSOC);
$fraud = $db->query("SELECT risk_level AS label, COUNT(*) AS total FROM fraud_logs GROUP BY risk_level")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Analytics - Anti-Counterfeit System';
$active_page = 'analytics';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h1 class="h2 mb-1">Enterprise Analytics</h1><p class="text-muted mb-0">Verification, fraud, hotspot, and supply-chain intelligence.</p></div>
            <div class="btn-group"><a class="btn btn-outline-primary" href="analytics.php?export=csv">CSV</a><button class="btn btn-outline-secondary" onclick="window.print()">PDF</button><a class="btn btn-outline-success" href="analytics.php?export=csv">Excel</a></div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="stat-card"><span>Total Products</span><strong><?php echo $stats['products']; ?></strong></div></div>
            <div class="col-md-3"><div class="stat-card"><span>Genuine Verifications</span><strong><?php echo $stats['genuine']; ?></strong></div></div>
            <div class="col-md-3"><div class="stat-card"><span>Counterfeit Attempts</span><strong><?php echo $stats['counterfeit']; ?></strong></div></div>
            <div class="col-md-3"><div class="stat-card"><span>Fraud Flags</span><strong><?php echo $stats['fraud']; ?></strong></div></div>
        </div>
        <div class="row g-4">
            <div class="col-lg-8"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Daily Verifications</h2></div><div class="app-card-body"><canvas id="dailyChart" height="120"></canvas></div></div></div>
            <div class="col-lg-4"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Fraud Risk</h2></div><div class="app-card-body"><canvas id="fraudChart" height="180"></canvas></div></div></div>
            <div class="col-lg-6"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Counterfeit Hotspots</h2></div><div class="app-card-body"><canvas id="hotspotChart" height="150"></canvas></div></div></div>
            <div class="col-lg-6"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Supply Chain Status</h2></div><div class="app-card-body"><canvas id="stageChart" height="150"></canvas></div></div></div>
        </div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartData = {
    daily: <?php echo json_encode(array_reverse($daily)); ?>,
    hotspots: <?php echo json_encode($hotspots); ?>,
    stages: <?php echo json_encode($stages); ?>,
    fraud: <?php echo json_encode($fraud); ?>
};
function labels(rows) { return rows.map(row => row.label || 'Unknown'); }
function totals(rows) { return rows.map(row => Number(row.total)); }
new Chart(document.getElementById('dailyChart'), {type:'line', data:{labels:labels(chartData.daily), datasets:[{label:'Verifications', data:totals(chartData.daily), borderColor:'#1457d9', backgroundColor:'rgba(20,87,217,.12)', fill:true}]}});
new Chart(document.getElementById('fraudChart'), {type:'pie', data:{labels:labels(chartData.fraud), datasets:[{data:totals(chartData.fraud), backgroundColor:['#12805c','#e0a800','#c2413f']}]}});
new Chart(document.getElementById('hotspotChart'), {type:'bar', data:{labels:labels(chartData.hotspots), datasets:[{label:'Scans', data:totals(chartData.hotspots), backgroundColor:'#0b2f6b'}]}});
new Chart(document.getElementById('stageChart'), {type:'bar', data:{labels:labels(chartData.stages), datasets:[{label:'Products', data:totals(chartData.stages), backgroundColor:'#12805c'}]}});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
