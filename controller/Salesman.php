<?php

class Salesman extends My_db{
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
        $sql = "SELECT S.NAMA, S.HP, S.TELP, CB.NAMA AS CABANG 
        FROM $dbmaster..SALESM S
        LEFT JOIN $dbmaster..CABANG CB on CB.KODE = S.CB ";
	
	$where = array(
            "S.CB" => $data->cabang
        );

	if(isset($data->kode)){
            $where['S.KODE'] = $data->kode;
	
	}

        $where1 = array_filter($where, function($data){
            return $data != "" && $data != '%%';	
        });

            
        if ($where1 != null) {    
            $where2 = $this->where($where1);
            $sql .= $where2." AND S.NAMA !='' ";   
        }
        else{
            $sql .= " WHERE S.NAMA !='' ";    
        }
        
	 $sql .= " ORDER BY S.NAMA";


        // echo $sql;

        $sales = $this->query($sql);

        return $sales->fetchAll(PDO::FETCH_ASSOC);
    }
}
