<?php 
$baglanti = new PDO("sqlite:../Database/database.sqlite");
$baglanti->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['psw'], PASSWORD_DEFAULT);
    $role = "user";
    $balance = 800;

    $sorgu = $baglanti->prepare("INSERT INTO User (full_name, email, role, password, balance) VALUES (:full_name, :email, :role, :password, :balance)");
    $sorgu->bindParam(':full_name', $full_name);
    $sorgu->bindParam(':email', $email);
    $sorgu->bindParam(':role', $role);
    $sorgu->bindParam(':password', $password);
    $sorgu->bindParam(':balance', $balance);

    if ($sorgu->execute()) {
        header("Location: login.php");
        exit();
    } else {
        echo "Kayıt başarısız!";
    }
}
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kayıt Ol - BUBilet</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --turkuaz: #00BCD4;
      --siyah: #0B0F10;
      --beyaz: #FFFFFF;
      --metin: #E6F7FA;
    }
    body {
      background: var(--siyah);
      color: var(--metin);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .navbar { background: #0F1416; border-bottom: 1px solid #16353b; }
    .navbar-brand { color: var(--turkuaz) !important; font-weight: 700; }
    .auth-box {
      background: #0f1416;
      border: 1px solid #16353b;
      border-radius: 1rem;
      box-shadow: 0 4px 20px rgba(0,0,0,.4);
      max-width: 500px;
      width: 100%;
      margin: 80px auto;
      padding: 2rem;
    }
    .form-control {
      background: #0B0F10;
      border-color: #16353b;
      color: var(--metin);
    }
    .form-control:focus {
      border-color: var(--turkuaz);
      box-shadow: 0 0 0 .25rem rgba(0,188,212,.25);
    }
    .btn-primary {
      background: var(--turkuaz);
      border: none;
      color: #001015;
      font-weight: 600;
    }
    .btn-primary:hover { filter: brightness(.9); }
    .form-check-input:checked { background-color: var(--turkuaz); border-color: var(--turkuaz); }
    .alert { border: none; border-radius: .5rem; }
    footer {
      margin-top: auto;
      border-top: 1px solid #16353b;
      background: #0F1416;
      color: var(--metin);
      padding: 1rem 0;
      text-align: center;
      font-size: .9rem;
    }
    a { color: var(--turkuaz); text-decoration: none; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="../index.php">BUBilet</a>
    <div class="ms-auto">
      <a href="login.php" class="btn btn-outline-light btn-sm">Giriş Yap</a>
    </div>
  </div>
</nav>

<main class="container">
  <div class="auth-box">
    <h2 class="text-center mb-4">Kayıt Ol</h2>

    <form action="register.php" method="post" novalidate>
      <div class="mb-3">
        <label for="full_name" class="form-label">Ad Soyad</label>
        <input type="text" name="full_name" id="full_name" class="form-control" required>
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">E-posta</label>
        <input type="email" name="email" id="email" class="form-control" required>
      </div>

      <div class="mb-3">
        <label for="psw" class="form-label">Şifre</label>
        <input type="password" name="psw" id="psw" class="form-control" required>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="agree" name="agree" value="yes" required>
        <label class="form-check-label" for="agree">
          Kullanım koşullarını kabul ediyorum
        </label>
      </div>

      <button type="submit" class="btn btn-primary w-100 mt-2">Kayıt Ol</button>

      <div class="text-center mt-3">
        <span class="text-secondary">Zaten üye misiniz?</span>
        <a href="login.php" class="ms-1">Giriş Yap</a>
      </div>
    </form>
  </div>
</main>

<footer>
  © <?=date('Y')?> BUBilet — Tüm hakları saklıdır.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
