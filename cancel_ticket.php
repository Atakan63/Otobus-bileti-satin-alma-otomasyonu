<?php

session_start();

require 'db.php'; 
require 'functions.php'; 


if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
    $_SESSION['alert_type'] = 'error';
    $_SESSION['alert_message'] = 'Bilet iptali için giriş yapmalısınız.';
    header('Location: login.php');
    exit;
}


$ticket_id_to_cancel = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];


$redirect_url = 'my_tickets.php'; 


if (empty($ticket_id_to_cancel)) {
   
    $_SESSION['alert_type'] = 'error';
    $_SESSION['alert_message'] = 'Hata: İptal edilecek bilet belirtilmedi.';
    header("Location: $redirect_url");
    exit;
}


$result = null;
$result_type = 'error'; 

try {
   
    $cancel_result = cancelTicket($pdo, (int)$ticket_id_to_cancel, $user_id); 

    if ($cancel_result === true) {
        $result = "Bilet başarıyla iptal edildi. Ücret iadesi bakiyenize yapıldı.";
        $result_type = 'success';
    } else {
       
        $result = $cancel_result; 
    }

} catch (Exception $e) {
     $result = "Bilet iptali sırasında beklenmedik bir sunucu hatası oluştu.";
     error_log("Bilet İptal Hatası (cancel_ticket.php): " . $e->getMessage());
}


$pdo = null;


$_SESSION['alert_type'] = $result_type;
$_SESSION['alert_message'] = $result;
header("Location: $redirect_url");
exit;
?>