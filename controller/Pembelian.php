<?php

class Pembelian extends My_db{
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
            "BLDT.GUDANG" => $data->gudang,
            "BLDT.CB" => $data->cabang,
   			"G.INISIAL LIKE" => "%".$data->barang."%",
			"BLDT.TANGGAL BETWEEN" => $filtertgl
        );

        $where1 = array_filter($where, function($data){
            return $data != "" && $data != '%%';	
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
		        	$keyword = $data->keyword == '' ? '' : "AND BLHD.TANGGAL = '$keyword'";
			    }

	    		$sql = "SELECT BLHD.TANGGAL AS REKAP, COUNT(DISTINCT(BLHD.NOMOR)) AS TOTALFAKTUR, SUM((BLDT.QTY*(BLDT.HARGA-BLDT.DISCN+BLDT.BIAYA+BLDT.TAXN+BLDT.LAIN)) * BLDT.KURS) AS TOTALHARGA
	    				FROM BLHD, BLDT, $dbmaster..[GROUP] AS G";
	    		$sql .= $where2. " AND BLHD.NOMOR=BLDT.NOMOR AND BLDT.[GROUP]=G.KODE $keyword";
				$sql .= " GROUP BY BLHD.TANGGAL ORDER BY BLHD.TANGGAL DESC";

	    		break;
	    	case 'supplier':
	    		if ($search) {
		        	$keyword = $data->keyword == '' ? '' : "AND VENDOR.NAMA LIKE '%$data->keyword%'";
			    }

	    		$sql = "SELECT VENDOR.NAMA AS REKAP, COUNT(DISTINCT(BLHD.NOMOR)) AS TOTALFAKTUR, SUM((BLDT.QTY*(BLDT.HARGA-BLDT.DISCN+BLDT.BIAYA+BLDT.TAXN+BLDT.LAIN)) * BLDT.KURS) AS TOTALHARGA
	    				FROM BLHD, BLDT, VENDOR, $dbmaster..[GROUP] AS G";
		        $sql .= $where2." AND BLHD.NOMOR=BLDT.NOMOR AND BLDT.VENDOR = VENDOR.KODE AND BLDT.[GROUP]=G.KODE $keyword ";
				$sql .= " GROUP BY VENDOR.NAMA ORDER BY TOTALHARGA DESC";
	    		break;
			case 'barang':
	    		if ($search) {
		        	$keyword = $data->keyword == '' ? '' : "AND PROD1.NAMA LIKE '%$data->keyword%'";
			    }

	    		$sql = "SELECT PROD1.NAMA AS REKAP, SUM(BLDT.QTY) AS TOTALFAKTUR, SUM((BLDT.QTY*(BLDT.HARGA-BLDT.DISCN+BLDT.BIAYA+BLDT.TAXN+BLDT.LAIN)) * BLDT.KURS) AS TOTALHARGA
	    				FROM BLHD, BLDT, PROD1, $dbmaster..[GROUP] AS G";
		        $sql .= $where2." AND BLDT.[GROUP]=PROD1.[GROUP] AND BLDT.BARANG=PROD1.KODE AND BLHD.NOMOR=BLDT.NOMOR AND BLDT.[GROUP]=G.KODE $keyword ";
				$sql .= " GROUP BY PROD1.NAMA ORDER BY TOTALFAKTUR DESC";
				break;
	    	default:
	    		# code...
	    		break;
	    }
	        



        // echo $sql;

        $so = $this->query($sql);

        return $so->fetchAll(PDO::FETCH_ASSOC);
    }

	function detail ($data,$search=false){
		$dbmaster = $_SESSION['dbname']."SHRDBF";

        //FILTER
        $filtertgl = "AND BLDT.TANGGAL BETWEEN ' $data->tanggal' AND ' $data->tanggal2'";
        $cb = $data->cabang == ''? '' : "AND BLDT.CB = '$data->cabang'";
        $gudang = $data->gudang == ''? '' : "AND BLDT.CUSTOM = '$data->gudang'";
        $barang = $data->barang == ''? '' : "AND G.INISIAL LIKE '%$data->barang%'";

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
        	$keyword = $data->keyword == '' ? '' : "AND BLHD.NOMOR LIKE '%$keyword%'";
	    }
	    
	    // DETAIL FAKTUR
	    if($data->fn=='faktur'){

		    $sql = "SELECT * FROM (
			    		SELECT BLHD.NOMOR AS REKAP, COUNT(BLDT.BARANG) AS TOTALFAKTUR, 
			    		SUM((BLDT.QTY*(BLDT.HARGA-BLDT.DISCN+BLDT.BIAYA+BLDT.TAXN+BLDT.LAIN)) * BLDT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY BLHD.NOMOR DESC) AS ROWNUM  
			    		FROM BLHD, BLDT, $dbmaster..[GROUP] AS G 
			    		WHERE BLHD.NOMOR=BLDT.NOMOR AND BLDT.[GROUP]=G.KODE AND BLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $barang $keyword  
			    		GROUP BY BLHD.NOMOR
			    	) AS DATA
			    	WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
			    	
	        $faktur = $this->query($sql);
			$list = $faktur->fetchAll(PDO::FETCH_ASSOC);

			if ($page == 1){
				$sql = "SELECT COUNT(*) AS TOTALDATA FROM (
			    		SELECT BLHD.NOMOR AS REKAP, COUNT(BLDT.BARANG)AS TOTALFAKTUR, 
			    		SUM((BLDT.QTY*(BLDT.HARGA-BLDT.DISCN+BLDT.BIAYA+BLDT.TAXN+BLDT.LAIN)) * BLDT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY BLHD.NOMOR DESC) AS ROWNUM  
			    		FROM BLHD, BLDT, $dbmaster..[GROUP] AS G 
			    		WHERE BLHD.NOMOR=BLDT.NOMOR AND BLDT.[GROUP]=G.KODE AND BLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $barang $keyword  
			    		GROUP BY BLHD.NOMOR
			    	) AS DATA";

		        $total = $this->query($sql);
				$totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

	            $sql = "SELECT 
			    		SUM((BLDT.QTY*(BLDT.HARGA-BLDT.DISCN+BLDT.BIAYA+BLDT.TAXN+BLDT.LAIN)) * BLDT.KURS) AS TOTALHARGA
			    		FROM BLDT, $dbmaster..[GROUP] AS G
			    		WHERE BLDT.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $barang AND BLDT.[GROUP]=G.KODE";
	            $totHrg = $this->query($sql);
	            $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

	            $totalPage = $totaldata['TOTALDATA'] / $perpage;
			}
	    }

		// DETAIL BARANG
		else if($data->fn=='barang'){

			$sql = "SELECT PROD1.NAMA AS REKAP, SUM(BLDT.QTY) AS TOTALFAKTUR, SUM(BLDT.HARGA-BLDT.DISCN+BLDT.BIAYA+BLDT.TAXN+BLDT.LAIN) AS HARGASATUAN, SUM((BLDT.QTY*(BLDT.HARGA-BLDT.DISCN+BLDT.BIAYA+BLDT.TAXN+BLDT.LAIN)) * BLDT.KURS) AS TOTALHARGA,BLDT.SAT
					FROM BLHD, BLDT, PROD1, $dbmaster..[GROUP] AS G ";
	        $sql .= " WHERE BLDT.[GROUP]=PROD1.[GROUP] AND BLDT.BARANG=PROD1.KODE AND BLHD.NOMOR=BLDT.NOMOR AND BLDT.[GROUP]=G.KODE AND BLDT.NOMOR = '$data->nofak' AND BLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $barang ";
			$sql .= " GROUP BY PROD1.NAMA, BLDT.NOURUT,BLDT.SAT ORDER BY BLDT.NOURUT ASC";

	        $barang = $this->query($sql);
			$list = $barang->fetchAll(PDO::FETCH_ASSOC);
		}


	    return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
	}
}
