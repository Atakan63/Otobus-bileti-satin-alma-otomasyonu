<?php
session_start();
require_once 'functions.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit;
}

if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'company_admin') {
    header("Location: index.php"); 
    exit;
}

$user_role = $_SESSION['user_role'];
$company_id = $_SESSION['user_company_id'] ?? null; 

$message = '';
$message_type = 'success';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    
    if (isset($_POST['add_coupon'])) {
        $data = [
            'code' => trim($_POST['code']),
            'discount' => (float)$_POST['discount'],
            'usage_limit' => (int)$_POST['usage_limit'],
            'expire_date' => $_POST['expire_date']
        ];
        
        
        $coupon_company_id = null; 
        if ($user_role === 'admin' && !empty($_POST['company_id'])) {
            $coupon_company_id = (int)$_POST['company_id']; 
        } elseif ($user_role === 'company_admin') {
            $coupon_company_id = $company_id; 
        }

        
        if (empty($data['code']) || $data['discount'] <= 0 || $data['usage_limit'] <= 0 || empty($data['expire_date'])) {
            $message = "Hata: Lütfen tüm alanları doğru bir şekilde doldurun.";
            $message_type = 'error';
        } else {
            $result = createCoupon($pdo, $data, $coupon_company_id);
            if ($result === true) {
                $message = "Yeni kupon başarıyla eklendi.";
                $message_type = 'success';
            } else {
                $message = $result; 
                $message_type = 'error';
            }
        }
    }

    
    if (isset($_POST['delete_coupon'])) {
        $coupon_id_to_delete = (int)$_POST['coupon_id'];
        
        
        if (deleteCoupon($pdo, $coupon_id_to_delete, $user_role, $company_id)) {
            $message = "Kupon başarıyla silindi.";
            $message_type = 'success';
        } else {
            $message = "Hata: Kupon silinemedi veya bu kupona erişim yetkiniz yok.";
            $message_type = 'error';
        }
    }
}


$coupons = getCoupons($pdo, $user_role, $company_id);
$companies = ($user_role === 'admin') ? getAllCompanies($pdo) : []; 

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Yönetimi - A-bilet</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f0f8ff; color: #333; margin: 0; padding: 20px; }
        .top-nav { background-color: #fff; padding: 10px 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); max-width: 1200px; margin: -20px auto 20px auto; border-radius: 0 0 8px 8px; display: flex; justify-content: space-between; align-items: center; }
        .top-nav .logo { font-size: 1.8em; font-weight: bold; color: #000080; text-decoration: none; }
        .top-nav .user-info { display: flex; align-items: center; gap: 15px; }
        .top-nav a { color: #0000CD; text-decoration: none; font-weight: bold; }
        .top-nav a:hover { text-decoration: underline; }
        .container { max-width: 1200px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        h1 { color: #000080; text-align: left; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .success { background-color: #d6eaff; color: #000080; }
        .error { background-color: #fdd; color: #e74c3c; }
        .management-section { background-color: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #eee; }
        .management-section h2 { margin-top: 0; color: #000080; border-bottom: 1px solid #d6eaff; padding-bottom: 10px; }

        .coupon-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
        .coupon-form .form-group { display: flex; flex-direction: column; }
        .coupon-form label { margin-bottom: 5px; font-weight: bold; }
        .coupon-form input, .coupon-form select { padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        .coupon-form button { background-color: #0000CD; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; padding: 12px; grid-column: 1 / -1; }
        .coupon-form button:hover { background-color: #000080; }

        .coupon-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .coupon-table th, .coupon-table td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: middle; }
        .coupon-table th { background-color: #e6f2ff; color: #000080; }
        .coupon-table tr:nth-child(even) { background-color: #f9f9f9; }
        .coupon-table .delete-button { background-color: #e74c3c; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="index.php" class="logo">A-bilet</a>
        <div class="user-info">
            <span>Hoş geldin, <strong><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Misafir') ?></strong>!</span>
            
            <?php if (isset($_SESSION['user_role'])): ?>
                <?php if ($_SESSION['user_role'] == 'user'): ?>
                    <a href="account.php">Hesabım</a>
                    <span>Bakiye: <strong><?= number_format($_SESSION['user_balance'] ?? 0, 2) ?> TL</strong></span>
                <?php elseif ($_SESSION['user_role'] == 'admin'): ?>
                    <a href="admin_panel.php">Admin Paneli</a>
                    <a href="coupon_management.php" style="color: #e74c3c; font-weight: bold;">Kupon Yönetimi</a>
                <?php elseif ($_SESSION['user_role'] == 'company_admin'): ?>
                    <a href="account.php">Hesabım</a>
                    <a href="company_admin_panel.php">Firma Paneli</a>
                    <a href="coupon_management.php" style="color: #e74c3c; font-weight: bold;">Kupon Yönetimi</a>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="logout.php">Çıkış Yap</a>
        </div>
    </div>

    <div class="container">
        <h1>Kupon Yönetimi</h1>

        <?php if ($message): ?>
            <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="management-section">
            <h2>Yeni Kupon Ekle</h2>
            <form action="coupon_management.php" method="POST" class="coupon-form">
                <div class="form-group">
                    <label for="code">Kupon Kodu</label>
                    <input type="text" id="code" name="code" required placeholder="Örn: YAZ2025">
                </div>
                <div class="form-group">
                    <label for="discount">İndirim Yüzdesi (%)</label>
                    <input type="number" step="0.01" id="discount" name="discount" required placeholder="Örn: 15.5">
                </div>
                <div class="form-group">
                    <label for="usage_limit">Kullanım Limiti</label>
                    <input type="number" id="usage_limit" name="usage_limit" required placeholder="Örn: 100">
                </div>
                <div class="form-group">
                    <label for="expire_date">Son Geçerlilik Tarihi</label>
                    <input type="datetime-local" id="expire_date" name="expire_date" required>
                </div>
                
                <?php if ($user_role === 'admin'): ?>
                <div class="form-group">
                    <label for="company_id">Firma (Opsiyonel)</label>
                    <select id="company_id" name="company_id">
                        <option value="">Tüm Firmalar İçin</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <button type="submit" name="add_coupon">Yeni Kupon Ekle</button>
            </form>
        </div>

        <div class="management-section">
            <h2>Mevcut Kuponlar</h2>
            <table class="coupon-table">
                <thead>
                    <tr>
                        <th>Kod</th>
                        <th>İndirim (%)</th>
                        <th>Kalan Limit</th>
                        <th>Son Tarih</th>
                        <?php if ($user_role === 'admin'): ?>
                            <th>Firma</th>
                        <?php endif; ?>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="<?= ($user_role === 'admin') ? '6' : '5' ?>" style="text-align: center;">Kayıtlı kupon bulunmamaktadır.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($coupon['code']) ?></strong></td>
                            <td><?= number_format($coupon['discount'], 2) ?>%</td>
                            <td><?= $coupon['usage_limit'] ?></td>
                            <td><?= date('d M Y - H:i', strtotime($coupon['expire_date'])) ?></td>
                            <?php if ($user_role === 'admin'): ?>
                                <td><?= htmlspecialchars($coupon['company_name'] ?? 'Tümü') ?></td>
                            <?php endif; ?>
                            <td>
                                <form action="coupon_management.php" method="POST" onsubmit="return confirm('Bu kuponu silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="coupon_id" value="<?= $coupon['id'] ?>">
                                    <button type="submit" name="delete_coupon" class="delete-button">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>