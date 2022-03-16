<?php

class Persediaan extends My_db{
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
        // $dbname = strtoupper($_SESSION['dbname'])."SHRDBF";
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
       $gudang = $data->gudang == '' ? '' : "AND O.GUDANG = '$data->gudang'";
       $klpbrg = $data->kelompokbarang == '' ? '' : "AND G.INISIAL LIKE '%$data->kelompokbarang%'";
       $cb = $data->cabang == '' ? '' : "AND O.CB = '$data->cabang'";

       //paging
       $perpage = $this->perpage;
       $page = $data->page;
       $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
       $offset2 = ($offset + $perpage) - 1;

       // echo $perpage."<br>".$page;
                
        $sql = "SELECT * FROM (
                    SELECT O.BARANG, G.KODE AS GROUPKODE, P.NAMA AS N_BARANG, 
                        SUM(CASE WHEN O.FLAG<=5 THEN O.HARGA*O.QTY ELSE -O.FIFO END) AS HARGA, S.KODE AS N_SATUAN, 
                        SUM(CASE WHEN O.FLAG<=5 THEN O.QTY ELSE -O.QTY END) AS QTY, ROW_NUMBER() OVER (ORDER BY P.NAMA ASC) AS ROWNUM
                    FROM PROD3 O,PROD1 P,$dbmaster..GUDANG W, $dbmaster..CABANG C, $dbmaster..[GROUP] G, $dbmaster..SATUAN S 
                    WHERE P.[GROUP]+P.KODE = O.[GROUP]+O.BARANG AND W.KODE = O.GUDANG AND C.KODE = O.CB AND G.KODE = O.[GROUP] AND S.KODE = O.SAT AND O.TANGGAL <= '$data->tglakhir' $gudang $klpbrg $cb
                    GROUP BY P.NAMA, O.BARANG,G.KODE, S.KODE
                    ) AS DATA 
                WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
        // echo $sql;

        $persediaan = $this->query($sql);
        $list = $persediaan->fetchAll(PDO::FETCH_ASSOC);

        $totalPage = 0;
        $totHrg = 0;
        if($page == 1){

            $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT O.BARANG, G.KODE AS GROUPKODE, P.NAMA AS N_BARANG, 
                            SUM(CASE WHEN O.FLAG<=5 THEN O.HARGA*O.QTY ELSE -O.FIFO END) AS HARGA, S.KODE AS N_SATUAN, 
                            SUM(CASE WHEN O.FLAG<=5 THEN O.QTY ELSE -O.QTY END) AS QTY, ROW_NUMBER() OVER (ORDER BY P.NAMA ASC) AS ROWNUM
                        FROM PROD3 O,PROD1 P,$dbmaster..GUDANG W, $dbmaster..CABANG C, $dbmaster..[GROUP] G, $dbmaster..SATUAN S 
                        WHERE P.[GROUP]+P.KODE = O.[GROUP]+O.BARANG AND W.KODE = O.GUDANG AND C.KODE = O.CB AND G.KODE = O.[GROUP] AND S.KODE = O.SAT AND O.TANGGAL <= '$data->tglakhir' $gudang $klpbrg $cb
                        GROUP BY P.NAMA, O.BARANG,G.KODE, S.KODE
                        ) AS DATA";
            $total = $this->query($sql);
            $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

            $sql = "SELECT SUM(CASE WHEN O.FLAG<=5 THEN O.HARGA*O.QTY ELSE -O.FIFO END) AS HARGA, 
                        SUM(CASE WHEN O.FLAG<=5 THEN O.QTY ELSE -O.QTY END) AS QTY
                    FROM PROD3 O, $dbmaster..[GROUP] G
                    WHERE O.[GROUP]=G.KODE AND O.TANGGAL <= '$data->tglakhir' $gudang $klpbrg $cb";
            $totHrg = $this->query($sql);
            $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

            $totalPage = $totaldata['TOTALDATA'] / $perpage;
        }

            
        return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
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
            WHERE P3.[GROUP]=P1.[GROUP] AND P3.BARANG=P1.KODE AND P3.GUDANG=G.KODE AND P1.KODE='".$data->kodebarang."' AND P1.[GROUP]='$data->groupbarang' AND P3.TANGGAL<='".$data->tglakhir."' 
            GROUP BY G.NAMA
            ORDER BY RINCI1";
            $dt = $this->query($sql);
            $dt = $dt->fetchAll(PDO::FETCH_ASSOC);
    
        return array("detail_barang"=>current($db), "detail_stok"=>$dt);
    }

    function search($data){

        $dbmaster = $_SESSION['dbname']."SHRDBF";

        $search = $data->keyword == '' ? '' : "AND P.NAMA LIKE '%$data->keyword%'";

        $gudang = $data->gudang == '' ? '' : "AND O.GUDANG = '$data->gudang'";
        $klpbrg = $data->kelompokbarang == '' ? '' : "AND G.INISIAL LIKE '%$data->kelompokbarang%'";
        $cb = $data->cabang == '' ? '' : "AND O.CB = '$data->cabang'";

        //paging
        $perpage = $this->perpage;
        $page = $data->pageSearch;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;

        $sql = "SELECT * FROM (
                    SELECT O.BARANG, G.KODE AS GROUPKODE, P.NAMA AS N_BARANG, 
                        SUM(CASE WHEN O.FLAG<=5 THEN O.HARGA*O.QTY ELSE -O.FIFO END) AS HARGA, S.KODE AS N_SATUAN, 
                        SUM(CASE WHEN O.FLAG<=5 THEN O.QTY ELSE -O.QTY END) AS QTY, ROW_NUMBER() OVER (ORDER BY P.NAMA ASC) AS ROWNUM
                    FROM PROD3 O,PROD1 P,$dbmaster..GUDANG W, $dbmaster..CABANG C, $dbmaster..[GROUP] G, $dbmaster..SATUAN S 
                    WHERE P.[GROUP]+P.KODE = O.[GROUP]+O.BARANG AND W.KODE = O.GUDANG AND C.KODE = O.CB AND G.KODE = O.[GROUP] AND S.KODE = O.SAT AND O.TANGGAL <= '$data->tglakhir' $gudang $klpbrg $cb $search AND QTY > 0
                    GROUP BY P.NAMA, O.BARANG,G.KODE, S.KODE
                    ) AS DATA
                WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
        $searchL = $this->query($sql);
        $searchL = $searchL->fetchAll(PDO::FETCH_ASSOC);

        $totalPage = 0;
        if($page == 1){

            $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT O.BARANG, G.KODE AS GROUPKODE, P.NAMA AS N_BARANG, 
                            SUM(CASE WHEN O.FLAG<=5 THEN O.HARGA*O.QTY ELSE -O.FIFO END) AS HARGA, S.KODE AS N_SATUAN, 
                            SUM(CASE WHEN O.FLAG<=5 THEN O.QTY ELSE -O.QTY END) AS QTY, ROW_NUMBER() OVER (ORDER BY P.NAMA ASC) AS ROWNUM
                        FROM PROD3 O,PROD1 P,$dbmaster..GUDANG W, $dbmaster..CABANG C, $dbmaster..[GROUP] G, $dbmaster..SATUAN S 
                        WHERE P.[GROUP]+P.KODE = O.[GROUP]+O.BARANG AND W.KODE = O.GUDANG AND C.KODE = O.CB AND G.KODE = O.[GROUP] AND S.KODE = O.SAT AND O.TANGGAL <= '$data->tglakhir' $gudang $klpbrg $cb $search AND QTY > 0
                        GROUP BY P.NAMA, O.BARANG,G.KODE, S.KODE
                        ) AS DATA";
            $total = $this->query($sql);
            $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

            $totalPage = $totaldata['TOTALDATA'] / $perpage;
        }

        return array("list"=>$searchL, "total"=>$totalPage);
    }
}
