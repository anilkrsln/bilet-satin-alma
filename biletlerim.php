<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Database/connectdb.php';
$pdo = getDBConnection();

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ----- Yardımcılar -----
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function currentUserId(): ?int { return !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function format_tl($raw): string { return number_format((float)$raw, 2, ',', '.'); }
function getUserBalance(PDO $pdo, int $user_id): int|float {
    $stmt = $pdo->prepare('SELECT balance FROM "User" WHERE id = ?');
    $stmt->execute([$user_id]);
    return (float)($stmt->fetchColumn() ?? 0);
}

if (!isLoggedIn()) {
    http_response_code(403);
    echo 'Bu sayfaya erişmek için giriş yapmalısınız.';
    exit;
}

$user_id = currentUserId();
$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    if ($ticket_id > 0) {
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare("
                SELECT t.id AS ticket_id, t.user_id, t.trip_id, t.total_price, t.status,
                       tr.departure_time, tr.id AS trip_id2
                FROM Tickets t
                JOIN Trips tr ON tr.id = t.trip_id
                WHERE t.id = ? AND t.user_id = ?
            ");
            $stmt->execute([$ticket_id, $user_id]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ticket && $ticket['status'] === 'active') {
                $now = new DateTime('now');
                $dep = new DateTime($ticket['departure_time']);
                if ($dep > (clone $now)->modify('+1 hour')) {
                    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM Booked_Seats WHERE ticket_id = ?");
                    $cntStmt->execute([$ticket_id]);
                    $seatCount = (int)$cntStmt->fetchColumn();
                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE "User" SET balance = balance + ? WHERE id = ?')
                        ->execute([$ticket['total_price'], $user_id]);
                    if ($seatCount > 0)
                        $pdo->prepare("UPDATE Trips SET capacity = capacity + ? WHERE id = ?")
                            ->execute([$seatCount, $ticket['trip_id']]);
                    $pdo->prepare("DELETE FROM Booked_Seats WHERE ticket_id = ?")->execute([$ticket_id]);
                    $pdo->prepare("DELETE FROM Tickets WHERE id = ?")->execute([$ticket_id]);
                    $pdo->commit();
                    $ok = true;
                } else $errors[] = 'Seferin başlamasına 1 saatten az kaldı, iptal edilemez.';
            } else $errors[] = 'Bilet bulunamadı veya iptal edilemez.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Hata: '.$e->getMessage();
        }
    } else $errors[] = 'Geçersiz bilet.';
}


$listStmt = $pdo->prepare("
    SELECT t.id, t.total_price, t.created_at, tr.departure_city, tr.destination_city,
           tr.departure_time, tr.arrival_time
    FROM Tickets t
    JOIN Trips tr ON tr.id = t.trip_id
    WHERE t.user_id = ? AND t.status = 'active'
    ORDER BY tr.departure_time ASC
");
$listStmt->execute([$user_id]);
$tickets = $listStmt->fetchAll(PDO::FETCH_ASSOC);
$seatMap=[];
if($tickets){
  $ids=array_map(fn($r)=>(int)$r['id'],$tickets);
  $in=implode(',',array_fill(0,count($ids),'?'));
  $s=$pdo->prepare("SELECT ticket_id, seat_number FROM Booked_Seats WHERE ticket_id IN ($in)");
  $s->execute($ids);
  foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$seatMap[$r['ticket_id']][]=$r['seat_number'];}
}
$balance_tl = format_tl(getUserBalance($pdo,$user_id));
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Biletlerim</title>
<style>
:root{
  --turkuaz:#00BCD4;
  --siyah:#0B0F10;
  --koyu:#0F1416;
  --cerceve:#16353b;
  --metin:#E6F7FA;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:var(--siyah);color:var(--metin);min-height:100vh;}
.container{max-width:1100px;margin:0 auto;padding:20px}
.header{background:var(--koyu);border:1px solid var(--cerceve);border-radius:1rem;padding:16px 20px;
display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 20px rgba(0,0,0,.4);margin-bottom:16px}
.title{font-size:20px;font-weight:700;color:var(--turkuaz);}
.badge{background:#00838f;color:#fff;padding:6px 10px;border-radius:8px;font-weight:700;margin-right:8px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 14px;border-radius:8px;border:none;cursor:pointer;font-weight:700;text-decoration:none}
.btn-primary{background:var(--turkuaz);color:#001015}
.btn-primary:hover{filter:brightness(.9)}
.btn-danger{background:#c62828;color:#fff}
.btn-danger:hover{background:#b71c1c}
.btn-light{background:#0B0F10;border:1px solid var(--cerceve);color:var(--metin)}
.btn-light:hover{border-color:var(--turkuaz)}
.list{background:var(--koyu);border:1px solid var(--cerceve);border-radius:1rem;box-shadow:0 4px 20px rgba(0,0,0,.4);padding:16px}
.item{border-left:4px solid var(--turkuaz);background:#0B0F10;padding:14px;border-radius:8px;margin-bottom:12px}
.item h3{margin:0 0 6px 0;font-size:16px;color:var(--turkuaz);}
.meta{font-size:13px;color:var(--metin);display:flex;flex-wrap:wrap;gap:12px;margin-bottom:8px}
.price{font-weight:700;color:#fff}
.actions{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.msg{padding:12px;border-radius:8px;margin-bottom:12px}
.msg-ok{background:#2e7d32;color:#fff}
.msg-err{background:#c62828;color:#fff}
.empty{text-align:center;color:#ccc;padding:30px}
@media(max-width:700px){.meta{flex-direction:column}}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="title">🎫 Biletlerim</div>
    <div>
      <span class="badge">💰 Bakiye: <?= $balance_tl ?> ₺</span>
      <button class="btn btn-light" onclick="location.href='dashboard.php'">🏠 Ana Sayfa</button>
    </div>
  </div>

  <?php if($ok): ?><div class="msg msg-ok">✓ Bilet iptal edildi, ücret hesabınıza iade edildi.</div><?php endif; ?>
  <?php if($errors): ?><div class="msg msg-err"><strong>❌ Hata:</strong><ul style="margin:6px 0 0 18px;"><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach;?></ul></div><?php endif; ?>

  <div class="list">
    <?php if(!$tickets): ?>
      <div class="empty">Aktif biletiniz bulunmuyor.</div>
    <?php else: foreach($tickets as $t):
      $tid=(int)$t['id'];
      $seats=$seatMap[$tid]??[];
      $seatStr=$seats?implode(', ',$seats):'—';
      $depDT=new DateTime($t['departure_time']);
      $arrDT=new DateTime($t['arrival_time']);
      $limitDT=(new DateTime('now'))->modify('+1 hour');
      $cancellable=($depDT>$limitDT);
      $price_tl=format_tl($t['total_price']);
    ?>
    <div class="item">
      <h3><?=h($t['departure_city'])?> → <?=h($t['destination_city'])?></h3>
      <div class="meta">
        <div>📅 Kalkış: <?=$depDT->format('d.m.Y H:i')?></div>
        <div>🏁 Varış: <?=$arrDT->format('d.m.Y H:i')?></div>
        <div>💺 Koltuk(lar): <?=h($seatStr)?></div>
        <div class="price">💵 <?=$price_tl?> ₺</div>
      </div>
      <div class="actions">
        <a class="btn btn-primary" href="fpdf/ticket_pdf.php?id=<?=$tid?>" target="_blank">⬇️ PDF İndir</a>
        <?php if($cancellable): ?>
          <form method="post" onsubmit="return confirm('Bileti iptal etmek istediğinize emin misiniz?');" style="margin:0">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="ticket_id" value="<?=$tid?>">
            <button class="btn btn-danger">✕ İptal Et</button>
          </form>
        <?php else: ?>
          <button class="btn" style="background:#222;color:#888;cursor:not-allowed" disabled>⏱ Son 1 saat — iptal edilemez</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>
</body>
</html>
