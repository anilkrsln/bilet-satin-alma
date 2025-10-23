<?php
// seferler.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ---- Yardımcı Fonksiyonlar ----
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function currentRole(): string { return strtolower($_SESSION['role'] ?? ''); }

function currentCompanyId(PDO $pdo): ?int {
    if (!empty($_SESSION['company_id'])) return (int)$_SESSION['company_id'];
    if (empty($_SESSION['user_id'])) return null;

    $stmt = $pdo->prepare('SELECT company_id FROM "User" WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $cid = $stmt->fetchColumn();
    if ($cid) {
        $_SESSION['company_id'] = $cid;
        return (int)$cid;
    }
    return null;
}

function requireFirmaAdmin(): void {
    if (!isLoggedIn()) { http_response_code(401); exit('Giriş gerekli'); }
    if (currentRole() !== 'firma_admin') {
        http_response_code(403); exit('Yetkisiz: Firma admin yetkisi gerekli');
    }
}

// ---- Yetki Kontrol ----
requireFirmaAdmin();
$company_id = currentCompanyId($pdo);
if (!$company_id) {
    die('<div class="container py-4"><div class="alert alert-danger">❌ Firma bulunamadı</div></div>');
}

// ---- İşlemler ----
$errors = [];
$ok = false;

// GÜNCELLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $trip_id = (int)($_POST['trip_id'] ?? 0);

    if ($trip_id <= 0) {
        $errors[] = 'Geçersiz sefer ID';
    } else {
        $chk = $pdo->prepare("SELECT id FROM Trips WHERE id = ? AND company_id = ?");
        $chk->execute([$trip_id, $company_id]);
        if (!$chk->fetchColumn()) $errors[] = 'Bu sefere erişim yetkiniz yok';
    }

    if (!$errors) {
        $departure_date = trim($_POST['departure_date'] ?? '');
        $departure_hour = trim($_POST['departure_time'] ?? '');
        $arrival_date   = trim($_POST['arrival_date'] ?? '');
        $arrival_hour   = trim($_POST['arrival_time'] ?? '');
        $departure_city = trim($_POST['departure_city'] ?? '');
        $destination_city = trim($_POST['destination_city'] ?? '');
        $price_input    = trim($_POST['price'] ?? '');
        $capacity_input = trim($_POST['capacity'] ?? '');

        if ($departure_city === '') $errors[] = 'Kalkış şehri seçiniz';
        if ($destination_city === '') $errors[] = 'Varış şehri seçiniz';
        if ($departure_date === '') $errors[] = 'Kalkış tarihi seçiniz';
        if ($departure_hour === '') $errors[] = 'Kalkış saati seçiniz';
        if ($arrival_date === '') $errors[] = 'Varış tarihi seçiniz';
        if ($arrival_hour === '') $errors[] = 'Varış saati seçiniz';

        if ($price_input === '' || !is_numeric(str_replace(',', '.', $price_input))) {
            $errors[] = 'Fiyat geçersiz';
        } else {
            $price = (float) str_replace(',', '.', $price_input);
            if ($price <= 0) $errors[] = "Fiyat 0'dan büyük olmalı";
        }

        if ($capacity_input === '' || !ctype_digit($capacity_input) || (int)$capacity_input < 1) {
            $errors[] = 'Kapasite geçersiz';
        } else {
            $capacity = (int)$capacity_input;
        }

        if (!$errors) {
            try {
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $departure_time = $departure_date . ' ' . $departure_hour;
                $arrival_time   = $arrival_date   . ' ' . $arrival_hour;

                $sql = "UPDATE Trips SET 
                            departure_city = :departure_city,
                            destination_city = :destination_city,
                            departure_time = :departure_time,
                            arrival_time = :arrival_time,
                            price = :price,
                            capacity = :capacity
                        WHERE id = :id AND company_id = :company_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':departure_city' => $departure_city,
                    ':destination_city' => $destination_city,
                    ':departure_time' => $departure_time,
                    ':arrival_time' => $arrival_time,
                    ':price' => $price,
                    ':capacity' => $capacity,
                    ':id' => $trip_id,
                    ':company_id' => $company_id,
                ]);
                $ok = true;
            } catch (Throwable $e) {
                $errors[] = 'DB hatası: ' . $e->getMessage();
            }
        }
    }
}

// SİLME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $trip_id = (int)($_POST['trip_id'] ?? 0);
    if ($trip_id <= 0) {
        $errors[] = 'Geçersiz sefer ID';
    } else {
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("DELETE FROM Trips WHERE id = ? AND company_id = ?");
            $stmt->execute([$trip_id, $company_id]);
            $ok = true;
        } catch (Throwable $e) {
            $errors[] = 'Silme hatası: ' . $e->getMessage();
        }
    }
}

// SEFERLERİ GETİR
$stmt = $pdo->prepare("SELECT * FROM Trips WHERE company_id = ? ORDER BY departure_time DESC");
$stmt->execute([$company_id]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Düzenleme modunda seçili sefer
$edit_trip = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($trips as $trip) {
        if ((int)$trip['id'] === $edit_id) { $edit_trip = $trip; break; }
    }
}

// Şehir listesi
$cities = [ "Adana","Adıyaman","Afyonkarahisar","Ağrı","Aksaray","Amasya","Ankara","Antalya","Ardahan",
  "Artvin","Aydın","Balıkesir","Bartın","Batman","Bayburt","Bilecik","Bingöl","Bitlis",
  "Bolu","Burdur","Bursa","Çanakkale","Çankırı","Çorum","Denizli","Diyarbakır","Düzce",
  "Edirne","Elazığ","Erzincan","Erzurum","Eskişehir","Gaziantep","Giresun","Gümüşhane",
  "Hakkari","Hatay","Iğdır","Isparta","İstanbul","İzmir","Kahramanmaraş","Karabük",
  "Karaman","Kars","Kastamonu","Kayseri","Kilis","Kırıkkale","Kırklareli","Kırşehir",
  "Kocaeli","Konya","Kütahya","Malatya","Manisa","Mardin","Mersin","Muğla","Muş",
  "Nevşehir","Niğde","Ordu","Osmaniye","Rize","Sakarya","Samsun","Siirt","Sinop",
  "Sivas","Şanlıurfa","Şırnak","Tekirdağ","Tokat","Trabzon","Tunceli","Uşak",
  "Van","Yalova","Yozgat","Zonguldak"];
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Seferler - Yönet</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --turkuaz:#00BCD4;
      --siyah:#0B0F10;
      --koyu:#0F1416;
      --cerceve:#16353b;
      --metin:#E6F7FA;
    }
    body{
      background: var(--siyah);
      color: var(--metin);
      min-height:100vh;
      display:flex; flex-direction:column;
    }
    .navbar{
      background: var(--koyu);
      border-bottom:1px solid var(--cerceve);
    }
    .navbar-brand{ color:var(--turkuaz)!important; font-weight:700; }
    .page-wrap{ flex:1 1 auto; }
    .card-dark{
      background: var(--koyu);
      border:1px solid var(--cerceve);
      border-radius:1rem;
      box-shadow: 0 4px 20px rgba(0,0,0,.4);
    }
    .card-header{
      border-bottom:1px solid var(--cerceve)!important;
      background:transparent;
    }
    .trip-item{
      background:#0B0F10;
      border:1px solid var(--cerceve);
      border-radius:.75rem;
      padding:1rem;
      transition:.2s;
    }
    .trip-item:hover{ transform: translateX(4px); border-color: var(--turkuaz); }
    .btn-primary{
      background:var(--turkuaz); border:none; color:#001015; font-weight:600;
    }
    .btn-primary:hover{ filter: brightness(.9); }
    .form-control, .form-select{
      background:#0B0F10; border-color:var(--cerceve); color:var(--metin);
    }
    .form-control:focus, .form-select:focus{
      border-color:var(--turkuaz);
      box-shadow:0 0 0 .25rem rgba(0,188,212,.25);
    }
    .btn-outline-secondary{
      border-color:var(--cerceve); color:var(--metin);
    }
    .btn-outline-secondary:hover{
      background:#11171a; border-color:var(--turkuaz); color:var(--metin);
    }
    .alert{
      border:none; border-radius:.75rem;
    }
    a{ color:var(--turkuaz); text-decoration:none; }
    a:hover{ text-decoration:underline; }
    .badge-dot{
      width:.6rem; height:.6rem; border-radius:50%; display:inline-block; margin-right:.4rem;
      background:var(--turkuaz);
    }
  </style>
</head>
<body>
  <!-- NAV -->
  <nav class="navbar navbar-expand-lg">
    <div class="container">
      <a class="navbar-brand" href="../index.php">BUBilet</a>
      <div class="ms-auto">
        <span class="text-secondary small">Firma Admin Paneli</span>
      </div>
    </div>
  </nav>

  <main class="page-wrap">
    <div class="container py-4">
      <div class="mb-4 text-center">
        <h1 class="h4 fw-bold">🚌 Seferler — Yönetim</h1>
        <p class="text-secondary mb-0">Firmanıza ait seferleri görüntüleyin, düzenleyin ve yönetin.</p>
      </div>

      <?php if ($ok): ?>
        <div class="alert alert-success">✓ İşlem başarıyla tamamlandı</div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <strong>❌ Hata:</strong>
          <ul class="mb-0 mt-2">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <!-- SOL: Sefer Listesi -->
        <div class="col-12 col-lg-6">
          <div class="card card-dark">
            <div class="card-header">
              <h2 class="h6 mb-0">📋 Seferler <span class="text-secondary">(<?= count($trips) ?>)</span></h2>
            </div>
            <div class="card-body">
              <?php if (empty($trips)): ?>
                <div class="text-center text-secondary py-4">Henüz sefer eklenmemiştir</div>
              <?php else: ?>
                <div class="d-flex flex-column gap-3">
                  <?php foreach ($trips as $trip): ?>
                    <div class="trip-item">
                      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                          <div class="fw-semibold">
                            <span class="badge-dot"></span>
                            <?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['destination_city']) ?>
                          </div>
                          <div class="small text-secondary mt-1">📅
                            <?= date('d.m.Y H:i', strtotime($trip['departure_time'])) ?>
                          </div>
                          <div class="small text-secondary">💺 Kapasite: <?= (int)$trip['capacity'] ?></div>
                          <div class="small text-secondary">💵 Fiyat: <?= number_format((float)$trip['price'], 2) ?> ₺</div>
                        </div>
                        <div class="d-flex gap-2">
                          <a href="?edit=<?= (int)$trip['id'] ?>" class="btn btn-sm btn-primary">✏️ Düzenle</a>
                          <form method="POST" onsubmit="return confirm('Seferi silmek istediğinizden emin misiniz?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️ Sil</button>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- SAĞ: Düzenleme Formu / Bilgi -->
        <div class="col-12 col-lg-6">
          <div class="card card-dark">
            <div class="card-header">
              <h2 class="h6 mb-0"><?= $edit_trip ? '✏️ Seferi Düzenle' : 'ℹ️ Bilgi' ?></h2>
            </div>
            <div class="card-body">
              <?php if ($edit_trip): ?>
                <?php $dt = new DateTime($edit_trip['departure_time']); $at = new DateTime($edit_trip['arrival_time']); ?>
                <form method="POST" class="row g-3">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="trip_id" value="<?= (int)$edit_trip['id'] ?>">

                  <div class="col-12 col-md-6">
                    <label class="form-label">Kalkış Şehri</label>
                    <select name="departure_city" class="form-select" required>
                      <option value="">-- Seç --</option>
                      <?php foreach ($cities as $city): ?>
                        <option value="<?= htmlspecialchars($city) ?>" <?= $city === $edit_trip['departure_city'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($city) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Varış Şehri</label>
                    <select name="destination_city" class="form-select" required>
                      <option value="">-- Seç --</option>
                      <?php foreach ($cities as $city): ?>
                        <option value="<?= htmlspecialchars($city) ?>" <?= $city === $edit_trip['destination_city'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($city) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Kalkış Tarihi</label>
                    <input type="date" name="departure_date" class="form-control" value="<?= $dt->format('Y-m-d') ?>" required>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Kalkış Saati</label>
                    <select name="departure_time" class="form-select" required>
                      <?php for ($h=6; $h<=23; $h++): $time=str_pad((string)$h,2,'0',STR_PAD_LEFT).':00'; ?>
                        <option value="<?= $time ?>" <?= $time === $dt->format('H:i') ? 'selected' : '' ?>><?= $time ?></option>
                      <?php endfor; ?>
                    </select>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Varış Tarihi</label>
                    <input type="date" name="arrival_date" class="form-control" value="<?= $at->format('Y-m-d') ?>" required>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Varış Saati</label>
                    <select name="arrival_time" class="form-select" required>
                      <?php for ($h=6; $h<=23; $h++): $time=str_pad((string)$h,2,'0',STR_PAD_LEFT).':00'; ?>
                        <option value="<?= $time ?>" <?= $time === $at->format('H:i') ? 'selected' : '' ?>><?= $time ?></option>
                      <?php endfor; ?>
                    </select>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Fiyat (₺)</label>
                    <input type="number" step="0.01" name="price" class="form-control"
                           value="<?= htmlspecialchars((string)$edit_trip['price']) ?>" required>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Kapasite</label>
                    <input type="number" name="capacity" class="form-control"
                           value="<?= (int)$edit_trip['capacity'] ?>" required>
                  </div>

                  <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                    <a href="?" class="btn btn-outline-secondary">❌ İptal</a>
                  </div>
                </form>
              <?php else: ?>
                <p class="text-secondary mb-0">
                  Soldaki listeden bir sefer seçerek düzenleyebilir veya silebilirsiniz.<br><br>
                  <strong>Düzenle:</strong> Seferin detaylarını güncellemek için “✏️ Düzenle” butonuna tıklayın.<br>
                  <strong>Sil:</strong> Seferi kalıcı olarak silmek için “🗑️ Sil” butonunu kullanın.
                </p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>

  <footer class="mt-auto border-top" style="border-color:var(--cerceve)!important; background:var(--koyu);">
    <div class="container py-3 text-center text-secondary">
      © <?=date('Y')?> BUBilet — Tüm hakları saklıdır.
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
