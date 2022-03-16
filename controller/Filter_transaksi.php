<?php

class Filter_transaksi extends My_db{
    protected $hostname;
    protected $username;
    protected $password;
    protected $dbname;

    function __construct(){
        $this->hostname = $_SESSION['hostname'];
        $this->username = $_SESSION['username'];
        $this->password = $_SESSION['password'];
        $this->dbname = $_SESSION['dbname'].$_SESSION['periode'];

        parent::__construct();
    }

    function get($id='', $data=null){
        if(isset($data->fn)){
            if ($data->fn==='so') {
               return $this->faktur($data, $data->search);
            }

            return $this->barang($data, $data->search);
        }
        else{
            return $this->all($id, $data);
        }
        
    }

    function all($id='', $data=null){
        // $dbname = strtoupper($_SESSION['dbname'])."SHRDBF";
        $dbmaster = $_SESSION['dbname']."SHRDBF";
        $gudang = [];
        if(isset($data->cabang)){
            $sql = "SELECT KODE, NAMA FROM $dbmaster..GUDANG WHERE CB = '$data->cabang' ORDER BY KODE ASC";
            $gd = $this->query($sql);
            $gudang = $gd->fetchAll(PDO::FETCH_ASSOC);
        }


        $sql = "SELECT KODE, NAMA FROM $dbmaster..[CABANG] ORDER BY NAMA ASC";
        $cb = $this->query($sql);
        $cabang = $cb->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM VENDOR ORDER BY NAMA ASC";
        $ven = $this->query($sql);
        $vendor = $ven->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM $dbmaster..SALESM ORDER BY NAMA ASC";
        $sales = $this->query($sql);
        $salesman = $sales->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA, WILAYAH, SALESM FROM CUSTOM ORDER BY NAMA ASC";
        $cust = $this->query($sql);
        $customer = $cust->fetchAll(PDO::FETCH_ASSOC);
        

        return array("cabang"=>$cabang, "gudang"=>$gudang, "supplier"=>$vendor, "salesman"=>$salesman, "pelanggan"=>$customer);
        
    }

    function barang($data, $search=false){

        //PAGINATION
        // $perpage = $this->perpage;
        $perpage = 30;
        $page = isset($data->page) ? $data->page : 0;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;
        $gudang = $data->gudang;
        $group = isset($data->group)? $data->group : '';
        $barang = isset($data->barang)? $data->barang : '';

        $totalPage = 0;
        $keyword = '';
        $keywordImei = '';

        if ($search) {
            $keyword = $data->keyword == '' ? '' : "AND NAMA LIKE '%$data->keyword%'";
            $keywordImei = $data->keyword == '' ? '' : "AND IMEI LIKE '%$data->keyword%'";
        }

        if($data->fn == 'barang'){
            $sql = "SELECT * FROM (
                        SELECT KODE, PROD1.[GROUP], NAMA, HTG, STDJUAL, STDBELI, SAT, ROW_NUMBER() OVER (ORDER BY NAMA ASC) AS ROWNUM, WARE1.STOK, PROD1.TIPE
                        FROM PROD1, WARE1 
                        WHERE WARE1.[GROUP]=PROD1.[GROUP] AND WARE1.BARANG = PROD1.KODE AND WARE1.GUDANG='$gudang' $keyword
                    ) AS DATA
                    WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
            $brg = $this->query($sql);
            $list = $brg->fetchAll(PDO::FETCH_ASSOC);

            if($page=='1'){
                $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                            SELECT KODE, PROD1.[GROUP], NAMA, HTG, STDJUAL, STDBELI, SAT, ROW_NUMBER() OVER (ORDER BY NAMA ASC) AS ROWNUM, WARE1.STOK, PROD1.TIPE
                            FROM PROD1, WARE1 
                            WHERE WARE1.[GROUP]=PROD1.[GROUP] AND WARE1.BARANG = PROD1.KODE AND WARE1.GUDANG='$gudang' $keyword
                        ) AS DATA";
                $total = $this->query($sql);
                $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                $totalPage = $totaldata['TOTALDATA'] / $perpage;
            }
        }
        elseif($data->fn == 'imei'){
            $sql = "SELECT * FROM (
                        SELECT IMEI, HARGA, ROW_NUMBER() OVER (ORDER BY IMEI ASC) AS ROWNUM 
                        FROM IMEIX 
                        WHERE GUDANG = '$gudang' AND BARANG = '$barang' AND [GROUP] = '$group' AND FLAG <= 5 $keywordImei
                    ) AS DATA
                    WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
            // echo $sql;
            $brg = $this->query($sql);
            $list = $brg->fetchAll(PDO::FETCH_ASSOC);

            if($page=='1'){
                $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                            SELECT IMEI, HARGA, ROW_NUMBER() OVER (ORDER BY IMEI ASC) AS ROWNUM 
                            FROM IMEIX 
                            WHERE GUDANG = '$gudang' AND BARANG = '$barang' AND [GROUP] = '$group' AND FLAG <= 5 $keywordImei
                        ) AS DATA";
                $total = $this->query($sql);
                $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                $totalPage = $totaldata['TOTALDATA'] / $perpage;
            }
        }

        return ["list"=>$list, "total"=>$totalPage];
        
    }

    function faktur($data, $search=false){

        //PAGINATION
        // $perpage = $this->perpage;
        $perpage = 30;
        $page = isset($data->page) ? $data->page : 0;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;

        $pelanggan = $data->pelanggan;
        $tgl = date('Y-m-d', strtotime(str_replace('/', '-', $data->tgl)));
        $totalPage = 0;
        $keyword = '';
        $keywordImei = '';

        if ($search) {
            $keyword = $data->keyword == '' ? '' : "AND NOMOR LIKE '%$data->keyword%'";
        }

        // if($data->fn == 'barang'){
            $sql = "SELECT * FROM (
                        SELECT NOMOR,(QTY-QTZ) AS SISA, ROW_NUMBER() OVER (ORDER BY NOMOR ASC) AS ROWNUM 
                        FROM SOHD 
                        WHERE QTY-QTZ > 0 AND TANGGAL<='$tgl' AND CUSTOM='$pelanggan' AND POSJ='V' $keyword
                    ) AS DATA
                    WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
            $brg = $this->query($sql);
            $list = $brg->fetchAll(PDO::FETCH_ASSOC);


            if($page=='1'){
                $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                            select NOMOR from SOHD where QTY-QTZ > 0 AND TANGGAL<='$tgl' AND CUSTOM='$pelanggan' AND POSJ='V' $keyword
                        ) AS DATA";
                $total = $this->query($sql);
                $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                $totalPage = $totaldata['TOTALDATA'] / $perpage;
            }
        // }

        return ["list"=>$list, "total"=>$totalPage];
        
    }
}
