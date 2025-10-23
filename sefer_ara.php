<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Yardımcı Fonksiyonlar
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function currentUserId(): ?int { return $_SESSION['user_id'] ?? null; }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function getUserBalance(PDO $pdo, int $user_id): float {
    $stmt = $pdo->prepare('SELECT balance FROM user WHERE id = ?');
    $stmt->execute([$user_id]);
    return (float)($stmt->fetchColumn() ?? 0);
}

function fetchValidCoupon(PDO $pdo, string $code, int $tripCompanyId): array {
    $code = strtoupper(trim($code));
    $stmt = $pdo->prepare("
        SELECT code, discount, usage_limit, expire_date, company_id
        FROM Coupons
        WHERE code = ?
          AND (company_id IS NULL OR company_id = ?)
    ");
    $stmt->execute([$code, $tripCompanyId]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$c) return ['ok' => false, 'msg' => 'Kupon bulunamadı veya geçerli değil'];
    if ((float)$c['discount'] <= 0) return ['ok' => false, 'msg' => 'Kupon indirimi geçersiz'];

    $now = new DateTime('now');
    $exp = new DateTime($c['expire_date']);
    if ($now > $exp) return ['ok' => false, 'msg' => 'Kupon süresi dolmuş'];
    if ((int)$c['usage_limit'] < 1) return ['ok' => false, 'msg' => 'Kupon kullanım limiti doldu'];

    return ['ok' => true, 'coupon' => $c];
}

function applyCouponPrice(float $price, array $coupon): float {
    $final = max(0.0, $price - (float)$coupon['discount']);
    return round($final, 2);
}

// --- POST İşlemi: Bilet Satın Alma ---
$errors = [];
$ok = false;
$user_id = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buy') {
    if (!isLoggedIn()) {
        $errors[] = "Lütfen giriş yapın.";
    } else {
        $trip_id = (int)($_POST['trip_id'] ?? 0);
        $seat_number = (int)($_POST['seat_number'] ?? 0);
        $coupon_code = trim($_POST['coupon_code'] ?? '');

        if ($trip_id <= 0) $errors[] = 'Geçersiz sefer';
        if ($seat_number <= 0) $errors[] = 'Geçersiz koltuk numarası';

        if (!$errors) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $trip = $pdo->prepare("SELECT * FROM Trips WHERE id = ?");
            $trip->execute([$trip_id]);
            $trip_data = $trip->fetch(PDO::FETCH_ASSOC);

            if (!$trip_data) {
                $errors[] = 'Sefer bulunamadı';
            } else {
                // Dolu koltuk kontrolü
                $q = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM Booked_Seats bs
                    JOIN Tickets t ON bs.ticket_id = t.id
                    WHERE t.trip_id = ? AND bs.seat_number = ? AND t.status='active'
                ");
                $q->execute([$trip_id, $seat_number]);
                if ($q->fetchColumn() > 0) $errors[] = 'Bu koltuk zaten dolu';

                // Kupon kontrol
                $applied_coupon = null;
                $final_price = (float)$trip_data['price'];
                if ($coupon_code !== '') {
                    $cid = (int)($trip_data['company_id'] ?? 0);
                    $c = fetchValidCoupon($pdo, $coupon_code, $cid);
                    if ($c['ok']) {
                        $applied_coupon = $c['coupon'];
                        $final_price = applyCouponPrice($final_price, $applied_coupon);
                    } else $errors[] = $c['msg'];
                }

                // Bakiye kontrolü
                $balance = getUserBalance($pdo, $user_id);
                if ($balance < $final_price) $errors[] = "Yetersiz bakiye! Gerekli: " . number_format($final_price, 2) . " ₺";

                if (!$errors) {
                    try {
                        $pdo->beginTransaction();
                        $ins = $pdo->prepare("INSERT INTO Tickets (trip_id, user_id, status, total_price, created_at)
                                              VALUES (?, ?, 'active', ?, CURRENT_TIMESTAMP)");
                        $ins->execute([$trip_id, $user_id, $final_price]);
                        $ticket_id = (int)$pdo->lastInsertId();

                        $pdo->prepare("INSERT INTO Booked_Seats (ticket_id, seat_number, created_at)
                                       VALUES (?, ?, CURRENT_TIMESTAMP)")
                            ->execute([$ticket_id, $seat_number]);
                        $pdo->prepare("UPDATE user SET balance = balance - ? WHERE id = ?")
                            ->execute([$final_price, $user_id]);
                        $pdo->prepare("UPDATE Trips SET capacity = capacity - 1 WHERE id = ?")
                            ->execute([$trip_id]);
                        if ($applied_coupon) {
                            $pdo->prepare("UPDATE Coupons SET usage_limit = usage_limit - 1 WHERE code = ? AND (company_id IS NULL OR company_id = ?)")
                                ->execute([$applied_coupon['code'], $trip_data['company_id']]);
                        }
                        $pdo->commit();
                        $ok = true;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $errors[] = 'İşlem hatası: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// --- Sefer Arama ---
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$trips = [];
if ($from !== '' || $to !== '') {
    $sql = "SELECT * FROM Trips WHERE capacity > 0";
    $params = [];
    if ($from) { $sql .= " AND departure_city = ?"; $params[] = $from; }
    if ($to) { $sql .= " AND destination_city = ?"; $params[] = $to; }
    $sql .= " ORDER BY departure_time ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$departureCities = $pdo->query("SELECT DISTINCT departure_city FROM Trips")->fetchAll(PDO::FETCH_COLUMN);
$destinationCities = $pdo->query("SELECT DISTINCT destination_city FROM Trips")->fetchAll(PDO::FETCH_COLUMN);

// Koltuklar
$trip_id = (int)($_GET['trip'] ?? 0);
$selected_trip = null;
$booked_seats = [];
if ($trip_id > 0) {
    $s = $pdo->prepare("SELECT * FROM Trips WHERE id=?");
    $s->execute([$trip_id]);
    $selected_trip = $s->fetch(PDO::FETCH_ASSOC);
    if ($selected_trip) {
        $q = $pdo->prepare("
            SELECT bs.seat_number
            FROM Booked_Seats bs
            JOIN Tickets t ON bs.ticket_id=t.id
            WHERE t.trip_id=? AND t.status='active'");
        $q->execute([$trip_id]);
        $booked_seats = array_map('intval', array_column($q->fetchAll(PDO::FETCH_ASSOC), 'seat_number'));
    }
}

$user_balance = $user_id ? getUserBalance($pdo, $user_id) : 0;
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bilet Satış - BUBilet</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --turkuaz: #00BCD4;
      --siyah: #0B0F10;
      --beyaz: #FFFFFF;
      --metin: #E6F7FA;
    }
    body { background: var(--siyah); color: var(--metin); min-height: 100vh; }
    .navbar { background: #0F1416; border-bottom: 1px solid #16353b; }
    .navbar-brand { color: var(--turkuaz) !important; font-weight: 700; }
    .card { background: #0f1416; border: 1px solid #16353b; }
    .btn-primary { background: var(--turkuaz); border: none; color: #001015; font-weight: 600; }
    .btn-primary:hover { filter: brightness(.9); }
    .seat { width:48px; height:48px; border:2px solid #16353b; border-radius:6px; display:flex; justify-content:center; align-items:center; cursor:pointer; }
    .seat.booked { background:#e53935; border-color:#b71c1c; color:#fff; cursor:not-allowed; }
    .seat.selected { background:#4caf50; border-color:#388e3c; color:#fff; }
    footer { border-top:1px solid #16353b; background:#0F1416; color:var(--metin); padding:1rem; text-align:center; margin-top:2rem; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php">BUBilet</a>
    <div class="ms-auto">
      <?php if (isLoggedIn()): ?>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">Ana Sayfa</a>
        <a href="biletlerim.php" class="btn btn-outline-light btn-sm me-2">Biletlerim</a>
        <a href="logout.php" class="btn btn-danger btn-sm">Çıkış Yap</a>
      <?php else: ?>
        <a href="auth/login.php" class="btn btn-outline-light btn-sm me-2">Giriş Yap</a>
        <a href="auth/register.php" class="btn btn-primary btn-sm">Kayıt Ol</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container py-4">
  <?php if ($ok): ?>
    <div class="alert alert-success">✅ Bilet satın alındı!</div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div><?=h($e)?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card p-3">
        <h4 class="mb-3">📋 Sefer Arama</h4>
        <form method="get" class="row g-2">
          <div class="col-md-5">
            <select name="from" class="form-select">
              <option value="">Kalkış</option>
              <?php foreach ($departureCities as $c): ?>
                <option value="<?=h($c)?>" <?= $from===$c?'selected':''?>><?=h($c)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <select name="to" class="form-select">
              <option value="">Varış</option>
              <?php foreach ($destinationCities as $c): ?>
                <option value="<?=h($c)?>" <?= $to===$c?'selected':''?>><?=h($c)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2"><button class="btn btn-primary w-100">Ara</button></div>
        </form>
        <hr>
        <?php if ($from==='' && $to===''): ?>
          <div class="text-secondary">🔎 Şehir seçerek arama yapın.</div>
        <?php elseif (empty($trips)): ?>
          <div class="text-danger">❌ Uygun sefer bulunamadı.</div>
        <?php else: ?>
          <?php foreach ($trips as $trip): ?>
            <a href="?from=<?=h($from)?>&to=<?=h($to)?>&trip=<?=$trip['id']?>" class="text-decoration-none text-light">
              <div class="p-2 border-bottom">
                <strong><?=h($trip['departure_city'])?> → <?=h($trip['destination_city'])?></strong><br>
                <small>📅 <?=date('d.m.Y H:i', strtotime($trip['departure_time']))?> | 💺 Kalan: <?=$trip['capacity']?> | 💵 <?=number_format($trip['price'],2)?> ₺</small>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card p-3">
        <?php if ($selected_trip): ?>
          <h4 class="mb-3">💺 Koltuk Seçimi</h4>
          <div class="mb-2">
            <strong><?=h($selected_trip['departure_city'])?> → <?=h($selected_trip['destination_city'])?></strong><br>
            <small>📅 <?=date('d.m.Y H:i', strtotime($selected_trip['departure_time']))?> | 💵 <?=number_format($selected_trip['price'],2)?> ₺</small>
          </div>
          <form method="POST" id="seatForm">
            <input type="hidden" name="action" value="buy">
            <input type="hidden" name="trip_id" value="<?=$selected_trip['id']?>">
            <input type="hidden" name="seat_number" id="selectedSeat" value="">
            <div class="d-flex flex-wrap gap-2 mb-3">
              <?php
              for ($i=1;$i<=$selected_trip['capacity'];$i++):
                $booked=in_array($i,$booked_seats);
              ?>
                <div class="seat <?=$booked?'booked':''?>" data-seat="<?=$i?>" onclick="selectSeat(<?=$i?>,this)">
                  <?=$i?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="input-group mb-2">
              <input type="text" name="coupon_code" class="form-control" placeholder="Kupon Kodu (isteğe bağlı)">
              <button type="submit" id="buyBtn" class="btn btn-primary" disabled>🎫 Satın Al</button>
            </div>
            <div class="text-secondary small">Bakiye: <?=number_format($user_balance,2)?> ₺</div>
          </form>
        <?php else: ?>
          <div class="text-secondary">ℹ️ Bir sefer seçiniz.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<footer>© <?=date('Y')?> BUBilet — Tüm hakları saklıdır.</footer>

<script>
let selectedSeat=null;
function selectSeat(num,el){
  if(el.classList.contains('booked'))return;
  document.querySelectorAll('.seat').forEach(s=>s.classList.remove('selected'));
  el.classList.add('selected');
  selectedSeat=num;
  document.getElementById('selectedSeat').value=num;
  document.getElementById('buyBtn').disabled=false;
}
document.getElementById('seatForm')?.addEventListener('submit',e=>{
  if(!selectedSeat){ e.preventDefault(); alert('Lütfen koltuk seçin.'); }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
