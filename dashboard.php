<?php
session_start();
require_once __DIR__ . '/Database/connectdb.php';

// Giriş yapmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

// Kullanıcı bilgilerini veritabanından al
$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM user WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: /auth/login.php");
    exit();
}

// Rol kontrol fonksiyonları (mantığa dokunulmadı)
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function isCompanyAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'firma_admin';
}
function isUser() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - BUBilet</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --turkuaz:#00BCD4; --siyah:#0B0F10; --beyaz:#FFFFFF; --metin:#E6F7FA;
      --panel:#0F1416; --border:#16353b; --focus: rgba(0,188,212,.25);
    }
    html, body { background: var(--siyah); color: var(--metin); }
    .navbar, footer, .card { background: var(--panel); border-color: var(--border) !important; }
    .navbar-brand { color: var(--turkuaz) !important; font-weight: 700; }
    .section-title { font-weight: 700; letter-spacing: .2px; }
    .card { border: 1px solid var(--border); box-shadow: 0 6px 24px rgba(0,0,0,.25); border-radius: 1rem; }
    .card-header { border-bottom-color: var(--border) !important; }
    .btn-primary { background: var(--turkuaz); border: none; color: #001015; font-weight: 600; }
    .btn-primary:hover { filter: brightness(.9); }
    .btn-outline-light { border-color: var(--border); color: var(--metin); }
    .btn-outline-light:hover { background: #102126; }
    .btn { border-radius: .8rem; }
    .form-control { background: var(--siyah); color: var(--metin); border-color: var(--border); }
    .form-control:focus { border-color: var(--turkuaz); box-shadow: 0 0 0 .25rem var(--focus); }
    .badge-soft { background: rgba(0,188,212,.15); color: var(--turkuaz); border: 1px solid rgba(0,188,212,.25); }
    .grid-gap > [class^="col-"] { margin-bottom: 1rem; }
    footer { border-top: 1px solid var(--border); }
    a { color: var(--turkuaz); text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg border-bottom">
  <div class="container">
    <a class="navbar-brand" href="../index.php">BUBilet</a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <span class="small text-secondary">
        <strong><?= htmlspecialchars($user['role']) ?></strong> — Hoş geldin, <?= htmlspecialchars($user['full_name']) ?>
      </span>
      <a class="btn btn-outline-light btn-sm" href="/auth/logout.php">Çıkış Yap</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="section-title h4 m-0">Dashboard</h1>
  </div>

  <div class="row grid-gap">
    <!-- PROFİL BİLGİLERİ -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="card-title m-0">Profil Bilgileri</h5>
        </div>
        <div class="card-body">
          <div class="mb-2"><strong>Ad Soyad:</strong><br><?= htmlspecialchars($user['full_name']) ?></div>
          <div class="mb-2"><strong>Email:</strong><br><?= htmlspecialchars($user['email']) ?></div>
          <div class="mb-2">
            <strong>Rol:</strong><br>
            <span class="badge badge-soft">
              <?= htmlspecialchars($user['role']) ?>
            </span>
          </div>
          <div class="mb-2"><strong>Bakiye:</strong><br><?= number_format((float)$user['balance'], 2, ',', '.') ?> TL</div>
          <div class="mb-0"><strong>Üyelik Tarihi:</strong><br><?= date('d.m.Y', strtotime($user['created_at'])) ?></div>
        </div>
      </div>
    </div>

    <!-- USER (YOLCU) PANELİ -->
    <?php if (isUser()): ?>
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title m-0">Yolcu İşlemleri</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <a href="sefer_ara.php" class="btn btn-primary w-100 py-3">
                <div class="fw-semibold">🛒 Yeni Bilet Al</div>
                <small class="text-dark-emphasis">Sefer ara ve bilet satın al</small>
              </a>
            </div>
            <div class="col-md-6">
              <a href="biletlerim.php" class="btn btn-outline-light w-100 py-3">
                <div class="fw-semibold">🎫 Biletlerim</div>
                <small>Geçmiş ve aktif biletler</small>
              </a>
            </div>
            <div class="col-md-6">
              <a href="profil_duzenle.php" class="btn btn-outline-light w-100 py-3">
                <div class="fw-semibold">👤 Profili Düzenle</div>
                <small>Bilgilerimi güncelle</small>
              </a>
            </div>
            <div class="col-md-6">
              <a href="bakiye_yukle.php" class="btn btn-outline-light w-100 py-3">
                <div class="fw-semibold">💳 Bakiye Yükle</div>
                <small>Hesabıma kredi ekle</small>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- SON BİLETLER -->
      <div class="card mt-3">
        <div class="card-header">
          <h5 class="card-title m-0">Son Biletlerim</h5>
        </div>
        <div class="card-body">
          <p class="text-secondary m-0">Henüz bilet almadınız.</p>
          <!-- Buraya kullanıcının son biletleri gelecek -->
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- FIRMA ADMIN PANELİ -->
    <?php if (isCompanyAdmin()): ?>
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title m-0">Firma Yönetimi</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <a href="seferekle.php" class="btn btn-primary w-100 py-3">
                <div class="fw-semibold">🚌 Yeni Sefer Ekle</div>
                <small class="text-dark-emphasis">Yolculuk oluştur</small>
              </a>
            </div>
            <div class="col-md-6">
              <a href="seferlerim.php" class="btn btn-outline-light w-100 py-3">
                <div class="fw-semibold">📋 Seferlerim</div>
                <small>Seferleri yönet</small>
              </a>
            </div>
            <div class="col-md-6">
              <a href="firma_kupon.php" class="btn btn-outline-light w-100 py-3">
                <div class="fw-semibold">📊 Kuponlar</div>
                <small>Kuponları Yönet</small>
              </a>
            </div>
            <div class="col-md-6">
              <a href="rezervasyonlar.php" class="btn btn-outline-light w-100 py-3">
                <div class="fw-semibold">🎟️ Rezervasyonlar</div>
                <small>Biletleri görüntüle</small>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- FIRMA BİLGİLERİ -->
      <div class="card mt-3">
        <div class="card-header">
          <h5 class="card-title m-0">Firma Bilgileri</h5>
        </div>
        <div class="card-body">
          <p class="text-secondary m-0">Firma bilgileriniz burada görünecek.</p>
          <!-- Buraya firma bilgileri gelecek -->
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ADMIN PANELİ -->
    <?php if (isAdmin()): ?>
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title m-0">Sistem Yönetimi</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <a href="/firmaekle.php" class="btn btn-primary w-100 py-3">
                <div class="fw-semibold">🏢 Firma Yönetimi</div>
                <small class="text-dark-emphasis">Firma ekle/düzenle</small>
              </a>
            </div>
            <div class="col-md-4">
              <a href="kullanici_yonetimi.php" class="btn btn-outline-light w-100 py-3">
                <div class="fw-semibold">👥 Kullanıcı Yönetimi</div>
                <small>Kullanıcıları yönet</small>
              </a>
            </div>
            <div class="col-md-4">
              <a href="admin_kupon_yonetimi.php" class="btn btn-outline-light w-100 py-3">
                <div class="fw-semibold">🎁 Kupon Yönetimi</div>
                <small>İndirim kuponları</small>
              </a>
            </div>
            <div class="col-md-6">
              <a href="sefer_listele.php" class="btn btn-outline-light w-100 py-3">
                <div class="fw-semibold">📈 Aktif Seferler</div>
                <small>Seferleri Listele</small>
              </a>
            </div>
          
          </div>
        </div>
      </div>

      <!-- SİSTEM ÖZET -->
      <div class="card mt-3">
        <div class="card-header">
          <h5 class="card-title m-0">Sistem Özeti</h5>
        </div>
        <div class="card-body">
          <div class="row text-center g-3">
            <div class="col-md-3">
              <h4 class="mb-1">0</h4>
              <small class="text-secondary">Toplam Kullanıcı</small>
            </div>
            <div class="col-md-3">
              <h4 class="mb-1">0</h4>
              <small class="text-secondary">Toplam Firma</small>
            </div>
            <div class="col-md-3">
              <h4 class="mb-1">0</h4>
              <small class="text-secondary">Aktif Sefer</small>
            </div>
            <div class="col-md-3">
              <h4 class="mb-1">0</h4>
              <small class="text-secondary">Toplam Bilet</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
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
