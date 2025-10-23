<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /bubilet/auth/login.php");
    exit();
}

require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();

$message = null;
$errors  = [];

// --- Firmaları al (dropdown için)
$companies = [];
try {
    $stmt = $pdo->query('SELECT id, name FROM "Bus_Company" ORDER BY name');
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errors[] = 'Firma listesi alınamadı: '.$e->getMessage();
}

// --- POST: Seçilenleri firma_admin yap + company_id ata
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_ids'])) {
    $userIds = array_map('intval', (array)$_POST['user_ids']);
    $userIds = array_values(array_filter($userIds, fn($v) => $v > 0));

    $company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    if ($company_id <= 0) {
        $errors[] = 'Lütfen bir firma seçin.';
    } else {
        // Firma var mı?
        $chk = $pdo->prepare('SELECT COUNT(1) FROM "Bus_Company" WHERE id = ?');
        $chk->execute([$company_id]);
        if (!$chk->fetchColumn()) {
            $errors[] = 'Seçilen firma bulunamadı.';
        }
    }

    if (!$errors && !empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "UPDATE user SET role = 'firma_admin', company_id = ? WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$company_id], $userIds));
        $message = count($userIds) . " kullanıcı firma admin yetkisine yükseltildi.";
    } elseif (!$errors) {
        $errors[] = 'Lütfen en az bir kullanıcı seçin.';
    }

    // (Mevcut kodunuzdaki ikinci UPDATE bloğu korunuyor — mantığa dokunmadım)
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = 'UPDATE "User"
                   SET role = :role, company_id = :cid
                 WHERE id IN ('.$placeholders.')
                   AND role <> "admin"';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':role', 'firma_admin');
        $stmt->bindValue(':cid',  $company_id, PDO::PARAM_INT);
        foreach ($userIds as $i => $uid) {
            $stmt->bindValue($i + 1, $uid, PDO::PARAM_INT);
        }
        $stmt->execute();
        $message = $stmt->rowCount().' kullanıcı firma admin yapıldı ve firmaya bağlandı (ID='.$company_id.').';
    }
}

// --- Kullanıcıları getir
$stmt = $pdo->query('SELECT id, full_name, email, role, company_id, created_at FROM "User" ORDER BY created_at DESC');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kullanıcı Yönetim Paneli - BUBilet</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --turkuaz:#00BCD4; --siyah:#0B0F10; --beyaz:#FFFFFF; --metin:#E6F7FA;
  --panel:#0F1416; --border:#16353b; --focus: rgba(0,188,212,.25);
}
html,body{background:var(--siyah);color:var(--metin);}
.navbar,footer,.card{background:var(--panel);border-color:var(--border)!important;}
.navbar-brand{color:var(--turkuaz)!important;font-weight:700;}
.container-tight{max-width:1200px;margin:auto;}
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
.badge-soft{background:rgba(0,188,212,.15);color:var(--turkuaz);border:1px solid rgba(0,188,212,.25);border-radius:1rem;padding:.25rem .5rem;font-size:.75rem;}
.badge-company{font-size:12px;color:#cfeaed;background:#0b0f10;border-radius:10px;padding:3px 8px;border:1px solid var(--border);}
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
    <h1 class="section-title h4 m-0">👥 Kullanıcı Yönetim Paneli</h1>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-success mb-3">✓ <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger mb-3">
      <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">
      <h5 class="card-title m-0">Seçim ve İşlem</h5>
    </div>
    <div class="card-body">
      <form method="POST" id="userForm" class="vstack gap-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <?php if (count($users) > 0): ?>
          <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="selectAll">
              <label class="form-check-label" for="selectAll">Tümünü Seç</label>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">
              <select name="company_id" class="form-select" style="min-width:260px" required>
                <option value="">— Firmayı Seçin —</option>
                <?php foreach ($companies as $c): ?>
                  <option value="<?= (int)$c['id'] ?>">
                    <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-primary">🔐 Seçilenleri Firma Admin Yap</button>
            </div>
          </div>
        <?php else: ?>
          <p class="text-secondary m-0">Henüz kayıtlı kullanıcı bulunmamaktadır.</p>
        <?php endif; ?>
      
        <?php if (count($users) > 0): ?>
        <div class="table-responsive mt-3">
          <table class="table align-middle">
            <thead>
              <tr>
                <th style="width:56px;">Seç</th>
                <th>ID</th>
                <th>Kullanıcı Adı</th>
                <th>E-posta</th>
                <th>Yetki</th>
                <th>Firma</th>
                <th>Kayıt Tarihi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
              <tr>
                <td>
                  <input class="form-check-input user-checkbox" type="checkbox" name="user_ids[]" value="<?= (int)$user['id'] ?>">
                </td>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['full_name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                  <span class="badge-soft"><?= htmlspecialchars($user['role']) ?></span>
                </td>
                <td>
                  <?php
                    if ($user['company_id']) {
                        $s = $pdo->prepare('SELECT name FROM "Bus_Company" WHERE id = ?');
                        $s->execute([(int)$user['company_id']]);
                        $n = $s->fetchColumn();
                        echo $n ? '<span class="badge-company">'.htmlspecialchars($n, ENT_QUOTES, 'UTF-8').'</span>' : '-';
                    } else {
                        echo '-';
                    }
                  ?>
                </td>
                <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($user['created_at']))) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </form>
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
<script>
// Tümünü seç/kaldır
document.getElementById('selectAll')?.addEventListener('change', function() {
  document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
});

// Submit öncesi kontrol
document.getElementById('userForm')?.addEventListener('submit', function(e) {
  const checked = document.querySelectorAll('.user-checkbox:checked');
  const company = document.querySelector('select[name="company_id"]');
  if (checked.length === 0) { e.preventDefault(); alert('Lütfen en az bir kullanıcı seçin!'); return; }
  if (!company.value) { e.preventDefault(); alert('Lütfen bir firma seçin!'); return; }
  if (!confirm(checked.length + ' kullanıcıyı seçilen firmaya bağlayıp firma admin yapmak istediğinize emin misiniz?')) {
    e.preventDefault();
  }
});
</script>
</body>
</html>
