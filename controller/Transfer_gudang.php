<?php

class Transfer_gudang extends My_db{
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
            "P.GUDANG" => $data->gudang,
            "P.PINDAH" => $data->pindah,
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

                $sql = "SELECT P.TANGGAL AS REKAP,SUM(P.QTY*P.HARGA) AS TOTALHARGA
                        FROM TXDT P ";
                $sql .= $where2. " AND P.NOMOR <> '' $keyword ";
                $sql .= " GROUP BY P.TANGGAL ORDER BY P.TANGGAL DESC";

                break;
            case 'faktur':
                if ($search) {
                    $keyword = preg_replace('/[\W_]+/','',$data->keyword);
                    $keyword = $data->keyword == '' ? '' : "AND P.NOMOR LIKE '%$keyword%'";
                }

                $sql = "SELECT P.NOMOR AS REKAP, SUM(P.QTY*P.HARGA) AS TOTALHARGA
                        FROM TXDT P ";
                $sql .= $where2." AND P.NOMOR <> '' $keyword ";
                $sql .= " GROUP BY P.NOMOR ORDER BY P.NOMOR DESC";
                // echo $sql;
                break;
            case 'barang':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND M.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT M.NAMA AS REKAP,SUM(P.QTY*P.HARGA) AS TOTALHARGA
                        FROM TXDT P,PROD1 M ";
                $sql .= $where2." AND P.NOMOR <> '' AND P.[GROUP]+P.BARANG=M.[GROUP]+M.KODE $keyword ";
                $sql .= " GROUP BY M.NAMA ORDER BY M.NAMA ASC";
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
        $filtertgl = "AND P.TANGGAL BETWEEN ' $data->tanggal' AND ' $data->tanggal2'";
        $cb = $data->cabang == ''? '' : "AND P.CB = '$data->cabang'";
        $gudang = $data->gudang == ''? '' : "AND P.GUDANG = '$data->gudang'";
        $pindah = $data->pindah == ''? '' : "AND P.PINDAH = '$data->pindah'";

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
            $keyword = $data->keyword == '' ? '' : "AND P.NOMOR LIKE '%$keyword%'";
        }
        
        // DETAIL FAKTUR
        if($data->fn=='faktur'){

            $sql = "SELECT * FROM (
                        SELECT P.NOMOR AS REKAP, COUNT(P.BARANG)AS TOTALFAKTUR, SUM(P.QTY*P.HARGA) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY P.NOMOR DESC) AS ROWNUM 
                        FROM TXDT P 
                        WHERE P.NOMOR <> '' AND P.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $pindah $keyword 
                        GROUP BY P.NOMOR 
                    )AS DATA
                    WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
                    // echo $sql;
            $faktur = $this->query($sql);
            $list = $faktur->fetchAll(PDO::FETCH_ASSOC);

            if($page == '1'){
                $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT P.NOMOR AS REKAP, COUNT(P.BARANG)AS TOTALFAKTUR, SUM(P.QTY*P.HARGA) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY P.NOMOR DESC) AS ROWNUM 
                        FROM TXDT P 
                        WHERE P.NOMOR <> '' AND P.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $pindah $keyword 
                        GROUP BY P.NOMOR 
                    )AS DATA";

                $total = $this->query($sql);
                $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                $sql = "SELECT SUM(P.QTY*P.HARGA) AS TOTALHARGA
                        FROM TXDT P 
                        WHERE P.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $pindah";

                $totHrg = $this->query($sql);
                $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                $totalPage = $totaldata['TOTALDATA'] / $perpage;
            }
        }

        // DETAIL BARANG
        if($data->fn=='barang'){
            
            $sql = "SELECT M.NAMA AS REKAP, SUM(P.QTY) AS TOTALFAKTUR, SUM(P.HARGA) AS HARGASATUAN, SUM(P.QTY*P.HARGA) AS TOTALHARGA, P.SAT
                    FROM TXDT P,PROD1 M ";
            $sql .= " WHERE P.NOMOR <> '' AND P.[GROUP]+P.BARANG=M.[GROUP]+M.KODE AND P.NOMOR = '$data->nofak' AND P.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $pindah ";
            $sql .= " GROUP BY M.NAMA, P.NOURUT, P.SAT ORDER BY P.NOURUT ASC";

            $barang = $this->query($sql);
            $list = $barang->fetchAll(PDO::FETCH_ASSOC);
        }


        return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];      
    }
}
