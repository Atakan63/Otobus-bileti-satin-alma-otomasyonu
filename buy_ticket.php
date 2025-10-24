<?php
session_start();
require_once 'db.php'; 
require_once 'functions.php'; 

if (!isset($_SESSION['user_id'])) {
	header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI'])); 
	exit;
}
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'user') {
	$_SESSION['alert_type'] = 'error';
	$_SESSION['alert_message'] = 'Bilet almak için normal kullanıcı olarak giriş yapmalısınız.';
	header("Location: index.php"); 
	exit;
}

if (!isset($_GET['trip_uuid'])) {
	header("Location: index.php");
	exit;
}
$trip_uuid = $_GET['trip_uuid'];

$trip = getTripDetails($pdo, $trip_uuid); 
if (!$trip) { 
	die("Hata: İstenen sefer bulunamadı veya geçersiz. <a href='index.php'>Ana Sayfaya Dön</a>"); 
}

$booked_seats = getBookedSeats($pdo, $trip['id']); 
$total_capacity = (int)$trip['capacity'];

$error_message = '';
$success_message = '';
$coupon_code_value = '';
$selected_seats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$selected_seats = $_POST['selected_seats'] ?? [];
	$coupon_code_value = trim($_POST['coupon_code'] ?? ''); 
	$coupon_id_from_post = null;

	if (!empty($coupon_code_value)) {
		$coupon = findCoupon($pdo, $coupon_code_value);
		if ($coupon) {
			$coupon_id_from_post = $coupon['id'];
		} else {
			$error_message = "Girilen kupon kodu satın alma sırasında geçersiz bulundu. Lütfen kaldırıp tekrar deneyin.";
			$coupon_code_value = '';
		}
	}


	if (empty($selected_seats)) {
		$error_message = "Lütfen en az bir koltuk seçin.";
	} elseif(empty($error_message)) {
		$current_booked_seats = getBookedSeats($pdo, $trip['id']);
		$already_taken = array_intersect($selected_seats, $current_booked_seats);

		if (!empty($already_taken)) {
			$error_message = "Üzgünüz, siz işlem yaparken " . implode(', ', $already_taken) . " numaralı koltuk(lar) başkası tarafından alındı. Lütfen boş koltuklardan tekrar seçin.";
			$booked_seats = $current_booked_seats; 
		} else {
			$base_price_per_seat = (float)$trip['price'];
			$seat_count = count($selected_seats);
			$total_base_price = $seat_count * $base_price_per_seat;
			$final_price = $total_base_price;
			$applied_coupon_details = null;

			if ($coupon_id_from_post) {
				$coupon_check_stmt = $pdo->prepare("SELECT discount FROM Coupons WHERE id = :id");
				$coupon_check_stmt->execute([':id' => $coupon_id_from_post]);
				$coupon_data = $coupon_check_stmt->fetch();
				if ($coupon_data) {
					$discount_rate = (float)$coupon_data['discount'];
					$final_price = max(0, $total_base_price * (1 - $discount_rate));
					$applied_coupon_details = ['id' => $coupon_id_from_post, 'discount' => $discount_rate];
				}
			}


			$result = processTicketPurchase(
				$pdo,
				(int)$_SESSION['user_id'], 
				(int)$trip['id'], 
				$selected_seats, 
				round($final_price, 2), 
				$coupon_id_from_post
			);

			if ($result === true) {
				$success_message = "Biletleriniz başarıyla oluşturuldu! Toplam Tutar: " . number_format($final_price, 2) . " TL.";
				if ($applied_coupon_details) {
					$success_message .= " (%". round($applied_coupon_details['discount']*100) . " indirim uygulandı)";
				}
				$success_message .= " Hesabım sayfasına yönlendiriliyorsunuz...";
				
				$_SESSION['alert_type'] = 'success';
				$_SESSION['alert_message'] = $success_message;
				header("Location: my_tickets.php"); 
				exit; 

			} else {
				$error_message = $result; 
			}
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
	<title>Bilet Satın Alma - <?php echo htmlspecialchars($trip['company_name'] ?? 'Firma'); ?></title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #eef2f7; margin: 0; padding: 20px; }
		.card { border: none; border-radius: .75rem; box-shadow: 0 .5rem 1rem rgba(0,0,0,.05); }
		.card-header { background-color: #0d6efd; color: white; font-weight: 500;}
		.bus-container { border: 2px solid #dee2e6; border-radius: 10px; padding: 20px; background: #fff; max-width: 400px; margin: 0 auto; }
		.bus-front { display: flex; align-items: center; justify-content: space-between; background-color: #f1f3f5; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
		.bus-front .driver-cab { font-size: 2rem; color: #495057; }
		.bus-front .entry { writing-mode: vertical-rl; transform: rotate(180deg); font-size: 0.8rem; color: #6c757d; font-weight: 500; }
		.seat-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
		.aisle { grid-column: 3; width: 100%; }
		.seat { aspect-ratio: 1 / 1; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease-in-out; border: 1px solid #ced4da; user-select: none; font-size: 0.9rem; position: relative;}
		.seat.available { background-color: #fff; color: #495057; }
		.seat.available:hover { background-color: #e9ecef; transform: scale(1.05); border-color: #adb5bd; }
		.seat.booked { background-color: #dc3545; color: white; cursor: not-allowed; opacity: 0.6; border-color: #dc3545; }
		.seat.selected { background-color: #0d6efd; color: white; border-color: #0a58ca; transform: scale(1.1); box-shadow: 0 0 10px rgba(13, 110, 253, 0.5); }
		.seat-checkbox { position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; left:0; top:0; margin:0;}
		.seat-checkbox:disabled { cursor: not-allowed; }
		.legend { display: flex; justify-content: center; flex-wrap: wrap; gap: 20px; margin-top: 20px; font-size: 0.9em; }
		.legend div { display: flex; align-items: center; }
		.legend span { width: 20px; height: 20px; border-radius: 5px; margin-right: 8px; border: 1px solid #ccc; }
		#total-price-display { font-size: 2rem; font-weight: bold; color: #198754; }
		.checkout-box { position: sticky; top: 20px; } 
		.form-text.text-success { font-weight: 500; }
		.form-text.text-danger { font-weight: 500; }
	</style>
</head>
<body>

	<div class="container my-5">
		<div class="text-center mb-4">
			<a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Seferlere Geri Dön</a>
		</div>

		<?php if ($error_message): ?>
			<div class="alert alert-danger text-center shadow-sm" role="alert">
				<i class="fas fa-exclamation-triangle fs-4 me-2"></i>
				<?= htmlspecialchars($error_message) ?>
			</div>
		<?php endif; ?>

		<?php if (!$success_message): ?>
		<form action="buy_ticket.php?trip_uuid=<?= htmlspecialchars($trip_uuid) ?>" method="POST" id="buyTicketForm">
			<input type="hidden" name="action" value="buy_ticket"> <input type="hidden" name="coupon_code" value=""> <div class="row g-4">
				<div class="col-lg-7">
					<div class="card shadow-sm">
						<div class="card-header text-center">
							<h2 class="h5 mb-0 py-1">Koltuk Seçimi (<?php echo count($booked_seats); ?>/<?php echo $total_capacity; ?> Dolu)</h2>
						</div>
						<div class="card-body">
							<?php if ($total_capacity > 0): ?>
								<div class="bus-container">
									<div class="bus-front"><i class="fas fa-user-tie driver-cab" title="Şoför"></i><span class="entry">KAPI</span></div>
									<div class="seat-grid">
										<?php 
										for ($i = 1; $i <= $total_capacity; $i++): 
											$is_booked = in_array($i, $booked_seats);
											$class = $is_booked ? 'booked' : 'available';
											$is_checked = !$is_booked && isset($_POST['selected_seats']) && in_array($i, $_POST['selected_seats']);
											if ($is_checked) $class .= ' selected'; 
										?>
											<div class="seat <?php echo $class; ?>" data-seat-number="<?php echo $i; ?>">
												<input
													type="checkbox"
													name="selected_seats[]"
													value="<?= $i ?>"
													id="seat-<?= $i ?>"
													class="seat-checkbox"
													<?= $is_booked ? 'disabled' : '' ?>
													<?= $is_checked ? 'checked' : '' ?> 
													title="Koltuk No: <?php echo $i; ?><?php echo $is_booked ? ' (Dolu)' : ''; ?>"
												>
												<label for="seat-<?= $i ?>" class="seat-label-overlay"><?= $i ?></label> 
											</div>
											<?php if ($i % 4 == 2 && $i < $total_capacity): ?><div class="aisle"></div><?php endif; ?>
										<?php endfor; ?>
									</div>
								</div>
								<div class="legend">
									<div><span style="background-color: #fff; border: 1px solid #ced4da;"></span> Boş</div>
									<div><span style="background-color: #dc3545;"></span> Dolu</div>
									<div><span style="background-color: #0d6efd;"></span> Seçili</div>
								</div>
							<?php else: ?>
								<p class="text-center text-muted">Koltuk planı bulunmamaktadır.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="col-lg-5">
					<div class="checkout-box card shadow-sm">
						<div class="card-header text-center">
							<h3 class="h5 mb-0 py-1">Ödeme Detayları</h3>
						</div>
						<div class="card-body">
							<div class="mb-3">
								<label class="form-label fw-medium">Sefer Bilgisi:</label>
								<p class="mb-1"><small><?= htmlspecialchars($trip['company_name']) ?></small></p>
								<p class="mb-1"><small><?= htmlspecialchars($trip['departure_city']) ?> &rarr; <?= htmlspecialchars($trip['destination_city']) ?></small></p>
								<p class="mb-0"><small><?= date('d.m.Y H:i', strtotime($trip['departure_time'])) ?></small></p>
							</div>
							<hr>
							<div class="mb-3">
								<label class="form-label fw-medium">Seçilen Koltuklar:</label>
								<p id="selected-seats-display" class="fw-bold">Yok</p> </div>
							<div class="mb-3">
								<label for="coupon_code_display" class="form-label fw-medium">Kupon Kodu (Varsa):</label>
								<div class="input-group">
									<input type="text" class="form-control" id="coupon_code_display" placeholder="İndirim Kodu" value="<?= htmlspecialchars($coupon_code_value) ?>">
									<button type="button" class="btn btn-outline-secondary" id="apply-coupon-btn">Uygula</button>
								</div>
								<small id="coupon-message" class="form-text mt-1 d-block"></small>
							</div>
							<hr>
							<div class="text-center mb-4">
								<span class="fs-6 text-muted d-block mb-1">Ödenecek Tutar</span>
								<span id="total-price-display">0.00 TL</span>
							</div>
							<button type="submit" id="buy-btn" class="btn btn-success w-100 btn-lg fw-bold" disabled>
								<i class="fas fa-credit-card me-2"></i>Güvenle Öde
							</button>
						</div>
					</div>
				</div>
			</div>
		</form>
		<?php endif; ?>
	</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const basePricePerSeat = parseFloat(<?php echo $trip['price'] ?? 0; ?>);
	const seatsCheckboxes = document.querySelectorAll('.seat-checkbox:not(:disabled)'); 
	const selectedDisplay = document.getElementById('selected-seats-display'); 
	const totalDisplay = document.getElementById('total-price-display');
	const discountDisplay = null;
	const couponInput = document.getElementById('coupon_code_display');
	const applyCouponBtn = document.getElementById('apply-coupon-btn');
	const couponMessage = document.getElementById('coupon-message'); 
	const buyBtn = document.getElementById('buy-btn');
	const tripUuid = '<?php echo $trip_uuid; ?>'; 
	const hiddenCouponInputForPost = document.querySelector('input[name="coupon_code"]'); 

	let appliedCouponCode = "<?php echo htmlspecialchars($coupon_code_value); ?>"; 
	let currentDiscountRate = 0.0; 
	let couponAppliedSuccessfully = false; 

	function formatPrice(number) {
		number = number || 0; 
		return number.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' TL';
	}

	function updateTotals() {
		const selectedSeats = document.querySelectorAll('.seat-checkbox:checked');
		const currentSeatCount = selectedSeats.length;
		
		let seatNumbers = [];
		selectedSeats.forEach(checkbox => { 
			seatNumbers.push(checkbox.value); 
			const seatDiv = checkbox.closest('.seat');
			if (seatDiv) seatDiv.classList.add('selected');
		});
		document.querySelectorAll('.seat-checkbox:not(:checked)').forEach(checkbox => {
			const seatDiv = checkbox.closest('.seat');
			if (seatDiv && !checkbox.disabled) seatDiv.classList.remove('selected');
		});
		
		selectedDisplay.textContent = currentSeatCount > 0 ? seatNumbers.join(', ') : 'Yok'; 
		
		buyBtn.disabled = currentSeatCount === 0;

		let totalBase = currentSeatCount * basePricePerSeat;
		let finalTotal = totalBase * (1 - currentDiscountRate); 
		let discountAmount = totalBase - finalTotal; 
		finalTotal = Math.max(0, finalTotal); 

		if (discountDisplay) { 
			discountDisplay.textContent = formatPrice(discountAmount);
		}
		totalDisplay.textContent = formatPrice(finalTotal);
	}

	seatsCheckboxes.forEach(checkbox => {
		checkbox.addEventListener('change', updateTotals); 
	});

	applyCouponBtn.addEventListener('click', function() {
		const code = couponInput.value.trim().toUpperCase(); 
		const selectedSeatsCount = document.querySelectorAll('.seat-checkbox:checked').length;
		if (selectedSeatsCount === 0) {
			Swal.fire({icon: 'warning', title: 'Uyarı', text: 'Lütfen önce bir koltuk seçin.'});
			return;
		}

		if (!code) {
			currentDiscountRate = 0.0; appliedCouponCode = ''; couponInput.value = '';
			couponMessage.textContent = 'Kupon kodu kaldırıldı.'; couponMessage.className = 'form-text mt-1 text-info'; 
			couponInput.classList.remove('is-invalid', 'is-valid'); couponInput.readOnly = false;
			applyCouponBtn.textContent = 'Kuponu Uygula';
			applyCouponBtn.classList.remove('btn-success'); applyCouponBtn.classList.add('btn-outline-secondary');
			couponAppliedSuccessfully = false;
			updateTotals();
			return;
		}

		applyCouponBtn.disabled = true;
		applyCouponBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Kontrol...';
		couponMessage.textContent = '';
		couponInput.classList.remove('is-invalid', 'is-valid');

		fetch('check_coupon.php', { 
			method: 'POST', 
			headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, 
			body: JSON.stringify({ code: code, trip_uuid: tripUuid }) 
		})
		.then(response => {
			if (!response.ok) { throw new Error('Sunucu hatası: ' + response.status); }
			return response.json();
		})
		.then(data => {
			if (data.success && data.discount !== undefined && data.discount > 0) { 
				currentDiscountRate = parseFloat(data.discount); 
				appliedCouponCode = code; 
				couponMessage.textContent = `Başarılı! %${(currentDiscountRate * 100).toFixed(0)} indirim uygulandı.`; 
				couponMessage.className = 'form-text mt-1 text-success'; 
				couponInput.classList.remove('is-invalid'); couponInput.classList.add('is-valid');
				couponInput.readOnly = true; 
				applyCouponBtn.textContent = 'Uygulandı';
				applyCouponBtn.classList.remove('btn-outline-secondary'); applyCouponBtn.classList.add('btn-success');
				couponAppliedSuccessfully = true; 
			} else { 
				currentDiscountRate = 0.0; appliedCouponCode = '';
				couponMessage.textContent = data.message || 'Geçersiz veya uygulanamayan kupon.';
				couponMessage.className = 'form-text mt-1 text-danger'; 
				couponInput.classList.remove('is-valid'); couponInput.classList.add('is-invalid');
				couponInput.readOnly = false;
				applyCouponBtn.textContent = 'Kuponu Uygula';
				applyCouponBtn.classList.add('btn-outline-secondary');
				applyCouponBtn.classList.remove('btn-success');
				couponAppliedSuccessfully = false; 
			}
			updateTotals(); 
		})
		.catch(err => { 
			Swal.fire({icon: 'error', title: 'İstek Hatası', text: 'Kupon kontrolü sırasında bir sorun oluştu: ' + err.message});
			console.error('Fetch Hatası:', err);
			currentDiscountRate = 0.0; appliedCouponCode = ''; couponInput.readOnly = false;
			applyCouponBtn.textContent = 'Kuponu Uygula';
			applyCouponBtn.classList.add('btn-outline-secondary');
			applyCouponBtn.classList.remove('btn-success');
			couponAppliedSuccessfully = false;
		})
		.finally(() => { if (!couponAppliedSuccessfully) { applyCouponBtn.disabled = false; } });
	});
	
	const buyTicketForm = document.getElementById('buyTicketForm');
	if(buyTicketForm && hiddenCouponInputForPost) {
		buyTicketForm.addEventListener('submit', function() {
			hiddenCouponInputForPost.value = couponAppliedSuccessfully ? appliedCouponCode : ''; 
		});
	} else {
		console.error("Form veya kupon için hidden input bulunamadı!"); 
	}

	if (appliedCouponCode && !couponInput.readOnly) { 
		couponInput.value = appliedCouponCode; 
	}
	
	updateTotals(); 

});
</script>

</body>
</html>