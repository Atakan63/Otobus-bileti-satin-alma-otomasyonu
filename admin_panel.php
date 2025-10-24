<?php
session_start();

require 'db.php'; 
require 'functions.php';

$message_from_url = '';
$error = false;

if (isset($_GET['msg'])) {
	$message_from_url = urldecode($_GET['msg']);
	$error = (strpos($message_from_url, 'Hata') !== false || strpos($message_from_url, 'başarısız') !== false);
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
	header("Location: index.php"); 
	exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$action = $_POST['action'] ?? '';
	$result = '';
	$redirect_tab = '';

	if ($action === 'create_company') {
		$result = createCompany($pdo, trim($_POST['company_name'] ?? ''));
		$redirect_tab = '&tab=company-management';
	} elseif ($action === 'update_company') { 
		$company_id = $_POST['company_id'] ?? null;
		$new_name = trim($_POST['company_name'] ?? '');
		$result = updateCompany($pdo, (int)$company_id, $new_name);
		$redirect_tab = '&tab=company-management';
	} elseif ($action === 'delete_company') { 
		$company_id_to_delete = $_POST['company_id'] ?? null;
		$delete_result = deleteCompany($pdo, (int)$company_id_to_delete);
		$result = $delete_result ? "Firma başarıyla silindi." : "Hata: Firma silinemedi veya bulunamadı.";
		$redirect_tab = '&tab=company-management';

	} elseif ($action === 'assign_admin') {
		$user_id = $_POST['user_id_assign'] ?? '';
		$company_id = $_POST['company_id_assign'] ?? '';
		$assign_result = updateUserRoleAndCompany($pdo, (int)$user_id, 'company_admin', (int)$company_id); 
		$result = ($assign_result === true) ? "Kullanıcı başarıyla Firma Admini olarak atandı." : $assign_result;
		$redirect_tab = '&tab=admin-assignment';

	} elseif ($action === 'create_coupon') {
		$coupon_data = [
			'code' => strtoupper(trim($_POST['coupon_code'] ?? '')),
			'discount' => (float)($_POST['coupon_discount'] ?? 0) / 100.0, 
			'usage_limit' => (int)($_POST['coupon_limit'] ?? 0),
			'expire_date' => $_POST['coupon_expire'] ?? ''
		];
		$create_coupon_result = createCoupon($pdo, $coupon_data, null); 
		$result = ($create_coupon_result === true) ? "Genel kupon başarıyla oluşturuldu." : $create_coupon_result;
		$redirect_tab = '&tab=coupon-management';

	} elseif ($action === 'update_general_coupon') {
		$coupon_id = $_POST['coupon_id'] ?? null;
		$coupon_data = [
			'code' => $_POST['coupon_code'] ?? '',
			'discount_percent' => (int)($_POST['coupon_discount'] ?? 0),
			'usage_limit' => (int)($_POST['coupon_limit'] ?? 0),
			'expire_date' => $_POST['coupon_expire'] ?? ''
		];
		if(function_exists('update_general_coupon')){
			$result = update_general_coupon($pdo, $coupon_id, $coupon_data);
		} else {
			$result = "Hata: Kupon güncelleme fonksiyonu bulunamadı.";
		}
		$redirect_tab = '&tab=coupon-management';

	} elseif ($action === 'delete_general_coupon') {
		$coupon_id_to_delete = $_POST['coupon_id'] ?? null;
		if(function_exists('delete_general_coupon')){
			$result = delete_general_coupon($pdo, $coupon_id_to_delete);
		} else {
			$result = "Hata: Kupon silme fonksiyonu bulunamadı.";
		}
		$redirect_tab = '&tab=coupon-management';
	} 

	header("Location: admin_panel.php?msg=" . urlencode($result) . $redirect_tab);
	exit;
}

$company_to_edit = null;
if (isset($_GET['edit_company_id'])) {
	$company_to_edit = getCompanyDetails($pdo, (int)$_GET['edit_company_id']); 
}

$coupon_to_edit = null;
if (isset($_GET['edit_coupon_id'])) {
	$edit_coupon_id = $_GET['edit_coupon_id'];
	$sql_edit_coup = "SELECT * FROM Coupons WHERE id = :id AND company_id IS NULL";
	$stmt_edit_coup = $pdo->prepare($sql_edit_coup);
	$stmt_edit_coup->execute([':id' => $edit_coupon_id]);
	$coupon_to_edit = $stmt_edit_coup->fetch(PDO::FETCH_ASSOC);
}

$companies = getAllCompanies($pdo); 
$all_users_raw = getAllUsers($pdo, $_SESSION['user_id']); 
$unassigned_users = array_filter($all_users_raw, function($user){
	return $user['role'] === 'user' && is_null($user['company_id']);
});
$coupons = $pdo->query("SELECT C.*, BC.name AS company_name FROM Coupons AS C LEFT JOIN Bus_Company AS BC ON C.company_id = BC.id WHERE C.company_id IS NULL ORDER BY C.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>YÖNETİCİ PANELİ (ADMIN)</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<style>
		body { background-color: #f8f9fa; }
		.container { margin-top: 2rem; margin-bottom: 2rem; }
		.card { margin-bottom: 1.5rem; }
		.nav-tabs .nav-link { margin-bottom: -1px; border-radius: 0.375rem 0.375rem 0 0;}
		.nav-tabs .nav-link.active { color: #0d6efd; background-color: #fff; border-color: #dee2e6 #dee2e6 #fff; }
		.table-hover tbody tr:hover { background-color: rgba(0, 123, 255, 0.05); }
		.btn-sm { padding: 0.25rem 0.5rem; font-size: .875rem; }
		.actions-form { display: inline-block; margin: 0; }
		.form-label { font-weight: 500; }
		.navbar-brand { font-size: 1.5rem; } 
	</style>
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
		<div class="container">
			<a class="navbar-brand fw-bold" href="admin_panel.php">Yönetim Paneli</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAdmin" aria-controls="navbarNavAdmin" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
			<div class="collapse navbar-collapse" id="navbarNavAdmin">
				<ul class="navbar-nav me-auto mb-2 mb-lg-0">
					<li class="nav-item"> <a class="nav-link active" aria-current="page" href="admin_panel.php"><i class="fas fa-tachometer-alt me-1"></i>Gösterge Paneli</a> </li>
				</ul>
				<ul class="navbar-nav ms-auto align-items-center">
					<li class="nav-item me-3"> <span class="navbar-text"> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?> (Admin) </span> </li>
					<li class="nav-item"> <a href="anasayfa.php" class="btn btn-outline-light btn-sm me-2" target="_blank"><i class="fas fa-external-link-alt me-1"></i>Siteyi Gör</a> </li>
					<li class="nav-item"> <a href="logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt me-1"></i>Çıkış Yap</a> </li>
				</ul>
			</div>
		</div>
	</nav>

	<div class="container mt-4">
		<ul class="nav nav-tabs mb-4" id="adminTab" role="tablist">
			<li class="nav-item" role="presentation"> <button class="nav-link active" id="company-tab" data-bs-toggle="tab" data-bs-target="#company-management" type="button" role="tab" aria-controls="company-management" aria-selected="true">Firma Yönetimi</button> </li>
			<li class="nav-item" role="presentation"> <button class="nav-link" id="assign-tab" data-bs-toggle="tab" data-bs-target="#admin-assignment" type="button" role="tab" aria-controls="admin-assignment" aria-selected="false">Firma Admini Atama</button> </li>
			<li class="nav-item" role="presentation"> <button class="nav-link" id="coupon-tab" data-bs-toggle="tab" data-bs-target="#coupon-management" type="button" role="tab" aria-controls="coupon-management" aria-selected="false">Genel Kupon Yönetimi</button> </li>
		</ul>

		<div class="tab-content" id="adminTabContent">

			<div class="tab-pane fade show active" id="company-management" role="tabpanel" aria-labelledby="company-tab">
				<div class="row g-4">
					<div class="col-lg-5">
						<div class="card shadow-sm">
							<div class="card-body">
								<?php if ($company_to_edit): ?>
									<h5 class="card-title mb-3"><i class="fas fa-edit me-2"></i>Firmayı Düzenle</h5>
									<form action="admin_panel.php" method="POST"> <input type="hidden" name="action" value="update_company"> <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($company_to_edit['id']); ?>"> <div class="mb-3"> <label for="company_name_edit" class="form-label">Firma Adı:</label> <input type="text" class="form-control" id="company_name_edit" name="company_name" value="<?php echo htmlspecialchars($company_to_edit['name']); ?>" required> </div> <button type="submit" class="btn btn-success w-100"><i class="fas fa-save me-2"></i>Güncelle</button> <a href="admin_panel.php?tab=company-management" class="btn btn-secondary w-100 mt-2">İptal</a> </form>
								<?php else: ?>
									<h5 class="card-title mb-3"><i class="fas fa-plus-circle me-2"></i>Yeni Firma Oluştur</h5>
									<form action="admin_panel.php" method="POST"> <input type="hidden" name="action" value="create_company"> <div class="mb-3"> <label for="company_name" class="form-label">Firma Adı:</label> <input type="text" class="form-control" id="company_name" name="company_name" required> </div> <button type="submit" class="btn btn-primary w-100">Firma Oluştur</button> </form>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<div class="col-lg-7">
						<div class="card shadow-sm">
							<div class="card-body">
								<h5 class="card-title mb-3"><i class="fas fa-list me-2"></i>Mevcut Firmalar</h5>
								<div class="table-responsive">
									<table class="table table-striped table-hover">
										<thead><tr><th>Firma Adı</th><th class="text-end">İşlemler</th></tr></thead>
										<tbody>
											<?php if(empty($companies)): ?> <tr><td colspan="2" class="text-center text-muted">Henüz kayıtlı firma yok.</td></tr>
											<?php else: foreach ($companies as $comp): ?>
												<tr> <td><?php echo htmlspecialchars($comp['name']); ?></td> <td class="text-end"> <a href="admin_panel.php?edit_company_id=<?php echo htmlspecialchars($comp['id']); ?>&tab=company-management" class="btn btn-sm btn-warning me-1" title="Düzenle"><i class="fas fa-edit"></i></a> <form action="admin_panel.php" method="POST" class="actions-form" onsubmit="return confirm('Bu firmayı silmek istediğinizden emin misiniz?');"> <input type="hidden" name="action" value="delete_company"> <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($comp['id']); ?>"> <button type="submit" class="btn btn-sm btn-danger" title="Sil"><i class="fas fa-trash-alt"></i></button> </form> </td> </tr>
											<?php endforeach; endif; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="tab-pane fade" id="admin-assignment" role="tabpanel" aria-labelledby="assign-tab">
				<div class="card shadow-sm">
					<div class="card-body"> <h5 class="card-title mb-3"><i class="fas fa-user-tie me-2"></i>Firma Admini Ata</h5> <p class="card-text text-muted mb-4">Seçilen kullanıcıyı belirlenen firmanın yöneticisi olarak atayın.</p> <form action="admin_panel.php" method="POST"> <input type="hidden" name="action" value="assign_admin"> <div class="row g-3"> <div class="col-md-6 mb-3"> <label for="user_id_assign" class="form-label">Kullanıcı:</label> <select class="form-select" id="user_id_assign" name="user_id_assign" required> <option value="">Seçiniz...</option> <?php if(empty($unassigned_users)): ?> <option disabled>Atanmamış kullanıcı yok.</option> <?php else: foreach ($unassigned_users as $user): ?> <option value="<?php echo htmlspecialchars($user['id']); ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</option> <?php endforeach; endif; ?> </select> </div> <div class="col-md-6 mb-3"> <label for="company_id_assign" class="form-label">Firma:</label> <select class="form-select" id="company_id_assign" name="company_id_assign" required> <option value="">Firma Seçiniz...</option> <?php foreach ($companies as $comp): ?> <option value="<?php echo htmlspecialchars($comp['id']); ?>"><?php echo htmlspecialchars($comp['name']); ?></option> <?php endforeach; ?> </select> </div> </div> <button type="submit" class="btn btn-primary w-100 mt-2" <?php echo empty($unassigned_users) ? 'disabled' : ''; ?>>Admin Olarak Ata</button> </form> </div>
				</div>
			</div>
			
			<div class="tab-pane fade" id="coupon-management" role="tabpanel" aria-labelledby="coupon-tab">
				<div class="row g-4">
					<div class="col-lg-5">
						<div class="card shadow-sm">
							<div class="card-body">
								<?php if ($coupon_to_edit): ?>
									<h5 class="card-title mb-3"><i class="fas fa-edit me-2"></i>Genel Kuponu Düzenle</h5>
									<form action="admin_panel.php" method="POST">
										<input type="hidden" name="action" value="update_general_coupon">
										<input type="hidden" name="coupon_id" value="<?php echo htmlspecialchars($coupon_to_edit['id']); ?>">
										<div class="mb-3"> <label for="coupon_code_edit" class="form-label">Kupon Kodu:</label> <input type="text" class="form-control" id="coupon_code_edit" name="coupon_code" value="<?php echo htmlspecialchars($coupon_to_edit['code']); ?>" required> </div>
										<div class="mb-3"> <label for="coupon_discount_edit" class="form-label">İndirim Oranı (%):</label> <input type="number" class="form-control" id="coupon_discount_edit" name="coupon_discount" step="1" min="1" max="100" value="<?php echo htmlspecialchars($coupon_to_edit['discount'] * 100); ?>" required> </div>
										<div class="mb-3"> <label for="coupon_limit_edit" class="form-label">Kullanım Limiti:</label> <input type="number" class="form-control" id="coupon_limit_edit" name="coupon_limit" min="1" value="<?php echo htmlspecialchars($coupon_to_edit['usage_limit']); ?>" required> </div>
										<div class="mb-3"> <label for="coupon_expire_edit" class="form-label">Son Kullanma Tarihi:</label> <input type="date" class="form-control" id="coupon_expire_edit" name="coupon_expire" value="<?php echo date('Y-m-d', strtotime($coupon_to_edit['expire_date'])); ?>" required> </div>
										<button type="submit" class="btn btn-success w-100">Kuponu Güncelle</button>
										<a href="admin_panel.php?tab=coupon-management" class="btn btn-secondary w-100 mt-2">İptal</a>
									</form>
								<?php else: ?>
									<h5 class="card-title mb-3"><i class="fas fa-plus-circle me-2"></i>Genel Kupon Oluştur</h5>
									<form action="admin_panel.php" method="POST"> <input type="hidden" name="action" value="create_coupon"> <div class="mb-3"> <label for="coupon_code" class="form-label">Kupon Kodu:</label> <input type="text" class="form-control" id="coupon_code" name="coupon_code" required> </div> <div class="mb-3"> <label for="coupon_discount" class="form-label">İndirim Oranı (%):</label> <input type="number" class="form-control" id="coupon_discount" name="coupon_discount" step="1" min="1" max="100" required> </div> <div class="mb-3"> <label for="coupon_limit" class="form-label">Kullanım Limiti:</label> <input type="number" class="form-control" id="coupon_limit" name="coupon_limit" min="1" required> </div> <div class="mb-3"> <label for="coupon_expire" class="form-label">Son Kullanma Tarihi:</label> <input type="date" class="form-control" id="coupon_expire" name="coupon_expire" required> </div> <button type="submit" class="btn btn-primary w-100">Kuponu Oluştur</button> </form>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<div class="col-lg-7">
						<div class="card shadow-sm">
							<div class="card-body">
								<h5 class="card-title mb-3"><i class="fas fa-list me-2"></i>Mevcut Genel Kuponlar</h5>
								<div class="table-responsive">
									<table class="table table-striped table-hover">
										<thead><tr><th>Kod</th><th>Oran</th><th>Limit</th><th>Son Kullanma</th><th class="text-end">İşlemler</th></tr></thead>
										<tbody>
											<?php if(empty($coupons)): ?> <tr><td colspan="5" class="text-center text-muted">Henüz genel kupon yok.</td></tr>
											<?php else: foreach ($coupons as $coup): ?>
												<tr> <td><?php echo htmlspecialchars($coup['code']); ?></td> <td><?php echo number_format(htmlspecialchars($coup['discount']) * 100, 0); ?>%</td> <td><?php echo htmlspecialchars($coup['usage_limit']); ?></td> <td><?php echo date('d.m.Y', strtotime($coup['expire_date'])); ?></td> <td class="text-end"> <a href="admin_panel.php?edit_coupon_id=<?php echo htmlspecialchars($coup['id']); ?>&tab=coupon-management" class="btn btn-sm btn-warning me-1" title="Düzenle"><i class="fas fa-edit"></i></a> <form action="admin_panel.php" method="POST" class="actions-form" onsubmit="return confirm('Bu genel kuponu silmek istediğinizden emin misiniz?');"> <input type="hidden" name="action" value="delete_general_coupon"> <input type="hidden" name="coupon_id" value="<?php echo htmlspecialchars($coup['id']); ?>"> <button type="submit" class="btn btn-sm btn-danger" title="Sil"><i class="fas fa-trash-alt"></i></button> </form> </td> </tr>
											<?php endforeach; endif; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<footer class="footer mt-auto py-3 bg-light text-center border-top mt-5">
		<div class="container">
			<span class="text-muted">&copy; <?php echo date("Y"); ?> Bilet Platformu Yönetim Paneli</span>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const message = <?php echo json_encode($message_from_url); ?>;
			if (message && message.trim() !== '') {
				const isError = message.includes('Hata:');
				const isSuccess = message.includes('Başarılı:') || message.includes('başarıyla');
				let iconType = isSuccess ? 'success' : (isError ? 'error' : 'info');
				let titleText = isSuccess ? 'İşlem Başarılı' : (isError ? 'Hata Oluştu!' : 'Bilgilendirme');
				Swal.fire({ icon: iconType, title: titleText, text: message.replace(/<[^>]*>?/gm, ''), confirmButtonText: 'Tamam'});
				if (window.history.replaceState) {
					const url = new URL(window.location);
					url.searchParams.delete('msg');
					window.history.replaceState({ path: url.href }, '', url.href);
				}
			}

			const urlParams = new URLSearchParams(window.location.search);
			let activeTabId = urlParams.get('tab') || 'company-management'; 
			if (urlParams.has('edit_company_id')) { activeTabId = 'company-management'; }
			if (urlParams.has('edit_coupon_id')) { activeTabId = 'coupon-management'; } 
			
			const triggerEl = document.querySelector(`#adminTab button[data-bs-target="#${activeTabId}"]`);
			if (triggerEl) {
				const tab = new bootstrap.Tab(triggerEl);
				tab.show();
			}

			const tabElList = document.querySelectorAll('#adminTab button[data-bs-toggle="tab"]');
			tabElList.forEach(tabEl => {
				tabEl.addEventListener('shown.bs.tab', event => {
					const targetTabId = event.target.getAttribute('data-bs-target').substring(1); 
					const url = new URL(window.location);
					url.searchParams.set('tab', targetTabId);
					if (targetTabId !== 'company-management' && url.searchParams.has('edit_company_id')) url.searchParams.delete('edit_company_id');
					if (targetTabId !== 'coupon-management' && url.searchParams.has('edit_coupon_id')) url.searchParams.delete('edit_coupon_id');
					window.history.pushState({}, '', url);
				});
			});
		});
	</script>
</body>
</html>