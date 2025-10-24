<?php
session_start();
require_once 'functions.php';


if (!isset($_SESSION['user_id'])) { header("Location: login_register.php"); exit; }
if ($_SESSION['user_role'] !== 'admin') { header("Location: index.php"); exit; }


if (!isset($_GET['company_id']) || !is_numeric($_GET['company_id'])) {
    header("Location: admin_panel.php?error=invalid_id"); 
    exit;
}
$company_id_to_edit = (int)$_GET['company_id'];


$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_company'])) {
    $new_name = trim($_POST['company_name'] ?? '');
    
    if (empty($new_name)) {
        $message = "Firma adı boş olamaz.";
        $message_type = 'error';
    } else {
        
        $result = updateCompany($pdo, $company_id_to_edit, $new_name, null); 
        if ($result === true) {
            $message = "Firma başarıyla güncellendi.";
            $message_type = 'success';
            
        } else {
            $message = $result; 
            $message_type = 'error';
        }
    }
}


$company_details = getCompanyDetails($pdo, $company_id_to_edit);


if (!$company_details) {
     header("Location: admin_panel.php?error=not_found"); 
     exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Düzenle - A-bilet</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f0f8ff; color: #333; margin: 0; padding: 20px; }
        .top-nav { background-color: #fff; padding: 10px 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); max-width: 700px; margin: -20px auto 20px auto; border-radius: 0 0 8px 8px; display: flex; justify-content: space-between; align-items: center; }
        .top-nav .logo { font-size: 1.8em; font-weight: bold; color: #000080; text-decoration: none; }
        .top-nav .user-info { display: flex; align-items: center; gap: 15px; }
        .top-nav a { color: #0000CD; text-decoration: none; font-weight: bold; }
        .top-nav a:hover { text-decoration: underline; }
        .container { max-width: 700px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        h1 { color: #000080; text-align: left; margin-top: 0;}
        .message { padding: 15px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .success { background-color: #d6eaff; color: #000080; }
        .error { background-color: #fdd; color: #e74c3c; }
        .breadcrumb { margin-bottom: 20px; font-size: 0.9em; }
        .breadcrumb a { color: #3498db; text-decoration: none; }

        .edit-form .form-group { margin-bottom: 15px; }
        .edit-form label { display: block; margin-bottom: 5px; font-weight: bold; }
        .edit-form input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .edit-form button {
            padding: 12px 25px;
            background-color: #0000CD;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            margin-right: 10px;
        }
        .edit-form a.cancel-button {
             padding: 12px 25px;
            background-color: #7f8c8d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1em;
        }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="index.php" class="logo">A-bilet</a>
        <div class="user-info">
            <span>Hoş geldin, <strong><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Misafir') ?></strong>!</span>
            <a href="admin_panel.php">Admin Paneli</a>
            <a href="coupon_management.php">Kupon Yönetimi</a>
            <a href="logout.php">Çıkış Yap</a>
        </div>
    </div>

    <div class="container">
        <p class="breadcrumb"><a href="admin_panel.php">Admin Paneli</a> &gt; Firma Düzenle</p>
        <h1>Firma Düzenle: <?= htmlspecialchars($company_details['name']) ?></h1>

        <?php if ($message): ?>
            <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form action="edit_company.php?company_id=<?= $company_id_to_edit ?>" method="POST" class="edit-form">
            <div class="form-group">
                <label for="company_name">Firma Adı:</label>
                <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($company_details['name']) ?>" required>
            </div>
            
            <button type="submit" name="update_company">Güncelle</button>
            <a href="admin_panel.php" class="cancel-button">İptal</a>
        </form>

    </div>

</body>
</html>