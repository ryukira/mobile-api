<?php
require_once("helper/init.php");

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
// header('Access-Control-Max-Age: 86400');
header('Content-Type: Application/json; charset=UTF-8');

// $_SESSION['userid']="P01";
// $_SESSION['hostname']="DESKTOP-IUA8UFG";
// $_SESSION['dbname'] = "NEWINT1909";
// $_SESSION['username'] = "sa";
// $_SESSION['password'] = "Siscom3519";
// session_destroy();
// print_r($_SESSION);

ini_set('memory_limit', '2048M');
ini_set('max_execution_time', '0');

$class = ucfirst(parseURL()[0]);

// session_destroy();

if (!empty($class) &&  $class != 'Login' && $class != 'Logout' && $class != 'UbahPeriode' && isset($_SESSION['userid'])) {
    $data = json_decode(file_get_contents('php://input'));
    
    // print_r($data);
    $controller = new $class();
    $method = strtolower($_SERVER['REQUEST_METHOD']);
    
    if ($method == 'get') {
        $id = isset(parseURL()[1]) ? parseURL()[1] : '';        
        
        $get = $controller->get($id);

        if (count($get) <= 0) {
            http_response_code(405);
            echo json_encode(array("message" => "Tidak Ada Data Yang Ditemukan"));
  
        }        
        else {
            http_response_code(200);
            echo json_encode(array("status" => "success", "data"=>$get));
        }
        
    }
    else if($method == 'post'){
        $data2 =[];

        if(isset($data->post)){
            $data2 = $controller->post($data);
        }
        else{
            switch ($class) {
                case 'Persediaan':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Penjualan':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Pembelian':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Cust':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Salesman':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Barang':
                    $data2 = $controller->get('', $data);
                    break;
                case 'So':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Si':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Grafik':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Lap_hutang':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Lap_piutang':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Lap_so':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Lap_po':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Lap_sr':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Lap_pr':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Kas_harian':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Penerimaan_piutang':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Pembayaran_hutang':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Transfer_gudang':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Laba_rugi':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Adjustment':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Neraca':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Filter_transaksi':
                    $data2 = $controller->get('', $data);
                    break;
                case 'Labarugi_bersih':
                    $data2 = $controller->get('', $data);
                    break;
                default:
                    
                    break;
            }
        }
        
        
        if(count($data2) <= 0 && isset($data->post)) {
            http_response_code(404);
            echo json_encode(array("message" => "Data Tidak Ditemukan"));
  
        } 
        elseif(isset($data->post) && !$data2['status']){
            http_response_code(404);
            echo json_encode(array("message" => "Data Tidak Ditemukan", "error"=>$data2['error']));
        } 
        else {
            http_response_code(200);
            echo json_encode(array("status" => "success", "data"=>$data2));
        }

    }
    else if($method == 'put'){
        $id = isset(parseURL()[1]) ? parseURL()[1] : '';
        $update = $controller->updateData($id, $data);
        // print_r($update);
        if(!$update['status']) {
            http_response_code(404);
            echo json_encode(array("message" => "Update Data failed", "error"=>$update['error']));
  
        }        
        else {
            http_response_code(200);
            echo json_encode(array("status" => "success"));
        }
    }
    else if($method == 'delete'){
        $id = isset(parseURL()[1]) ? parseURL()[1] : '';
        $delete = $controller->deleteData($id, $data);

        if(!$delete['status']) {
            http_response_code(404);
            echo json_encode(array("message" => "Delete Data failed", "error"=>$delete['error']));
  
        }        
        else {
            http_response_code(200);
            echo json_encode(array("status" => "success"));
        }
    }
    else {
        
    }
}  
else if($class == 'UbahPeriode'){
    $data = json_decode(file_get_contents('php://input'));
    
    $_SESSION['periode'] = $data->periode;

    $controller = new Login();
    $dataUser = $controller->getUserData();

    if (count($dataUser)>0) {
        $data = array_merge($dataUser);

        http_response_code(200);
        $status = array("status" => 'success', "data"=>$data);   

    }
    else{
        http_response_code(404);
        $status = array("status" => 'failed', "message"=>$dataUser['error']);   

    }
    echo json_encode($status);
    
}
else if($class == 'Login'){
    $data = json_decode(file_get_contents('php://input'));

    // $url = "https://finance.siscom.id/api/addon/".$data->email."/".$data->appcode;
    // $data2 = json_decode(file_get_contents($url));


    $code = strtoupper($data->appcode);
    $pass = $data->password;
    // if ($data2->status == 'success') {
        $datadb = $data->dataLogin;
        
        $now = strtotime(date("Y-m-d"));
        // echo $now."<br>";
        foreach ($datadb as $key => $value) {
            // echo $pass."<br>".$value->password_user;
            // echo password_verify($pass, $value->password_user);
            // echo password_verify($pass, $value->hash);

            if ($code == $value->code && password_verify($pass, $value->password_user) && $value->valid == 'Y' && $value->user_status == 'A' && $value->host_status=='A' && $value->is_login == 'N' ) {
                $_SESSION['hostname'] = strtoupper($value->hostname);
                $_SESSION['username'] = $value->username; 
                $_SESSION['dbname'] = strtoupper($value->dbname); 
                $_SESSION['password'] = $value->password; 
                
                $_SESSION['email'] = $value->user_email; 
                $_SESSION['userid'] = $value->user_id;
                $_SESSION['periode'] = $data->periode;
                // $_SESSION['token'] = $data->token;

                $controller = new $class();
                $dataUser = $controller->getUserData();
                
                if (count($dataUser)>0) {

                    $user = (array) $value;
                    $data = array_merge($user, $dataUser);

                    http_response_code(200);
                    $status = array("status" => 'success', "data"=>$data);   

                }
                else{
                    http_response_code(404);
                    $status = array("status" => 'failed', "message"=>$dataUser['error']);   

                }
                break;
            }
            else if($key < (count($datadb)-1)){
                continue;
            }
            else{
                http_response_code(403);
                if($value->valid=='N'){
                    $status = array('status' => 'failed', 'message'=>'Email belum diaktifkan. Silakan konfirmasi email terlebih dahulu.'); 
                }
                else if($value->user_status=='D'){
                    $status = array('status' => 'failed', 'message'=>'Akun Anda belum diaktifkan. Silakan hubungi Finance SISCOM.'); 
                }
                else if($value->host_status=='D'){
                    $status = array('status' => 'failed', 'message'=>'Akun Anda tidak dapat digunakan. Host belum diaktifkan.'); 
                }
                else if($code != $value->code){
                    $status = array('status' => 'failed', 'message'=>'App Code salah.'); 
                }
                else if(!password_verify($pass, $value->password_user)){
                    $status = array('status' => 'failed', 'message'=>'Password salah.'); 
                }
                else if($value->is_login == 'Y'){
                    $status = array('status' => 'failed', 'message'=>"User $data->email sedang digunakan. \nLast login: $value->login_time."); 
                }
                // else if(strtotime($value->expired_date)>$now){
                //     $status = array('status' => 'failed', 'message'=>'Tanggal berlaku App Code sudah habis. Silakan hubungi Finance SISCOM.'); 
                // }
                break;
            }
        }
        
    // } else {
    //     http_response_code(403);
    //     $status = array('status' => 'failed', 'message'=>'Email tidak terdaftar. Silakan registrasi terlebih dahulu.');
    // }
    
    // print_r($data2);
    echo json_encode($status);
    
}
else if($class == 'Logout'){
    $login = new Login();
    $logout =  $login->logout();

    
    if ($logout) {
        session_destroy();
        http_response_code(200);
        $status = array("status" => 'success', "message"=>"Berhasil Logout ");
    }
    else{
        $status = array("status" => 'failed', "message"=>"Gagal Logout ");
    }
    echo json_encode($status);
}
else{
    http_response_code(404);
    $status = array("status" => 'not login', "message"=>"Service does not exist ");
    echo json_encode($status);
}