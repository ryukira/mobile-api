<?php

class Lap_so extends My_db{
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

          $result = $this->detail($data, $data->search);
        }
        else{
            $result= $this->all($id, $data, $data->search);
        }
        
        return $result;
    }

    function all($id='', $data=null, $search=false){
        $dbmaster = $_SESSION['dbname']."SHRDBF";
        
        $bulan = substr($_SESSION['periode'], 2,2);
        $tahun = 2000 + substr($_SESSION['periode'], 0,2);
        $awal = date('Y-m-d', strtotime($tahun."-".$bulan."-01"));
        $akhir = date('Y-m-t', strtotime($awal));

        $filtertgl = $data->tanggal."' AND '".$data->tanggal2;

        $where = array(
            "SODT.CB" => $data->cabang,
            "SODT.CUSTOM" => $data->pelanggan,
            "SODT.SALESM" => $data->salesman,
            "G.INISIAL LIKE" => "%".$data->barang."%",
            "SODT.TANGGAL BETWEEN" => $filtertgl
        );

        if ($data->outs == 'outs') {
            $where["(SODT.QTY-SODT.QTZ) >"] = '0';
        }
        else if($data->outs == 'tertutup'){
            $where["(SODT.QTY-SODT.QTZ)"] = '0';
        }

        $where1 = array_filter($where, function($data){
            return $data != "";    
        });

        $where2 = '';        
        if ($where1 != null) {    
            $where2 = $this->where($where1); 
        }

        // search
        $keyword = '';

        switch ($data->orderby) {
            case 'tanggal':
                if ($search) {
                    $keyword = date('Y-m-d', strtotime($data->keyword));
                    $keyword = $data->keyword == '' ? '' : "AND SOHD.TANGGAL = '$keyword'";
                }

                $sql = "SELECT SOHD.TANGGAL AS REKAP, COUNT(DISTINCT(SOHD.NOMOR)) AS TOTALFAKTUR, SUM(SODT.QTY*(SODT.HARGA-SODT.DISCN+SODT.BIAYA+SODT.TAXN+SODT.LAIN) * SODT.KURS) AS TOTALHARGA
                        FROM SOHD, SODT, $dbmaster..[GROUP] AS G";
                $sql .= $where2. " AND SOHD.NOMOR=SODT.NOMOR AND SODT.[GROUP]=G.KODE $keyword";
                $sql .= " GROUP BY SOHD.TANGGAL ORDER BY SOHD.TANGGAL DESC";
                // echo $sql;
                break;
            case 'pelanggan':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND CUSTOM.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT CUSTOM.NAMA AS REKAP, COUNT(DISTINCT(SOHD.NOMOR)) AS TOTALFAKTUR, SUM(SODT.QTY*(SODT.HARGA-SODT.DISCN+SODT.BIAYA+SODT.TAXN+SODT.LAIN) * SODT.KURS) AS TOTALHARGA
                        FROM SOHD, SODT, CUSTOM, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND SOHD.NOMOR=SODT.NOMOR AND SOHD.CUSTOM = CUSTOM.KODE AND SODT.[GROUP]=G.KODE $keyword ";
                $sql .= " GROUP BY CUSTOM.NAMA ORDER BY TOTALHARGA DESC";
                break;
            case 'salesman':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND SALESM.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT SALESM.NAMA AS REKAP, COUNT(DISTINCT(SOHD.NOMOR)) AS TOTALFAKTUR, SUM(SODT.QTY*(SODT.HARGA-SODT.DISCN+SODT.BIAYA+SODT.TAXN+SODT.LAIN) * SODT.KURS) AS TOTALHARGA
                        FROM SOHD, SODT, $dbmaster..SALESM, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND SOHD.NOMOR=SODT.NOMOR AND SOHD.SALESM = SALESM.KODE AND SODT.[GROUP]=G.KODE $keyword ";
                $sql .= " GROUP BY SALESM.NAMA ORDER BY TOTALHARGA DESC";
                break;
            case 'barang':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND PROD1.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT PROD1.NAMA AS REKAP, SUM(SODT.QTY) AS TOTALFAKTUR, SUM(SODT.QTY*(SODT.HARGA-SODT.DISCN+SODT.BIAYA+SODT.TAXN+SODT.LAIN) * SODT.KURS) AS TOTALHARGA
                        FROM SOHD, SODT, PROD1, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND SODT.[GROUP]=PROD1.[GROUP] AND SODT.BARANG=PROD1.KODE AND SOHD.NOMOR=SODT.NOMOR AND SODT.[GROUP]=G.KODE $keyword ";
                $sql .= " GROUP BY PROD1.NAMA ORDER BY TOTALFAKTUR DESC";
                break;
            default:
                # code...
                break;
        }

        
        // echo $sql;

        

        $so = $this->query($sql);

        return $so->fetchAll(PDO::FETCH_ASSOC);
    }

    function detail($data, $search=false){
        $dbmaster = $_SESSION['dbname']."SHRDBF";

        //FILTER
        $filtertgl = "AND SODT.TANGGAL BETWEEN ' $data->tanggal' AND ' $data->tanggal2'";
        $cb = $data->cabang == ''? '' : "AND SODT.CB = '$data->cabang'";
        $pelanggan = $data->pelanggan == ''? '' : "AND SODT.CUSTOM = '$data->pelanggan'";
        $salesman = $data->salesman == ''? '' : "AND SODT.SALESM = '$data->salesman'";
        $barang = $data->barang == ''? '' : "AND G.INISIAL LIKE '%$data->barang%'";
        $outs = '';
        if ($data->outs == 'outs') {
            $outs = "AND (SODT.QTY-SODT.QTZ) > '0'";
        }
        else if($data->outs == 'tertutup'){
            $outs = "AND (SODT.QTY-SODT.QTZ) = '0'";
        }

        //PAGINATION
        $perpage = $this->perpage;
        $page = isset($data->page) ? $data->page : 0;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;

        $totalPage = 0;
        $totHrg = 0;
        
        $keyword = '';
        if ($search) {
            $keyword = preg_replace('/[\W_]+/','',$data->keyword);
            $keyword = $data->keyword == '' ? '' : "AND SOHD.NOMOR LIKE '%$keyword%'";
        }
        // print_r($data);
        // DETAIL FAKTUR
        if($data->fn=='faktur'){

            $sql = "SELECT * FROM (
                        SELECT SOHD.NOMOR AS REKAP, COUNT(SODT.BARANG)AS TOTALFAKTUR, 
                        SUM(SODT.QTY*(SODT.HARGA-SODT.DISCN+SODT.BIAYA+SODT.TAXN+SODT.LAIN) * SODT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY SOHD.NOMOR DESC) AS ROWNUM
                        FROM SOHD, SODT, $dbmaster..[GROUP] AS G
                        WHERE SOHD.NOMOR=SODT.NOMOR AND SODT.[GROUP]=G.KODE AND SOHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $pelanggan $salesman $barang $outs  $keyword 
                        GROUP BY SOHD.NOMOR
                    ) AS DATA
                    WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
                    // echo $sql;
            $faktur = $this->query($sql);
            $list = $faktur->fetchAll(PDO::FETCH_ASSOC);

            if ($page == 1) {
                $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT SOHD.NOMOR AS REKAP, COUNT(SODT.BARANG)AS TOTALFAKTUR, 
                        SUM(SODT.QTY*(SODT.HARGA-SODT.DISCN+SODT.BIAYA+SODT.TAXN+SODT.LAIN) * SODT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY SOHD.NOMOR DESC) AS ROWNUM
                        FROM SOHD, SODT, $dbmaster..[GROUP] AS G
                        WHERE SOHD.NOMOR=SODT.NOMOR AND SODT.[GROUP]=G.KODE AND SOHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $pelanggan $salesman $barang $outs  $keyword 
                        GROUP BY SOHD.NOMOR
                    ) AS DATA";

                $total = $this->query($sql);
                $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                $sql = "SELECT  
                        SUM(SODT.QTY*(SODT.HARGA-SODT.DISCN+SODT.BIAYA+SODT.TAXN+SODT.LAIN) * SODT.KURS) AS TOTALHARGA
                        FROM SODT, $dbmaster..[GROUP] AS G
                        WHERE SODT.TANGGAL = '$data->rekaptgl' AND SODT.[GROUP]=G.KODE $filtertgl $cb $pelanggan $salesman $barang $outs";
                
                $totHrg = $this->query($sql);
                $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                $totalPage = $totaldata['TOTALDATA'] / $perpage;
            }
        }

        // DETAIL BARANG
         if($data->fn=='barang'){
            
            $sql = "SELECT PROD1.NAMA AS REKAP, SUM(SODT.QTY) AS TOTALFAKTUR, SUM(SODT.HARGA-SODT.DISCN+SODT.BIAYA+SODT.TAXN+SODT.LAIN) AS HARGASATUAN, SUM(SODT.QTY*(SODT.HARGA-SODT.DISCN+SODT.BIAYA+SODT.TAXN+SODT.LAIN) * SODT.KURS) AS TOTALHARGA, SODT.SAT
                    FROM SOHD, SODT, PROD1, $dbmaster..[GROUP] AS G ";
            $sql .= " WHERE SODT.[GROUP]=PROD1.[GROUP] AND SODT.BARANG=PROD1.KODE AND SOHD.NOMOR=SODT.NOMOR AND SODT.[GROUP]=G.KODE AND SODT.NOMOR = '$data->nofak' AND SOHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $pelanggan $salesman $barang $outs ";
            $sql .= " GROUP BY PROD1.NAMA, SODT.NOURUT, SODT.SAT ORDER BY SODT.NOURUT ASC";

            $barang = $this->query($sql);
            $list = $barang->fetchAll(PDO::FETCH_ASSOC);
        }


        return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
        
    }
}
