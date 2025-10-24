<?php
session_start();
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) { header("Location: login_register.php"); exit; }
if ($_SESSION['user_role'] !== 'company_admin') { header("Location: index.php"); exit; }
if (empty($_SESSION['user_company_id'])) { die("Hata: Hesabınız bir firmaya atanmamış."); }

$company_id = $_SESSION['user_company_id'];
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_trip'])) {  }
    if (isset($_POST['delete_trip'])) {  }
    if (isset($_POST['add_trip'])) {
        $data = [ 'departure_city' => trim($_POST['departure_city']), 'destination_city' => trim($_POST['destination_city']), 'departure_time' => $_POST['departure_time'], 'arrival_time' => $_POST['arrival_time'], 'price' => (float)$_POST['price'], 'capacity' => (int)$_POST['capacity'] ];
        if (empty($data['departure_city']) || empty($data['destination_city']) || empty($data['departure_time']) || empty($data['arrival_time']) || $data['price'] <= 0 || $data['capacity'] <= 0) {
            $message = "Hata: Lütfen tüm alanları doğru bir şekilde doldurun."; $message_type = 'error';
        } else {
            $result = createTrip($pdo, $company_id, $data);
            if ($result === true) { $message = "Yeni sefer başarıyla eklendi."; $message_type = 'success'; }
            else { $message = $result; $message_type = 'error'; }
        }
    }
    if (isset($_POST['delete_trip'])) {
        $trip_id_to_delete = (int)$_POST['trip_id'];
        if (deleteTrip($pdo, $trip_id_to_delete, $company_id)) { $message = "Sefer başarıyla silindi."; $message_type = 'success'; }
        else { $message = "Hata: Sefer silinemedi veya bu sefere erişim yetkiniz yok."; $message_type = 'error'; }
    }
}

$company_details = getCompanyDetails($pdo, $company_id);
$company_trips = getCompanyTrips($pdo, $company_id);
$all_cities = ['Adana',  'Düzce']; sort($all_cities); 
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Paneli - A-bilet</title>
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
        .trip-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .trip-form .form-group { display: flex; flex-direction: column; }
        .trip-form label { margin-bottom: 5px; font-weight: bold; }
        .trip-form input, .trip-form select { padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 1em; }
        .trip-form button { background-color: #0000CD; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; padding: 12px; grid-column: 1 / -1; }
        .trip-form button:hover { background-color: #000080; }
        .trip-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .trip-table th, .trip-table td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: middle; }
        .trip-table th { background-color: #e6f2ff; color: #000080; }
        .trip-table tr:nth-child(even) { background-color: #f9f9f9; }
        .trip-table .action-buttons form { display: inline-block; margin: 0 2px;} 
        .trip-table .delete-button { background-color: #e74c3c; color: white; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; font-size: 0.9em; }
        .trip-table .manage-link {
            background-color: #3498db;
            color: white;
            padding: 6px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
            margin-right: 5px; 
        }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="index.php" class="logo">A-bilet</a>
        <div class="user-info">
            <span>Hoş geldin, <strong><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Misafir') ?></strong>!</span>
            <a href="account.php">Hesabım</a>
            <a href="company_admin_panel.php" style="color: #e74c3c; font-weight: bold;">Firma Paneli</a>
             <a href="coupon_management.php">Kupon Yönetimi</a>
           <a href="logout.php">Çıkış Yap</a>
        </div>
    </div>

    <div class="container">
        <h1><?= htmlspecialchars($company_details['name'] ?? 'Firma') ?> - Sefer Yönetim Paneli</h1>
        <?php if ($message): ?> <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div> <?php endif; ?>

        <div class="management-section">
            <h2>Yeni Sefer Ekle</h2>
            <form action="company_admin_panel.php" method="POST" class="trip-form">
                <div class="form-group"> <label for="departure_city">Nereden</label> <select id="departure_city" name="departure_city" required> <option value="">Kalkış şehri...</option> <?php foreach ($all_cities as $city): ?> <option value="<?= $city ?>"><?= $city ?></option> <?php endforeach; ?> </select> </div>
                <div class="form-group"> <label for="destination_city">Nereye</label> <select id="destination_city" name="destination_city" required> <option value="">Varış şehri...</option> <?php foreach ($all_cities as $city): ?> <option value="<?= $city ?>"><?= $city ?></option> <?php endforeach; ?> </select> </div>
                <div class="form-group"> <label for="departure_time">Kalkış</label> <input type="datetime-local" id="departure_time" name="departure_time" required> </div>
                <div class="form-group"> <label for="arrival_time">Varış</label> <input type="datetime-local" id="arrival_time" name="arrival_time" required> </div>
                <div class="form-group"> <label for="price">Fiyat (TL)</label> <input type="number" step="0.01" id="price" name="price" required placeholder="Örn: 450.00"> </div>
                <div class="form-group"> <label for="capacity">Kapasite</label> <input type="number" id="capacity" name="capacity" required placeholder="Örn: 40"> </div>
                <button type="submit" name="add_trip">Yeni Seferi Ekle</button>
            </form>
       </div>

        <div class="management-section">
            <h2>Mevcut Seferler</h2>
            <table class="trip-table">
                <thead>
                    <tr>
                        <th>Güzergah</th>
                        <th>Kalkış Zamanı</th>
                        <th>Varış Zamanı</th>
                        <th>Durum (Dolu/Kapasite)</th>
                        <th>Fiyat</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($company_trips)): ?> <tr> <td colspan="6" style="text-align: center;">Henüz kayıtlı seferiniz bulunmamaktadır.</td> </tr> <?php endif; ?>
                    <?php foreach ($company_trips as $trip): ?>
                        <?php $booked_count = count(getBookedSeats($pdo, $trip['id'])); ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($trip['departure_city']) ?> &rarr; <?= htmlspecialchars($trip['destination_city']) ?></strong></td>
                            <td><?= date('d M Y - H:i', strtotime($trip['departure_time'])) ?></td>
                            <td><?= date('d M Y - H:i', strtotime($trip['arrival_time'])) ?></td>
                            <td> <strong style="color: <?= ($booked_count >= $trip['capacity']) ? '#e74c3c' : '#000080' ?>"> <?= $booked_count ?> / <?= $trip['capacity'] ?> </strong> </td>
                            <td><?= number_format($trip['price'], 2) ?> TL</td>
                            
                            <td class="action-buttons">
                                <a href="trip_passengers.php?trip_id=<?= $trip['id'] ?>" class="manage-link">Yönet</a>
                                
                                <form action="company_admin_panel.php" method="POST" onsubmit="return confirm('Bu seferi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');">
                                    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                    <button type="submit" name="delete_trip" class="delete-button">Sil</button>
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