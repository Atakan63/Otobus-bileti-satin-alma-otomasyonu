<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'functions.php';

$login_error = '';
$register_error = '';
$register_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $login_error = "Lütfen tüm alanları doldurun.";
        } else {
            $user = loginUser($pdo, $email, $password); 

            if ($user) {
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_uuid'] = $user['uuid'];
                $_SESSION['user_fullname'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_balance'] = $user['balance'];
                $_SESSION['user_company_id'] = $user['company_id']; 

                
                if ($_SESSION['user_role'] === 'admin') {
                    header("Location: admin_panel.php");
                } elseif ($_SESSION['user_role'] === 'company_admin') {
                    header("Location: company_admin_panel.php");
                } else {
                    header("Location: index.php");
                }
                exit; 
            } else {
                $login_error = "E-posta veya şifre hatalı.";
            }
        }
    }

    if (isset($_POST['register'])) {
        $fullName = trim($_POST['fullName']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        if (empty($fullName) || empty($email) || empty($password)) {
            $register_error = "Lütfen tüm alanları doldurun.";
        } elseif ($password !== $password_confirm) {
            $register_error = "Şifreler uyuşmuyor.";
        } else {
            $result = registerUser($pdo, $fullName, $email, $password);

            if ($result === true) {
                $register_success = "Kayıt başarılı! Şimdi giriş yapabilirsiniz.";
            } else {
                $register_error = $result;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap / Kayıt Ol - A-bilet</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f0f8ff; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .auth-container { display: flex; flex-wrap: wrap; gap: 40px; justify-content: center;}
        .auth-form { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 350px; }
        .auth-form h2 { text-align: center; color: #000080; margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background-color: #0000CD; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #000080; }
        .error { color: #e74c3c; background-color: #fdd; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 15px; }
        .success { color: #000080; background-color: #d6eaff; padding: 10px; border-radius: 5px; text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-form" id="login-form">
            <form action="login_register.php" method="POST">
                <h2>Giriş Yap</h2>
                <?php if ($login_error): ?>
                    <p class="error"><?= htmlspecialchars($login_error) ?></p>
                <?php endif; ?>
                <div class="form-group">
                    <label for="login-email">E-posta</label>
                    <input type="email" id="login-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Şifre</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <button type="submit" name="login">Giriş Yap</button>
            </form>
        </div>

        <div class="auth-form" id="register-form">
            <form action="login_register.php" method="POST">
                <h2>Kayıt Ol</h2>
                <?php if ($register_error): ?>
                    <p class="error"><?= htmlspecialchars($register_error) ?></p>
                <?php endif; ?>
                <?php if ($register_success): ?>
                    <p class="success"><?= htmlspecialchars($register_success) ?></p>
                <?php endif; ?>
                <div class="form-group">
                    <label for="reg-fullname">Tam Adınız</label>
                    <input type="text" id="reg-fullname" name="fullName" required>
                </div>
                <div class="form-group">
                    <label for="reg-email">E-posta</label>
                    <input type="email" id="reg-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="reg-password">Şifre</label>
                    <input type="password" id="reg-password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="reg-password-confirm">Şifre Tekrar</label>
                    <input type="password" id="reg-password-confirm" name="password_confirm" required>
                </div>
                <button type="submit" name="register">Kayıt Ol</button>
            </form>
        </div>
    </div>

</body>
</html>