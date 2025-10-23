<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Database/connectdb.php';

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* === Helpers === */
function isLoggedIn(): bool { return !empty($_SESSION['user_id']); }
function currentUserId(): ?int { return $_SESSION['user_id'] ?? null; }
function safe($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function format_tl(float|string $tl): string {
    return number_format((float)$tl, 2, ',', '.');
}
function diff_time(string $start, string $end): string {
    $a = new DateTime($start); $b = new DateTime($end);
    $d = $a->diff($b); $h = $d->h + ($d->days * 24); $m = $d->i;
    if ($h && $m) return "$h saat $m dakika";
    if ($h) return "$h saat";
    if ($m) return "$m dakika";
    return "—";
}
function grayscale_logo(string $path): string {
    if (!is_file($path)) return $path;
    if (!function_exists('imagefilter')) return $path;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'png') $src = @imagecreatefrompng($path);
    elseif (in_array($ext, ['jpg','jpeg'], true)) $src = @imagecreatefromjpeg($path);
    elseif ($ext === 'gif') $src = @imagecreatefromgif($path);
    else return $path;
    if (!$src) return $path;
    imagefilter($src, IMG_FILTER_GRAYSCALE);
    $tmp = sys_get_temp_dir() . '/logo_bw_' . md5($path) . '.png';
    imagepng($src, $tmp); imagedestroy($src);
    return is_file($tmp) ? $tmp : $path;
}

/* === Guard === */
if (!isLoggedIn()) { http_response_code(403); exit('Bu sayfaya erişmek için giriş yapmalısınız.'); }
$user_id   = (int)currentUserId();
$ticket_id = (int)($_GET['id'] ?? 0);
if ($ticket_id <= 0) exit('Geçersiz bilet ID!');


try {
    $stmt = $pdo->prepare('
        SELECT 
            t.id, t.total_price, t.status, t.created_at,
            tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time,
            u.full_name, u.email,
            bc.name AS company_name, bc.logo_path
        FROM "Tickets" t
        JOIN "Trips" tr       ON tr.id = t.trip_id
        JOIN "User" u         ON u.id = t.user_id
        JOIN "Bus_Company" bc ON bc.id = tr.company_id
        WHERE t.id = ? AND t.user_id = ?
        LIMIT 1
    ');
    $stmt->execute([$ticket_id, $user_id]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$t) exit('Bilet bulunamadı veya size ait değil!');
    if ($t['status'] !== 'active') exit('Bu bilet aktif değil!');

    $seatStmt = $pdo->prepare('
        SELECT seat_number 
        FROM "Booked_Seats" 
        WHERE ticket_id = ? 
        ORDER BY seat_number ASC
    ');
    $seatStmt->execute([$ticket_id]);
    $seats   = $seatStmt->fetchAll(PDO::FETCH_COLUMN);
    $seatStr = $seats ? implode(', ', array_map('safe', $seats)) : '—';

} catch (Throwable $e) {
    exit('Veritabanı hatası: ' . $e->getMessage());
}


$depDT     = new DateTime($t['departure_time']);
$arrDT     = new DateTime($t['arrival_time']);
$createdDT = new DateTime($t['created_at']);
$duration  = diff_time($t['departure_time'], $t['arrival_time']);
$price_tl = format_tl($t['total_price']);



$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('BuBilet');
$pdf->SetAuthor('Otobüs Bilet Sistemi');
$pdf->SetTitle('Bilet #' . $t['id']);
$pdf->SetSubject('Otobüs Bileti');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();
$pdf->setImageScale(1.25); 
$pdf->SetFont('dejavusans', '', 10.5);

/* === Logo (gri ton) === */
$logoPath = $t['logo_path'] ?? '';
$logoPlaced = false;
if (!empty($logoPath)) {
    $cands = [];
    if (is_file($logoPath)) $cands[] = $logoPath;
    $cands[] = realpath(__DIR__ . '/../' . ltrim($logoPath, '/\\'));
    $cands[] = realpath(__DIR__ . '/../../' . ltrim($logoPath, '/\\'));
    $cands[] = realpath(__DIR__ . '/' . ltrim($logoPath, '/\\'));
    foreach ($cands as $p) {
        if ($p && is_file($p)) {
            $bw = grayscale_logo($p);
            $w = 24; // mm
            $x = ($pdf->getPageWidth() - $w) / 2;
            $pdf->Image($bw, $x, 14, $w, 0, '', '', 'T', true, 300);
            $logoPlaced = true; break;
        }
    }
}
$pdf->Ln($logoPlaced ? 22 : 4);


$css = '
<style>
  body { color:#000; font-family: DejaVu Sans, sans-serif; }
  .ticket { border: 0.6mm solid #000; border-radius: 3mm; padding: 8mm 7mm; }
  .title { text-align:center; font-weight:800; font-size:16px; margin: 2mm 0 1mm; }
  .company { text-align:center; font-size:11px; letter-spacing:.3px; margin-bottom: 2mm; text-transform: uppercase; }
  .route { text-align:center; font-size:13.5px; font-weight:700; margin: 2mm 0 3mm; }
  .muted { color:#111; font-size:10px; text-align:center; margin-bottom: 2mm; }
  table.grid { width:100%; border-collapse: collapse; margin-top: 1mm; }
  table.grid td { width:50%; vertical-align:top; padding: 2.2mm 2mm; border: 0.3mm solid #000; }
  .label { display:block; font-size:9.5px; font-weight:700; margin-bottom: 0.7mm; }
  .value { display:block; font-size:11px; }
  .price { text-align:center; border: 0.4mm solid #000; padding: 2.8mm; margin: 4mm 0 2mm; font-size:12.5px; font-weight:800; }
  .bar-title { text-align:center; font-size:10px; margin-top: 2mm; }
  .foot { text-align:center; font-size:9px; margin-top: 2mm; border-top: 0.3mm dashed #000; padding-top: 2mm; }
</style>
';

$html = $css . '
<div class="ticket">
  <div class="title">OTOBÜS BİLETİ</div>
  <div class="company">'.safe($t['company_name']).'</div>
  <div class="route">'.safe($t['departure_city']).' → '.safe($t['destination_city']).'</div>
  <div class="muted">Bilet No: #'.safe($t['id']).' • Yaklaşık Süre: '.safe($duration).'</div>

  <table class="grid">
    <tr>
      <td>
        <span class="label">📅 Kalkış Tarihi:    </span>
        <span class="value">'.$depDT->format('d.m.Y').'</span>
      </td>
      <td>
        <span class="label">🕐 Kalkış Saati:    </span>
        <span class="value">'.$depDT->format('H:i').'</span>
      </td>
    </tr>
    <tr>
      <td>
        <span class="label">🏁 Varış:    </span>
        <span class="value">'.$arrDT->format('d.m.Y H:i').'</span>
      </td>
      <td>
        <span class="label">💺 Koltuk(lar):    </span>
        <span class="value">'.safe($seatStr).'</span>
      </td>
    </tr>
    <tr>
      <td>
        <span class="label">👤 Yolcu:    </span>
        <span class="value">'.safe($t['full_name']).'</span>
      </td>
      <td>
        <span class="label">📧 E-posta:    </span>
        <span class="value">'.safe($t['email']).'</span>
      </td>
    </tr>
  </table>

  <div class="price">ÖDENEN TUTAR: '.safe($price_tl).' ₺</div>
  <div class="bar-title">Barkod / Bilet Referans Numarası</div>
</div>
';

$pdf->writeHTML($html, true, false, true, false, '');


$pdf->Ln(1.5);
$ref = str_pad((string)$t['id'], 10, '0', STR_PAD_LEFT);
$style = [
    'position' => '',
    'align' => 'C',
    'stretch' => false,
    'fitwidth' => true,
    'border' => false,
    'padding' => 0,
    'fgcolor' => [0,0,0],
    'bgcolor' => false,
    'text' => true,
    'font' => 'dejavusans',
    'fontsize' => 9.5,
    'stretchtext' => 0
];
$pdf->write1DBarcode($ref, 'C128', '', '', '', 14, 0.38, $style, ''); // yükseklik & kalınlık küçültüldü

$pdf->Ln(1.5);
$pdf->SetFont('dejavusans', '', 9);
$footer = '
<div class="foot">
  <div><strong>İletişim:</strong> info@bubilet.com • 0850 XXX XX XX</div>
  <div>Satın Alma: '.$createdDT->format('d.m.Y H:i').' • Yazdırma: '.date('d.m.Y H:i').'</div>
  <div>İyi yolculuklar dileriz</div>
</div>';
$pdf->writeHTML($footer, true, false, true, false, '');


$dosya_adi = 'bilet_'. $t['id'] .'_'. date('Ymd') .'.pdf';
$pdf->Output($dosya_adi, 'D');
