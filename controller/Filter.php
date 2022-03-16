<?php

class Filter extends My_db{
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
        
        $sql = "SELECT KODE, NAMA FROM $dbmaster..GUDANG ORDER BY NAMA ASC";
        $gd = $this->query($sql);
        $gudang = $gd->fetchAll(PDO::FETCH_ASSOC);


        $sql = "SELECT KODE, NAMA FROM $dbmaster..[CABANG] ORDER BY NAMA ASC";
        $cb = $this->query($sql);
        $cabang = $cb->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT INISIAL AS KODE, NAMA FROM $dbmaster..[GROUP] ORDER BY NAMA ASC";
        $brg = $this->query($sql);
        $barang = $brg->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM VENDOR ORDER BY NAMA ASC";
        $ven = $this->query($sql);
        $vendor = $ven->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM $dbmaster..SALESM ORDER BY NAMA ASC";
        $sales = $this->query($sql);
        $salesman = $sales->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM CUSTOM ORDER BY NAMA ASC";
        $cust = $this->query($sql);
        $customer = $cust->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM $dbmaster..LOKASI ORDER BY NAMA ASC";
        $lokasi = $this->query($sql);
        $klp_supp = $lokasi->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM $dbmaster..WILAYAH ORDER BY NAMA ASC";
        $wilayah = $this->query($sql);
        $klp_pelanggan = $wilayah->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM $dbmaster..TBAYAR ORDER BY NAMA ASC";
        $tbayar = $this->query($sql);
        $tipebayar = $tbayar->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT KODE, NAMA FROM $dbmaster..MEREK ORDER BY NAMA ASC";
        $merk = $this->query($sql);
        $merek = $merk->fetchAll(PDO::FETCH_ASSOC);
        

        return array("cabang"=>$cabang, "gudang"=>$gudang, "barang"=>$barang, "vendor"=>$vendor, "salesman"=>$salesman, "pelanggan"=>$customer, "klp_supp"=>$klp_supp, "klp_pelanggan"=>$klp_pelanggan, "tbayar"=>$tipebayar, "merek"=>$merek);
        
    }

    function tambahData($data){
        
    }
}
