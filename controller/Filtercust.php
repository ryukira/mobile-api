<?php

class Filtercust extends My_db{
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
        $dbmaster = $_SESSION['dbname']."SHRDBF";

        $sql = "SELECT KODE, NAMA FROM $dbmaster..SALESM ORDER BY NAMA ASC";
        $sales = $this->query($sql);
        $salesman = $sales->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM $dbmaster..WILAYAH ORDER BY NAMA ASC";
        $wilayah = $this->query($sql);
        $kelompok = $wilayah->fetchAll(PDO::FETCH_ASSOC);
        

        return array("salesman"=>$salesman, "kelompok"=>$kelompok);
        
    }

}
