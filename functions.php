<?php
require_once 'db.php';

function generateUuidV4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, 
        mt_rand(0, 0x3fff) | 0x8000, 
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function searchTrips(PDO $pdo, string $departure_city, string $destination_city, string $trip_date): array
{
    $sql = "
        SELECT
            T.*,
            BC.name as company_name,
            BC.logo_path
        FROM Trips AS T
        JOIN Bus_Company AS BC ON T.company_id = BC.id
        WHERE
            T.departure_city = :departure_city
            AND T.destination_city = :destination_city
            AND date(T.departure_time) = :trip_date
        ORDER BY
            T.departure_time ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':departure_city' => $departure_city,
        ':destination_city' => $destination_city,
        ':trip_date' => $trip_date
    ]);
    return $stmt->fetchAll();
}

function checkIfUserExists(PDO $pdo, string $email): bool
{
    $sql = "SELECT COUNT(*) FROM Users WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    return $stmt->fetchColumn() > 0;
}

function registerUser(PDO $pdo, string $fullName, string $email, string $password)
{
    if (checkIfUserExists($pdo, $email)) {
        return "Bu e-posta adresi zaten kayıtlı.";
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $userUuid = generateUuidV4();

    $sql = "INSERT INTO Users (uuid, full_name, email, password) VALUES (:uuid, :full_name, :email, :password)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':uuid'      => $userUuid,
            ':full_name' => $fullName,
            ':email'     => $email,
            ':password'  => $hashedPassword
        ]);
        return true;
    } catch (PDOException $e) {
        return "Kayıt sırasında bir hata oluştu: " . $e->getMessage();
    }
}

function loginUser(PDO $pdo, string $email, string $password)
{
    $sql = "SELECT id, uuid, full_name, email, password, role, balance, company_id FROM Users WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        return $user;
    }

    return false;
}

function getTripDetails(PDO $pdo, string $trip_uuid)
{
    $sql = "
        SELECT T.*, BC.name as company_name
        FROM Trips AS T
        JOIN Bus_Company AS BC ON T.company_id = BC.id
        WHERE T.uuid = :trip_uuid
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':trip_uuid' => $trip_uuid]);
    return $stmt->fetch();
}

function getBookedSeats(PDO $pdo, int $trip_id): array
{
    $sql = "
        SELECT bs.seat_number
        FROM Booked_Seats AS bs
        JOIN Tickets AS t ON bs.ticket_id = t.id
        WHERE t.trip_id = :trip_id AND t.status = 'active'
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':trip_id' => $trip_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function findCoupon(PDO $pdo, string $coupon_code)
{
    $sql = "
        SELECT * FROM Coupons
        WHERE code = :code
        AND expire_date > strftime('%Y-%m-%d %H:%M:%S', 'now', 'localtime')
        AND usage_limit > 0
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':code' => $coupon_code]);
    return $stmt->fetch();
}

function processTicketPurchase(PDO $pdo, int $user_id, int $trip_id, array $selected_seats, float $total_price, ?int $coupon_id)
{
    try {
        $pdo->beginTransaction();

        $stmt_balance_check = $pdo->prepare("SELECT balance FROM Users WHERE id = :user_id");
        $stmt_balance_check->execute([':user_id' => $user_id]);
        $current_balance = $stmt_balance_check->fetchColumn();

        if ($current_balance === false || $current_balance < $total_price) {
             throw new Exception("Yetersiz bakiye veya kullanıcı hatası.");
        }

        $sql_balance_update = "UPDATE Users SET balance = balance - :total_price WHERE id = :user_id";
        $stmt_balance_update = $pdo->prepare($sql_balance_update);
        $stmt_balance_update->execute([':total_price' => $total_price, ':user_id' => $user_id]);
        $_SESSION['user_balance'] = $current_balance - $total_price;

        $ticket_uuid = generateUuidV4();
        $sql_ticket = "INSERT INTO Tickets (uuid, user_id, trip_id, total_price, status) VALUES (:uuid, :user_id, :trip_id, :total_price, 'active')";
        $stmt_ticket = $pdo->prepare($sql_ticket);
        $stmt_ticket->execute([':uuid' => $ticket_uuid, ':user_id' => $user_id, ':trip_id' => $trip_id, ':total_price' => $total_price]);
        $ticket_id = $pdo->lastInsertId();

        $sql_seat = "INSERT INTO Booked_Seats (uuid, ticket_id, seat_number) VALUES (:uuid, :ticket_id, :seat_number)";
        $stmt_seat = $pdo->prepare($sql_seat);
        foreach ($selected_seats as $seat) {
            $seat_uuid = generateUuidV4();
            $stmt_seat->execute([':uuid' => $seat_uuid, ':ticket_id' => $ticket_id, ':seat_number' => (int)$seat]);
        }

        if ($coupon_id) {
            $sql_coupon = "UPDATE Coupons SET usage_limit = usage_limit - 1 WHERE id = :coupon_id";
            $stmt_coupon = $pdo->prepare($sql_coupon);
            $stmt_coupon->execute([':coupon_id' => $coupon_id]);
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollBack();
        return "İşlem sırasında bir hata oluştu: " . $e->getMessage();
    }
}

function getAllUsers(PDO $pdo, int $current_admin_id, ?string $searchTerm = null): array
{
    $sql = "
        SELECT U.id, U.full_name, U.email, U.role, U.company_id, BC.name AS company_name
        FROM Users AS U LEFT JOIN Bus_Company AS BC ON U.company_id = BC.id
        WHERE U.id != :current_admin_id";
    $params = [':current_admin_id' => $current_admin_id];
    if (!empty($searchTerm)) {
        $sql .= " AND U.full_name LIKE :searchTerm";
        $params[':searchTerm'] = '%' . $searchTerm . '%';
    }
    $sql .= " ORDER BY U.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAllCompanies(PDO $pdo): array
{
    $sql = "SELECT id, name FROM Bus_Company ORDER BY name ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function updateUserRoleAndCompany(PDO $pdo, int $user_id, string $new_role, ?int $company_id)
{
    if (!in_array($new_role, ['user', 'company_admin', 'admin'])) { return "Geçersiz rol ataması."; }
    if ($new_role === 'company_admin' && empty($company_id)) { return "Firma Admini için bir firma seçilmelidir."; }
    $company_id_to_set = ($new_role === 'company_admin') ? $company_id : NULL;
    try {
        $sql = "UPDATE Users SET role = :role, company_id = :company_id WHERE id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':role' => $new_role, ':company_id' => $company_id_to_set, ':user_id' => $user_id]);
        return true;
    } catch (PDOException $e) { return "Veritabanı hatası: " . $e->getMessage(); }
}

function createCompany(PDO $pdo, string $company_name)
{
    try {
        $company_uuid = generateUuidV4();
        $sql = "INSERT INTO Bus_Company (uuid, name) VALUES (:uuid, :name)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uuid' => $company_uuid, ':name' => $company_name]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000 || $e->getCode() == 19) { return "Bu firma adı zaten kayıtlı."; }
        return "Firma oluşturulurken bir hata oluştu: " . $e->getMessage();
    }
}

function updateCompany(PDO $pdo, int $company_id, string $new_name, ?string $new_logo_path = null)
{
    $update_fields = ['name = :name'];
    $params = [':id' => $company_id, ':name' => $new_name];
    if ($new_logo_path !== null) {
        $update_fields[] = 'logo_path = :logo_path';
        $params[':logo_path'] = $new_logo_path;
    }
    $sql = "UPDATE Bus_Company SET " . implode(', ', $update_fields) . " WHERE id = :id";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000 || $e->getCode() == 19) { return "Bu firma adı zaten başka bir firma tarafından kullanılıyor."; }
        return "Firma güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

function deleteCompany(PDO $pdo, int $company_id): bool
{
    try {
        $sql = "DELETE FROM Bus_Company WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $company_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Firma silinirken hata: " . $e->getMessage());
        return false;
    }
}

function getCompanyDetails(PDO $pdo, int $company_id)
{
    $sql = "SELECT * FROM Bus_Company WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $company_id]);
    return $stmt->fetch();
}

function getCompanyTrips(PDO $pdo, int $company_id): array
{
    $sql = "SELECT * FROM Trips WHERE company_id = :company_id ORDER BY departure_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':company_id' => $company_id]);
    return $stmt->fetchAll();
}

function createTrip(PDO $pdo, int $company_id, array $data)
{
    try {
        $trip_uuid = generateUuidV4();
        $sql = "INSERT INTO Trips (uuid, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity) VALUES (:uuid, :company_id, :departure_city, :destination_city, :departure_time, :arrival_time, :price, :capacity)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uuid' => $trip_uuid,
            ':company_id' => $company_id,
            ':departure_city' => $data['departure_city'],
            ':destination_city' => $data['destination_city'],
            ':departure_time' => $data['departure_time'],
            ':arrival_time' => $data['arrival_time'],
            ':price' => (float)$data['price'],
            ':capacity' => (int)$data['capacity']
        ]);
        return true;
    } catch (PDOException $e) { return "Sefer oluşturulurken hata: " . $e->getMessage(); }
}

function deleteTrip(PDO $pdo, int $trip_id, int $company_id): bool
{
    $sql = "DELETE FROM Trips WHERE id = :trip_id AND company_id = :company_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':trip_id' => $trip_id, ':company_id' => $company_id]);
    return $stmt->rowCount() > 0;
}

function getAllUpcomingTrips(PDO $pdo, int $limit = 20): array
{
    $sql = "
        SELECT T.*, BC.name as company_name, BC.logo_path
        FROM Trips AS T JOIN Bus_Company AS BC ON T.company_id = BC.id
        WHERE T.departure_time >= strftime('%Y-%m-%d %H:%M:%S', 'now', 'localtime')
        ORDER BY T.departure_time ASC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getUserTickets(PDO $pdo, int $user_id): array
{
     $sql = "
        SELECT
            T.id AS ticket_id, T.uuid AS ticket_uuid, T.total_price, T.status, T.created_at,
            Tr.departure_city, Tr.destination_city, Tr.departure_time,
            BC.name AS company_name
        FROM Tickets AS T
        JOIN Trips AS Tr ON T.trip_id = Tr.id
        JOIN Bus_Company AS BC ON Tr.company_id = BC.id
        WHERE T.user_id = :user_id ORDER BY Tr.departure_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll();
}

function addBalance(PDO $pdo, int $user_id, float $amount): bool
{
    try {
        $pdo->beginTransaction();
        $sql = "UPDATE Users SET balance = balance + :amount WHERE id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':amount' => $amount, ':user_id' => $user_id]);
        $_SESSION['user_balance'] = ($_SESSION['user_balance'] ?? 0) + $amount;
        $pdo->commit();
        return true;
    } catch (PDOException $e) { $pdo->rollBack(); return false; }
}

function getCoupons(PDO $pdo, string $user_role, ?int $company_id): array
{
    $sql = "SELECT C.*, BC.name AS company_name FROM Coupons AS C LEFT JOIN Bus_Company AS BC ON C.company_id = BC.id";
    $params = [];
    if ($user_role === 'company_admin' && $company_id) {
        $sql .= " WHERE C.company_id = :company_id";
        $params[':company_id'] = $company_id;
    }
    $sql .= " ORDER BY C.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function createCoupon(PDO $pdo, array $data, ?int $company_id)
{
    try {
        $coupon_uuid = generateUuidV4();
        $sql = "INSERT INTO Coupons (uuid, code, discount, company_id, usage_limit, expire_date) VALUES (:uuid, :code, :discount, :company_id, :usage_limit, :expire_date)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uuid' => $coupon_uuid,
            ':code' => $data['code'],
            ':discount' => (float)$data['discount'],
            ':company_id' => $company_id,
            ':usage_limit' => (int)$data['usage_limit'],
            ':expire_date' => $data['expire_date']
        ]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000 || $e->getCode() == 19) { return "Bu kupon kodu zaten mevcut."; }
        return "Kupon oluşturulurken hata: " . $e->getMessage();
    }
}

function deleteCoupon(PDO $pdo, int $coupon_id, string $user_role, ?int $company_id): bool
{
    $sql = "DELETE FROM Coupons WHERE id = :coupon_id";
    $params = [':coupon_id' => $coupon_id];
    if ($user_role === 'company_admin' && $company_id) {
        $sql .= " AND company_id = :company_id";
        $params[':company_id'] = $company_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

/**
 * Belirtilen bileti yetki ve zaman kurallarına göre iptal eder.
 * @param PDO $pdo PDO bağlantı objesi.
 * @param int $ticket_id İptal edilecek biletin ID'si (primary key).
 * @param int $user_id Oturumdaki kullanıcının ID'si.
 * @return bool|string Başarılı ise true, hata olursa hata mesajı (string).
 */
function cancelTicket(PDO $pdo, int $ticket_id, int $user_id)
{
    try {
        $pdo->beginTransaction();
        
        // Bilet bilgilerini çek ve kontrol et
        $sql_fetch = "SELECT T.id, T.user_id, T.total_price, T.status, Tr.departure_time 
                      FROM Tickets AS T 
                      JOIN Trips AS Tr ON T.trip_id = Tr.id 
                      WHERE T.id = :ticket_id";
        $stmt_fetch = $pdo->prepare($sql_fetch);
        $stmt_fetch->execute([':ticket_id' => $ticket_id]);
        $ticket_info = $stmt_fetch->fetch(PDO::FETCH_ASSOC); // Fetch assoc daha iyi

        if (!$ticket_info) { throw new Exception("Bilet bulunamadı."); }
        // User ID veritabanında INTEGER ise karşılaştırmayı int olarak yap
        if ((int)$ticket_info['user_id'] !== $user_id) { throw new Exception("Bu bileti iptal etme yetkiniz yok."); } 
        // Durumu küçük harfe çevirerek kontrol et (veritabanı tutarsızlıklarına karşı)
        if (strtolower($ticket_info['status']) !== 'active') { throw new Exception("Bu bilet zaten aktif değil (iptal edilmiş veya süresi geçmiş olabilir)."); } 
        
        // Zaman kontrolü
        $departure_timestamp = strtotime($ticket_info['departure_time']);
        $current_timestamp = time();
        $time_diff_hours = ($departure_timestamp - $current_timestamp) / 3600;
        if ($time_diff_hours <= 1) { throw new Exception("Sefere 1 saatten az kaldığı için bilet iptal edilemez."); }
        
        // Bilet durumunu güncelle (cancelled küçük harf olabilir DB'de)
        $sql_update_ticket = "UPDATE Tickets SET status = 'cancelled' WHERE id = :ticket_id"; 
        $stmt_update_ticket = $pdo->prepare($sql_update_ticket);
        $stmt_update_ticket->execute([':ticket_id' => $ticket_id]);
        
        // Para iadesi
        $refund_amount = $ticket_info['total_price'];
        $sql_update_balance = "UPDATE Users SET balance = balance + :amount WHERE id = :user_id";
        $stmt_update_balance = $pdo->prepare($sql_update_balance);
        $stmt_update_balance->execute([':amount' => $refund_amount, ':user_id' => $user_id]);
        
        // Koltuk kaydını sil
        $sql_delete_seats = "DELETE FROM Booked_Seats WHERE ticket_id = :ticket_id";
        $stmt_delete_seats = $pdo->prepare($sql_delete_seats);
        $stmt_delete_seats->execute([':ticket_id' => $ticket_id]);
        
        $pdo->commit();
        return true; // Başarılı ise true döndür

    } catch (Exception $e) { 
        if ($pdo->inTransaction()) { // Hata olursa rollback yap
             $pdo->rollBack(); 
        }
        // Hata durumunda mesajı döndür
        return "İptal işlemi sırasında bir hata oluştu: " . $e->getMessage(); 
    }
}

function getTicketDetailsForPdf(PDO $pdo, string $ticket_uuid, int $user_id)
{
    $sql_main = "
        SELECT T.id AS ticket_id, T.uuid AS ticket_uuid, T.total_price, T.status, T.created_at AS purchase_date,
               Tr.departure_city, Tr.destination_city, Tr.departure_time, Tr.arrival_time,
               BC.name AS company_name, U.full_name AS passenger_name, U.email AS passenger_email
        FROM Tickets AS T
        JOIN Trips AS Tr ON T.trip_id = Tr.id
        JOIN Bus_Company AS BC ON Tr.company_id = BC.id
        JOIN Users AS U ON T.user_id = U.id
        WHERE T.uuid = :ticket_uuid AND T.user_id = :user_id";
    $stmt_main = $pdo->prepare($sql_main);
    $stmt_main->execute([':ticket_uuid' => $ticket_uuid, ':user_id' => $user_id]);
    $details = $stmt_main->fetch();
    if (!$details) { return false; }
    $sql_seats = "SELECT seat_number FROM Booked_Seats WHERE ticket_id = :ticket_id ORDER BY seat_number ASC";
    $stmt_seats = $pdo->prepare($sql_seats);
    $stmt_seats->execute([':ticket_id' => $details['ticket_id']]);
    $seat_numbers = $stmt_seats->fetchAll(PDO::FETCH_COLUMN);
    $details['seat_numbers'] = $seat_numbers;
    return $details;
}

function getTicketsForTrip(PDO $pdo, int $trip_id, int $company_id)
{
    $sql_check = "SELECT id FROM Trips WHERE id = :trip_id AND company_id = :company_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':trip_id' => $trip_id, ':company_id' => $company_id]);
    if (!$stmt_check->fetch()) { return false; }

    $sql = "
        SELECT T.id AS ticket_id, T.status, T.total_price, U.full_name AS passenger_name, U.email AS passenger_email,
               COALESCE(GROUP_CONCAT(BS.seat_number), 'N/A') AS seat_numbers
        FROM Tickets AS T
        JOIN Users AS U ON T.user_id = U.id
        LEFT JOIN Booked_Seats AS BS ON T.id = BS.ticket_id
        WHERE T.trip_id = :trip_id
        GROUP BY T.id ORDER BY passenger_name ASC, T.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':trip_id' => $trip_id]);
    return $stmt->fetchAll();
}

function cancelTicketByAdmin(PDO $pdo, int $ticket_id, int $company_id)
{
     try {
        $pdo->beginTransaction();
        $sql_fetch = "SELECT T.id, T.user_id, T.total_price, T.status, Tr.company_id AS trip_company_id FROM Tickets AS T JOIN Trips AS Tr ON T.trip_id = Tr.id WHERE T.id = :ticket_id";
        $stmt_fetch = $pdo->prepare($sql_fetch);
        $stmt_fetch->execute([':ticket_id' => $ticket_id]);
        $ticket_info = $stmt_fetch->fetch();
        if (!$ticket_info) { throw new Exception("Bilet bulunamadı."); }
        if ($ticket_info['trip_company_id'] !== $company_id) { throw new Exception("Bu bileti iptal etme yetkiniz yok (farklı firma)."); }
        if ($ticket_info['status'] !== 'active') { throw new Exception("Bu bilet zaten iptal edilmiş veya süresi geçmiş."); }
        $sql_update_ticket = "UPDATE Tickets SET status = 'cancelled' WHERE id = :ticket_id";
        $stmt_update_ticket = $pdo->prepare($sql_update_ticket);
        $stmt_update_ticket->execute([':ticket_id' => $ticket_id]);
        $refund_amount = $ticket_info['total_price'];
        $user_id_to_refund = $ticket_info['user_id'];
        $sql_update_balance = "UPDATE Users SET balance = balance + :amount WHERE id = :user_id";
        $stmt_update_balance = $pdo->prepare($sql_update_balance);
        $stmt_update_balance->execute([':amount' => $refund_amount, ':user_id' => $user_id_to_refund]);
        $sql_delete_seats = "DELETE FROM Booked_Seats WHERE ticket_id = :ticket_id";
        $stmt_delete_seats = $pdo->prepare($sql_delete_seats);
        $stmt_delete_seats->execute([':ticket_id' => $ticket_id]);
        $pdo->commit();
        return true;
    } catch (Exception $e) { $pdo->rollBack(); return "İptal işlemi sırasında bir hata oluştu: " . $e->getMessage(); }
}

/**
 * Genel bir kuponu (company_id IS NULL) günceller.
 * Admin paneli tarafından kullanılır.
 *
 * @param PDO $pdo PDO bağlantı objesi.
 * @param string $coupon_id Güncellenecek kuponun ID'si.
 * @param array $data Güncelleme verilerini içeren dizi ('code', 'discount_percent', 'usage_limit', 'expire_date').
 * @return string Başarı veya hata mesajı.
 */
function update_general_coupon($pdo, $coupon_id, $data) {
    // Gerekli alanların varlığını ve temel formatını kontrol et
    if (empty($coupon_id) || empty($data['code']) || !isset($data['discount_percent']) || !is_numeric($data['discount_percent']) || !isset($data['usage_limit']) || !is_numeric($data['usage_limit']) || empty($data['expire_date'])) { 
        return "Hata: Kupon ID'si ve tüm alanlar (kod, oran, limit, tarih) güncellenirken gereklidir ve doğru formatta olmalıdır."; 
    }

    // Yüzde olarak gelen indirim oranını ondalık formata çevir
    $discount_percent_int = (int)$data['discount_percent'];
    if ($discount_percent_int <= 0 || $discount_percent_int > 100) { 
        return "Hata: İndirim oranı %1 ile %100 arasında bir tam sayı olmalıdır."; 
    }
    $discount_decimal = $discount_percent_int / 100.0;

    // Diğer verileri temizle
    $code = strtoupper(trim($data['code']));
    $usage_limit = (int)$data['usage_limit'];
    $expire_date = $data['expire_date']; // Formatın YYYY-MM-DD olduğundan emin olunmalı (input type=date sağlar)

    // SQL UPDATE sorgusu (Sadece company_id NULL olanları hedefler)
    $sql = "
        UPDATE Coupons SET 
            code = :code, 
            discount = :discount, 
            usage_limit = :usage_limit, 
            expire_date = :expire_date 
        WHERE 
            id = :coupon_id AND company_id IS NULL 
    ";

    try {
        $stmt = $pdo->prepare($sql);
        // Parametreleri bağla
        $stmt->bindParam(':code', $code);
        $stmt->bindParam(':discount', $discount_decimal);
        $stmt->bindParam(':usage_limit', $usage_limit);
        $stmt->bindParam(':expire_date', $expire_date);
        $stmt->bindParam(':coupon_id', $coupon_id);
        
        $stmt->execute();

        // Etkilenen satır sayısını kontrol et
        if ($stmt->rowCount() > 0) {
            return "Genel kupon başarıyla güncellendi.";
        } else {
            // Hiç satır etkilenmediyse ya kupon bulunamadı ya da veriler aynıydı.
            return "Hata: Genel kupon bulunamadı, size ait değil (bu admin için geçerli değil) veya gönderilen veriler mevcut verilerle aynı.";
        }
    } catch (PDOException $e) {
         // UNIQUE kısıtlaması hatası (başka bir kupon aynı koda sahipse)
         if ($e->getCode() == '23000' || strpos($e->getMessage(), 'UNIQUE constraint failed')) { 
            return "Hata: Girdiğiniz kupon kodu ('{$code}') başka bir kupon tarafından zaten kullanılıyor."; 
        }
        // Diğer veritabanı hataları
        return "Hata: Genel kupon güncellenirken bir veritabanı sorunu oluştu: " . $e->getMessage();
    }
}

/**
 * Genel bir kuponu (company_id IS NULL) siler.
 * Admin paneli tarafından kullanılır.
 *
 * @param PDO $pdo PDO bağlantı objesi.
 * @param string $coupon_id Silinecek kuponun ID'si.
 * @return string Başarı veya hata mesajı.
 */
function delete_general_coupon($pdo, $coupon_id) {
    // Kupon ID'sinin boş olup olmadığını kontrol et
    if (empty($coupon_id)) { 
        return "Hata: Silinecek genel kupon ID'si belirtilmedi."; 
    }

    // SQL DELETE sorgusu (Sadece company_id NULL olanları hedefler)
    $sql = "DELETE FROM Coupons WHERE id = :coupon_id AND company_id IS NULL";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':coupon_id', $coupon_id);
        $stmt->execute();

        // Etkilenen satır sayısını kontrol et
        if ($stmt->rowCount() > 0) {
            return "Genel kupon başarıyla silindi.";
        } else {
            return "Hata: Silinecek genel kupon bulunamadı.";
        }
    } catch (PDOException $e) {
        // Kupon başka bir tabloda (örn: User_Coupons) kullanılıyorsa FOREIGN KEY hatası verebilir.
        if ($e->getCode() == '23000' || strpos($e->getMessage(), 'FOREIGN KEY constraint failed')) {
            return "Hata: Bu genel kupon daha önce bir kullanıcıya atanmış veya kullanılmış olduğu için silinemez. (İlişkili kayıtlar var)";
        }
        // Diğer veritabanı hataları
        return "Hata: Genel kupon silinirken bir veritabanı sorunu oluştu: " . $e->getMessage();
    }
}
?>