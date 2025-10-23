<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /bubilet/auth/login.php");
    exit();
}

require_once __DIR__ . '/Database/connectdb.php'; // sadece 1 kez yeterli
$pdo = getDBConnection(); // bağlantı hazır

// ---- LOGO YÜKLEME AYARLARI ----
$UPLOAD_DIR = __DIR__ . '/uploads/logos';
$PUBLIC_BASE = 'uploads/logos'; // DB'ye kaydedilecek rölatif yol
if (!is_dir($UPLOAD_DIR)) { mkdir($UPLOAD_DIR, 0777, true); }

$errors = [];
$okMsg  = null;

// ---- POST: Firma ekle ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    // 1) Firma adı kontrolü
    if ($name === '') {
        $errors[] = 'Firma adı zorunlu.';
    } elseif (mb_strlen($name) > 120) {
        $errors[] = 'Firma adı çok uzun (max 120).';
    }

    // 2) Logo zorunlu
    $logoPathForDB = null;
    if (!isset($_FILES['logo']) || ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Logo yükleyin.';
    } elseif (isset($_FILES['logo']) && ($_FILES['logo']['error'] ?? 0) === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['logo']['tmp_name'];
        $size = $_FILES['logo']['size'] ?? 0;

        // Boyut sınırı: 2 MB
        if ($size > 2 * 1024 * 1024) {
            $errors[] = 'Logo en fazla 2MB olmalı.';
        } else {
            // MIME doğrulama
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($tmp);
            $allowed = [
                'image/jpeg' => '.jpg',
                'image/png'  => '.png',
                'image/webp' => '.webp',
            ];
            if (!isset($allowed[$mime])) {
                $errors[] = 'Logo türü desteklenmiyor (sadece JPG/PNG/WebP).';
            } else {
                // Güvenli dosya adı
                $ext = $allowed[$mime];
                $safeBase = preg_replace('~[^a-z0-9]+~i', '-', pathinfo($_FILES['logo']['name'], PATHINFO_FILENAME));
                $filename = date('YmdHis') . '' . bin2hex(random_bytes(6)) . '' . $safeBase . $ext;
                $dest = $UPLOAD_DIR . '/' . $filename;

                if (!move_uploaded_file($tmp, $dest)) {
                    $errors[] = 'Logo kaydedilemedi.';
                } else {
                    // Rölatif yol DB için
                    $logoPathForDB = $PUBLIC_BASE . '/' . $filename;
                }
            }
        }
    } else {
        $errors[] = 'Logo yüklenirken bir hata oluştu.';
    }

    // 3) DB insert
    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO "Bus_Company" ("name","logo_path") VALUES (:name, :logo)');
            $stmt->execute([':name' => $name, ':logo' => $logoPathForDB]);
            $okMsg = 'Firma eklendi.';
        } catch (Throwable $e) {
            $errors[] = 'Kayıt başarısız: ' . $e->getMessage();
            if ($logoPathForDB) @unlink($UPLOAD_DIR . '/' . basename($logoPathForDB));
        }
    }
}

// ---- Listeleme ----
$companies = $pdo->query('SELECT id, name, logo_path, created_at FROM "Bus_Company" ORDER BY id DESC')
                 ->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Firma Yönetimi (Admin) - BUBilet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --turkuaz:#00BCD4; --siyah:#0B0F10; --beyaz:#FFFFFF; --metin:#E6F7FA;
  --panel:#0F1416; --border:#16353b; --focus: rgba(0,188,212,.25);
}
html,body{background:var(--siyah);color:var(--metin);}
.navbar,footer,.card{background:var(--panel);border-color:var(--border)!important;}
.navbar-brand{color:var(--turkuaz)!important;font-weight:700;}
.container-tight{max-width:980px;margin:auto;}
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
.alert.ok{background:#001f1b;color:#b2f5ea;border:1px solid #003a32;}
.alert.err{background:#2a0003;color:#ffcdd2;border:1px solid #4d0010;}
.table{--bs-table-color:var(--metin);--bs-table-bg:transparent;--bs-table-border-color:var(--border);}
.table thead th{color:var(--beyaz);}
.logo{height:40px;object-fit:contain;border-radius:.4rem;border:1px solid var(--border);background:#0b0f10;padding:.25rem;}
footer{border-top:1px solid var(--border);}
a{color:var(--turkuaz);text-decoration:none;}
a:hover{text-decoration:underline;}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/bubilet/dashboard.php">BUBilet</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a class="btn btn-outline-light btn-sm" href="/bubilet/auth/logout.php">Çıkış Yap</a>
    </div>
  </div>
</nav>

<main class="container container-tight py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="section-title h4 m-0">Firma Yönetimi (Admin)</h1>
  </div>

  <?php if ($okMsg): ?>
    <div class="alert ok mb-3"><?= htmlspecialchars($okMsg) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert err mb-3">
      <?php foreach ($errors as $er) echo '<div>'.htmlspecialchars($er).'</div>'; ?>
    </div>
  <?php endif; ?>

  <!-- Yeni Firma Ekle -->
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="card-title m-0">Yeni Firma Ekle</h5>
    </div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-12 col-md-7">
          <label for="name" class="form-label">Firma Adı</label>
          <input type="text" id="name" name="name" class="form-control" placeholder="Örn: Yavuzlar Turizm" required>
        </div>
        <div class="col-12 col-md-5">
          <label for="logo" class="form-label">Logo (JPG/PNG/WebP, max 2MB)</label>
          <input type="file" id="logo" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*" required>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Kaydet</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Firmalar -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title m-0">Firmalar</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th class="px-3">ID</th>
              <th>Logo</th>
              <th>Ad</th>
              <th>Oluşturulma</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$companies): ?>
            <tr><td colspan="4" class="px-3 py-3 text-secondary">Henüz firma yok.</td></tr>
          <?php else: foreach ($companies as $c): ?>
            <tr>
              <td class="px-3"><?= (int)$c['id'] ?></td>
              <td>
                <?php if (!empty($c['logo_path'])): ?>
                  <img class="logo" src="<?= htmlspecialchars($c['logo_path']) ?>" alt="logo">
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($c['name']) ?></td>
              <td><?= htmlspecialchars($c['created_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<footer class="py-3">
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
