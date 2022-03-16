<?php

class Login extends My_db{
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

    
    function getUserData(){
        $userid = $_SESSION['userid'];
        $dbmaster = $_SESSION['dbname']."SHRDBF";

        $query = "SELECT IDPT, IDGD, USLEVEL FROM $dbmaster..SYSUSER WHERE USID='$userid'";
        $stmt = $this->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = count($data) > 0 ? current($data) : array("IDPT"=>"", "IDGD"=>"", "USLEVEL"=>0);

        $query = "SELECT S.NAMA AS CUSTOM FROM $dbmaster..SYSDATA S, CUSTOM C WHERE S.KODE='007' AND S.NAMA=C.KODE";
        $stmt = $this->query($query);
        $cust = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cust = count($cust) > 0 ? current($cust) : array("CUSTOM" => "");

        $res = array_merge($data, $cust);
        return $res;
    }

    function logout(){
        $this->closeConnection();
        return true;
    }
}

?>
