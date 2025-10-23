<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* --- Yetki Kontrolü --- */
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'firma_admin') {
    http_response_code(403);
    exit('Erişim reddedildi: Bu sayfa yalnızca Firma Admin kullanıcılarına özeldir.');
}

/* --- Yardımcılar --- */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function formatTL(float $v): string {
    if ($v >= 1000) $v /= 100; // kuruş → TL
    return number_format($v, 2, ',', '.') . ' ₺';
}
function dt_tr(string $iso): string {
    $ts = strtotime($iso);
    return $ts ? date('d.m.Y H:i', $ts) : $iso;
}

/* --- Firma bilgisi --- */
$stmt = $pdo->prepare('SELECT company_id FROM User WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$companyId = (int)($stmt->fetchColumn() ?? 0);
if ($companyId <= 0) exit('Bu kullanıcıya atanmış bir firma yok.');

/* --- AJAX iptal isteği --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    header('Content-Type: application/json; charset=utf-8');
    $ticketId = (int)($_POST['ticket_id'] ?? 0);

    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare("
            SELECT t.id, t.user_id, t.total_price, t.status, tr.company_id
            FROM Tickets t
            JOIN Trips tr ON tr.id = t.trip_id
            WHERE t.id = ? AND tr.company_id = ?
        ");
        $check->execute([$ticketId, $companyId]);
        $ticket = $check->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) throw new RuntimeException('Bu bilete erişim izniniz yok.');
        if ($ticket['status'] !== 'active') throw new RuntimeException('Bu bilet zaten iptal edilmiş.');

        // İptal işlemleri
        $pdo->prepare("UPDATE Tickets SET status='canceled' WHERE id=?")->execute([$ticketId]);
        $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id=?")->execute([$ticket['total_price'], $ticket['user_id']]);
        $pdo->prepare("
            UPDATE Trips SET capacity = capacity + 1 
            WHERE id = (SELECT trip_id FROM Tickets WHERE id=?)
        ")->execute([$ticketId]);

        $pdo->commit();
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

/* --- Aktif biletleri çek --- */
$stmt = $pdo->prepare("
    SELECT 
        tk.id AS ticket_id,
        u.full_name AS user_name,
        tr.departure_city,
        tr.destination_city,
        tr.departure_time,
        tk.total_price
    FROM Tickets tk
    JOIN Trips tr ON tk.trip_id = tr.id
    JOIN User u ON tk.user_id = u.id
    WHERE tr.company_id = ? AND tk.status = 'active'
    ORDER BY tk.created_at DESC
");
$stmt->execute([$companyId]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Firma Bilet Yönetimi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --turkuaz:#00BCD4;
  --siyah:#0B0F10;
  --koyu:#0F1416;
  --cerceve:#16353b;
  --metin:#E6F7FA;
}
body { background:var(--siyah); color:var(--metin); min-height:100vh; display:flex; flex-direction:column; }
.navbar { background:var(--koyu); border-bottom:1px solid var(--cerceve); }
.navbar-brand { color:var(--turkuaz)!important; font-weight:700; }
.card-dark { background:var(--koyu); border:1px solid var(--cerceve); border-radius:.75rem; }
.table-dark th,.table-dark td { color:var(--metin); vertical-align:middle; }
.btn-turkuaz { background:var(--turkuaz); border:none; color:#001015; font-weight:600; }
.btn-turkuaz:hover { filter:brightness(.9); }
footer { border-top:1px solid var(--cerceve); background:var(--koyu); text-align:center; padding:1rem 0; margin-top:auto; color:var(--metin); }
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
    <h1 class="h4 fw-bold">🎫 Satılmış Aktif Biletler</h1>
    <p class="text-secondary mb-0">Yalnızca aktif biletler görüntülenir. İptal edilenler listeden otomatik kaldırılır.</p>
  </div>

  <div class="card card-dark">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0" id="ticketTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Yolcu</th>
              <th>Kalkış</th>
              <th>Varış</th>
              <th>Kalkış Saati</th>
              <th>Fiyat</th>
              <th class="text-end">İşlemler</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$tickets): ?>
              <tr><td colspan="7" class="text-center text-secondary py-4">Aktif bilet bulunamadı.</td></tr>
            <?php else: foreach ($tickets as $t): ?>
              <tr id="row-<?=h($t['ticket_id'])?>">
                <td><?=h($t['ticket_id'])?></td>
                <td><?=h($t['user_name'])?></td>
                <td><?=h($t['departure_city'])?></td>
                <td><?=h($t['destination_city'])?></td>
                <td><?=h(dt_tr($t['departure_time']))?></td>
                <td><?=h(formatTL((float)$t['total_price']))?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-danger" onclick="cancelTicket(<?=h($t['ticket_id'])?>, this)">İptal Et</button>
                </td>
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
<script>
async function cancelTicket(id, btn) {
  if (!confirm("Bu bileti iptal etmek istiyor musunuz?")) return;

  btn.disabled = true;
  btn.textContent = "İptal ediliyor...";

  const formData = new FormData();
  formData.append("action", "cancel");
  formData.append("ticket_id", id);

  const res = await fetch(location.href, { method: "POST", body: formData });
  const data = await res.json();

  if (data.ok) {
    const row = document.getElementById("row-" + id);
    if (row) row.remove(); // tablo satırını anında kaldır
  } else {
    alert("İptal başarısız: " + (data.msg || "Bilinmeyen hata"));
    btn.disabled = false;
    btn.textContent = "İptal Et";
  }
}
</script>
</body>
</html>
