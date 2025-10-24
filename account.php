<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit();
}

$user_id = $_SESSION['user_id'];
$user = null;
$message = '';
$message_type = 'info';

try {
	$stmt = $pdo->prepare("SELECT id, full_name, email, balance FROM Users WHERE id = :user_id");
	$stmt->execute([':user_id' => $user_id]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if(!$user) {
		session_destroy();
		header('Location: login.php?error=user_not_found');
		exit();
	}
} catch (PDOException $e) {
	$message = "Hata: Kullanıcı bilgileri alınamadı. Lütfen daha sonra tekrar deneyin.";
	$message_type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
	$action = $_POST['action'] ?? '';

	if ($action === 'load_balance') {
		$amount = (float)($_POST['amount'] ?? 0);

		if ($amount <= 0 || !is_numeric($_POST['amount'])) {
			$message = "Hata: Lütfen geçerli bir bakiye miktarı girin (0'dan büyük).";
			$message_type = 'error';
		} elseif ($amount > 10000) {
			$message = "Hata: Tek seferde en fazla 10.000 TL yükleyebilirsiniz.";
			$message_type = 'error';
		} else {
			try {
				$add_result = addBalance($pdo, $user_id, $amount);

				if ($add_result === true) {
					$message = "Başarılı: Hesabınıza " . number_format($amount, 2) . " TL yüklendi!";
					$message_type = 'success';
					$stmt_refetch = $pdo->prepare("SELECT * FROM Users WHERE id = :user_id");
					$stmt_refetch->execute([':user_id' => $user_id]);
					$user = $stmt_refetch->fetch(PDO::FETCH_ASSOC);
				} else {
					$message = "Hata: Bakiye yükleme işlemi başarısız oldu.";
					$message_type = 'error';
				}
			} catch (PDOException $e) {
				$message = "Hata: Bakiye yüklenirken veritabanı hatası oluştu.";
				$message_type = 'error';
			}
		}
	}
	elseif ($action === 'update_profile') {
		$new_fullname = trim($_POST['full_name'] ?? $user['full_name']);
		$new_email = trim($_POST['email'] ?? $user['email']);
		$new_password = $_POST['password'] ?? null;

		if (empty($new_fullname) || empty($new_email)) {
			$message = "Hata: Ad Soyad ve E-posta boş bırakılamaz.";
			$message_type = 'error';
		} else {
			try {
				if (!empty($new_password)) {
					$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
					$stmt = $pdo->prepare("UPDATE Users SET full_name = :fullname, email = :email, password = :password WHERE id = :id");
					$stmt->execute([':fullname' => $new_fullname, ':email' => $new_email, ':password' => $hashed_password, ':id' => $user_id]);
					$message = "Bilgileriniz ve şifreniz başarıyla güncellendi.";
					$message_type = 'success';
				} else {
					$stmt = $pdo->prepare("UPDATE Users SET full_name = :fullname, email = :email WHERE id = :id");
					$stmt->execute([':fullname' => $new_fullname, ':email' => $new_email, ':id' => $user_id]);
					$message = "Bilgileriniz başarıyla güncellendi.";
					$message_type = 'success';
				}

				$_SESSION['user_fullname'] = $new_fullname;

				$stmt_refetch = $pdo->prepare("SELECT * FROM Users WHERE id = :user_id");
				$stmt_refetch->execute([':user_id' => $user_id]);
				$user = $stmt_refetch->fetch(PDO::FETCH_ASSOC);

			} catch (PDOException $e) {
				if ($e->getCode() == 23000 || (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false)) {
					$message = "Hata: Bu e-posta adresi zaten başka bir kullanıcı tarafından kullanılıyor.";
					$message_type = 'error';
				} else {
					$message = "Hata: Güncelleme sırasında bir veritabanı sorunu oluştu.";
					$message_type = 'error';
				}
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
	<title>Hesabım - Bilet Platformu</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
	<style>
		body { background-color: #f8f9fa; }
		.card { border: none; border-radius: .75rem; box-shadow: 0 .5rem 1rem rgba(0,0,0,.05); }
		.card-header { background-color: #0d6efd; color: white; font-weight: 500;}
		.balance-display { font-size: 1.5rem; font-weight: 600; color: #198754; }
		.form-label { font-weight: 500; color: #495057;}
	</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
	<div class="container">
		<a class="navbar-brand fw-bold text-primary" href="index.php">A-Bilet</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="navbarNav">
			<ul class="navbar-nav ms-auto align-items-center">
				<?php if (isset($_SESSION['user_id'])): ?>
					<li class="nav-item"> <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Ana Sayfa</a> </li>
					<li class="nav-item"> <a class="nav-link" href="my_tickets.php"><i class="fas fa-ticket-alt me-1"></i>Biletlerim</a> </li>
					<li class="nav-item"> <a class="nav-link active" aria-current="page" href="account.php"><i class="fas fa-user-circle me-1"></i>Hesabım</a> </li>
					<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'company'): ?>
						<li class="nav-item"><a class="nav-link" href="sefer.php">Firma Paneli</a></li>
					<?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
						<li class="nav-item"><a class="nav-link" href="admin_panel.php">Admin Paneli</a></li>
					<?php endif; ?>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_fullname'] ?? 'Kullanıcı'); ?>
						</a>
						<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
							<li><a class="dropdown-item" href="account.php">Hesap Ayarları</a></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Çıkış Yap</a></li>
						</ul>
					</li>
				<?php else: ?>
					<li class="nav-item"> <a class="nav-link" href="login.php">Giriş Yap</a> </li>
					<li class="nav-item"> <a class="btn btn-primary btn-sm" href="register.php">Kayıt Ol</a> </li>
				<?php endif; ?>
			</ul>
		</div>
	</div>
</nav>

<div class="container my-5">
	<div class="row justify-content-center">
		<div class="col-lg-8 col-xl-7">

			<?php if ($message): ?>
				<div class="alert alert-<?php echo ($message_type === 'success' ? 'success' : ($message_type === 'error' ? 'danger' : 'info')); ?> alert-dismissible fade show shadow-sm" role="alert">
					<?php echo htmlspecialchars($message); ?>
					<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
				</div>
			<?php endif; ?>

			<?php if ($user): ?>
				<div class="card shadow-sm mb-4">
					<div class="card-header"><h1 class="h5 mb-0"><i class="fas fa-id-card me-2"></i>Hesap Bilgileri</h1></div>
					<div class="card-body">
						<form action="account.php" method="POST">
							<input type="hidden" name="action" value="update_profile">
							<div class="mb-3">
								<label for="full_name" class="form-label">Ad Soyad:</label>
								<input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
							</div>
							<div class="mb-3">
								<label for="email" class="form-label">E-posta:</label>
								<input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
							</div>
							<div class="mb-3">
								<label for="password" class="form-label">Yeni Şifre (Değiştirmek istemiyorsanız boş bırakın):</label>
								<input type="password" class="form-control" id="password" name="password" placeholder="Yeni Şifre">
							</div>
							<button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Bilgileri Güncelle</button>
						</form>
					</div>
				</div>

				<div class="card shadow-sm">
					<div class="card-header bg-success text-white"><h2 class="h5 mb-0"><i class="fas fa-wallet me-2"></i>Bakiye İşlemleri</h2></div>
					<div class="card-body">
						<div class="mb-3 text-center">
							<label class="form-label d-block">Mevcut Bakiye</label>
							<span class="balance-display"><?php echo htmlspecialchars(number_format($user['balance'] ?? 0, 2)); ?> TL</span>
						</div>
						<hr>
						<h3 class="h6 text-success mb-3">Bakiye Yükle</h3>
						<form action="account.php" method="POST">
							<input type="hidden" name="action" value="load_balance">
							<div class="input-group mb-3">
								<span class="input-group-text">₺</span>
								<input type="number" class="form-control" id="amount" name="amount" min="1" step="0.01" required placeholder="Yüklenecek Tutar">
							</div>
							<button type="submit" class="btn btn-success w-100"><i class="fas fa-plus-circle me-2"></i>Bakiyeyi Yükle</button>
						</form>
					</div>
				</div>
			<?php else: ?>
				<div class="alert alert-warning text-center">Kullanıcı bilgileri yüklenemedi.</div>
			<?php endif; ?>
			<div class="text-center mt-4">
				<a href="my_tickets.php" class="btn btn-outline-secondary"><i class="fas fa-ticket-alt me-1"></i>Biletlerime Git</a>
			</div>
		</div>
	</div>
</div>

<footer class="footer mt-auto py-3 bg-light text-center border-top mt-5">
	<div class="container">
		<span class="text-muted">&copy; <?php echo date("Y"); ?> Bilet Platformu</span>
	</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>