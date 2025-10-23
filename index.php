<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();
error_reporting(E_ALL);
ini_set('display_errors','1');

/* yardımcılar */
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function h(string $s): string { return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function tl_from_tl(float $tl): string { return number_format($tl,2,',','.').' ₺'; }
function dt_tr(string $iso): string {
  $ts=strtotime($iso); return $ts?date('d.m.Y H:i',$ts):$iso;
}

/* filtreler */
$origin=trim($_GET['origin']??'');
$destination=trim($_GET['destination']??'');
$date=trim($_GET['date']??'');

/* şehir listeleri */
try{
$turkeyCities = [
  "Adana","Adıyaman","Afyonkarahisar","Ağrı","Aksaray","Amasya","Ankara","Antalya","Ardahan",
  "Artvin","Aydın","Balıkesir","Bartın","Batman","Bayburt","Bilecik","Bingöl","Bitlis",
  "Bolu","Burdur","Bursa","Çanakkale","Çankırı","Çorum","Denizli","Diyarbakır","Düzce",
  "Edirne","Elazığ","Erzincan","Erzurum","Eskişehir","Gaziantep","Giresun","Gümüşhane",
  "Hakkari","Hatay","Iğdır","Isparta","İstanbul","İzmir","Kahramanmaraş","Karabük",
  "Karaman","Kars","Kastamonu","Kayseri","Kilis","Kırıkkale","Kırklareli","Kırşehir",
  "Kocaeli","Konya","Kütahya","Malatya","Manisa","Mardin","Mersin","Muğla","Muş",
  "Nevşehir","Niğde","Ordu","Osmaniye","Rize","Sakarya","Samsun","Siirt","Sinop",
  "Sivas","Şanlıurfa","Şırnak","Tekirdağ","Tokat","Trabzon","Tunceli","Uşak",
  "Van","Yalova","Yozgat","Zonguldak"
];
$origins = $turkeyCities;
$destinations = $turkeyCities;
}catch(Throwable){
  $origins=$destinations=[];
}

/* sorgu */
$where=[];$params=[];
if($origin!==''){ $where[]='t.departure_city=?'; $params[]=$origin; }
if($destination!==''){ $where[]='t.destination_city=?'; $params[]=$destination; }
if($date!==''){ $where[]='substr(t.departure_time,1,10)=?'; $params[]=$date; }

$sql="SELECT t.id,t.departure_city,t.destination_city,t.departure_time,t.arrival_time,
t.price,t.capacity,
(t.capacity-COALESCE((SELECT COUNT(bs.id)
FROM Tickets tk JOIN Booked_Seats bs ON bs.ticket_id=tk.id
WHERE tk.trip_id=t.id AND tk.status='active'),0)) AS seats_available
FROM Trips t";
if($where)$sql.=' WHERE '.implode(' AND ',$where);
$sql.=' ORDER BY t.departure_time ASC';

try{
  $stmt=$pdo->prepare($sql);$stmt->execute($params);
  $trips=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
}catch(Throwable $e){
  $trips=[];$queryError=$e->getMessage();
}

$loggedIn=isLoggedIn();
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>BUBilet | Sefer Ara</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --turkuaz:#00BCD4;
  --siyah:#0B0F10;
  --koyu:#0F1416;
  --cerceve:#16353b;
  --metin:#E6F7FA;
}
body{background:var(--siyah);color:var(--metin);}
.navbar{background:var(--koyu);border-bottom:1px solid var(--cerceve);}
.navbar-brand{color:var(--turkuaz)!important;font-weight:700;font-size:1.3rem;}
.btn-turkuaz{background:var(--turkuaz);border:none;color:#001015;font-weight:600;}
.btn-turkuaz:hover{filter:brightness(.9);}
.form-select,.form-control{background:#0B0F10;border-color:var(--cerceve);color:var(--metin);}
.form-select:focus,.form-control:focus{border-color:var(--turkuaz);box-shadow:0 0 0 .25rem rgba(0,188,212,.25);}
.card{
  background:var(--koyu);
  border:1px solid var(--cerceve);
  border-radius:1rem;
  box-shadow:0 8px 24px rgba(0,0,0,.4);
  transition:all .2s ease;
}
.card:hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(0,188,212,.15);}
.price-badge{
  background:rgba(0,188,212,.15);
  color:var(--turkuaz);
  padding:.35rem .7rem;
  border-radius:.5rem;
  font-weight:600;
  font-size:.9rem;
}
footer{border-top:1px solid var(--cerceve);text-align:center;padding:1rem 0;color:#999;margin-top:3rem;}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg mb-4">
  <div class="container">
    <a class="navbar-brand" href="/">🚌 BUBilet</a>
    <div class="ms-auto d-flex gap-2">
      <?php if($loggedIn): ?>
        <span class="badge bg-dark border border-turkuaz">Giriş yapıldı</span>
        <a href="/auth/logout.php" class="btn btn-outline-light btn-sm">Çıkış</a>
      <?php else: ?>
        <a href="/auth/login.php" class="btn btn-turkuaz btn-sm">Giriş Yap</a>
        <a href="/auth/register.php" class="btn btn-outline-light btn-sm">Kayıt Ol</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="container pb-5">
  <div class="card mb-4 p-3">
    <form class="row g-3 align-items-end" method="get">
      <div class="col-md-3">
        <label class="form-label">Kalkış</label>
        <select name="origin" class="form-select">
          <option value="">Tümü</option>
          <?php foreach($origins as $o): ?>
            <option value="<?=h($o)?>" <?=$origin===$o?'selected':''?>><?=h($o)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Varış</label>
        <select name="destination" class="form-select">
          <option value="">Tümü</option>
          <?php foreach($destinations as $d): ?>
            <option value="<?=h($d)?>" <?=$destination===$d?'selected':''?>><?=h($d)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Tarih</label>
        <input type="date" name="date" class="form-control" value="<?=h($date)?>">
      </div>
      <div class="col-md-3 d-grid">
        <button class="btn btn-turkuaz btn-lg">Sefer Ara</button>
      </div>
    </form>
  </div>

  <?php if(!$loggedIn): ?>
    <div class="alert alert-warning border-warning">Bilet almak için lütfen <a href="/auth/login.php" class="alert-link">giriş yapın</a> veya <a href="/auth/register.php" class="alert-link">kayıt olun</a>.</div>
  <?php endif; ?>

  <?php if(!empty($queryError)): ?>
    <div class="alert alert-danger">Sorgu hatası: <?=h($queryError)?></div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="m-0 fw-bold text-turkuaz">Sefer Sonuçları</h5>
    <span class="text-secondary small"><?=count($trips)?> adet sefer bulundu</span>
  </div>

  <?php if(!$trips): ?>
    <div class="text-secondary">Aramanıza uygun sefer bulunamadı.</div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
      <?php foreach($trips as $t):
        $id=(int)$t['id'];
        $available=max(0,(int)$t['seats_available']);
        $capacity=(int)$t['capacity'];
        $full=$available<=0;
        $btnDisabled=(!$loggedIn||$full);
        $href=($loggedIn&&!$full)?'/bilet_al.php?trip_id='.$id:'/auth/login.php';
      ?>
      <div class="col">
        <div class="card h-100">
          <div class="card-body d-flex flex-column justify-content-between">
            <div>
              <h5 class="fw-bold text-turkuaz mb-1"><?=h($t['departure_city'])?> → <?=h($t['destination_city'])?></h5>
              <div class="text-secondary small mb-2">
                Kalkış: <?=h(dt_tr($t['departure_time']))?><br>
                Varış: <?=h(dt_tr($t['arrival_time']))?>
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <div class="price-badge"><?=tl_from_tl((float)$t['price'])?></div>
              <div class="text-secondary small">Kalan: <?=$available?> / <?=$capacity?></div>
            </div>
            <a href="<?=$href?>" class="btn btn-turkuaz w-100 mt-3 <?=$btnDisabled?'disabled':''?>">
              <?= $full ? 'Dolu' : '🎫 Bilet Al' ?>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<footer>© <?=date('Y')?> BUBilet — Tüm hakları saklıdır.</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
