<?php

class My_db 
{
    protected $hostname;
    protected $username;
    protected $password;
    protected $dbname;
    protected $conn;
    protected $where;
    protected $perpage;
    

    function __construct(){
        $host = $this->hostname;
        $user = $this->username;
        $pass = $this->password;
        $db = $this->dbname;
        $this->perpage = 20;

        $this->conn = $this->getConnection($host, $user, $pass, $db);
    }

    // function __destruct(){
    //     $this->closeConnection();
    // }

    function getConnection($host, $user, $pass, $db){
        try {
            
            $conn = new PDO("sqlsrv:server = tcp:$host; Database=$db", $user, $pass);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $conn;

        } catch (PDOException  $th) {
            // echo $th->getMessage();
            $tahun = substr($_SESSION['periode'], 0, 2) +2000;
            $bulan = sprintf('%02d', substr($_SESSION['periode'], 2, 2));

            $err = json_encode(array("message"=>"Periode bulan $bulan tahun $tahun tidak ada"));
            die($err);

        }
    }

    function closeConnection(){
        $this->conn = null;
    }


    function query($sql, $data=null){

        // echo $sql;
        
        $stmt = $this->conn->prepare($sql);

        if (is_null($data)) {
            $stmt->execute();
        } else {
            $stmt->execute($data);
        }
        return $stmt;
    }

    function where ($where=null){
        $column = [];

        if(is_array($where)){
            foreach ($where as $key => $value) {
                $spasi = strpos($key, " ");
                
                if ($spasi <= 0) {
                    array_push($column, "$key = '$value'");
                } else {
                    array_push($column, "$key '$value'");
                }
            }

            $where = implode(" and ", $column);
            return $this->where = ' where '.$where;
        }
    }

    function update($data=null, $table){
        $column = [];

        if(is_array($data) && $data != null){
            foreach ($data as $key => $value) {
                array_push($column, "$key = '$value'");
            }

            $data = implode(", ", $column);
            $data = " SET ".$data;
        }
        
        $query = "update $table ".$data.$this->where;
        // echo $query;
        $stmt = $this->query($query);
        return $stmt;
    }

    function delete($table, $where=null){
        $query = "delete from $table ".$this->where;
        
        if ($where != null) {
            $query = "delete from $table where $where";   
        }
        
        $stmt = $this->query($query);
        return $stmt;
    }

    function insert($data=null, $table){
        $column = [];
        $values = [];
        if(is_array($data) && $data != null){
            foreach ($data as $key => $value) {
                array_push($column, $key);
                array_push($values, "'$value'");
            }

            $column = implode(", ", $column);
            $column = "( $column )";

            $values = implode(", ", $values);
            $values = "( $values )";
        }
        
        $query = "insert into $table $column values $values";

        // print_r($query);
        $stmt = $this->query($query);

        return $stmt;
    }
    
    function getNoFak($tempnofak, $table){
        $data = $this->query("select TOP 1 NOMOR from $table order by NOMOR desc");
        $data = current($data->fetchAll(PDO::FETCH_ASSOC));
        $nomor = $data['NOMOR'] == '' ? $tempnofak.'0000' : $data['NOMOR'];
        $temp = substr($nomor, 0, 8);
        $temp2 = substr($nomor, 8)+1;
        $temp2 = sprintf('%04d',$temp2);

        $nofak = $temp.$temp2;
        return $nofak;
    }

    function getSatFromUkur($barang, $group){
        $query = "SELECT U.PAK, P.PTG, U.STDJUAL FROM UKURAN U, PROD1 P WHERE P.[GROUP] = U.[GROUP] AND P.KODE=U.BARANG AND U.[GROUP] = '$group' U.BARANG = '$barang'";
        $data = $this->query($query);
        $result = $data->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    function getNokey($nofak, $table){
        $query = "SELECT NOKEY from $table WHERE NOMOR = '$nofak' ORDER BY NOKEY DESC";
        $data = $this->query($query);
        $result = current($data->fetchAll(PDO::FETCH_ASSOC));
        $nokey = $result['NOKEY']+1;

        return sprintf('%05d',$nokey);
    }

    function getNoUrut($nofak, $table){
        $query = "SELECT NOURUT from $table WHERE NOMOR = '$nofak' ORDER BY NOURUT DESC";
        $data = $this->query($query);
        $result = current($data->fetchAll(PDO::FETCH_ASSOC));
        $nokey = $result['NOURUT']+1;

        return sprintf('%05d',$nokey);
    }

    function getCustomInfo($kode){
        $query = "SELECT * FROM CUSTOM WHERE KODE='$kode'";
        $data = $this->query($query)->fetchAll(PDO::FETCH_ASSOC);

        return current($data);
    }

    function getDetailTransaksi($nofak, $table, $all=true, $nourut=null){
        $query = "SELECT * FROM $table WHERE NOMOR = '$nofak'";

        if (!$all && $nourut !== null) {
            $query .= " AND NOURUT != '$nourut'";
        }
        else if ($nourut !== null && $all) {
            $query .= " AND NOURUT = '$nourut'";
        }

        $data = $this->query($query)->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    function getStok($where, $type){
        extract($where);
        $qry = "SELECT ISNULL(SUM(QTY*HTG), 0) AS TOTALSTOK FROM PROD3 WHERE BARANG = '$barang' AND [GROUP] = '$group' AND GUDANG = '$gudang' AND TANGGAL <= '$tanggal'";

        switch ($type) {
            case 'awal':
                $qry .= " AND FLAG = 1";
                break;
            case 'masuk':
                $qry .= " AND FLAG <= 5";
                break;
            case 'keluar':
                $qry .= " AND FLAG >= 6";
                break;
        }

        $data = $this->query($qry)->fetchAll(PDO::FETCH_ASSOC);
        $data = current($data);
        return $data['TOTALSTOK'];
    }
    
    function getQtyBarang ( $where, $table){
        extract($where);
        $qry = "SELECT ISNULL(SUM(QTY*HTG), 0) AS QTYBARANG FROM $table WHERE BARANG = '$barang' AND [GROUP] = '$group' AND GUDANG = '$gudang' AND NOMOR = '$nofak' AND NOURUT = '$nourut'";
        // echo $qry;
        $data = $this->query($qry)->fetchAll(PDO::FETCH_ASSOC);
        $data = current($data);
        return $data['QTYBARANG'];
    }

    function cekBarang($barang, $group){
        $qry = "SELECT BARANG FROM PODT WHERE BARANG = '$barang' AND [GROUP] = '$group' UNION ALL
                SELECT BARANG FROM SODT WHERE BARANG = '$barang' AND [GROUP] = '$group' UNION ALL
                SELECT BARANG FROM BLDT WHERE BARANG = '$barang' AND [GROUP] = '$group' UNION ALL
                SELECT BARANG FROM JLDT WHERE BARANG = '$barang' AND [GROUP] = '$group' UNION ALL
                SELECT BARANG FROM TUKARJ WHERE BARANG = '$barang' AND [GROUP] = '$group' UNION ALL
                SELECT BARANG FROM TUKARB WHERE BARANG = '$barang' AND [GROUP] = '$group' UNION ALL
                SELECT BARANG FROM RJLDT WHERE BARANG = '$barang' AND [GROUP] = '$group' UNION ALL
                SELECT BARANG FROM RBLDT WHERE BARANG = '$barang' AND [GROUP] = '$group'";
        $data = $this->query($qry)->fetchAll(PDO::FETCH_ASSOC);

        $result = 0;
        if($data != null){
            $result = $data;
        }
        return count($result);
    }

    function logUser($data){
        $result = $this->insert($data, 'SYSLOG');

        return $result;
    }



    function email($data){
        $html = urldecode($data->html);

        $lampiran['file'] = renderPdf($html);
        $lampiran['nama_file'] = $data->nofak.".pdf";

        // file_put_contents('bohyee.pdf', $lampiran['file']);
        $email = $data->email;
        $message = $data->message;
        $subject=$data->subject;

        $send = sendEmail($email, $message, $subject, $lampiran);

        if (!$send['status']) {
            return array('status'=>false, 'error'=>$send['err']);
        }

        return array('status'=>true);
    }


}

?>