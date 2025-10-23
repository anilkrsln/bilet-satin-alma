<?php
// firma_coupons.php — ÇALIŞAN SÜRÜM (turkuaz/siyah/beyaz dark tema)
declare(strict_types=1);
session_start();
require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Kullanıcı kontrol ve yetkilendirme
function currentUser(PDO $pdo): array {
    if (empty($_SESSION['user_id'])) {
        header("Location: auth/login.php");
        exit();
    }
    $stmt = $pdo->prepare('SELECT id, full_name, role, company_id FROM user WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        exit('Kullanıcı bulunamadı.');
    }
    if (!in_array($u['role'], ['firma_admin', 'admin'], true)) {
        exit('Yetkisiz erişim.');
    }
    if ($u['role'] === 'firma_admin' && empty($u['company_id'])) {
        exit('Firma ataması yapılmamış.');
    }
    return $u;
}

$user = currentUser($pdo);
$isAdmin = $user['role'] === 'admin';
$userCompanyId = $user['company_id'] ?? null;

$errors = [];
$flash = '';
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// ==========================
//  CRUD İşlemleri
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- OLUŞTUR ---
    if ($action === 'create') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $amount = (float)($_POST['amount_tl'] ?? 0);
        $limit = (int)($_POST['usage_limit'] ?? 0);
        $expire = trim($_POST['expire_date'] ?? '');
        $company_id = null;

        if ($isAdmin) {
            $company_id = !empty($_POST['company_id']) ? (int)$_POST['company_id'] : null;
        } else {
            $company_id = (int)$userCompanyId;
        }

        if (strlen($code) < 3) $errors[] = 'Kupon kodu en az 3 karakter olmalı.';
        if ($amount <= 0) $errors[] = 'İndirim tutarı pozitif olmalı.';
        if ($limit < 1) $errors[] = 'Kullanım limiti en az 1 olmalı.';
        if (!$expire) $errors[] = 'Son kullanma tarihi gerekli.';

        if (!$errors) {
            try {
                $stmt = $pdo->prepare("INSERT INTO Coupons (code, discount, usage_limit, expire_date, company_id, created_at)
                                       VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$code, $amount, $limit, $expire, $company_id]);
                $flash = "Kupon başarıyla eklendi.";
            } catch (Throwable $e) {
                $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
            }
        }
    }

    // --- GÜNCELLE ---
    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $amount = (float)($_POST['amount_tl'] ?? 0);
        $limit = (int)($_POST['usage_limit'] ?? 0);
        $expire = trim($_POST['expire_date'] ?? '');

        if ($id <= 0) $errors[] = 'Geçersiz ID.';
        if ($amount <= 0) $errors[] = 'İndirim tutarı hatalı.';
        if ($limit < 1) $errors[] = 'Limit en az 1 olmalı.';

        if (!$errors) {
            try {
                // Firma admin yalnızca kendi kuponunu güncelleyebilir
                if ($isAdmin) {
                    $stmt = $pdo->prepare("UPDATE Coupons SET code=?, discount=?, usage_limit=?, expire_date=? WHERE id=?");
                    $stmt->execute([$code, $amount, $limit, $expire, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE Coupons SET code=?, discount=?, usage_limit=?, expire_date=? WHERE id=? AND company_id=?");
                    $stmt->execute([$code, $amount, $limit, $expire, $id, $userCompanyId]);
                }
                if ($stmt->rowCount()) $flash = "Kupon güncellendi.";
                else $errors[] = "Güncelleme başarısız veya yetkisiz.";
            } catch (Throwable $e) {
                $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
            }
        }
    }

    // --- SİL ---
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) $errors[] = 'Geçersiz kupon ID.';
        if (!$errors) {
            try {
                if ($isAdmin) {
                    $stmt = $pdo->prepare("DELETE FROM Coupons WHERE id=?");
                    $stmt->execute([$id]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM Coupons WHERE id=? AND company_id=?");
                    $stmt->execute([$id, $userCompanyId]);
                }
                if ($stmt->rowCount()) $flash = "Kupon silindi.";
                else $errors[] = "Silme başarısız veya yetkisiz.";
            } catch (Throwable $e) {
                $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
            }
        }
    }
}

// ==========================
// Kupon Listeleme
// ==========================
$coupons = [];
if ($isAdmin) {
    $stmt = $pdo->query("SELECT id, code, discount AS amount_tl, usage_limit, expire_date, created_at, company_id 
                         FROM Coupons ORDER BY id DESC");
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} else {
    $stmt = $pdo->prepare("SELECT id, code, discount AS amount_tl, usage_limit, expire_date, created_at, company_id 
                           FROM Coupons WHERE company_id = ? ORDER BY id DESC");
    $stmt->execute([$userCompanyId]);
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kupon Yönetimi - BUBilet</title>
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
  background:var(--siyah);
  color:var(--metin);
  min-height:100vh;
  display:flex;
  flex-direction:column;
}
.navbar{background:var(--koyu);border-bottom:1px solid var(--cerceve);}
.navbar-brand{color:var(--turkuaz)!important;font-weight:700;}
.card-dark{
  background:var(--koyu);
  border:1px solid var(--cerceve);
  border-radius:1rem;
  box-shadow:0 4px 20px rgba(0,0,0,.4);
}
.card-header{border-bottom:1px solid var(--cerceve)!important;background:transparent;}
.form-control,.form-select{
  background:#0B0F10;border-color:var(--cerceve);color:var(--metin);
}
.form-control:focus,.form-select:focus{
  border-color:var(--turkuaz);
  box-shadow:0 0 0 .25rem rgba(0,188,212,.25);
}
.btn-primary{background:var(--turkuaz);border:none;color:#001015;font-weight:600;}
.btn-primary:hover{filter:brightness(.9);}
.btn-outline-secondary{border-color:var(--cerceve);color:var(--metin);}
.btn-outline-secondary:hover{border-color:var(--turkuaz);}
.table-dark th,.table-dark td{color:var(--metin);}
.alert{border:none;border-radius:.75rem;}
footer{margin-top:auto;border-top:1px solid var(--cerceve);background:var(--koyu);color:var(--metin);padding:1rem 0;text-align:center;}
a{color:var(--turkuaz);text-decoration:none;}a:hover{text-decoration:underline;}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="dashboard.php">BUBilet</a>
    <div class="ms-auto text-secondary small"><?= $isAdmin ? 'Admin' : 'Firma Admin' ?> Paneli</div>
  </div>
</nav>

<main class="container py-4 flex-grow-1">
  <div class="text-center mb-4">
    <h1 class="h4 fw-bold">🎟️ Kupon Yönetimi</h1>
    <p class="text-secondary mb-0">Kupon oluştur, düzenle veya sil.</p>
  </div>

  <?php if($flash): ?><div class="alert alert-success"><?=h($flash)?></div><?php endif; ?>
  <?php if($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <!-- Yeni Kupon -->
  <div class="card card-dark mb-4">
    <div class="card-header">Yeni Kupon Oluştur</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create">
        <div class="col-md-3">
          <label class="form-label">Kod</label>
          <input name="code" class="form-control" placeholder="ORNEK10" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">İndirim (₺)</label>
          <input name="amount_tl" type="number" step="0.01" min="0.01" class="form-control" placeholder="10.00" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Kullanım Limiti</label>
          <input name="usage_limit" type="number" min="1" class="form-control" value="1" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Son Kullanma</label>
          <input name="expire_date" type="datetime-local" class="form-control" required>
        </div>
        <?php if($isAdmin): ?>
        <div class="col-md-3">
          <label class="form-label">Firma ID (opsiyonel)</label>
          <input name="company_id" type="number" min="1" class="form-control" placeholder="Firma ID">
          <div class="form-text">Boş = global kupon</div>
        </div>
        <?php endif; ?>
        <div class="col-12"><button class="btn btn-primary">Oluştur</button></div>
      </form>
    </div>
  </div>

  <!-- Kupon Listesi -->
  <div class="card card-dark">
    <div class="card-header">Mevcut Kuponlar</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th><th>Kod</th><th>İndirim</th><th>Limit</th>
              <th>Son Kullanma</th><th>Oluşturulma</th>
              <?php if($isAdmin): ?><th>Firma ID</th><?php endif; ?>
              <th class="text-end">İşlemler</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$coupons): ?>
              <tr><td colspan="8" class="text-center text-secondary py-4">Henüz kupon yok.</td></tr>
            <?php else: foreach($coupons as $c): ?>
            <tr>
              <td><?=$c['id']?></td>
              <td><?=h($c['code'])?></td>
              <td><?=number_format((float)$c['amount_tl'],2,',','.')?> ₺</td>
              <td><?=$c['usage_limit']?></td>
              <td><?=h($c['expire_date'])?></td>
              <td><?=h($c['created_at']??'')?></td>
              <?php if($isAdmin): ?><td><?=h((string)($c['company_id']??''))?></td><?php endif; ?>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#edit-<?=$c['id']?>">Düzenle</button>
                <form method="post" class="d-inline" onsubmit="return confirm('Silinsin mi?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?=$c['id']?>">
                  <button class="btn btn-sm btn-outline-danger">Sil</button>
                </form>
              </td>
            </tr>
            <tr class="collapse bg-dark" id="edit-<?=$c['id']?>">
              <td colspan="8">
                <form method="post" class="row g-3 p-3 border-top">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?=$c['id']?>">
                  <div class="col-md-3">
                    <label class="form-label">Kod</label>
                    <input name="code" class="form-control" value="<?=h($c['code'])?>" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">İndirim (₺)</label>
                    <input name="amount_tl" type="number" step="0.01" class="form-control" value="<?=number_format((float)$c['amount_tl'],2,'.','')?>" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Limit</label>
                    <input name="usage_limit" type="number" min="1" class="form-control" value="<?=$c['usage_limit']?>" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Son Kullanma</label>
                    <input name="expire_date" type="datetime-local" class="form-control" value="<?=h(str_replace(' ','T',$c['expire_date']))?>" required>
                  </div>
                  <div class="col-12">
                    <button class="btn btn-success">Kaydet</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#edit-<?=$c['id']?>">Vazgeç</button>
                  </div>
                </form>
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
</body>
</html>
