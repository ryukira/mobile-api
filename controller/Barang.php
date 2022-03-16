<?php

class Barang extends My_db{
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
            if($data->fn == 'search'){
                $result = $this->search($data);
            }else{

                $result = $this->detail($data);
            }
        }
        else{
            $result= $this->all($id, $data);
        }
        return $result;
    }

    function all($id='', $data=null){
        $dbmaster = $_SESSION['dbname']."SHRDBF";
        //FILTER
        // $where = array(
        //     "G.INISIAL LIKE" => "%".$data->kelompokbarang."%",
        //     "P.MEREK" => $data->merek,
        //     "P.TIPE" => $data->tipe,
        //     "P.AKTIF" => $data->status
        // );


        // $where1 = array_filter($where, function($data){
        //     return $data != "";    
        // });

        // $where2 = '';        
        // if ($where1 != null) {    
        //     $where2 = $this->where($where1); 
        // }
        $group = $data->kelompokbarang == '' ? '' : "AND G.INISIAL LIKE '%$data->kelompokbarang%'";
        $merek = $data->merek == '' ? '' : "AND P.MEREK = '$data->merek'";
        $tipe = $data->tipe == '' ? '' : "AND P.TIPE = '$data->tipe'";
        $status = $data->status == '' ? '' : "AND P.AKTIF = '$data->status'";

        //PAGINATION
        $perpage = $this->perpage;
        $page = $data->page;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;

        $sql = "SELECT * FROM (
                    SELECT P.KODE, P.[GROUP], P.NAMA, G.NAMA AS KELOMPOK, P.STOK, P.SAT, ROW_NUMBER() OVER (ORDER BY P.NAMA ASC) AS ROWNUM
                    FROM PROD1 P, $dbmaster..[GROUP] G 
                    WHERE P.[GROUP]=G.KODE $group $merek $tipe $status
                    GROUP BY P.KODE, P.NAMA, P.SAT, P.STOK, P.[GROUP], G.NAMA
                ) AS DATA
                WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
        // echo $sql;
        
        $barang = $this->query($sql);
        $list =  $barang->fetchAll(PDO::FETCH_ASSOC);

        $totalPage = 0;
        if($page == 1){

            $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT P.KODE, P.[GROUP], P.NAMA, G.NAMA AS KELOMPOK, P.STOK, P.SAT, ROW_NUMBER() OVER (ORDER BY P.NAMA ASC) AS ROWNUM
                        FROM PROD1 P, $dbmaster..[GROUP] G 
                        WHERE P.[GROUP]=G.KODE $group $merek $tipe $status
                        GROUP BY P.KODE, P.NAMA, P.SAT, P.STOK, P.[GROUP], G.NAMA
                    ) AS DATA";
            // echo $sql;

            $total = $this->query($sql);
            $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));


            $totalPage = $totaldata['TOTALDATA'] / $perpage;
        }
            
        return ["list"=>$list, "total"=>$totalPage];
    }


    function detail($data){

        $dbmaster = $_SESSION['dbname']."SHRDBF";
        $sql = "SELECT P.KODE, P.NAMA, P.TIPE AS TRACKING, P.INISIAL, P.BARCODE, P.STDJUAL, P.STDBELI, P.STOK, M.NAMA AS BRAND, G.NAMA AS NMGROUP, P.SAT 
            FROM PROD1 P 
            INNER JOIN $dbmaster..[GROUP] G ON G.KODE = P.[GROUP] 
            INNER JOIN $dbmaster..MEREK M ON M.KODE = P.MEREK
            WHERE P.KODE='$data->kodebarang' AND G.KODE='$data->groupbarang'";
            $gd = $this->query($sql);
            $db = $gd->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT G.NAMA AS RINCI1, SUM(CASE WHEN P3.FLAG <= 5 THEN P3.QTY*P3.HTG/P1.HTG ELSE -P3.QTY*P3.HTG/P1.HTG END) AS QTY 
            FROM PROD3 P3, PROD1 P1, $dbmaster..GUDANG G 
            WHERE P3.[GROUP]=P1.[GROUP] AND P3.BARANG=P1.KODE AND P3.GUDANG=G.KODE AND P1.KODE='$data->kodebarang' AND P1.[GROUP]='$data->groupbarang' AND P3.TANGGAL<='$data->tglakhir' 
            GROUP BY G.NAMA
            ORDER BY RINCI1";
            $dt = $this->query($sql);
            $dt = $dt->fetchAll(PDO::FETCH_ASSOC);
    
        return array("detail_barang"=>current($db), "detail_stok"=>$dt);
    }

    function search($data){

        $dbmaster = $_SESSION['dbname']."SHRDBF";

        $search = $data->keyword == '' ? '' : "AND P.NAMA LIKE '%$data->keyword%'";

        $group = $data->kelompokbarang == '' ? '' : "AND G.INISIAL LIKE '%$data->kelompokbarang%'";
        $merek = $data->merek == '' ? '' : "AND P.MEREK = '$data->merek'";
        $tipe = $data->tipe == '' ? '' : "AND P.TIPE = '$data->tipe'";
        $status = $data->status == '' ? '' : "AND P.AKTIF = '$data->status'";

        //PAGINATION
        $perpage = $this->perpage;
        $page = $data->pageSearch;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;

        $sql = "SELECT * FROM(
                    SELECT P.KODE, P.[GROUP], P.NAMA, G.NAMA AS KELOMPOK, P.STOK, P.SAT, ROW_NUMBER() OVER (ORDER BY P.NAMA ASC) AS ROWNUM
                    FROM PROD1 P, $dbmaster..[GROUP] G 
                    WHERE P.[GROUP]=G.KODE $group $merek $tipe $status $search
                    GROUP BY P.KODE, P.NAMA, P.SAT, P.STOK, P.[GROUP], G.NAMA
                ) AS DATA
                WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
        $searchL = $this->query($sql);
        $searchL = $searchL->fetchAll(PDO::FETCH_ASSOC);

        $totalPage = 0;
        if($page == 1){

            $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT P.KODE, P.[GROUP], P.NAMA, G.NAMA AS KELOMPOK, P.STOK, P.SAT, ROW_NUMBER() OVER (ORDER BY P.NAMA ASC) AS ROWNUM
                        FROM PROD1 P, $dbmaster..[GROUP] G 
                        WHERE P.[GROUP]=G.KODE $group $merek $tipe $status $search
                        GROUP BY P.KODE, P.NAMA, P.SAT, P.STOK, P.[GROUP], G.NAMA
                    ) AS DATA";
            $total = $this->query($sql);
            $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

            $totalPage = $totaldata['TOTALDATA'] / $perpage;
        }

        return array("list"=>$searchL, "total"=>$totalPage);
    }
}
