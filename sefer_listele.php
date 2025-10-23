<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* --- Yetki Kontrolü --- */
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Erişim reddedildi: Bu sayfaya yalnızca admin kullanıcılar erişebilir.');
}

/* --- Yardımcı Fonksiyonlar --- */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function tl_format($v): string {
    $v = (float)$v;
    if ($v >= 1000) $v /= 100; // kuruşu TL’ye çevir
    return number_format($v, 2, ',', '.') . ' ₺';
}
function dt_tr(string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('d.m.Y H:i', $ts) : $iso;
}

/* --- Sorgu --- */
try {
    $stmt = $pdo->query("
        SELECT 
            t.id,
            bc.name AS company_name,
            t.departure_city,
            t.destination_city,
            t.departure_time,
            t.arrival_time,
            t.price,
            t.capacity
        FROM Trips t
        LEFT JOIN Bus_Company bc ON t.company_id = bc.id
        WHERE t.capacity > 0
        ORDER BY t.departure_time ASC
    ");
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $trips = [];
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin | Tüm Aktif Seferler</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --turkuaz:#00BCD4;
  --siyah:#0B0F10;
  --koyu:#0F1416;
  --cerceve:#16353b;
  --metin:#E6F7FA;
}
body {
  background: var(--siyah);
  color: var(--metin);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
.navbar {
  background: var(--koyu);
  border-bottom: 1px solid var(--cerceve);
}
.navbar-brand {
  color: var(--turkuaz)!important;
  font-weight: 700;
}
.table-dark th, .table-dark td {
  color: var(--metin);
  vertical-align: middle;
}
.card-dark {
  background: var(--koyu);
  border: 1px solid var(--cerceve);
  border-radius: .75rem;
}
footer {
  border-top: 1px solid var(--cerceve);
  background: var(--koyu);
  text-align: center;
  padding: 1rem 0;
  margin-top: auto;
  color: var(--metin);
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php">BUBilet</a>
    <div class="ms-auto">
      <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">Ana Sayfa</a>
      <a href="logout.php" class="btn btn-danger btn-sm">Çıkış Yap</a>
    </div>
  </div>
</nav>

<main class="container py-4 flex-grow-1">
  <div class="text-center mb-4">
    <h1 class="h4 fw-bold">🚌 Tüm Aktif Seferler</h1>
    <p class="text-secondary mb-0">Sistemde yer alan tüm aktif (kapasitesi > 0) seferler listeleniyor.</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger">Veritabanı hatası: <?=h($error)?></div>
  <?php endif; ?>

  <div class="card card-dark">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Firma</th>
              <th>Kalkış</th>
              <th>Varış</th>
              <th>Kalkış Zamanı</th>
              <th>Varış Zamanı</th>
              <th>Fiyat</th>
              <th>Kapasite</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$trips): ?>
              <tr>
                <td colspan="8" class="text-center text-secondary py-4">Aktif sefer bulunamadı.</td>
              </tr>
            <?php else: foreach ($trips as $t): ?>
              <tr>
                <td><?=h($t['id'])?></td>
                <td><?=h($t['company_name'] ?? '-')?></td>
                <td><?=h($t['departure_city'])?></td>
                <td><?=h($t['destination_city'])?></td>
                <td><?=h(dt_tr($t['departure_time']))?></td>
                <td><?=h(dt_tr($t['arrival_time']))?></td>
                <td><?=h(tl_format((float)$t['price']))?></td>
                <td><?=h($t['capacity'])?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<footer>© <?=date('Y')?> BUBilet — Tüm hakları saklıdır.</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
