<?php
session_start();
require_once 'functions.php';

if (!isset($_GET['trip_uuid'])) {
    header("Location: index.php");
    exit;
}

$trip_uuid = $_GET['trip_uuid'];
$trip = getTripDetails($pdo, $trip_uuid);

if (!$trip) {
    die("Hata: Belirtilen sefer bulunamadı. <a href='index.php'>Ana Sayfaya Dön</a>");
}

$booked_seats_list = getBookedSeats($pdo, $trip['id']);
$booked_seats_count = count($booked_seats_list);
$total_capacity = $trip['capacity'];
$available_seats = $total_capacity - $booked_seats_count;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Detayları - A-bilet</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f0f8ff; color: #333; margin: 0; padding: 20px; }
        .top-nav { background-color: #fff; padding: 10px 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); max-width: 900px; margin: -20px auto 20px auto; border-radius: 0 0 8px 8px; display: flex; justify-content: space-between; align-items: center; }
        .top-nav .logo { font-size: 1.8em; font-weight: bold; color: #000080; text-decoration: none; }
        .top-nav .user-info { display: flex; align-items: center; gap: 15px; }
        .top-nav a { color: #0000CD; text-decoration: none; font-weight: bold; }
        .top-nav a:hover { text-decoration: underline; }
        .container { max-width: 900px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        h1 { color: #000080; text-align: center; }
        .details-container { display: flex; flex-wrap: wrap; gap: 30px; }
        .trip-details { flex: 2; min-width: 300px; }
        .trip-details h1 { text-align: left; margin-top: 0; border-bottom: 2px solid #e6f2ff; padding-bottom: 10px; }
        .trip-details .detail-item { font-size: 1.2em; margin-bottom: 15px; }
        .trip-details .detail-item strong { display: inline-block; width: 120px; color: #555; }
        .trip-details .route { font-size: 1.8em; font-weight: bold; color: #000080; margin-bottom: 20px; }
        .action-box { flex: 1; min-width: 250px; background-color: #e6f2ff; padding: 20px; border-radius: 8px; text-align: center; }
        .action-box .price { font-size: 2.5em; font-weight: bold; color: #000080; margin: 10px 0; }
        .action-box .seats { font-size: 1.2em; color: #0000CD; font-weight: bold; margin-bottom: 20px; }
        .action-box .buy-button { display: block; width: 100%; padding: 15px; background-color: #0000CD; color: white; text-decoration: none; font-size: 1.2em; font-weight: bold; border: none; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; }
        .action-box .buy-button:hover { background-color: #000080; }
        .action-box .buy-button.disabled { background-color: #ccc; cursor: not-allowed; color: #777; }
        .action-box .info-text { font-size: 0.9em; color: #555; margin-top: 10px; }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="index.php" class="logo">A-bilet</a>
        <div class="user-info">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span>Hoş geldin, <strong><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Misafir') ?></strong>!</span>
                
                <?php if (isset($_SESSION['user_role'])): ?>
                    <?php if ($_SESSION['user_role'] == 'user'): ?>
                        <a href="account.php">Hesabım</a>
                        <span>Bakiye: <strong style="color: #0000CD;"><?= number_format($_SESSION['user_balance'] ?? 0, 2) ?> TL</strong></span>
                    <?php elseif ($_SESSION['user_role'] == 'company_admin'): ?>
                        <a href="account.php">Hesabım</a>
                        <a href="company_admin_panel.php" style="color: #e74c3c; font-weight: bold;">Firma Paneli</a>
                    <?php elseif ($_SESSION['user_role'] == 'admin'): ?>
                        <a href="admin_panel.php" style="color: #e74c3c; font-weight: bold;">Admin Paneli</a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <a href="logout.php">Çıkış Yap</a>
            <?php else: ?>
                <a href="login_register.php">Giriş Yap / Kayıt Ol</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        
        <div class="details-container">
            <div class="trip-details">
                <h1><?= htmlspecialchars($trip['company_name']) ?></h1>
                
                <div class="route">
                    <?= htmlspecialchars($trip['departure_city']) ?> 
                    <span style="color: #555;">&rarr;</span> 
                    <?= htmlspecialchars($trip['destination_city']) ?>
                </div>

                <div class="detail-item">
                    <strong>Tarih:</strong> <?= date('d F Y, l', strtotime($trip['departure_time'])) ?>
                </div>
                <div class="detail-item">
                    <strong>Kalkış Saati:</strong> <?= date('H:i', strtotime($trip['departure_time'])) ?>
                </div>
                <div class="detail-item">
                    <strong>Tahmini Varış:</strong> <?= date('H:i', strtotime($trip['arrival_time'])) ?>
                </div>
            </div>

            <div class="action-box">
                <h3>Bilet Fiyatı</h3>
                <div class="price">
                    <?= htmlspecialchars(number_format($trip['price'], 2, ',', '.')) ?> TL
                </div>
                <div class="seats">
                    Kalan Koltuk: <?= $available_seats ?> / <?= $total_capacity ?>
                </div>
                
                <?php
                    $button_link = "buy_ticket.php?trip_uuid=" . $trip['uuid'];
                    $button_text = "Bilet Satın Al";
                    $button_class = "buy-button";
                    $info_text = "";

                    if ($available_seats == 0) {
                        $button_text = "Tüm Koltuklar Dolu";
                        $button_class .= " disabled";
                        $button_link = "javascript:void(0);";
                    } elseif (!isset($_SESSION['user_id'])) {
                        $button_link = "login_register.php";
                        $info_text = "Bilet almak için lütfen <a href='login_register.php'>giriş yapın</a>.";
                    } elseif ($_SESSION['user_role'] !== 'user') {
                        $button_text = "Sadece Kullanıcılar Alabilir";
                        $button_class .= " disabled";
                        $button_link = "javascript:void(0);";
                        $info_text = "Sadece 'Kullanıcı' rolündeki hesaplar bilet alabilir.";
                    }
                ?>

                <a href="<?= $button_link ?>" class="<?= $button_class ?>">
                    <?= $button_text ?>
                </a>

                <?php if ($info_text): ?>
                    <p class="info-text"><?= $info_text ?></p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</body>
</html>