<?php
// seferekle.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();

// ---- Yardımcılar ----
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function currentRole(): string { return strtolower($_SESSION['role'] ?? ''); }

function currentCompanyId(PDO $pdo): ?int {
    if (!empty($_SESSION['company_id'])) return (int)$_SESSION['company_id'];
    if (empty($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT company_id FROM user WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cid = $stmt->fetchColumn();
    if ($cid) { $_SESSION['company_id'] = $cid; return (int)$cid; }
    return null;
}

function requireCompanyRoleOrAdmin(): void {
    if (!isLoggedIn()) { http_response_code(401); exit('Giriş gerekli'); }
    $role = currentRole();
    if (!in_array($role, ['company','firma_admin','admin'], true)) {
        http_response_code(403); exit('Yetkisiz: Firma yetkisi gerekli');
    }
}

// ---- 81 İL LİSTESİ ----
$cities = [
  'Adana','Adıyaman','Afyonkarahisar','Ağrı','Amasya','Ankara','Antalya','Artvin','Aydın',
  'Balıkesir','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı','Çorum',
  'Denizli','Diyarbakır','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir',
  'Gaziantep','Giresun','Gümüşhane','Hakkari','Hatay','Isparta','Mersin',
  'İstanbul','İzmir','Kars','Kastamonu','Kayseri','Kırklareli','Kırşehir','Kocaeli',
  'Konya','Kütahya','Malatya','Manisa','Kahramanmaraş','Mardin','Muğla','Muş',
  'Nevşehir','Niğde','Ordu','Rize','Sakarya','Samsun','Siirt','Sinop','Sivas',
  'Tekirdağ','Tokat','Trabzon','Tunceli','Şanlıurfa','Uşak','Van','Yozgat','Zonguldak',
  'Aksaray','Bayburt','Karaman','Kırıkkale','Batman','Şırnak','Bartın','Ardahan','Iğdır',
  'Yalova','Karabük','Kilis','Osmaniye','Düzce'
];

// ---- Yetki kontrol ----
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isLoggedIn()) {
    echo '<pre style="background:#ffe9e9;padding:15px;border-radius:4px;font-family:monospace;">';
    echo '❌ Session hatası: Giriş yapılmamış<br><br>';
    echo 'Session içeriği:<br>';
    var_dump($_SESSION);
    echo '<br><br>$_SERVER["REQUEST_METHOD"]: ' . $_SERVER['REQUEST_METHOD'];
    echo '</pre>';
    exit;
}
try { requireCompanyRoleOrAdmin(); }
catch (Throwable $e) {
    echo '<pre style="background:#ffe9e9;padding:15px;border-radius:4px;font-family:monospace;">';
    echo '❌ Yetki Hatası:<br>' . htmlspecialchars($e->getMessage());
    echo '</pre>';
    exit;
}

// ---- İşlem ----
$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = currentRole();

    // Firma id belirleme
    if (in_array($role, ['company','firma_admin'], true)) {
        $company_id = currentCompanyId($pdo);
        if (!$company_id) $errors[] = 'Hesabınız bir firmaya bağlı değil (company_id eksik).';
    } else { // admin
        $company_id = trim($_POST['company_id'] ?? '');
        if ($company_id === '') $errors[] = 'company_id gerekli';
        if (strlen($company_id) === 0) $errors[] = 'Geçerli company_id girin';
    }

    // Alanlar
    $departure_city   = trim($_POST['departure_city'] ?? '');
    $destination_city = trim($_POST['destination_city'] ?? '');
    $departure_date   = trim($_POST['departure_date'] ?? '');
    $departure_hour   = trim($_POST['departure_time'] ?? '');
    $arrival_date     = trim($_POST['arrival_date'] ?? '');
    $arrival_hour     = trim($_POST['arrival_time'] ?? '');
    $price_input      = trim($_POST['price'] ?? '');
    $capacity_input   = trim($_POST['capacity'] ?? '');

    // Doğrulamalar
    if ($departure_city === '' || mb_strlen($departure_city) < 2) $errors[] = 'Kalkış şehri seçiniz';
    if ($destination_city === '' || mb_strlen($destination_city) < 2) $errors[] = 'Varış şehri seçiniz';
    if ($departure_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $departure_date)) $errors[] = 'Kalkış tarihi seçiniz';
    if ($departure_hour === '' || !preg_match('/^\d{2}:\d{2}$/', $departure_hour)) $errors[] = 'Kalkış saati seçiniz';
    if ($arrival_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrival_date)) $errors[] = 'Varış tarihi seçiniz';
    if ($arrival_hour === '' || !preg_match('/^\d{2}:\d{2}$/', $arrival_hour)) $errors[] = 'Varış saati seçiniz';

    // Fiyat
    if ($price_input === '' || !is_numeric(str_replace(',', '.', $price_input))) {
        $errors[] = 'Fiyat geçersiz';
    } else {
        $price = (float) str_replace(',', '.', $price_input);
        if ($price <= 0) $errors[] = 'Fiyat 0\'dan büyük olmalı';
    }

    // Kapasite
    if ($capacity_input === '' || !ctype_digit($capacity_input) || (int)$capacity_input < 1) {
        $errors[] = 'Kapasite geçersiz';
    } else {
        $capacity = (int)$capacity_input;
    }

    if (!$errors && $company_id) {
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $departure_time = $departure_date . ' ' . $departure_hour;
            $arrival_time   = $arrival_date   . ' ' . $arrival_hour;

            $sql = "INSERT INTO Trips
                        (company_id, destination_city, arrival_time, departure_time, departure_city, price, capacity, created_date)
                    VALUES
                        (:company_id, :destination_city, :arrival_time, :departure_time, :departure_city, :price, :capacity, CURRENT_TIMESTAMP)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':company_id'       => (int)$company_id,
                ':destination_city' => $destination_city,
                ':arrival_time'     => $arrival_time,
                ':departure_time'   => $departure_time,
                ':departure_city'   => $departure_city,
                ':price'            => $price,
                ':capacity'         => $capacity,
            ]);
            $ok = true;
        } catch (Throwable $e) {
            $errors[] = 'DB hatası: ' . $e->getMessage();
        }
    }
}

// Firma admini için formda göstermek üzere company_id
$cid_for_view = null;
if (in_array(currentRole(), ['company','firma_admin'], true)) {
    $cid_for_view = currentCompanyId($pdo);
}

// Saat seçenekleri
$hours = ['06:00','07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00'];
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sefer Ekle - BUBilet</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --turkuaz:#00BCD4; --siyah:#0B0F10; --beyaz:#FFFFFF; --metin:#E6F7FA;
      --panel:#0F1416; --border:#16353b; --focus: rgba(0,188,212,.25);
    }
    html,body{background:var(--siyah);color:var(--metin);}
    .navbar, footer, .card{background:var(--panel);border-color:var(--border)!important;}
    .navbar-brand{color:var(--turkuaz)!important;font-weight:700;}
    .card{border:1px solid var(--border);box-shadow:0 6px 24px rgba(0,0,0,.25);border-radius:1rem;}
    .card-header{border-bottom-color:var(--border)!important;}
    .form-control,.form-select{background:var(--siyah);border-color:var(--border);color:var(--metin);}
    .form-control:focus,.form-select:focus{border-color:var(--turkuaz);box-shadow:0 0 0 .25rem var(--focus);}
    .btn-primary{background:var(--turkuaz);border:none;color:#001015;font-weight:600;border-radius:.8rem;}
    .btn-primary:hover{filter:brightness(.9);}
    .btn-outline-light{border-color:var(--border);color:var(--metin);border-radius:.8rem;}
    .btn-outline-light:hover{background:#102126;}
    .alert{border:none;border-radius:.8rem;}
    .alert-success{background:#001f1b;color:#b2f5ea;border:1px solid #003a32;}
    .alert-danger{background:#2a0003;color:#ffcdd2;border:1px solid #4d0010;}
    footer{border-top:1px solid var(--border);}
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/dashboard.php">BUBilet</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a class="btn btn-outline-light btn-sm" href="/dashboard.php">🏠 Dashboard</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title m-0">🚌 Sefer Ekle</h5>
        </div>
        <div class="card-body">
          <?php if ($ok): ?>
            <div class="alert alert-success">✓ Sefer başarıyla eklendi.</div>
          <?php endif; ?>

          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <strong>❌ Hata:</strong>
              <ul class="mb-0 mt-2">
                <?php foreach ($errors as $e): ?>
                  <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" class="row g-3" novalidate>
            <?php if (currentRole() === 'admin'): ?>
            <div class="col-12">
              <label class="form-label">Firma ID (UUID)</label>
              <input name="company_id" class="form-control" required placeholder="UUID formatında girin" />
            </div>
            <?php else: ?>
              <input type="hidden" name="company_id" value="<?= htmlspecialchars((string)($cid_for_view ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>

            <div class="col-12 col-md-6">
              <label class="form-label">Kalkış Şehri</label>
              <select name="departure_city" class="form-select" required>
                <option value="">-- Şehir Seçin --</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                    <?= (($_POST['departure_city'] ?? '') === $c) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Varış Şehri</label>
              <select name="destination_city" class="form-select" required>
                <option value="">-- Şehir Seçin --</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                    <?= (($_POST['destination_city'] ?? '') === $c) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Kalkış Tarihi</label>
              <input name="departure_date" type="date" class="form-control" required
                     value="<?= htmlspecialchars($_POST['departure_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Kalkış Saati</label>
              <select name="departure_time" class="form-select" required>
                <option value="">-- Saat Seçin --</option>
                <?php foreach ($hours as $h): ?>
                  <option value="<?= htmlspecialchars($h, ENT_QUOTES, 'UTF-8') ?>"
                    <?= (($_POST['departure_time'] ?? '') === $h) ? 'selected' : '' ?>><?= $h ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Varış Tarihi</label>
              <input name="arrival_date" type="date" class="form-control" required
                     value="<?= htmlspecialchars($_POST['arrival_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Varış Saati</label>
              <select name="arrival_time" class="form-select" required>
                <option value="">-- Saat Seçin --</option>
                <?php foreach ($hours as $h): ?>
                  <option value="<?= htmlspecialchars($h, ENT_QUOTES, 'UTF-8') ?>"
                    <?= (($_POST['arrival_time'] ?? '') === $h) ? 'selected' : '' ?>><?= $h ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Fiyat (₺) — Örn: 199.90</label>
              <input name="price" type="number" step="0.01" min="0.01" class="form-control" required
                     value="<?= htmlspecialchars($_POST['price'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Kapasite</label>
              <input name="capacity" type="number" min="1" class="form-control" required
                     value="<?= htmlspecialchars($_POST['capacity'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-12">
              <button type="submit" class="btn btn-primary">🚌 Seferi Kaydet</button>
              <a href="/dashboard.php" class="btn btn-outline-light ms-2">Geri Dön</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<footer class="py-3 border-top">
  <div class="container d-flex justify-content-between align-items-center">
    <span class="small text-secondary">© <?=date('Y')?> BUBilet</span>
    <div class="d-flex gap-3 small">
      <a href="/bubilet/hakkimizda.php">Hakkımızda</a>
      <a href="/bubilet/iletisim.php">İletişim</a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
