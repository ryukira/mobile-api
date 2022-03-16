<?php

class Pembayaran_hutang extends My_db{
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
            "P.CB" => $data->cabang,
            "P.VENDOR" => $data->vendor,
            "P.TBAYAR" => $data->tbayar,
            "P.TANGGAL BETWEEN" => $filtertgl
        );


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
                    $keyword = $data->keyword == '' ? '' : "AND P.TANGGAL = '$keyword'";
                }

                $sql = "SELECT P.TANGGAL AS REKAP,SUM((P.BAYAR+P.DISCN)*P.KURS) AS TOTALHARGA, COUNT(DISTINCT P.VENDOR) AS TOTALFAKTUR
                        FROM PHTGDT P ";
                $sql .= $where2. " AND P.NOMOR <> '' $keyword ";
                $sql .= " GROUP BY P.TANGGAL ORDER BY P.TANGGAL DESC";

                break;
            case 'vendor':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND M.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT M.NAMA AS REKAP,SUM((P.BAYAR+P.DISCN)*P.KURS) AS TOTALHARGA
                        FROM PHTGDT P,VENDOR M ";
                $sql .= $where2." AND P.NOMOR <> '' AND P.VENDOR=M.KODE $keyword ";
                $sql .= " GROUP BY M.NAMA ORDER BY M.NAMA ASC";
                break;
            case 'tbayar':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND M.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT M.NAMA AS REKAP,SUM((P.BAYAR+P.DISCN)*P.KURS) AS TOTALHARGA
                        FROM PHTGDT P,$dbmaster..TBAYAR M ";
                $sql .= $where2." AND P.NOMOR <> '' AND P.TBAYAR=M.KODE $keyword ";
                $sql .= " GROUP BY M.NAMA ORDER BY M.NAMA ASC";
                break;
            case 'voucher':
                if ($search) {
                    $keyword = preg_replace('/[\W_]+/','',$data->keyword);
                    $keyword = $data->keyword == '' ? '' : "AND P.NOMOR LIKE '%$keyword%'";
                }
                $sql = "SELECT P.NOMOR AS REKAP, SUM((P.BAYAR+P.DISCN)*P.KURS) AS TOTALHARGA
                        FROM PHTGDT P ";
                $sql .= $where2." AND P.NOMOR <> '' $keyword ";
                $sql .= " GROUP BY P.NOMOR ORDER BY P.NOMOR DESC";
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
        $supplier = isset($data->supplier) ? "AND P.VENDOR = '$data->supplier'" : '';

        //FILTER
        $filtertgl = "AND P.TANGGAL BETWEEN ' $data->tanggal' AND ' $data->tanggal2'";
        $cb = $data->cabang == ''? '' : "AND P.CB = '$data->cabang'";
        $vendor = $data->vendor == ''? '' : "AND P.VENDOR = '$data->vendor'";
        $tbayar = $data->tbayar == ''? '' : "AND P.TBAYAR = '$data->tbayar'";

        //PAGINATION
        $perpage = $this->perpage;
        $page = isset($data->page) ? $data->page : 0;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;

        $totalPage = 0;
        $totHrg = 0;
        
        $keyword = '';
        if ($search) {
            $keyword = $data->keyword == '' ? '' : "AND M.NAMA LIKE '%$data->keyword%'";
        }
        
        
        // DETAIL SUPPLIER
        if($data->fn=='supplier'){

            $sql = "SELECT * FROM (
                        SELECT P.VENDOR, M.NAMA AS REKAP, SUM((P.BAYAR+P.DISCN)*P.KURS) AS TOTALHARGA, COUNT(DISTINCT P.NOMOR) AS TOTALFAKTUR, ROW_NUMBER() OVER (ORDER BY M.NAMA ASC) AS ROWNUM
                        FROM PHTGDT P, VENDOR M 
                        WHERE P.NOMOR <> '' AND P.VENDOR=M.KODE AND P.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $tbayar $keyword
                        GROUP BY M.NAMA, P.VENDOR 
                    )AS DATA
                    WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
                    // echo $sql;
            $supplier = $this->query($sql);
            $list = $supplier->fetchAll(PDO::FETCH_ASSOC);

            if ($page == 1) {
                $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                            SELECT P.VENDOR, M.NAMA AS REKAP, SUM((P.BAYAR+P.DISCN)*P.KURS) AS TOTALHARGA, COUNT(DISTINCT P.NOMOR) AS TOTALFAKTUR, ROW_NUMBER() OVER (ORDER BY M.NAMA ASC) AS ROWNUM
                        FROM PHTGDT P, VENDOR M 
                        WHERE P.NOMOR <> '' AND P.VENDOR=M.KODE AND P.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $tbayar $keyword
                        GROUP BY M.NAMA, P.VENDOR 
                    )AS DATA";

                $total = $this->query($sql);
                $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                $sql = "SELECT  
                        SUM((P.BAYAR+P.DISCN)*P.KURS) AS TOTALHARGA
                        FROM PHTGDT P 
                        WHERE P.NOMOR <> '' AND P.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $tbayar";
                
                $totHrg = $this->query($sql);
                $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                $totalPage = $totaldata['TOTALDATA'] / $perpage;
            }
        }

        // DETAIL FAKTUR
        if($data->fn=='faktur'){
            
            $sql = "SELECT DISTINCT P.NOMOR AS REKAP, SUM((P.BAYAR+P.DISCN)*P.KURS) AS TOTALHARGA
                    FROM PHTGDT P ";
            $sql .= " WHERE P.NOMOR <> '' $supplier AND P.TANGGAL = '$data->rekaptgl' $filtertgl $cb $vendor $tbayar ";
            $sql .= " GROUP BY P.NOMOR ORDER BY P.NOMOR DESC";

            $faktur = $this->query($sql);
            $list = $faktur->fetchAll(PDO::FETCH_ASSOC);
        }


        return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];       
    }
}
