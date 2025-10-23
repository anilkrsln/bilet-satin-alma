<?php
session_start();
require_once '../Database/connectdb.php';

// Eğer zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['user_id'])) {
    header("Location: /dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $errors = [];
    
    // Validasyon
    if (empty($email)) $errors[] = "Email gereklidir.";
    if (empty($password)) $errors[] = "Şifre gereklidir.";
    
    if (empty($errors)) {
        try {
            $db = getDBConnection();
            
            $stmt = $db->prepare("SELECT * FROM user WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['balance'] = $user['balance'];
                    header("Location: /dashboard.php");
                    exit();
                } else {
                    $errors[] = "Email veya şifre hatalı!";
                }
            } else {
                $errors[] = "Bu email ile kayıtlı kullanıcı bulunamadı!";
            }
        } catch (PDOException $e) {
            $errors[] = "Giriş sırasında hata: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="tr" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Giriş Yap - BUBilet</title>
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
      max-width: 420px;
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
  </div>
</nav>

<main class="container">
  <div class="auth-box">
    <h2 class="text-center mb-4">Giriş Yap</h2>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
          <div><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Şifre</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <button type="submit" class="btn btn-primary w-100 mt-2">Giriş Yap</button>

      <div class="text-center mt-3">
        <span class="text-secondary">Hesabın yok mu?</span>
        <a href="register.php" class="ms-1">Kayıt Ol</a>
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
