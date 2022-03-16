<?php

class Neraca extends My_db{
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
            $result= $this->all($id, $data, $data->search);
        }
        return $result;
    }

    function all($id='', $data=null, $search=false){
        $dbmaster = $_SESSION['dbname']."SHRDBF";
        $bulan = $data->bulan;
        $tahun = $data->tahun;

        $level = $data->level;

        //PAGINATION
        $perpage = $this->perpage;
        $page = $data->page;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;

        // search
        $keyword = '';
        if ($search) {
            $keyword = $data->keyword == '' ? '' : "AND NAMA LIKE '%$data->keyword%'";
        }


        $totalPage = 0;
        $totHrg = 0;

        $sql = "SELECT * FROM (
                    SELECT NAMA,TDIS,LEVEL,B$bulan AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY NOMOR ASC) AS ROWNUM
                    FROM $dbmaster..BS$tahun
                    WHERE LEVEL <= $level AND (TDIS<>'D' OR B$bulan<>0 OR B00<>0 OR THN<>0) AND (TDIS<>'S' OR LEVEL <$level) $keyword
                ) AS DATA
                WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
        // echo $sql;
        
        $barang = $this->query($sql);
        $list =  $barang->fetchAll(PDO::FETCH_ASSOC);

        if($page == 1){

            $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT NAMA,TDIS,LEVEL,B$bulan AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY NOMOR ASC) AS ROWNUM
                        FROM $dbmaster..BS$tahun P 
                        WHERE LEVEL <= $level AND (TDIS<>'D' OR B$bulan<>0 OR B00<>0 OR THN<>0) AND (TDIS<>'S' OR LEVEL <$level) $keyword
                    ) AS DATA";
            // echo $sql;

            $total = $this->query($sql);
            $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

            $sql = "SELECT  
                    SUM(B$bulan) AS TOTALHARGA
                    FROM $dbmaster..BS$tahun P 
                    WHERE LEVEL <= $level AND TDIS = 'D'";
            
            $totHrg = $this->query($sql);
            $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));


            $totalPage = $totaldata['TOTALDATA'] / $perpage;
        }
            
        return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
    }


    function detail($data){
    }
}
