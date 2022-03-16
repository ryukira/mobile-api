<?php

class Cust extends My_db{
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
	$dbmaster = $_SESSION['dbname']."SHRDBF";
        $sql = "SELECT c.*, c.NAMA AS N_PELANGGAN, s.NAMA AS SALESMAN, w.NAMA AS KELOMPOK 
        FROM CUSTOM c 
        LEFT JOIN $dbmaster..SALESM s on s.KODE = c.SALESM 
        LEFT JOIN $dbmaster..WILAYAH w on w.KODE = c.WILAYAH";
	
	$where = array(
            "c.WILAYAH" => $data->kelompok,
            "c.SALESM" => $data->salesman
        );

	if(isset($data->kode)){
            $where['c.KODE'] = $data->kode;
	
	}

        $where1 = array_filter($where, function($data){
            return $data != "" && $data != '%%';	
        });

    
        if ($where1 != null) {    
            $where2 = $this->where($where1);
            $sql .= $where2 . " AND c.NAMA != '' ";   
        }
        else{
            $sql.= " WHERE c.NAMA != '' ";
        }
        
	   $sql .= " ORDER BY c.NAMA,w.NAMA,s.NAMA";


        // echo $sql;

        $cust = $this->query($sql);

        return $cust->fetchAll(PDO::FETCH_ASSOC);
    }
}
