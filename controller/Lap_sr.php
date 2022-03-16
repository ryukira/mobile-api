<?php

class Lap_sr extends My_db{
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
            "RJLDT.CB" => $data->cabang,
            "RJLDT.CUSTOM" => $data->pelanggan,
            "RJLDT.SALESM" => $data->salesman,
            "G.INISIAL LIKE" => "%".$data->barang."%",
            "RJLDT.TANGGAL BETWEEN" => $filtertgl
        );

        // if ($data->outs == 'outs') {
        //     $where["(RJLHD.QTY-RJLHD.QTZ) >"] = '0';
        // }
        // else if($data->outs == 'tertutup'){
        //     $where["(RJLHD.QTY-RJLHD.QTZ)"] = '0';
        // }

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
                    $keyword = $data->keyword == '' ? '' : "AND RJLHD.TANGGAL = '$keyword'";
                }

                $sql = "SELECT RJLHD.TANGGAL AS REKAP, COUNT(DISTINCT(RJLHD.NOMOR)) AS TOTALFAKTUR, SUM((RJLDT.QTY*(HARGA+RJLDT.TAXN)) * RJLDT.KURS) AS TOTALHARGA
                        FROM RJLHD, RJLDT, $dbmaster..[GROUP] AS G";
                $sql .= $where2. " AND RJLHD.NOMOR=RJLDT.NOMOR AND RJLDT.[GROUP]=G.KODE $keyword";
                $sql .= " GROUP BY RJLHD.TANGGAL ORDER BY RJLHD.TANGGAL DESC";

                break;
            case 'pelanggan':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND CUSTOM.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT CUSTOM.NAMA AS REKAP, COUNT(DISTINCT(RJLHD.NOMOR)) AS TOTALFAKTUR, SUM((RJLDT.QTY*(HARGA+RJLDT.TAXN)) * RJLDT.KURS) AS TOTALHARGA
                        FROM RJLHD, RJLDT, CUSTOM, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND RJLHD.NOMOR=RJLDT.NOMOR AND RJLHD.CUSTOM = CUSTOM.KODE AND RJLDT.[GROUP]=G.KODE $keyword ";
                $sql .= " GROUP BY CUSTOM.NAMA ORDER BY TOTALHARGA DESC";
                break;
            case 'salesman':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND SALESM.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT SALESM.NAMA AS REKAP, COUNT(DISTINCT(RJLHD.NOMOR)) AS TOTALFAKTUR, SUM((RJLDT.QTY*(HARGA+RJLDT.TAXN)) * RJLDT.KURS) AS TOTALHARGA
                        FROM RJLHD, RJLDT, $dbmaster..SALESM, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND RJLHD.NOMOR=RJLDT.NOMOR AND RJLHD.SALESM = SALESM.KODE AND RJLDT.[GROUP]=G.KODE $keyword ";
                $sql .= " GROUP BY SALESM.NAMA ORDER BY TOTALHARGA DESC";
                break;
            case 'barang':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND PROD1.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT PROD1.NAMA AS REKAP, SUM(RJLDT.QTY) AS TOTALFAKTUR, SUM((RJLDT.QTY*(HARGA+RJLDT.TAXN)) * RJLDT.KURS) AS TOTALHARGA
                        FROM RJLHD, RJLDT, PROD1, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND RJLDT.[GROUP]=PROD1.[GROUP] AND RJLDT.BARANG=PROD1.KODE AND RJLHD.NOMOR=RJLDT.NOMOR AND RJLDT.[GROUP]=G.KODE $keyword ";
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
        $filtertgl = "AND RJLDT.TANGGAL BETWEEN ' $data->tanggal' AND ' $data->tanggal2'";
        $cb = $data->cabang == ''? '' : "AND RJLDT.CB = '$data->cabang'";
        $pelanggan = $data->pelanggan == ''? '' : "AND RJLDT.CUSTOM = '$data->pelanggan'";
        $salesman = $data->salesman == ''? '' : "AND RJLDT.SALESM = '$data->salesman'";
        $barang = $data->barang == ''? '' : "AND G.INISIAL LIKE '%$data->barang%'";

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
            $keyword = $data->keyword == '' ? '' : "AND RJLDT.NOMOR LIKE '%$keyword%'";
        }

        
        // DETAIL FAKTUR
        if($data->fn=='faktur'){
            
            $sql = "SELECT * FROM (
                        SELECT RJLHD.NOMOR AS REKAP, COUNT(RJLDT.BARANG)AS TOTALFAKTUR, 
                        SUM((RJLDT.QTY*(HARGA+RJLDT.TAXN)) * RJLDT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY RJLHD.NOMOR DESC) AS ROWNUM 
                        FROM RJLHD, RJLDT, $dbmaster..[GROUP] AS G 
                        WHERE RJLHD.NOMOR=RJLDT.NOMOR AND RJLDT.[GROUP]=G.KODE AND RJLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $pelanggan $salesman $barang $keyword 
                        GROUP BY RJLHD.NOMOR
                    )AS DATA
                    WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

            $faktur = $this->query($sql);
            $list = $faktur->fetchAll(PDO::FETCH_ASSOC);

            if($page == '1'){
                $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT RJLHD.NOMOR AS REKAP, COUNT(RJLDT.BARANG)AS TOTALFAKTUR, 
                        SUM((RJLDT.QTY*(HARGA+RJLDT.TAXN)) * RJLDT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY RJLHD.NOMOR DESC) AS ROWNUM 
                        FROM RJLHD, RJLDT, $dbmaster..[GROUP] AS G 
                        WHERE RJLHD.NOMOR=RJLDT.NOMOR AND RJLDT.[GROUP]=G.KODE AND RJLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $pelanggan $salesman $barang $keyword 
                        GROUP BY RJLHD.NOMOR
                    )AS DATA";

                $total = $this->query($sql);
                $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                $sql = "SELECT 
                        SUM((RJLDT.QTY*(HARGA+RJLDT.TAXN)) * RJLDT.KURS) AS TOTALHARGA
                        FROM RJLDT, $dbmaster..[GROUP] AS G 
                        WHERE RJLDT.[GROUP]=G.KODE AND RJLDT.TANGGAL = '$data->rekaptgl' $filtertgl $cb $pelanggan $salesman $barang ";

                $totHrg = $this->query($sql);
                $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                $totalPage = $totaldata['TOTALDATA'] / $perpage;
            }
        }

        //DETAIL BARANG
        if($data->fn=='barang'){
            
            $sql = "SELECT PROD1.NAMA AS REKAP, SUM(RJLDT.QTY) AS TOTALFAKTUR, SUM(HARGA+RJLDT.TAXN) AS HARGASATUAN, SUM((RJLDT.QTY*(HARGA+RJLDT.TAXN)) * RJLDT.KURS) AS TOTALHARGA, RJLDT.SAT
                    FROM RJLHD, RJLDT, PROD1, $dbmaster..[GROUP] AS G ";
            $sql .= " WHERE RJLDT.[GROUP]=PROD1.[GROUP] AND RJLDT.BARANG=PROD1.KODE AND RJLHD.NOMOR=RJLDT.NOMOR AND RJLDT.[GROUP]=G.KODE AND RJLDT.NOMOR = '$data->nofak' AND RJLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $pelanggan $salesman $barang ";
            $sql .= " GROUP BY PROD1.NAMA, RJLDT.NOURUT, RJLDT.SAT ORDER BY RJLDT.NOURUT ASC";

            $barang = $this->query($sql);
            $list = $barang->fetchAll(PDO::FETCH_ASSOC);
        }


        return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
    }
}
