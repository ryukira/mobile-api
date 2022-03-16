<?php

class Lap_piutang extends My_db{
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

	  $result = $this->detail($data);
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

		$outs = $data->outs;
		$tanda = "<>";
		
		if($outs == 'semua' && $outs == ''){
			$tanda = "<>";
		} else if($outs == 'plus'){
			$tanda = ">";
		} else if($outs == 'minus'){
			$tanda = "<";
		}

	    // $where = array(
		   //          "PUTANG.CB" => $data->cabang,
		   //          "PUTANG.WILAYAH" => $data->klp_pelanggan,
		   //          "PUTANG.CUSTOM" => $data->pelanggan,
		   //          "PUTANG.SALESM" => $data->salesman,
		   //          "PUTANG.TGLJTP" => $data->tgljtp,
		   //          "PUTANG.SALDO ".$tanda => "0",
		   //          "PUTANG.TANGGAL <>" => "1990-01-01" //HANYA SEBAGAI PANCINGAN
		   //      );
	    // $where1 = array_filter($where, function($data){
     //        return $data != "" && $data != '%%';	
     //    });

	    // $where2 = '';        
     //    if ($where1 != null) {    
     //        $where2 = $this->where($where1); 
     //    }
	    $cb = $data->cabang == '' ? '' : "AND PUTANG.CB = '$data->cabang'";
        $klp_pelanggan = $data->klp_pelanggan == '' ? '' : "AND PUTANG.WILAYAH  = '$data->klp_pelanggan'";
        $pelanggan = $data->pelanggan == '' ? '' : "AND PUTANG.CUSTOM  = '$data->pelanggan'";
        $salesman = $data->salesman == '' ? '' : "AND PUTANG.SALESM  = '$data->salesman'";
        $tgljtp = $data->tgljtp == '' ? '' : "AND PUTANG.TGLJTP  <= '$data->tgljtp'";
        $saldo = "AND PUTANG.SALDO $tanda '0'";

        //PAGINATION
        $perpage = $this->perpage;
        $page = isset($data->page) ? $data->page : 0;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;

        $totalPage = 0;
        $totHrg = 0;

        // search
        $keyword = '';

	    switch ($data->orderby) {
	    	case 'faktur':
                if ($search) {
                    $keyword = preg_replace('/[\W_]+/','',$data->keyword);
                    $keyword = $data->keyword == '' ? '' : "AND PUTANG.NOMOR LIKE '%$keyword%'";
                }

	    		$sql = "SELECT * FROM (
		    				SELECT PUTANG.NOMOR, PUTANG.NOREF, PUTANG.TANGGAL AS TGLFAKTUR, PUTANG.TGLJTP, (PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA, CUSTOM.NAMA AS NPELANGGAN, ROW_NUMBER() OVER (ORDER BY PUTANG.NOMOR DESC) AS ROWNUM
							FROM PUTANG, CUSTOM
							WHERE PUTANG.NOMOR=PUTANG.NOXX AND CUSTOM.KODE=PUTANG.CUSTOM AND LEFT(PUTANG.NOXX,2)<>'DP' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo $keyword
						)AS DATA
						WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

                $faktur = $this->query($sql);
                $list = $faktur->fetchAll(PDO::FETCH_ASSOC);

                if ($page == 1){
                    $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                                SELECT PUTANG.NOMOR, PUTANG.NOREF, PUTANG.TANGGAL AS TGLFAKTUR, PUTANG.TGLJTP, (PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA, CUSTOM.NAMA AS NPELANGGAN, ROW_NUMBER() OVER (ORDER BY PUTANG.NOMOR DESC) AS ROWNUM
								FROM PUTANG, CUSTOM
								WHERE PUTANG.NOMOR=PUTANG.NOXX AND CUSTOM.KODE=PUTANG.CUSTOM AND LEFT(PUTANG.NOXX,2)<>'DP' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo $keyword
							)AS DATA";

                    $total = $this->query($sql);
                    $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                    $sql = "SELECT 
                            	SUM(PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA
                            FROM PUTANG
							WHERE PUTANG.NOMOR=PUTANG.NOXX AND LEFT(PUTANG.NOXX,2)<>'DP' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo";

                    $totHrg = $this->query($sql);
                    $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                    $totalPage = $totaldata['TOTALDATA'] / $perpage;
                }

	    		break;
	   //  	case 'klp_pelanggan':
    //             if ($search) {
    //                 $keyword = $data->keyword == '' ? '' : "AND WILAYAH.NAMA LIKE '%$data->keyword%'";
    //             }

	   //  		$sql = "SELECT WILAYAH.NAMA AS REKAP,SUM(PUTANG.SALDO*PUTANG.KURS) AS TOTALHARGA,COUNT(DISTINCT(PUTANG.NOMOR)) AS TOTALFAKTUR 
				// 		FROM PUTANG, $dbmaster..WILAYAH AS WILAYAH";
		  //       $sql .= $where2." AND PUTANG.NOMOR=PUTANG.NOXX AND WILAYAH.KODE=PUTANG.WILAYAH $keyword ";
				// $sql .= " GROUP BY WILAYAH.NAMA ORDER BY TOTALHARGA DESC";
	   //  		break;
	    	case 'pelanggan':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND CUSTOM.NAMA LIKE '%$data->keyword%'";

                }

	    		$sql = "SELECT * FROM (
		    				SELECT CUSTOM.NAMA AS REKAP,SUM(PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA,COUNT(DISTINCT(PUTANG.NOMOR)) AS TOTALFAKTUR, PUTANG.CUSTOM, ROW_NUMBER() OVER (ORDER BY SUM(PUTANG.SALDO*PUTANG.KURS) DESC) AS ROWNUM 
							FROM PUTANG, CUSTOM
							WHERE PUTANG.NOMOR=PUTANG.NOXX AND CUSTOM.KODE=PUTANG.CUSTOM AND LEFT(PUTANG.NOXX,2)<>'DP' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo $keyword 
							GROUP BY CUSTOM.NAMA, PUTANG.CUSTOM
						)AS DATA
						WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

                $plg = $this->query($sql);
                $list = $plg->fetchAll(PDO::FETCH_ASSOC);

                if ($page == 1){
                    $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
			    				SELECT CUSTOM.NAMA AS REKAP,SUM(PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA,COUNT(DISTINCT(PUTANG.NOMOR)) AS TOTALFAKTUR, ROW_NUMBER() OVER (ORDER BY SUM(PUTANG.SALDO*PUTANG.KURS) DESC) AS ROWNUM 
								FROM PUTANG, CUSTOM
								WHERE PUTANG.NOMOR=PUTANG.NOXX AND CUSTOM.KODE=PUTANG.CUSTOM AND LEFT(PUTANG.NOXX,2)<>'DP' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo $keyword 
								GROUP BY CUSTOM.NAMA
							)AS DATA";

                    $total = $this->query($sql);
                    $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                    $sql = "SELECT 
                            	SUM(PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA
                            FROM PUTANG
							WHERE PUTANG.NOMOR=PUTANG.NOXX AND LEFT(PUTANG.NOXX,2)<>'DP' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo";

                    $totHrg = $this->query($sql);
                    $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                    $totalPage = $totaldata['TOTALDATA'] / $perpage;
                }
	    		break;
	    	case 'salesman':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND SALESM.NAMA LIKE '%$data->keyword%'";
                }

	    		$sql = "SELECT * FROM (
		    				SELECT SALESM.NAMA AS REKAP,SUM(PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA,COUNT(DISTINCT(PUTANG.NOMOR)) AS TOTALFAKTUR, ROW_NUMBER() OVER (ORDER BY SUM(PUTANG.SALDO*PUTANG.KURS) DESC) AS ROWNUM 
							FROM PUTANG, $dbmaster..SALESM AS SALESM
							WHERE PUTANG.NOMOR=PUTANG.NOXX AND SALESM.KODE=PUTANG.SALESM AND LEFT(PUTANG.NOXX,2)<>'DP' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo $keyword 
							GROUP BY SALESM.NAMA
						)AS DATA
						WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

                $plg = $this->query($sql);
                $list = $plg->fetchAll(PDO::FETCH_ASSOC);

                if ($page == 1){
                    $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
			    				SELECT SALESM.NAMA AS REKAP,SUM(PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA,COUNT(DISTINCT(PUTANG.NOMOR)) AS TOTALFAKTUR, ROW_NUMBER() OVER (ORDER BY SUM(PUTANG.SALDO*PUTANG.KURS) DESC) AS ROWNUM 
								FROM PUTANG, $dbmaster..SALESM AS SALESM
								WHERE PUTANG.NOMOR=PUTANG.NOXX AND SALESM.KODE=PUTANG.SALESM AND LEFT(PUTANG.NOXX,2)<>'DP' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo $keyword 
								GROUP BY SALESM.NAMA
							)AS DATA";

                    $total = $this->query($sql);
                    $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                    $sql = "SELECT 
                            	SUM(PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA
                            FROM PUTANG
							WHERE PUTANG.NOMOR=PUTANG.NOXX AND LEFT(PUTANG.NOXX,2)<>'DP' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo";

                    $totHrg = $this->query($sql);
                    $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                    $totalPage = $totaldata['TOTALDATA'] / $perpage;
                }
	    		break;
	    	default:
	    		# code...
	    		break;
	    }

		return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
    }
    function detail($data){
        $bulan = substr($_SESSION['periode'], 2,2);
        $tahun = 2000 + substr($_SESSION['periode'], 0,2);
        $awal = date('Y-m-d', strtotime($tahun."-".$bulan."-01"));
        $akhir = date('Y-m-t', strtotime($awal));

        $outs = $data->outs;
        $tanda = "<>";
        
        if($outs == 'semua' && $outs == ''){
            $tanda = "<>";
        } else if($outs == 'plus'){
            $tanda = ">";
        } else if($outs == 'minus'){
            $tanda = "<";
        }

        $cb = $data->cabang == '' ? '' : "AND PUTANG.CB = '$data->cabang'";
        $klp_pelanggan = $data->klp_pelanggan == '' ? '' : "AND PUTANG.WILAYAH  = '$data->klp_pelanggan'";
        $pelanggan = $data->pelanggan == '' ? '' : "AND PUTANG.CUSTOM  = '$data->pelanggan'";
        $salesman = $data->salesman == '' ? '' : "AND PUTANG.SALESM  = '$data->salesman'";
        $tgljtp = $data->tgljtp == '' ? '' : "AND PUTANG.TGLJTP  <= '$data->tgljtp'";
        $saldo = "AND PUTANG.SALDO $tanda '0'";


        $sql = "SELECT PUTANG.NOMOR, PUTANG.NOREF, PUTANG.TANGGAL AS TGLFAKTUR, PUTANG.TGLJTP, (PUTANG.SALDO * PUTANG.KURS) AS TOTALHARGA
                FROM PUTANG, CUSTOM
                WHERE PUTANG.NOMOR=PUTANG.NOXX AND CUSTOM.KODE=PUTANG.CUSTOM AND PUTANG.CUSTOM = '$data->custom' $cb $klp_pelanggan $pelanggan $salesman $tgljtp $saldo
                ORDER BY PUTANG.NOMOR DESC";
        $plg = $this->query($sql);
        $list = $plg->fetchAll(PDO::FETCH_ASSOC);

        return $list;
    }
}
