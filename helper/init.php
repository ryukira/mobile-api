<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use Dompdf\Dompdf;

session_start();

date_default_timezone_set("Asia/Bangkok");

require_once(__DIR__.'/../controller/My_db.php');
require_once(__DIR__.'/../controller/So.php');
require_once(__DIR__.'/../controller/Si.php');
require_once(__DIR__.'/../controller/Cust.php');
require_once(__DIR__.'/../controller/Persediaan.php');
require_once(__DIR__.'/../controller/Penjualan.php');
require_once(__DIR__.'/../controller/Filter.php');
require_once(__DIR__.'/../controller/Filtercust.php');
require_once(__DIR__.'/../controller/Pembelian.php');
require_once(__DIR__.'/../controller/Salesman.php');
require_once(__DIR__.'/../controller/Barang.php');
require_once(__DIR__.'/../controller/Grafik.php');
require_once(__DIR__.'/../controller/Login.php');
require_once(__DIR__.'/../controller/Lap_hutang.php');
require_once(__DIR__.'/../controller/Lap_piutang.php');
require_once(__DIR__.'/../controller/Lap_so.php');
require_once(__DIR__.'/../controller/Lap_po.php');
require_once(__DIR__.'/../controller/Lap_sr.php');
require_once(__DIR__.'/../controller/Lap_pr.php');
require_once(__DIR__.'/../controller/Kas_harian.php');
require_once(__DIR__.'/../controller/Penerimaan_piutang.php');
require_once(__DIR__.'/../controller/Pembayaran_hutang.php');
require_once(__DIR__.'/../controller/Transfer_gudang.php');
require_once(__DIR__.'/../controller/Laba_rugi.php');
require_once(__DIR__.'/../controller/Adjustment.php');
require_once(__DIR__.'/../controller/Neraca.php');
require_once(__DIR__.'/../controller/Filter_transaksi.php');
require_once(__DIR__.'/../controller/Labarugi_bersih.php');

require_once(__DIR__.'/../vendor/expo_notifications/vendor/autoload.php');


function parseURL(){
    if(isset($_GET['url'])){
        $url = rtrim($_GET['url'], '/');
        $url = filter_var($url, FILTER_SANITIZE_URL);
        $url = explode('/', $url);

        return $url;
    }
}

function sendEmail($email, $message, $subject, $lampiran=null){

    require_once(__DIR__.'/../vendor/phpmailer/src/Exception.php');
    require_once(__DIR__.'/../vendor/phpmailer/src/PHPMailer.php');
    require_once(__DIR__.'/../vendor/phpmailer/src/SMTP.php');

    $mail = new PHPMailer(true);

    $username = "onlinesiscom@gmail.com";
    $password = "Siscom123";

    try {
        
        $mail->SMTPDebug =0;
        $mail->isSMTP();
        // $mail->Host = 'siscomonline.co.id';
        $mail->Host = 'smtp.gmail.com';
        $mail->Username = "onlinesiscom@gmail.com";
        $mail->Password = "Siscom123!";
        $mail->SMTPSecure = 'tls';
        $mail->SMTPAuth = true;
        $mail->Port = 587;

        $mail->setFrom($username);
        $mail->addAddress($email);

        if ($lampiran != null && is_array($lampiran)) {
            $mail->addStringAttachment($lampiran['file'], $lampiran['nama_file']);
        }

        $mail->Charset = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        return array('status'=>true);

    } catch (Exception $e) {
        // echo json_encode(array('err'=>$mail->ErrorInfo)); 
        return array('status'=>false, 'err'=>$mail->ErrorInfo);  
    }
}

function renderPdf($html){
    // require_once(__DIR__.'/../vendor/mpdf/content/modules/mPDF/MPDF61/mpdf.php');

    // $mpdf = new mPDF('utf-8', 'A4');

    // $html2 = urldecode($html);



    // $mpdf->WriteHTML('<h1>Laporan Penjualan Barang</h1>
    //             <table border="0">
    //                 <tr>
    //                     <th>No</th>
    //                     <th>No Faktur</th>
    //                     <th>Nama Barang</th>
    //                     <th>Qty</th>
    //                     <th>Harga</th>
    //                     <th>Total</th>
    //                 </tr>   
    //             </table>');

    // $mpdf->Output('doc3.pdf', 'F');
    // return 'fdff';

    require_once(__DIR__."/../vendor/dompdf/autoload.inc.php");

    $dompdf = new Dompdf();
    $dompdf->load_html($html, 'UTF-8');
    $dompdf->render();
    // $dompdf->stream('laporan_.pdf');
    return $dompdf->output();
}

// function isLogin(){
//     if (isset($_SESSION) && $_SESSION['host'] != '' && $_SESSION['email'] != '') {
//         return TRUE;
//     }
//     return TRUE;
// }

function http_request($url, $data){
    // persiapkan curl
    $ch = curl_init(); 

    // set url 
    curl_setopt($ch, CURLOPT_URL, $url);
    
    // set POST REQUEST WITH DATA
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    // set data header to json
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:Application/json; charset=UTF-8'));

    // return the transfer as a string 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 

    // $output contains the output string 
    $output = curl_exec($ch); 

    // tutup curl 
    curl_close($ch);      

    // mengembalikan hasil curl
    return json_decode($output);
}

?>
