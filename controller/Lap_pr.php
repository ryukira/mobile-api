<?php

class Lap_pr extends My_db{
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
            "RBLDT.CB" => $data->cabang,
            "G.INISIAL LIKE" => "%".$data->barang."%",
            "RBLDT.VENDOR" => $data->vendor,
            "RBLDT.TANGGAL BETWEEN" => $filtertgl
        );

        // if ($data->outs == 'outs') {
        //     $where["(RBLHD.QTY-RBLHD.QTZ) >"] = '0';
        // }
        // else if($data->outs == 'tertutup'){
        //     $where["(RBLHD.QTY-RBLHD.QTZ)"] = '0';
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
                    $keyword = $data->keyword == '' ? '' : "AND RBLHD.TANGGAL = '$keyword'";
                }

                $sql = "SELECT RBLHD.TANGGAL AS REKAP, COUNT(DISTINCT(RBLHD.NOMOR)) AS TOTALFAKTUR, SUM(RBLDT.QTY*(HARGA+RBLDT.TAXN) * RBLDT.KURS) AS TOTALHARGA
                        FROM RBLHD, RBLDT, $dbmaster..[GROUP] AS G";
                $sql .= $where2. " AND RBLHD.NOMOR=RBLDT.NOMOR AND RBLDT.[GROUP]=G.KODE $keyword ";
                $sql .= " GROUP BY RBLHD.TANGGAL ORDER BY RBLHD.TANGGAL DESC";

                break;
            case 'vendor':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND VENDOR.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT VENDOR.NAMA AS REKAP, COUNT(DISTINCT(RBLHD.NOMOR)) AS TOTALFAKTUR, SUM(RBLDT.QTY*(HARGA+RBLDT.TAXN) * RBLDT.KURS) AS TOTALHARGA
                        FROM RBLHD, RBLDT, VENDOR, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND RBLHD.NOMOR=RBLDT.NOMOR AND RBLHD.VENDOR = VENDOR.KODE AND RBLDT.[GROUP]=G.KODE $keyword ";
                $sql .= " GROUP BY VENDOR.NAMA ORDER BY TOTALHARGA DESC";
                break;
            case 'barang':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND PROD1.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT PROD1.NAMA AS REKAP, SUM(RBLDT.QTY) AS TOTALFAKTUR, SUM(RBLDT.QTY*(HARGA+RBLDT.TAXN) * RBLDT.KURS) AS TOTALHARGA
                        FROM RBLHD, RBLDT, PROD1, $dbmaster..[GROUP] AS G";
                $sql .= $where2." AND RBLDT.[GROUP]=PROD1.[GROUP] AND RBLDT.BARANG=PROD1.KODE AND RBLHD.NOMOR=RBLDT.NOMOR AND RBLDT.[GROUP]=G.KODE $keyword ";
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
        $filtertgl = "AND RBLDT.TANGGAL BETWEEN ' $data->tanggal' AND ' $data->tanggal2'";
        $cb = $data->cabang == ''? '' : "AND RBLDT.CB = '$data->cabang'";
        $vendor = $data->vendor == ''? '' : "AND RBLDT.CUSTOM = '$data->vendor'";
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
            $keyword = $data->keyword == '' ? '' : "AND RBLDT.NOMOR LIKE '%$keyword%'";
        }

        
        // DETAIL FAKTUR
        if($data->fn=='faktur'){
            
            $sql = "SELECT * FROM (
                        SELECT RBLHD.NOMOR AS REKAP, COUNT(RBLDT.BARANG)AS TOTALFAKTUR, 
                        SUM(RBLDT.QTY*(HARGA+RBLDT.TAXN) * RBLDT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY RBLHD.NOMOR DESC) AS ROWNUM 
                        FROM RBLHD, RBLDT, $dbmaster..[GROUP] AS G 
                        WHERE RBLHD.NOMOR=RBLDT.NOMOR AND RBLDT.[GROUP]=G.KODE AND RBLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $barang $keyword 
                        GROUP BY RBLHD.NOMOR
                    )AS DATA
                    WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

            $faktur = $this->query($sql);
            $list = $faktur->fetchAll(PDO::FETCH_ASSOC);

            if($page == '1'){
                $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT RBLHD.NOMOR AS REKAP, COUNT(RBLDT.BARANG)AS TOTALFAKTUR, 
                        SUM(RBLDT.QTY*(HARGA+RBLDT.TAXN) * RBLDT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY RBLHD.NOMOR DESC) AS ROWNUM 
                        FROM RBLHD, RBLDT, $dbmaster..[GROUP] AS G 
                        WHERE RBLHD.NOMOR=RBLDT.NOMOR AND RBLDT.[GROUP]=G.KODE AND RBLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $barang $keyword 
                        GROUP BY RBLHD.NOMOR
                    )AS DATA";

                $total = $this->query($sql);
                $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                $sql = "SELECT 
                        SUM(RBLDT.QTY*(HARGA+RBLDT.TAXN) * RBLDT.KURS) AS TOTALHARGA
                        FROM RBLDT, $dbmaster..[GROUP] AS G 
                        WHERE RBLDT.[GROUP]=G.KODE AND RBLDT.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $barang ";

                $totHrg = $this->query($sql);
                $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                $totalPage = $totaldata['TOTALDATA'] / $perpage;
            }
        }

        //DETAIL BARANG
        if($data->fn=='barang'){
            
            $sql = "SELECT PROD1.NAMA AS REKAP, SUM(RBLDT.QTY) AS TOTALFAKTUR, SUM(HARGA+RBLDT.TAXN) AS HARGASATUAN, SUM(RBLDT.QTY*(HARGA+RBLDT.TAXN) * RBLDT.KURS) AS TOTALHARGA, RBLDT.SAT
                    FROM RBLHD, RBLDT, PROD1, $dbmaster..[GROUP] AS G ";
            $sql .= " WHERE RBLDT.[GROUP]=PROD1.[GROUP] AND RBLDT.BARANG=PROD1.KODE AND RBLHD.NOMOR=RBLDT.NOMOR AND RBLDT.[GROUP]=G.KODE AND RBLDT.NOMOR = '$data->nofak' AND RBLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $barang ";
            $sql .= " GROUP BY PROD1.NAMA, RBLDT.NOURUT, RBLDT.SAT ORDER BY RBLDT.NOURUT ASC";

            $barang = $this->query($sql);
            $list = $barang->fetchAll(PDO::FETCH_ASSOC);
        }


        return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
    }
}
