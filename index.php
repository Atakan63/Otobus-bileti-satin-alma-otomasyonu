<?php
session_start();
require_once 'db.php';
require_once 'functions.php';

$all_cities = ['Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin', 'Aydın', 'Balıkesir', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkâri', 'Hatay', 'Isparta', 'Mersin', 'İstanbul', 'İzmir', 'Kars', 'Kastamonu', 'Kayseri', 'Kırklareli', 'Kırşehir', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Şanlıurfa', 'Uşak', 'Van', 'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt', 'Karaman', 'Kırıkkale', 'Batman', 'Şırnak', 'Bartın', 'Ardahan', 'Iğdır', 'Yalova', 'Karabük', 'Kilis', 'Osmaniye', 'Düzce'];
sort($all_cities);

$trips = [];
$all_upcoming_trips = [];
$form_submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_trips'])) {
	$form_submitted = true;
	$departure_city = trim($_POST['departure_city'] ?? '');
	$destination_city = trim($_POST['destination_city'] ?? '');
	$trip_date = trim($_POST['trip_date'] ?? '');

	if (!empty($departure_city) && !empty($destination_city) && !empty($trip_date)) {
		$trips = searchTrips($pdo, $departure_city, $destination_city, $trip_date);
	}
} else {
	$form_submitted = false;
	$all_upcoming_trips = getAllUpcomingTrips($pdo, 20);
}

$pdo = null;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Bilet Platformu - Ana Sayfa</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
	<style>
		body { background-color: #f0f8ff; }
		.navbar { background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,.05); }
		.navbar-brand { color: #000080 !important; font-weight: bold; font-size: 1.5rem; }
		.nav-link { color: #333 !important; font-weight: 500; }
		.nav-link:hover { color: #0000CD !important; }
		.navbar-text strong { color: #000080; }
		.container.search-container {
			background-color: #ffffff;
			padding: 2.5rem;
			border-radius: 0.5rem;
			box-shadow: 0 0.5rem 1rem rgba(0,0,0,.05);
			margin-top: 2rem;
			border-top: 5px solid #0d6efd;
		}
		.form-label { font-weight: 600; color: #495057; }
		.btn-primary { background-color: #0000CD; border-color: #0000CD; }
		.btn-primary:hover { background-color: #000080; border-color: #000080; }
		.results-section h2 { color: #000080; border-bottom: 2px solid #0000CD; padding-bottom: 0.5rem; margin-bottom: 1.5rem; }
		.trip-card {
			border: 1px solid #dee2e6;
			border-left: 5px solid #0d6efd;
			transition: box-shadow 0.2s ease-in-out;
			margin-bottom: 1rem;
		}
		.trip-card:hover { box-shadow: 0 0.5rem 1rem rgba(0,0,0,.1); }
		.trip-price { color: #000080; font-weight: bold; font-size: 1.5rem; }
		.upcoming-trips .card-body { padding-top: 1rem; padding-bottom: 1rem;}
		.upcoming-trips .fw-bold { color: #000080;}
	</style>
</head>
<body>

	<nav class="navbar navbar-expand-lg mb-4">
		<div class="container" style="max-width: 900px;">
			<a class="navbar-brand" href="index.php">A-Bilet</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="mainNav">
				<ul class="navbar-nav ms-auto align-items-lg-center">
					<?php if (isset($_SESSION['user_id'])): ?>
						<li class="nav-item">
							<span class="navbar-text me-3">
								Hoş geldin, <strong><?= htmlspecialchars($_SESSION['user_fullname'] ?? 'Kullanıcı') ?></strong>!
							</span>
						</li>

						<?php if (isset($_SESSION['user_role'])): ?>
							<?php if ($_SESSION['user_role'] == 'user'): ?>
								<li class="nav-item">
									<a class="nav-link" href="account.php"><i class="fas fa-user-circle me-1"></i>Hesabım</a>
								</li>
								<li class="nav-item">
									<a class="nav-link" href="my_tickets.php"><i class="fas fa-ticket-alt me-1"></i>Biletlerim</a>
								</li>
								<li class="nav-item">
									<span class="navbar-text me-3">
										<i class="fas fa-wallet me-1"></i>Bakiye:
										<strong class="text-success">
											<?php
											echo isset($_SESSION['user_balance']) ? number_format((float)$_SESSION['user_balance'], 2, ',', '.') : '0,00';
											?> TL
										</strong>
									</span>
								</li>
							<?php elseif ($_SESSION['user_role'] == 'admin'): ?>
								<li class="nav-item">
									<a class="nav-link fw-bold text-danger" href="admin_panel.php"><i class="fas fa-cogs me-1"></i>Admin Paneli</a>
								</li>
							<?php elseif ($_SESSION['user_role'] == 'company_admin'): ?>
								<li class="nav-item">
									<a class="nav-link" href="account.php"><i class="fas fa-user-circle me-1"></i>Hesabım</a>
								</li>
								<li class="nav-item">
									<a class="nav-link fw-bold text-info" href="sefer.php"><i class="fas fa-bus-alt me-1"></i>Firma Paneli</a>
								</li>
							<?php endif; ?>
						<?php endif; ?>

						<li class="nav-item">
							<a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Çıkış Yap</a>
						</li>
					<?php else: ?>
						<li class="nav-item">
							<a class="nav-link" href="login_register.php">Giriş Yap / Kayıt Ol</a>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</nav>

	<div class="container search-container p-4 p-md-5 rounded shadow-sm" style="max-width: 900px;">
		<h1 class="text-center display-6 mb-3">Sefer Arama</h1>
		<p class="text-center text-muted mb-4">Türkiye'nin her yerine en uygun otobüs biletini bulun.</p>

		<form action="index.php" method="POST" class="mb-4">
			<input type="hidden" name="search_trips" value="1">
			<div class="row g-3 align-items-end">
				<div class="col-md">
					<label for="departure_city" class="form-label">Nereden</label>
					<input list="cities" class="form-control form-control-lg" id="departure_city" name="departure_city" placeholder="Kalkış şehri" required value="<?= htmlspecialchars($_POST['departure_city'] ?? '') ?>">
				</div>
				<div class="col-md">
					<label for="destination_city" class="form-label">Nereye</label>
					<input list="cities" class="form-control form-control-lg" id="destination_city" name="destination_city" placeholder="Varış şehri" required value="<?= htmlspecialchars($_POST['destination_city'] ?? '') ?>">
				</div>
				<div class="col-md-3">
					<label for="trip_date" class="form-label">Tarih</label>
					<input type="date" class="form-control form-control-lg" id="trip_date" name="trip_date" value="<?= htmlspecialchars($_POST['trip_date'] ?? date('Y-m-d')) ?>" required min="<?= date('Y-m-d') ?>">
				</div>
				<div class="col-md-auto mt-3 mt-md-0">
					<button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-search me-2"></i>Sefer Ara</button>
				</div>
			</div>
			<datalist id="cities">
				<?php foreach ($all_cities as $city): ?>
					<option value="<?= htmlspecialchars($city) ?>">
				<?php endforeach; ?>
			</datalist>
		</form>
	</div>

	<div class="container mt-5 results-section" style="max-width: 900px;">
			<?php if ($form_submitted): ?>
				<h2 class="fs-3 mb-3"><i class="fas fa-list-ul me-2"></i>Arama Sonuçları</h2>
				<?php if (!empty($trips)): ?>
					<?php foreach ($trips as $trip): ?>
						<div class="card mb-3 trip-card shadow-sm">
							<div class="card-body">
								<div class="row align-items-center g-3">
									<div class="col-md-3">
										<span class="fw-bold fs-6 text-secondary"><?= htmlspecialchars($trip['company_name']) ?></span>
									</div>
									<div class="col-md-4">
										<i class="fas fa-clock text-muted me-1"></i><strong>Kalkış:</strong> <?= date('H:i', strtotime($trip['departure_time'])) ?>
										<span class="mx-1 text-muted">|</span>
										<strong>Varış:</strong> <?= date('H:i', strtotime($trip['arrival_time'])) ?>
									</div>
									<div class="col-md-2 text-md-center">
										<span class="trip-price"><?= htmlspecialchars(number_format($trip['price'], 2, ',', '.')) ?> TL</span>
									</div>
									<div class="col-md-3 text-md-end">
										<a href="trip_details.php?trip_uuid=<?= $trip['uuid'] ?>" class="btn btn-primary w-100 w-md-auto">Koltuk Seç</a>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else: ?>
					<div class="alert alert-warning text-center shadow-sm">Aradığınız kriterlere uygun sefer bulunamadı.</div>
				<?php endif; ?>
			<?php else: ?>
				<div class="upcoming-trips">
					<h2 class="fs-3 mb-3"><i class="fas fa-bus me-2"></i>Yaklaşan Popüler Seferler</h2>
					<?php if (!empty($all_upcoming_trips)): ?>
						<?php foreach ($all_upcoming_trips as $trip): ?>
							<div class="card mb-3 trip-card shadow-sm">
								<div class="card-body">
									<div class="row align-items-center g-3">
										<div class="col-md-5">
											<span class="fw-bold text-secondary"><?= htmlspecialchars($trip['company_name']) ?></span><br>
											<span class="text-primary fw-bold fs-6">
												<?= htmlspecialchars($trip['departure_city']) ?> &rarr; <?= htmlspecialchars($trip['destination_city']) ?>
											</span>
										</div>
										<div class="col-md-3">
											<i class="fas fa-calendar-alt text-muted me-1"></i><?= date('d M Y, H:i', strtotime($trip['departure_time'])) ?>
										</div>
										<div class="col-md-2 text-md-center">
											<span class="trip-price"><?= htmlspecialchars(number_format($trip['price'], 2, ',', '.')) ?> TL</span>
										</div>
										<div class="col-md-2 text-md-end">
											<a href="trip_details.php?trip_uuid=<?= $trip['uuid'] ?>" class="btn btn-primary w-100 w-md-auto">Koltuk Seç</a>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else: ?>
						<div class="alert alert-info text-center shadow-sm">Gösterilecek yaklaşan sefer bulunmamaktadır.</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
	</div>

	<footer class="footer mt-auto py-3 bg-light text-center border-top mt-5">
		<div class="container">
			<span class="text-muted">&copy; <?php echo date("Y"); ?> Bilet Platformu</span>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>