<?php
session_start();
require_once 'functions.php';


if (!isset($_SESSION['user_id'])) { header("Location: login_register.php"); exit; }
if ($_SESSION['user_role'] !== 'company_admin') { header("Location: index.php"); exit; }
if (empty($_SESSION['user_company_id'])) { die("Hata: Hesabınız bir firmaya atanmamış."); }
if (!isset($_GET['trip_id']) || !is_numeric($_GET['trip_id'])) { header("Location: company_admin_panel.php"); exit; }

$company_id = $_SESSION['user_company_id'];
$trip_id = (int)$_GET['trip_id'];
$message = '';
$message_type = 'success';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_passenger_ticket'])) {
    $ticket_id_to_cancel = (int)($_POST['ticket_id'] ?? 0);
    if($ticket_id_to_cancel > 0) {
        $result = cancelTicketByAdmin($pdo, $ticket_id_to_cancel, $company_id);
        if ($result === true) {
            $message = "Yolcunun bileti başarıyla iptal edildi ve tutarı iade edildi.";
            $message_type = 'success';
        } else {
            $message = $result;
            $message_type = 'error';
        }
    } else {
        $message = "Geçersiz bilet ID'si.";
        $message_type = 'error';
    }
}


$company_details = getCompanyDetails($pdo, $company_id);
$stmt_trip = $pdo->prepare("SELECT * FROM Trips WHERE id = :trip_id AND company_id = :company_id");
$stmt_trip->execute([':trip_id' => $trip_id, ':company_id' => $company_id]);
$trip_details = $stmt_trip->fetch();
if (!$trip_details) { header("Location: company_admin_panel.php?error=trip_not_found"); exit; }


$tickets = getTicketsForTrip($pdo, $trip_id, $company_id);
if ($tickets === false) { header("Location: company_admin_panel.php?error=unauthorized"); exit; }

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yolcu Listesi - A-bilet</title>
    <style>
         body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f0f8ff; color: #333; margin: 0; padding: 20px; }
        .top-nav { background-color: #fff; padding: 10px 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); max-width: 1200px; margin: -20px auto 20px auto; border-radius: 0 0 8px 8px; display: flex; justify-content: space-between; align-items: center; }
        .top-nav .logo { font-size: 1.8em; font-weight: bold; color: #000080; text-decoration: none; }
        .top-nav .user-info { display: flex; align-items: center; gap: 15px; }
        .top-nav a { color: #0000CD; text-decoration: none; font-weight: bold; }
        .top-nav a:hover { text-decoration: underline; }
        .container { max-width: 1200px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        h1 { color: #000080; text-align: left; margin-top: 0; }
        h2 { color: #0000CD; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .success { background-color: #d6eaff; color: #000080; }
        .error { background-color: #fdd; color: #e74c3c; }
        .breadcrumb { margin-bottom: 20px; font-size: 0.9em; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .trip-summary { background-color: #e6f2ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .passenger-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .passenger-table th, .passenger-table td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: middle; font-size: 0.9em; }
        .passenger-table th { background-color: #e6f2ff; color: #000080; }
        .passenger-table tr:nth-child(even) { background-color: #f9f9f9; }
        .passenger-table .status-active { color: #3498db; font-weight: bold; }
        .passenger-table .status-cancelled { color: #e74c3c; text-decoration: line-through; }
        .passenger-table .status-expired { color: #7f8c8d; } 
        .passenger-table .cancel-button { background-color: #e74c3c; color: white; border: none; padding: 5px 8px; border-radius: 4px; cursor: pointer; font-size: 0.85em; }
        .passenger-table .cancel-button:hover { background-color: #c0392b; }
        .passenger-table .cancel-button:disabled { background-color: #bdc3c7; cursor: not-allowed; }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="index.php" class="logo">A-bilet</a>
        <div class="user-info">
            <span>Hoş geldin, <strong><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Misafir') ?></strong>!</span>
            <a href="account.php">Hesabım</a>
            <a href="company_admin_panel.php">Firma Paneli</a>
            <a href="coupon_management.php">Kupon Yönetimi</a>
            <a href="logout.php">Çıkış Yap</a>
        </div>
    </div>

    <div class="container">
        <p class="breadcrumb"><a href="company_admin_panel.php">Firma Paneli</a> &gt; Yolcu Listesi</p>
        <h1>Yolcu Listesi ve Bilet İptali</h1>

        <div class="trip-summary">
            <h2>Sefer Bilgileri</h2>
            <p>
                <strong>Güzergah:</strong> <?= htmlspecialchars($trip_details['departure_city']) ?> &rarr; <?= htmlspecialchars($trip_details['destination_city']) ?><br>
                <strong>Kalkış:</strong> <?= date('d M Y - H:i', strtotime($trip_details['departure_time'])) ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <table class="passenger-table">
            <thead>
                <tr>
                    <th>Yolcu Adı</th>
                    <th>E-posta</th>
                    <th>Koltuk No(lar)</th>
                    <th>Ödenen Tutar</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="6" style="text-align: center;">Bu sefer için henüz bilet satılmamıştır.</td></tr>
                <?php endif; ?>
                <?php foreach ($tickets as $ticket): ?>
                    <?php $status_class = 'status-' . htmlspecialchars($ticket['status']); ?>
                    <tr>
                        <td><?= htmlspecialchars($ticket['passenger_name']) ?></td>
                        <td><?= htmlspecialchars($ticket['passenger_email']) ?></td>
                        <td><?= htmlspecialchars($ticket['seat_numbers'])  ?></td>
                        <td><?= number_format($ticket['total_price'], 2) ?> TL</td>
                        <td class="<?= $status_class ?>"><?= htmlspecialchars($ticket['status']) ?></td>
                        <td>
                            <?php if ($ticket['status'] === 'active'): ?>
                                <form action="trip_passengers.php?trip_id=<?= $trip_id ?>" method="POST" onsubmit="return confirm('Bu yolcunun biletini iptal etmek istediğinizden emin misiniz? Tutar yolcuya iade edilecektir.');">
                                    <input type="hidden" name="ticket_id" value="<?= $ticket['ticket_id'] ?>">
                                    <button type="submit" name="cancel_passenger_ticket" class="cancel-button">Bileti İptal Et</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>

</body>
</html>