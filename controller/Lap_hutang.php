<?php

class Lap_hutang extends My_db{
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
		   //          "HUTANG.CB" => $data->cabang,
		   //          "HUTANG.LOKASI" => $data->klp_supp,
		   //          "HUTANG.VENDOR" => $data->vendor,
		   //          "HUTANG.TGLJTP" => $data->tgljtp,
		   //          "HUTANG.SALDO ".$tanda => "0",
		   //          "HUTANG.TANGGAL <>" => "1990-01-01" //HANYA SBG PANCINGAN
		   //      );
	    // $where1 = array_filter($where, function($data){
     //        return $data != "" && $data != '%%';	
     //    });

	    // $where2 = '';        
     //    if ($where1 != null) {    
     //        $where2 = $this->where($where1); 
     //    }
	    $cb = $data->cabang == '' ? '' : "AND HUTANG.CB = '$data->cabang'";
        $klp_supp = $data->klp_supp == '' ? '' : "AND HUTANG.LOKASI  = '$data->klp_supp'";
        $vendor = $data->vendor == '' ? '' : "AND HUTANG.VENDOR  = '$data->vendor'";
        $tgljtp = $data->tgljtp == '' ? '' : "AND HUTANG.TGLJTP  <= '$data->tgljtp'";
        $saldo = "AND HUTANG.SALDO $tanda '0'";

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
                    $keyword = $data->keyword == '' ? '' : "AND HUTANG.NOMOR LIKE '%$keyword%'";
                }

	    		$sql = "SELECT * FROM (
		    				SELECT HUTANG.NOMOR, HUTANG.NOREF, HUTANG.TANGGAL AS TGLFAKTUR, HUTANG.TGLJTP, (HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA, VENDOR.NAMA AS NSUPPLIER, ROW_NUMBER() OVER (ORDER BY HUTANG.NOMOR DESC) AS ROWNUM
							FROM HUTANG, VENDOR 
							WHERE HUTANG.NOMOR=HUTANG.NOXX AND VENDOR.KODE=HUTANG.VENDOR AND LEFT(HUTANG.NOXX,2)<>'PD' AND HUTANG.VENDOR IN (SELECT KODE FROM VENDOR) AND HUTANG.LOKASI != '' $cb $klp_supp $vendor $tgljtp $saldo $keyword
						)AS DATA
						WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

                $faktur = $this->query($sql);
                $list = $faktur->fetchAll(PDO::FETCH_ASSOC);

                if ($page == 1){
                    $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                                SELECT HUTANG.NOMOR, HUTANG.NOREF, HUTANG.TANGGAL AS TGLFAKTUR, HUTANG.TGLJTP, (HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA, VENDOR.NAMA AS NSUPPLIER, ROW_NUMBER() OVER (ORDER BY HUTANG.NOMOR DESC) AS ROWNUM
								FROM HUTANG, VENDOR 
								WHERE HUTANG.NOMOR=HUTANG.NOXX AND VENDOR.KODE=HUTANG.VENDOR AND LEFT(HUTANG.NOXX,2)<>'PD' AND HUTANG.VENDOR IN (SELECT KODE FROM VENDOR) AND HUTANG.LOKASI != '' $cb $klp_supp $vendor $tgljtp $saldo $keyword
							)AS DATA";

                    $total = $this->query($sql);
                    $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                    $sql = "SELECT 
                            	SUM(HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA
                            FROM HUTANG 
							WHERE HUTANG.NOMOR=HUTANG.NOXX AND LEFT(HUTANG.NOXX,2)<>'PD' AND HUTANG.VENDOR IN (SELECT KODE FROM VENDOR) AND HUTANG.LOKASI != '' $cb $klp_supp $vendor $tgljtp $saldo";

                    $totHrg = $this->query($sql);
                    $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                    $totalPage = $totaldata['TOTALDATA'] / $perpage;
                }
				
	    		break;
	    	case 'klp_supp':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND LOKASI.NAMA LIKE '%$data->keyword%'";
                }

	    		$sql = "SELECT * FROM (
		    				SELECT LOKASI.NAMA AS REKAP,SUM(HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA,COUNT(DISTINCT(HUTANG.NOMOR)) AS TOTALFAKTUR, ROW_NUMBER() OVER (ORDER BY SUM(HUTANG.SALDO*HUTANG.KURS) DESC) AS ROWNUM
							FROM HUTANG, $dbmaster..LOKASI AS LOKASI 
							WHERE HUTANG.NOMOR=HUTANG.NOXX AND LOKASI.KODE=HUTANG.LOKASI AND LEFT(HUTANG.NOXX,2)<>'PD' AND HUTANG.VENDOR IN (SELECT KODE FROM VENDOR) AND HUTANG.LOKASI != '' $cb $klp_supp $vendor $tgljtp $saldo $keyword 
							GROUP BY LOKASI.NAMA
						) AS DATA
						WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

                $supp = $this->query($sql);
                $list = $supp->fetchAll(PDO::FETCH_ASSOC);

                if ($page == 1){
                    $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                                SELECT LOKASI.NAMA AS REKAP,SUM(HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA,COUNT(DISTINCT(HUTANG.NOMOR)) AS TOTALFAKTUR, ROW_NUMBER() OVER (ORDER BY SUM(HUTANG.SALDO*HUTANG.KURS) DESC) AS ROWNUM 
								FROM HUTANG, $dbmaster..LOKASI AS LOKASI 
								WHERE HUTANG.NOMOR=HUTANG.NOXX AND LOKASI.KODE=HUTANG.LOKASI AND LEFT(HUTANG.NOXX,2)<>'PD' AND HUTANG.VENDOR IN (SELECT KODE FROM VENDOR) AND HUTANG.LOKASI != '' $cb $klp_supp $vendor $tgljtp $saldo $keyword 
								GROUP BY LOKASI.NAMA
							) AS DATA";

                    $total = $this->query($sql);
                    $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                    $sql = "SELECT 
                            	SUM(HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA
                            FROM HUTANG 
							WHERE HUTANG.NOMOR=HUTANG.NOXX AND LEFT(HUTANG.NOXX,2)<>'PD' AND HUTANG.VENDOR IN (SELECT KODE FROM VENDOR) AND HUTANG.LOKASI != '' $cb $klp_supp $vendor $tgljtp $saldo";

                    $totHrg = $this->query($sql);
                    $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                    $totalPage = $totaldata['TOTALDATA'] / $perpage;
                }
	    		break;
	    	case 'vendor':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND VENDOR.NAMA LIKE '%$data->keyword%'";
                }

	    		$sql = "SELECT * FROM (
		    				SELECT VENDOR.NAMA AS REKAP,SUM(HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA,COUNT(DISTINCT(HUTANG.NOMOR)) AS TOTALFAKTUR, HUTANG.VENDOR, ROW_NUMBER() OVER (ORDER BY SUM(HUTANG.SALDO*HUTANG.KURS) DESC) AS ROWNUM 
							FROM HUTANG, VENDOR 
							WHERE HUTANG.NOMOR=HUTANG.NOXX AND VENDOR.KODE=HUTANG.VENDOR AND LEFT(HUTANG.NOXX,2)<>'PD' AND HUTANG.VENDOR IN (SELECT KODE FROM VENDOR) AND HUTANG.LOKASI != '' $cb $klp_supp $vendor $tgljtp $saldo $keyword 
							GROUP BY VENDOR.NAMA, HUTANG.VENDOR
						)AS DATA
						WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

                $vend = $this->query($sql);
                $list = $vend->fetchAll(PDO::FETCH_ASSOC);

                if ($page == 1){
                    $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                                SELECT VENDOR.NAMA AS REKAP,SUM(HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA,COUNT(DISTINCT(HUTANG.NOMOR)) AS TOTALFAKTUR, ROW_NUMBER() OVER (ORDER BY SUM(HUTANG.SALDO*HUTANG.KURS) DESC) AS ROWNUM 
								FROM HUTANG, VENDOR 
								WHERE HUTANG.NOMOR=HUTANG.NOXX AND VENDOR.KODE=HUTANG.VENDOR AND LEFT(HUTANG.NOXX,2)<>'PD' AND HUTANG.VENDOR IN (SELECT KODE FROM VENDOR) AND HUTANG.LOKASI != '' $cb $klp_supp $vendor $tgljtp $saldo $keyword 
								GROUP BY VENDOR.NAMA
							)AS DATA";

                    $total = $this->query($sql);
                    $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                    $sql = "SELECT 
                            	SUM(HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA
                            FROM HUTANG 
							WHERE HUTANG.NOMOR=HUTANG.NOXX AND LEFT(HUTANG.NOXX,2)<>'PD' AND HUTANG.VENDOR IN (SELECT KODE FROM VENDOR) AND HUTANG.LOKASI != '' $cb $klp_supp $vendor $tgljtp $saldo";

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

        $cb = $data->cabang == '' ? '' : "AND HUTANG.CB = '$data->cabang'";
        $klp_supp = $data->klp_supp == '' ? '' : "AND HUTANG.LOKASI  = '$data->klp_supp'";
        $vendor = $data->vendor == '' ? '' : "AND HUTANG.VENDOR  = '$data->vendor'";
        $tgljtp = $data->tgljtp == '' ? '' : "AND HUTANG.TGLJTP  <= '$data->tgljtp'";
        $saldo = "AND HUTANG.SALDO $tanda '0'";


        //$sql = "SELECT HUTANG.NOMOR, HUTANG.NOREF, HUTANG.TANGGAL AS TGLFAKTUR, HUTANG.TGLJTP, (HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA
        //        FROM HUTANG, VENDOR 
        //        WHERE HUTANG.NOMOR=HUTANG.NOXX AND VENDOR.KODE=HUTANG.VENDOR AND HUTANG.TANGGAL BETWEEN '$awal' AND '$akhir' AND HUTANG.VENDOR = '$data->vendor' $cb $klp_supp $vendor $tgljtp $saldo 
        //        ORDER BY HUTANG.NOMOR DESC ";
	$sql = "SELECT HUTANG.NOMOR, HUTANG.NOREF, HUTANG.TANGGAL AS TGLFAKTUR, HUTANG.TGLJTP, (HUTANG.SALDO*HUTANG.KURS) AS TOTALHARGA
                FROM HUTANG, VENDOR 
                WHERE HUTANG.NOMOR=HUTANG.NOXX AND VENDOR.KODE=HUTANG.VENDOR AND HUTANG.VENDOR = '$data->vendor' $cb $klp_supp $vendor $tgljtp $saldo 
                ORDER BY HUTANG.NOMOR DESC ";
        // $sql = "SELECT HUTANG.VENDOR FROM HUTANG";
        $supp = $this->query($sql);
        $list = $supp->fetchAll(PDO::FETCH_ASSOC);

        return $list;
    }
}
