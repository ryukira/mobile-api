<?php

class Lap_po extends My_db{
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
            "PODT.CB" => $data->cabang,
            "G.INISIAL LIKE" => "%".$data->barang."%",
            "PODT.VENDOR" => $data->vendor,
            "PODT.TANGGAL BETWEEN" => $filtertgl
        );

        if ($data->outs == 'outs') {
            $where["(PODT.QTY-PODT.QTZ) >"] = '0';
        }
        else if($data->outs == 'tertutup'){
            $where["(PODT.QTY-PODT.QTZ)"] = '0';
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
                    $keyword = $data->keyword == '' ? '' : "AND POHD.TANGGAL = '$keyword'";
                }

                $sql = "SELECT POHD.TANGGAL AS REKAP, COUNT(DISTINCT(POHD.NOMOR)) AS TOTALFAKTUR, SUM(PODT.QTY*(PODT.HARGA-PODT.DISCN+PODT.BIAYA+PODT.TAXN+PODT.LAIN) * PODT.KURS) AS TOTALHARGA
                        FROM POHD, PODT, $dbmaster..[GROUP] AS G";
                $sql .= $where2. " AND POHD.NOMOR=PODT.NOMOR AND PODT.[GROUP]=G.KODE $keyword";
                $sql .= " GROUP BY POHD.TANGGAL ORDER BY POHD.TANGGAL DESC";

                break;
            case 'vendor':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND VENDOR.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT VENDOR.NAMA AS REKAP, COUNT(DISTINCT(POHD.NOMOR)) AS TOTALFAKTUR, SUM(PODT.QTY*(PODT.HARGA-PODT.DISCN+PODT.BIAYA+PODT.TAXN+PODT.LAIN) * PODT.KURS) AS TOTALHARGA
                        FROM POHD, PODT, VENDOR, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND POHD.NOMOR=PODT.NOMOR AND POHD.VENDOR = VENDOR.KODE  AND PODT.[GROUP]=G.KODE $keyword";
                $sql .= " GROUP BY VENDOR.NAMA ORDER BY TOTALHARGA DESC";
                break;
            case 'barang':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND PROD1.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT PROD1.NAMA AS REKAP, SUM(PODT.QTY) AS TOTALFAKTUR, SUM(PODT.QTY*(PODT.HARGA-PODT.DISCN+PODT.BIAYA+PODT.TAXN+PODT.LAIN) * PODT.KURS) AS TOTALHARGA
                        FROM POHD, PODT, PROD1, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND PODT.[GROUP]=PROD1.[GROUP] AND PODT.BARANG=PROD1.KODE AND POHD.NOMOR=PODT.NOMOR AND PODT.[GROUP]=G.KODE $keyword ";
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
        $filtertgl = "AND PODT.TANGGAL BETWEEN ' $data->tanggal' AND ' $data->tanggal2'";
        $cb = $data->cabang == ''? '' : "AND PODT.CB = '$data->cabang'";
        $vendor = $data->vendor == ''? '' : "AND PODT.CUSTOM = '$data->vendor'";
        $barang = $data->barang == ''? '' : "AND G.INISIAL LIKE '%$data->barang%'";
        $outs = '';
        if ($data->outs == 'outs') {
            $outs = "AND (PODT.QTY-PODT.QTZ) > '0'";
        }
        else if($data->outs == 'tertutup'){
            $outs = "AND (PODT.QTY-PODT.QTZ) = '0'";
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
            $keyword = $data->keyword == '' ? '' : "AND POHD.NOMOR LIKE '%$keyword%'";
        }

        
        // DETAIL FAKTUR
        if($data->fn=='faktur'){

            $sql = "SELECT * FROM (
                        SELECT POHD.NOMOR AS REKAP, COUNT(PODT.BARANG)AS TOTALFAKTUR, 
                        SUM(PODT.QTY*(PODT.HARGA-PODT.DISCN+PODT.BIAYA+PODT.TAXN+PODT.LAIN) * PODT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY POHD.NOMOR DESC) AS ROWNUM
                        FROM POHD, PODT, $dbmaster..[GROUP] AS G 
                        WHERE POHD.NOMOR=PODT.NOMOR AND PODT.[GROUP]=G.KODE AND POHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $barang $outs  $keyword
                        GROUP BY POHD.NOMOR
                    ) AS DATA
                    WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

            $faktur = $this->query($sql);
            $list = $faktur->fetchAll(PDO::FETCH_ASSOC);


            if($page=='1'){
                $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                            SELECT POHD.NOMOR AS REKAP, COUNT(PODT.BARANG)AS TOTALFAKTUR, 
                            SUM(PODT.QTY*(PODT.HARGA-PODT.DISCN+PODT.BIAYA+PODT.TAXN+PODT.LAIN) * PODT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY POHD.NOMOR DESC) AS ROWNUM
                            FROM POHD, PODT, $dbmaster..[GROUP] AS G 
                            WHERE POHD.NOMOR=PODT.NOMOR AND PODT.[GROUP]=G.KODE AND POHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $barang $outs  $keyword
                            GROUP BY POHD.NOMOR
                        ) AS DATA";

                $total = $this->query($sql);
                $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                $sql = "SELECT  
                        SUM(PODT.QTY*(PODT.HARGA-PODT.DISCN+PODT.BIAYA+PODT.TAXN+PODT.LAIN) * PODT.KURS) AS TOTALHARGA
                        FROM PODT, $dbmaster..[GROUP] AS G 
                        WHERE PODT.[GROUP]=G.KODE AND PODT.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $barang $outs";

                $totHrg = $this->query($sql);
                $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                $totalPage = $totaldata['TOTALDATA'] / $perpage;

            }
        }

        // DETAIL BARANG
        if($data->fn=='barang'){
            
            $sql = "
                        SELECT PROD1.NAMA AS REKAP, SUM(PODT.QTY) AS TOTALFAKTUR, SUM(PODT.HARGA-PODT.DISCN+PODT.BIAYA+PODT.TAXN+PODT.LAIN) AS HARGASATUAN, SUM(PODT.QTY*(PODT.HARGA-PODT.DISCN+PODT.BIAYA+PODT.TAXN+PODT.LAIN) * PODT.KURS) AS TOTALHARGA, PODT.SAT
                        FROM POHD, PODT, PROD1, $dbmaster..[GROUP] AS G 
                            WHERE PODT.[GROUP]=PROD1.[GROUP] AND PODT.BARANG=PROD1.KODE AND POHD.NOMOR=PODT.NOMOR AND PODT.[GROUP]=G.KODE AND PODT.NOMOR = '$data->nofak' AND POHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $barang $outs
                        GROUP BY PROD1.NAMA, PODT.NOURUT, PODT.SAT ORDER BY PODT.NOURUT ASC";

            $barang = $this->query($sql);
            $list = $barang->fetchAll(PDO::FETCH_ASSOC);
        }


        return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
    }
}
