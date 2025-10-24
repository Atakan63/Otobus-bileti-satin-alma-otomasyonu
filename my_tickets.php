<?php
session_start();

require 'db.php'; 
require 'functions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
	$_SESSION['alert_type'] = 'error';
	$_SESSION['alert_message'] = 'Biletlerinizi görmek için kullanıcı olarak giriş yapmalısınız.';
	header('Location: login.php'); 
	exit;
}

$user_id = $_SESSION['user_id'];
$message_for_alert = '';
$alert_type = 'info';

if (isset($_SESSION['alert_message'])) {
	$message_for_alert = $_SESSION['alert_message'];
	$alert_type = $_SESSION['alert_type'] ?? 'info';
	unset($_SESSION['alert_message']); 
	unset($_SESSION['alert_type']); 	
}

$tickets = [];
$critical_error = null;

try {
	$tickets = getUserTickets($pdo, $user_id); 
	
	if ($tickets === false) {
		$critical_error = "Biletleriniz yüklenirken bir sorun oluştu.";
		$tickets = [];
	}

} catch (PDOException $e) {
	$critical_error = "Veritabanı hatası nedeniyle biletleriniz yüklenemedi.";
	error_log("my_tickets.php DB Error: " . $e->getMessage());
	$tickets = [];
}

$active_tickets = [];
$past_and_canceled_tickets = [];
$current_time = time();

if (is_array($tickets)) { 
	foreach ($tickets as $ticket) {
		$departure_timestamp = strtotime($ticket['departure_time']);
		$is_past = $departure_timestamp < $current_time;
		if (isset($ticket['status']) && strtolower($ticket['status']) === 'active' && !$is_past) { 
			$active_tickets[] = $ticket;
		} else {
			$past_and_canceled_tickets[] = $ticket;
		}
	}
}

$pdo = null;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Biletlerim - Bilet Platformu</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<style>
		body { background-color: #f0f2f5; }
		.card { border: none; border-radius: 0.75rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05); }
		.card-header { background-color: #0d6efd; color: white; }
		.table th { font-weight: 600; font-size: 0.85em; color: #495057; background-color: #e9ecef; border-top: none; }
		.table td { vertical-align: middle; font-size: 0.9em; }
		.table-hover tbody tr:hover { background-color: rgba(13, 110, 253, 0.06); }
		.status-active { color: #198754; font-weight: 500; } 
		.status-cancelled, .status-canceled { color: #dc3545; text-decoration: line-through; opacity: 0.7; } 
		.status-past { color: #6c757d; font-style: italic; }
		.btn-pdf { font-size: 0.8em !important; padding: 0.2rem 0.5rem !important; }
		.link-danger { color: #dc3545 !important; text-decoration: none; }
		.link-danger:hover { text-decoration: underline !important; }
		.action-links a { margin: 0 0.5rem; }
	</style>
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top mb-4">
		<div class="container">
			<a class="navbar-brand fw-bold text-primary" href="index.php">A-Bilet</a>
			<div class="ms-auto">
				<a href="account.php" class="btn btn-outline-secondary btn-sm me-2"><i class="fas fa-user-circle me-1"></i>Hesabım</a>
				<a href="logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i>Çıkış Yap</a>
			</div>
		</div>
	</nav>

	<div class="container my-5">
		<h1 class="text-center mb-5 display-6 text-primary"><i class="fas fa-ticket-alt me-2"></i>Biletlerim</h1>

		<?php if ($critical_error): ?>
			<div class="alert alert-danger text-center shadow-sm"><?php echo $critical_error; ?></div>
		<?php else: ?>
			<div class="card shadow-sm mb-4">
				<div class="card-header">
					<h2 class="h5 mb-0"><i class="fas fa-clock me-2"></i>Aktif ve Gelecek Biletler</h2>
				</div>
				<div class="card-body p-0">
					<?php if (empty($active_tickets)): ?>
						<p class="text-center text-muted p-4 mb-0">Aktif biletiniz bulunmamaktadır.</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-hover table-sm align-middle mb-0">
								<thead><tr><th>Güzergah</th><th>Firma</th><th>Kalkış</th><th>Fiyat</th><th class="text-end">İşlemler</th></tr></thead>
								<tbody>
									<?php foreach ($active_tickets as $ticket): ?>
									<tr>
										<td class="fw-medium"><?php echo htmlspecialchars($ticket['departure_city']); ?>&rarr;<?php echo htmlspecialchars($ticket['destination_city']); ?></td>
										<td><?php echo htmlspecialchars($ticket['company_name']); ?></td>
										<td><?php echo date('d.m.Y H:i', strtotime($ticket['departure_time'])); ?></td>
										<td class="text-nowrap"><?php echo htmlspecialchars(number_format($ticket['total_price'], 2)); ?> TL</td>
										<td class="text-end text-nowrap">
											<a href="cancel_ticket.php?id=<?php echo htmlspecialchars($ticket['ticket_id']); ?>" 
												onclick="return confirm('Biletinizi iptal etmek istediğinizden emin misiniz? Ücret iade edilir.');"
												class="link-danger me-2" title="İptal Et"><i class="fas fa-times-circle me-1"></i>İptal</a>
											<a href="generate_pdf.php?ticket_uuid=<?php echo htmlspecialchars($ticket['ticket_uuid']); ?>" class="btn btn-danger btn-pdf" target="_blank" title="PDF İndir"><i class="fas fa-file-pdf"></i> PDF</a>
										</td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="card shadow-sm">
				<div class="card-header">
					<h2 class="h5 mb-0"><i class="fas fa-history me-2"></i>Geçmiş ve İptal Edilmiş Biletler</h2>
				</div>
				<div class="card-body p-0">
					<?php if (empty($past_and_canceled_tickets)): ?>
						<p class="text-center text-muted p-4 mb-0">Geçmiş veya iptal edilmiş biletiniz bulunmamaktadır.</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-hover table-sm align-middle mb-0">
								<thead><tr><th>Güzergah</th><th>Firma</th><th>Kalkış</th><th>Fiyat</th><th>Durum</th><th class="text-end">İşlem</th></tr></thead>
								<tbody>
									<?php foreach ($past_and_canceled_tickets as $ticket): 
										$departure_timestamp = strtotime($ticket['departure_time']);
										$is_past = $departure_timestamp < $current_time;
										$status_raw = strtolower($ticket['status'] ?? 'unknown'); 
										$status_class = 'status-' . $status_raw; 
										
										if ($is_past && $status_raw === 'active') { 
											$display_status = 'GEÇMİŞ'; $status_class = 'status-past'; 
										} else { 
											$display_status = htmlspecialchars(strtoupper($status_raw == 'unknown' ? $ticket['status'] : $status_raw)); 
										} 
									?>
									<tr>
										<td><?php echo htmlspecialchars($ticket['departure_city']); ?>&rarr;<?php echo htmlspecialchars($ticket['destination_city']); ?></td>
										<td><?php echo htmlspecialchars($ticket['company_name']); ?></td>
										<td><?php echo date('d.m.Y H:i', strtotime($ticket['departure_time'])); ?></td>
										<td class="text-nowrap"><?php echo htmlspecialchars(number_format($ticket['total_price'], 2)); ?> TL</td>
										<td class="<?php echo $status_class; ?>"><?php echo $display_status; ?></td>
										<td class="text-end"><a href="generate_pdf.php?ticket_uuid=<?php echo htmlspecialchars($ticket['ticket_uuid']); ?>" class="btn btn-danger btn-pdf" target="_blank" title="PDF İndir"><i class="fas fa-file-pdf"></i> PDF</a></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="text-center mt-5 action-links border-top pt-4">
				<a href="index.php" class="btn btn-outline-primary"><i class="fas fa-home me-1"></i>Ana Sayfa</a>
				<a href="account.php" class="btn btn-outline-secondary"><i class="fas fa-user-cog me-1"></i>Hesap Ayarları</a>
			</div>
		<?php endif; ?>

	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const alertMessage = <?php echo json_encode($message_for_alert); ?>; 
			const alertIcon = <?php echo json_encode($alert_type); ?>; 

			if (alertMessage && alertMessage.trim() !== '') {
				Swal.fire({
					icon: alertIcon, 
					title: alertIcon === 'success' ? 'İşlem Başarılı!' : 'Bilgi / Hata', 
					text: alertMessage.replace(/<[^>]*>?/gm, ''),
					confirmButtonText: 'Tamam',
					customClass: { confirmButton: 'btn btn-primary' }, 
					buttonsStyling: false 
				});

				if (window.history.replaceState) {
					const url = new URL(window.location);
					url.searchParams.delete('msg');
					window.history.replaceState({ path: url.href }, '', url.href);
				}
			}
		});
	</script>
</body>
</html>