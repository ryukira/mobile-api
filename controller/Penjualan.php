<?php

class Penjualan extends My_db{
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
		            "JLDT.GUDANG" => $data->gudang,
		            "JLDT.CB" => $data->cabang,
		            "JLDT.SALESM" => $data->salesman,
           			"G.INISIAL LIKE" => "%".$data->barang."%",
        			"JLDT.TANGGAL BETWEEN" => $filtertgl
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
		        	$keyword = $data->keyword == '' ? '' : "AND JLHD.TANGGAL = '$keyword'";
			    }

	    		$sql = "SELECT JLHD.TANGGAL AS REKAP, COUNT(DISTINCT(JLHD.NOMOR)) AS TOTALFAKTUR, SUM((JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN)) * JLDT.KURS) AS TOTALHARGA
	    				FROM JLHD, JLDT, $dbmaster..[GROUP] AS G ";
	    		$sql .= $where2. " AND JLHD.NOMOR=JLDT.NOMOR AND JLDT.[GROUP]=G.KODE $keyword";
				$sql .= " GROUP BY JLHD.TANGGAL ORDER BY JLHD.TANGGAL DESC";
				
	    		break;
	    	case 'pelanggan':
	    		if ($search) {
		        	$keyword = $data->keyword == '' ? '' : "AND CUSTOM.NAMA LIKE '%$data->keyword%'";
			    }

	    		$sql = "SELECT CUSTOM.NAMA AS REKAP, COUNT(DISTINCT(JLHD.NOMOR)) AS TOTALFAKTUR, SUM((JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN)) * JLDT.KURS) AS TOTALHARGA
	    				FROM JLHD, JLDT, CUSTOM, $dbmaster..[GROUP] AS G ";
		        $sql .= $where2." AND JLHD.NOMOR=JLDT.NOMOR AND JLHD.CUSTOM = CUSTOM.KODE AND JLDT.[GROUP]=G.KODE $keyword ";
				$sql .= " GROUP BY CUSTOM.NAMA ORDER BY TOTALHARGA DESC";
	    		break;
	    	case 'sales':
	    		if ($search) {
		        	$keyword = $data->keyword == '' ? '' : "AND SALESM.NAMA LIKE '%$data->keyword%'";
			    }

	    		$sql = "SELECT SALESM.NAMA AS REKAP, COUNT(DISTINCT(JLHD.NOMOR)) AS TOTALFAKTUR, SUM((JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN)) * JLDT.KURS) AS TOTALHARGA
	    				FROM JLHD, JLDT, $dbmaster..SALESM, $dbmaster..[GROUP] AS G ";
		        $sql .= $where2." AND JLHD.NOMOR=JLDT.NOMOR AND JLHD.SALESM = SALESM.KODE AND JLDT.[GROUP]=G.KODE $keyword ";
				$sql .= " GROUP BY SALESM.NAMA ORDER BY TOTALHARGA DESC";
	    		break;
			case 'barang':
				if ($search) {
		        	$keyword = $data->keyword == '' ? '' : "AND PROD1.NAMA LIKE '%$data->keyword%'";
			    }
			    
	    		$sql = "SELECT PROD1.NAMA AS REKAP, SUM(JLDT.QTY) AS TOTALFAKTUR, SUM((JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN))  * JLDT.KURS) AS TOTALHARGA
	    				FROM JLHD, JLDT, PROD1, $dbmaster..[GROUP] AS G ";
		        $sql .= $where2." AND JLDT.[GROUP]=PROD1.[GROUP] AND JLDT.BARANG=PROD1.KODE AND JLHD.NOMOR=JLDT.NOMOR AND JLDT.[GROUP]=G.KODE $keyword ";
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

	function detail ($data, $search=false){
		$dbmaster = $_SESSION['dbname']."SHRDBF";

        //FILTER
        $filtertgl = "AND JLDT.TANGGAL BETWEEN ' $data->tanggal' AND ' $data->tanggal2'";
        $cb = $data->cabang == ''? '' : "AND JLDT.CB = '$data->cabang'";
        $gudang = $data->gudang == ''? '' : "AND JLDT.CUSTOM = '$data->gudang'";
        $salesman = $data->salesman == ''? '' : "AND JLDT.SALESM = '$data->salesman'";
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
        	$keyword = $data->keyword == '' ? '' : "AND JLHD.NOMOR LIKE '%$keyword%'";
	    }

	    // DETAIL FAKTUR
	    if($data->fn=='faktur'){
		    $sql = "SELECT * FROM (
			    		SELECT JLHD.NOMOR AS REKAP, COUNT(JLDT.BARANG)AS TOTALFAKTUR, 
			    		SUM((JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN)) * JLDT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY JLHD.NOMOR DESC) AS ROWNUM 
			    		FROM JLHD, JLDT, $dbmaster..[GROUP] AS G
			    		WHERE JLHD.NOMOR=JLDT.NOMOR AND JLDT.[GROUP]=G.KODE AND JLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $salesman $barang $keyword
			    		GROUP BY JLHD.NOMOR
		    		) AS DATA
		    		WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

	        $faktur = $this->query($sql);
			$list = $faktur->fetchAll(PDO::FETCH_ASSOC);

			if ($page == 1){
				$sql = "SELECT COUNT(*) AS TOTALDATA FROM (
			    		SELECT JLHD.NOMOR AS REKAP, COUNT(JLDT.BARANG)AS TOTALFAKTUR, 
			    		SUM((JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN))  * JLDT.KURS) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY JLHD.NOMOR DESC) AS ROWNUM 
			    		FROM JLHD, JLDT, $dbmaster..[GROUP] AS G 
			    		WHERE JLHD.NOMOR=JLDT.NOMOR AND JLDT.[GROUP]=G.KODE AND JLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $salesman $barang $keyword
			    		GROUP BY JLHD.NOMOR
		    		) AS DATA";

		        $total = $this->query($sql);
				$totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

	            $sql = "SELECT 
			    		SUM((JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN))  * JLDT.KURS) AS TOTALHARGA
			    		FROM JLDT, $dbmaster..[GROUP] AS G
			    		WHERE JLDT.TANGGAL = '$data->rekaptgl' AND JLDT.[GROUP]=G.KODE $filtertgl $cb $gudang $salesman $barang ";
	            $totHrg = $this->query($sql);
	            $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

	            $totalPage = $totaldata['TOTALDATA'] / $perpage;
			}

	    }
		// DETAIL BARANG
		else if($data->fn=='barang'){
			$sql = "SELECT * FROM (
						SELECT PROD1.NAMA AS REKAP, SUM(JLDT.QTY) AS TOTALFAKTUR, SUM(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN) AS HARGASATUAN, SUM((JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN))  * JLDT.KURS) AS TOTALHARGA, JLDT.SAT, ROW_NUMBER() OVER (ORDER BY JLDT.NOURUT ASC) AS ROWNUM 
						FROM JLHD, JLDT, PROD1, $dbmaster..[GROUP] AS G 
						WHERE JLDT.[GROUP]=PROD1.[GROUP] AND JLDT.BARANG=PROD1.KODE AND JLHD.NOMOR=JLDT.NOMOR AND JLDT.[GROUP]=G.KODE AND JLDT.NOMOR = '$data->nofak' AND JLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $salesman $barang 
						GROUP BY PROD1.NAMA, JLDT.NOURUT, JLDT.SAT
					) AS DATA 
					WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

	        $barang = $this->query($sql);
			$list = $barang->fetchAll(PDO::FETCH_ASSOC);

			// if ($page == 1){
			// 	$sql = "SELECT COUNT(*) AS TOTALDATA FROM (
			// 			SELECT PROD1.NAMA AS REKAP, SUM(JLDT.QTY) AS TOTALFAKTUR, SUM(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN) AS HARGASATUAN, SUM(JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN)) AS TOTALHARGA, JLDT.SAT, ROW_NUMBER() OVER (ORDER BY JLDT.NOURUT ASC) AS ROWNUM 
			// 			FROM JLHD, JLDT, PROD1, $dbmaster..[GROUP] AS G 
			// 			WHERE JLDT.[GROUP]=PROD1.[GROUP] AND JLDT.BARANG=PROD1.KODE AND JLHD.NOMOR=JLDT.NOMOR AND JLDT.[GROUP]=G.KODE AND JLDT.NOMOR = '$data->nofak' AND JLHD.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $salesman $barang
			// 			GROUP BY PROD1.NAMA, JLDT.NOURUT, JLDT.SAT
			// 		) AS DATA ";

		 //        $total = $this->query($sql);
			// 	$totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

			// 	$sql = "SELECT 
			//     		SUM(JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN)) AS TOTALHARGA
			//     		FROM JLDT, $dbmaster..[GROUP] AS G
			//     		WHERE JLDT.NOMOR = '$data->nofak' AND JLDT.[GROUP]=G.KODE AND JLDT.TANGGAL = '$data->rekaptgl' $filtertgl $cb $gudang $salesman $barang ";
	  //           $totHrg = $this->query($sql);
	  //           $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

	  //           $totalPage = $totaldata['TOTALDATA'] / $perpage;
			// }

		}


	    return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
	}


    // function search($data){

    //     $dbmaster = $_SESSION['dbname']."SHRDBF";

    //     $keyword = preg_replace('/[\W_]+/','',$data->keyword);
    //     $search = $data->keyword == '' ? '' : "AND JLHD.NOMOR LIKE '%$keyword%'";

    //     //paging
    //     $perpage = $this->perpage;
    //     $page = $data->pageSearch;
    //     $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
    //     $offset2 = ($offset + $perpage) - 1;

    //     $sql = "SELECT * FROM (
			 //    		SELECT JLHD.NOMOR AS REKAP, COUNT(JLDT.BARANG)AS TOTALFAKTUR, 
			 //    		SUM(JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN)) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY JLHD.NOMOR DESC) AS ROWNUM 
			 //    		FROM JLHD, JLDT 
			 //    		WHERE JLHD.NOMOR=JLDT.NOMOR AND JLHD.TANGGAL = '$data->tanggal' $search
			 //    		GROUP BY JLHD.NOMOR
		  //   		) AS DATA
		  //   		WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
    //     $searchL = $this->query($sql);
    //     $searchL = $searchL->fetchAll(PDO::FETCH_ASSOC);

    //     $totalPage = 0;
    //     if($page == 1){

    //         $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
    //                     SELECT JLHD.NOMOR AS REKAP, COUNT(JLDT.BARANG)AS TOTALFAKTUR, 
			 //    		SUM(JLDT.QTY*(JLDT.HARGA-JLDT.DISCN+JLDT.BIAYA+JLDT.TAXN+JLDT.LAIN)) AS TOTALHARGA, ROW_NUMBER() OVER (ORDER BY JLHD.NOMOR DESC) AS ROWNUM 
			 //    		FROM JLHD, JLDT 
			 //    		WHERE JLHD.NOMOR=JLDT.NOMOR AND JLHD.TANGGAL = '$data->tanggal' $search
			 //    		GROUP BY JLHD.NOMOR
		  //   		) AS DATA";
    //         $total = $this->query($sql);
    //         $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

    //         $totalPage = $totaldata['TOTALDATA'] / $perpage;
    //     }

    //     return array("list"=>$searchL, "total"=>$totalPage);
    // }

}
