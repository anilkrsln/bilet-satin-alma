<?php

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();

error_reporting(E_ALL);
ini_set('display_errors', '1');

function isAdmin(): bool {
    return !empty($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'admin');
}
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
if (!isAdmin()) { http_response_code(403); exit('Bu sayfaya yalnızca admin erişebilir.'); }

$pdo->exec('CREATE TABLE IF NOT EXISTS "Coupons" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  discount REAL NOT NULL,
  usage_limit INTEGER NOT NULL,
  expire_date TEXT NOT NULL,
  created_at TEXT DEFAULT (datetime(\'now\'))
)');

$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($action === 'create') {
            $code = trim((string)($_POST['code'] ?? ''));
            $discount = (float)($_POST['discount'] ?? 0);
            $usage_limit = (int)($_POST['usage_limit'] ?? 0);
            $expire_raw = trim((string)($_POST['expire_date'] ?? '')); // HTML datetime-local: YYYY-MM-DDTHH:MM
            $expire_date = $expire_raw !== '' ? str_replace('T', ' ', $expire_raw) . ':00' : '';

            if ($code === '' || !preg_match('/^[A-Za-z0-9_-]{3,50}$/', $code)) $errors[] = 'Kupon kodu: 3-50 karakter, harf/rakam/_/-.';
            if (!($discount > 0)) $errors[] = 'İndirim (₺) pozitif olmalı.';
            if ($usage_limit < 1) $errors[] = 'Kullanım limiti 1 veya daha büyük olmalı.';
            if ($expire_date === '') $errors[] = 'Geçerlilik bitiş tarihi zorunlu.';

            if (!$errors) {
                $stmt = $pdo->prepare('INSERT INTO Coupons (code, discount, usage_limit, expire_date) VALUES (?, ?, ?, ?)');
                $stmt->execute([$code, $discount, $usage_limit, $expire_date]);
                $ok = true;
            }
        }

        if ($action === 'delete') {
            $code = trim((string)($_POST['code'] ?? ''));
            if ($code === '') { $errors[] = 'Geçersiz kupon kodu.'; }
            if (!$errors) {
                $stmt = $pdo->prepare('DELETE FROM Coupons WHERE code = ?');
                $stmt->execute([$code]);
                $ok = true;
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'İşlem hatası: ' . $e->getMessage();
    }
}

// Liste
$list = $pdo->query('
    SELECT id, code, discount, usage_limit, expire_date, created_at
    FROM Coupons
    ORDER BY datetime(created_at) DESC
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kupon Yönetimi (Admin) - BUBilet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --turkuaz:#00BCD4; --siyah:#0B0F10; --beyaz:#FFFFFF; --metin:#E6F7FA;
  --panel:#0F1416; --border:#16353b; --focus: rgba(0,188,212,.25);
}
html,body{background:var(--siyah);color:var(--metin);}
.navbar,footer,.card{background:var(--panel);border-color:var(--border)!important;}
.navbar-brand{color:var(--turkuaz)!important;font-weight:700;}
.container-tight{max-width:1100px;margin:auto;}
.section-title{font-weight:700;letter-spacing:.2px;}
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
.table{--bs-table-color:var(--metin);--bs-table-bg:transparent;--bs-table-border-color:var(--border);}
.table thead th{color:var(--beyaz);}
footer{border-top:1px solid var(--border);}
a{color:var(--turkuaz);text-decoration:none;}
a:hover{text-decoration:underline;}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/bubilet/dashboard.php">BUBilet</a>
    <div class="ms-auto">
      <a class="btn btn-outline-light btn-sm" href="/bubilet/dashboard.php">🏠 Ana Sayfa</a>
    </div>
  </div>
</nav>

<main class="container container-tight py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="section-title h4 m-0">🎟️ Kupon Yönetimi (Admin)</h1>
  </div>

  <?php if ($ok): ?>
    <div class="alert alert-success">✓ İşlem başarıyla tamamlandı.</div>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <strong>❌ Hata:</strong>
      <ul class="mb-0 mt-2">
        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- Yeni Kupon -->
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="card-title m-0">➕ Yeni Kupon Oluştur</h5>
    </div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create">
        <div class="col-12 col-md-6">
          <label for="code" class="form-label">Kupon Kodu</label>
          <input id="code" name="code" class="form-control" placeholder="Örn: ILKALIS50" required>
          <div class="form-text">Harf/rakam/_/-, 3–50 karakter</div>
        </div>
        <div class="col-12 col-md-6">
          <label for="discount" class="form-label">İndirim (₺)</label>
          <input id="discount" name="discount" type="number" step="0.01" min="0.01" class="form-control" required>
        </div>
        <div class="col-12 col-md-6">
          <label for="usage_limit" class="form-label">Kullanım Limiti</label>
          <input id="usage_limit" name="usage_limit" type="number" min="1" class="form-control" required>
        </div>
        <div class="col-12 col-md-6">
          <label for="expire_date" class="form-label">Bitiş Tarihi</label>
          <input id="expire_date" name="expire_date" type="datetime-local" class="form-control" required>
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary" type="submit">Kaydet</button>
          <button class="btn btn-outline-light" type="reset">Temizle</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Kupon Listesi -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title m-0">📋 Kupon Listesi</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($list)): ?>
        <p class="text-secondary p-3 m-0">Henüz kupon yok.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Kod</th>
                <th>İndirim (₺)</th>
                <th>Kullanım Limiti</th>
                <th>Bitiş</th>
                <th>Oluşturma</th>
                <th style="width:140px">İşlemler</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($list as $r): ?>
              <tr>
                <td><strong><?= h($r['code']) ?></strong></td>
                <td><?= number_format((float)$r['discount'], 2, ',', '.') ?></td>
                <td><?= (int)$r['usage_limit'] ?></td>
                <td><?= h($r['expire_date']) ?></td>
                <td><?= h($r['created_at']) ?></td>
                <td>
                  <form method="post" class="d-inline" onsubmit="return confirm('Kupon silinsin mi?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="code" value="<?= h($r['code']) ?>">
                    <button class="btn btn-outline-light btn-sm" type="submit">Sil</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <p class="text-secondary small p-3 mb-0">
        * Bu şemada indirim <strong>₺ sabit tutar</strong> olarak uygulanır (yüzdelik alan yoktur).<br>
        * Satın almada indirimi uygularken: <code>final = max(0, price - discount)</code>.
      </p>
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
