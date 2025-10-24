<?php
session_start();
require_once 'functions.php'; 
require_once 'lib/fpdf/fpdf.php'; 


if (!isset($_SESSION['user_id'])) { die("PDF oluşturmak için giriş yapmalısınız."); }
if ($_SESSION['user_role'] !== 'user' && $_SESSION['user_role'] !== 'company_admin') { die("Bu sayfaya erişim yetkiniz yok."); }
if (!isset($_GET['ticket_uuid'])) { die("Hata: İndirilecek bilet belirtilmedi."); }

$ticket_uuid = $_GET['ticket_uuid'];
$user_id = $_SESSION['user_id'];


$ticket_details = getTicketDetailsForPdf($pdo, $ticket_uuid, $user_id);

if (!$ticket_details) { die("Hata: Bilet bulunamadı veya bu bileti görüntüleme yetkiniz yok."); }



class PDF extends FPDF
{
    function Header()
    {
        
        $this->SetFont('Helvetica','B',15); 
        $this->Cell(0,10, $this->ConvertUTF8('A-bilet Yolcu Bileti'),0,0,'C');
        $this->Ln(20); 
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica','I',8); 
        $this->Cell(0,10,$this->ConvertUTF8('Sayfa ').$this->PageNo().'/{nb}',0,0,'C');
    }
    
   
    function ConvertUTF8($text) {
        if (function_exists('mb_convert_encoding')) {
            
            return mb_convert_encoding($text, 'ISO-8859-9', 'UTF-8');
        } elseif (function_exists('iconv')) {
        
            return iconv('UTF-8', 'ISO-8859-9//TRANSLIT', $text);
        } else {
             return $text; 
        }
    }
    
     
    function DetailRow($label, $value) {
        
        $this->SetFont('Helvetica','B',11); 
        $this->Cell(50, 8, $this->ConvertUTF8($label . ': '), 0, 0, 'L');
        $this->SetFont('Helvetica','',11); 
        $this->Cell(0, 8, $this->ConvertUTF8($value), 0, 1, 'L');
    }
}


$pdf = new PDF();
$pdf->AliasNbPages(); 
$pdf->AddPage();
$pdf->SetFont('Helvetica','',11); 
$pdf->SetFillColor(230, 242, 255);



$pdf->SetFont('Helvetica','B',14); 
$pdf->Cell(0, 10, $pdf->ConvertUTF8('Yolculuk Bilgileri'), 0, 1, 'L', true); 
$pdf->Ln(5);

$pdf->DetailRow('Firma', $ticket_details['company_name']);
$pdf->DetailRow('Güzergah', $ticket_details['departure_city'] . ' -> ' . $ticket_details['destination_city']);

$pdf->DetailRow('Kalkış Tarihi', date('d.m.Y', strtotime($ticket_details['departure_time']))); 
$pdf->DetailRow('Kalkış Saati', date('H:i', strtotime($ticket_details['departure_time'])));
$pdf->DetailRow('Tahmini Varış', date('H:i', strtotime($ticket_details['arrival_time'])));

$pdf->Ln(10); 

$pdf->SetFont('Helvetica','B',14); 
$pdf->Cell(0, 10, $pdf->ConvertUTF8('Yolcu ve Bilet Bilgileri'), 0, 1, 'L', true);
$pdf->Ln(5);

$pdf->DetailRow('Yolcu Adı', $ticket_details['passenger_name']);
$pdf->DetailRow('Koltuk No(lar)', implode(', ', $ticket_details['seat_numbers']));
$pdf->DetailRow('Bilet Fiyatı', number_format($ticket_details['total_price'], 2) . ' TL');
$pdf->DetailRow('Bilet Durumu', $ticket_details['status']);
$pdf->DetailRow('Satın Alma Tarihi', date('d.m.Y H:i', strtotime($ticket_details['purchase_date'])));
$pdf->DetailRow('Bilet No (UUID)', $ticket_details['ticket_uuid']);



$filename = "A_Bilet_" . $ticket_details['departure_city'] . "_" . date('Ymd', strtotime($ticket_details['departure_time'])) . ".pdf";
$pdf->Output('D', $filename); 

exit; 
?>