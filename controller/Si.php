<?php

class Si extends My_db{
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
            switch ($data->fn) {
                case 'siDetail':
                    $result = $this->getDetail($data);
                    break;
                case 'sendEmail':
                    $data->message='Faktur Penjualan';
                    $data->subject='Faktur SI '.date('d-m-Y');
                    $result = $this->email($data);
                    break;
                case 'imei':
                    $result = $this->getImei($data, $data->search);
                    break;
                default:
                    $result = $this->cetakFaktur($data);
                    break;
            }
        }
        else{
            $result= $this->all($id, $data, $data->search);
        }
        return $result;
    }

    function all($id='', $data=null, $search=false){
        $dbmaster = $_SESSION['dbname']."SHRDBF";

        //FILTER
        $cb = $data->cabang == ''? '' : "AND CB.KODE = '$data->cabang'";
        $pelanggan = $data->pelanggan == ''? '' : "AND C.KODE = '$data->pelanggan'";
        $salesman = $data->salesman == ''? '' : "AND S.KODE = '$data->salesman'";
        $outs = '';
        if ($data->outs == 'outs') {
            $outs = "AND (H.QTY-H.QTZ) > '0'";
        }
        else if($data->outs == 'tertutup'){
            $outs = "AND (H.QTY-H.QTZ) = '0'";
        }

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
            $keyword = $data->keyword == '' ? '' : "AND H.NOMOR LIKE '%$keyword%'";
        }

        
        $sql = "SELECT * FROM (
                    SELECT H.POSJ, H.ACC, H.NOMOR, H.CUSTOM, H.CB, H.TANGGAL, H.NOREF, (H.QTY-H.QTZ) AS OUTS, C.NAMA AS NAMACUSTOM, H.HRGNET AS HARGANET, CB.NAMA AS NAMACABANG, S.NAMA AS SALESMAN, ROW_NUMBER() OVER (ORDER BY H.NOMOR DESC) AS ROWNUM
                    FROM JLHD H, $dbmaster..SALESM S, $dbmaster..CABANG CB, CUSTOM C
                    WHERE S.KODE = H.SALESM AND CB.KODE = H.CB AND C.KODE = H.CUSTOM $cb $pelanggan $salesman $outs  $keyword
                    GROUP BY H.TANGGAL,C.NAMA, S.NAMA, H.NOMOR, H.QTY, H.QTZ, H.HRGNET, CB.NAMA, H.NOREF, H.CUSTOM, H.CB, H.POSJ, H.ACC
                ) AS DATA
                WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
                
        $faktur = $this->query($sql);
        $list = $faktur->fetchAll(PDO::FETCH_ASSOC);

        if ($page == 1) {
            $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT H.POSJ, H.ACC, H.NOMOR, H.CUSTOM, H.TANGGAL, H.NOREF, (H.QTY-H.QTZ) AS OUTS, C.NAMA AS NAMACUSTOM, H.HRGNET AS HARGANET, CB.NAMA AS NAMACABANG, S.NAMA AS SALESMAN, ROW_NUMBER() OVER (ORDER BY H.NOMOR DESC) AS ROWNUM
                        FROM JLHD H, $dbmaster..SALESM S, $dbmaster..CABANG CB, CUSTOM C
                        WHERE S.KODE = H.SALESM AND CB.KODE = H.CB AND C.KODE = H.CUSTOM $cb $pelanggan $salesman $outs  $keyword
                        GROUP BY H.TANGGAL,C.NAMA, S.NAMA, H.NOMOR, H.QTY, H.QTZ, H.HRGNET, CB.NAMA, H.NOREF, H.CUSTOM, H.POSJ, H.ACC
                    ) AS DATA";

            $total = $this->query($sql);
            $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

            $sql = "SELECT  
                    SUM(H.HRGNET) AS TOTALHARGA
                    FROM JLHD H, $dbmaster..SALESM S, $dbmaster..CABANG CB, CUSTOM C
                    WHERE S.KODE = H.SALESM AND CB.KODE = H.CB AND C.KODE = H.CUSTOM $cb $pelanggan $salesman $outs";
            
            $totHrg = $this->query($sql);
            $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

            $totalPage = $totaldata['TOTALDATA'] / $perpage;
        }
        

        // echo $sql;

        return ["list"=>$list, "total"=>$totalPage, "totHarga"=>$totHrg];
    }

    function cetakFaktur($param){

        $dbmaster = $_SESSION['dbname']."SHRDBF";
        $sql = "SELECT J.NOMOR, J.NOMORCB, J.CB, J.DOE, J.NOREF, J.TANGGAL, J.TERM, J.TGLJTP,
                    J.CUSTOM, J.SALESM, V.NAMA AS NAMASALESM, C.NAMA AS NAMACUSTOM, 
                    J.TAXP, J.TAXN, J.BIAYA, J.DISCH, J.DISCN, J.DISCD, J.HRGNET, J.HRGTOT, 
                    J.KET1, J.KET3, J.KET4, CB.ALAMAT1 AS C_ALAMAT1, CB.ALAMAT2 AS C_ALAMAT2,
                    CB.TELP AS C_TELP, CB.NAMA AS NAMACABANG, CB.GAMBAR, C.ALAMAT1, 
                    C.ALAMAT2, C.TELP, J.QTZ , J.POSJ,J.ACC, P.BAYAR
                FROM JLHD J
                    LEFT JOIN $dbmaster..SALESM V ON J.SALESM = V.KODE
                    LEFT JOIN CUSTOM C ON J.CUSTOM = C.KODE
                    LEFT JOIN $dbmaster..CABANG CB ON CB.KODE = J.CB
                    LEFT JOIN PPTGDT P ON J.NOMOR = P.NOXX
                WHERE J.NOMOR='$param->nofak'";
        $data['header'] = current($this->query($sql)->fetchAll(PDO::FETCH_ASSOC));


        $detail = "SELECT J.NOURUT, J.GUDANG, J.BARANG, J.QTY, J.SAT, J.HARGA, J.DISC1, J.[GROUP], G.NAMA AS NAMAGUDANG, P.
                        NAMA AS NAMABARANG, J.DISCN, J.BIAYA, J.TAXN, G.KODE AS KODEGUDANG
                   FROM JLDT J
                        JOIN $dbmaster..GUDANG G ON J.GUDANG = G.KODE 
                        JOIN PROD1 P ON J.BARANG = P.KODE AND J.[GROUP] = P.[GROUP]
                   WHERE NOMOR = '$param->nofak'";

        $data['detail'] = $this->query($detail)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data['detail'] as $key => $value) {
            $query = "SELECT IMEI FROM IMEIX WHERE NOMOR='$param->nofak' AND BARANG='$value[BARANG]' AND [GROUP]='$value[GROUP]' AND GUDANG='$value[GUDANG]'";

            $data['imei'][$value['NAMABARANG']] = $this->query($query)->fetchAll(PDO::FETCH_ASSOC);

            if (count($data['imei'][$value['NAMABARANG']]) == 0) {
                unset($data['imei'][$value['NAMABARANG']]);
            }
        }

    
        return $data;
    }

    function post($data){
        if (isset($data->fn) && $data->fn == 'so') {
            $nofakso = $data->nofakso;


            $nofak = $this->insertHeader($data->header);


            $sql = $this->query("SELECT * FROM SODT s, PROD1 p WHERE s.NOMOR='$nofakso' and s.BARANG=p.KODE and s.[GROUP]=p.[GROUP]");
            $detail = $sql->fetchAll(PDO::FETCH_ASSOC);

            $qtySoH = 0;

            foreach ($detail as $key => $value) {

                $quantitySO = $value['QTY']-$value['QTZ'];

                $data->gudang       = $value['GUDANG'];
                $data->pelanggan    = $value['CUSTOM'];
                $data->salesman     = $value['SALESM'];
                $data->tanggal      = $data->header->tgl;
                $data->cabang       = $value['CB'];
                $data->satuan       = $value['SAT'];
                $data->group        = $value['GROUP'];
                $data->barang       = $value['BARANG'];
                $data->harga        = $value['HARGA'];
                $data->hargaDiskon  = $value['DISCD'];
                $data->qty          = $quantitySO;
                $data->diskon       = $value['DISC1'];
                $data->tipe         = $value['TIPE'];

                // if ($value['TIPE'] == 1) {
                //     $sqlImei = $this->query("SELECT * FROM IMEIX where BARANG=$value[BARANG] AND [GROUP]=$value[GROUP] AND GUDANG=$value[GUDANG]");
                //     $imei = $sqlImei->fetchAll(PDO::FETCH_ASSOC);
                    $data->imei = [];

                //     foreach ($imei as $key => $val) {
                //         array_push($data->imei, $val['IMEI']);
                //     }
                    
                // }
                

                $result = $this->insertDetail($data, $nofak);
                $dataSOD = array(
                    "QTZ"=> $quantitySO + $value['QTZ']
                );

                $where = array(
                    "NOURUT" => $value['NOURUT'],
                    "NOMOR" => $nofakso
                );

                $this->where($where);
                $this->update($dataSOD, 'SODT');

                $qtySoH += $quantitySO + $value['QTZ'];
                
                $dataSO = array(
                    "QTZ"=> $qtySoH
                );

                $where = array(
                    "NOMOR" => $nofakso
                );
                $this->where($where);
                $this->update($dataSO, 'SOHD');
            }

            $res['error'] = '';
            $res['status']=$result;
            return $res;
        }
        else{

            // data bukan dari so
            $res = $this->postData($data);
            return $res;
        }
    }

    function postData($data){
        $nourut = $this->getNoUrut($data->nofak, 'JLDT');
        $tgl1 = str_replace('/', '-', $data->header->tgl);
        $tgl = date('Y-m-d H:i:s', strtotime($tgl1));

        $where = array('group'=>$data->group, 'gudang'=>$data->gudang, 'barang'=>$data->barang, 'tanggal'=>$tgl, 'nourut'=>$nourut, 'nofak'=>$data->nofak);

        $stokMasuk = $this->getStok($where, 'masuk');
        $stokKeluar = $this->getStok($where, 'keluar');
        $qtyBarang = $this->getQtyBarang($where, 'JLDT');

        $sisaStok = ($stokMasuk-$stokKeluar) + $qtyBarang - $data->qty;
        // echo "sisastok :".$sisaStok;
        if($sisaStok < 0){
            $qtyAwal = $this->getStok($where, 'awal');
            $debe = $stokMasuk - $qtyAwal;
            $ceer = $stokKeluar;
            $stok = $qtyAwal + $debe - $ceer;

            // return $result = array('message'=>"Gagal. Detail barang: DEBE = $debe, CEER = $ceer, STOK = $stok");
            return $result = array('status'=>false,'error'=>"Gagal menambahkan detail. Stok barang tidak mencukupi, sisa stok: $stok");
        }

        if ($data->nofak == '') {

            $nofak = $this->insertHeader($data->header);
            $result = $this->insertDetail($data, $nofak);
        }
        else{
            $result = $this->insertDetail($data, $data->nofak);

        }
        $res['error'] = '';
        $res['status']=$result;
        return $res;
    }

    function insertHeader($data){
        $cb = $data->cabang;
        $pelanggan = $data->pelanggan;
        $salesman = $data->salesman;
        $noref = $data->noref;
        $nofak = $this->getNoFak($data->tempnofak, 'JLHD');
        $custom = $this->getCustomInfo($pelanggan);

        $tgl1 = str_replace('/', '-', $data->tgl);
        $tgl = date('Y-m-d H:i:s', strtotime($tgl1));

        $tgl2 = str_replace('/', '-', $data->tgljtp);
        $tgljtp = date('Y-m-d H:i:s', strtotime($tgl2));
        
        // hitung jumlah hari jatuh tempo
        $now = date_create($tgl);
        $jt = date_create($tgljtp);
        $term = date_diff($now, $jt);
        $term = $term->format('%a');

        $data = array(
            "CABANG"=>'01', 
            "NOMOR" => $nofak, 
            'NOMORCB'=> $nofak, 
            'NOREF'=>$noref,
            'TANGGAL'=> $tgl,
            'TGLINV'=>$tgl,
            'TERM'=>$term, 
            'TGLJTP'=> $tgljtp, 
            'CUSTOM'=>$pelanggan, 
            'WILAYAH'=>$custom['WILAYAH'],
            'SALESM'=>$salesman,
            'UANG'=>'RP', 
            'KURS'=>1, 
            'HRGTOT'=>0,
            'DISCD'=>0,
            'DISCH' => 0,
            'DISCN' => 0,
            'BIAYA' => 0,
            'TAXP' => 0,
            'TAXN' => 0,
            'LAIN' => 0,
            'HRGNET' => 0,
            'UM' =>0,
            'PAJAK'=>0,
            'BAYAR'=>0,
            'KETB'=>'',
            'KETL'=>'',
            'KET1'=>'',
            'KET2'=>'',
            'KET3'=>'',
            'KET4'=>'',
            'KKET1'=>'',
            'KKET2'=>'',
            'KKET3'=>'',
            'KKET4'=>'',
            'QTY' => 0,
            'QTX' => 0,
            'QTZ' => 0,
            'NPRT' =>0,
            'KETPRT' => 0,
            'POSJ' => '',
            'POSP' => '',
            'FLAG' => '',
            'ID1'=> $_SESSION['userid'],
            'ID2'=>'',
            'ID3'=>'',
            'ID4'=>'',
            'DOE'=>date('Y-m-d H:i:s'), 
            'TOE'=>date('H:i:s'), 
            'LOE'=>date('Y-m-d H:i:s'),
            'DEO'=>$_SESSION['userid'], 
            'SIGN'=>'', 
            'IP'=>$_SESSION['userid'], 
            'CB'=>$cb,
            'KOTA'=> '',
            'TUNAI'=> '',
            'NOPJ'=> '',
            'NOSERI'=> ''
        );

        // print_r($data);
        $result = $this->insert($data, 'JLHD');

        // print_r($result);
        if ($result) {
            return $nofak;
        }
        return false;
    }

    function insertDetail($data, $nofak){

        $nofak = $nofak;
        $gd = $data->gudang;
        $pelanggan = $data->pelanggan;
        $salesm = $data->salesman;
        $tgl1 = str_replace('/', '-', $data->tanggal);
        $tgl = date('Y-m-d H:i:s', strtotime($tgl1));
        $cabang = $data->cabang;
        $satuan = $data->satuan;
        $group = $data->group;
        $barang = $data->barang;
        $htg = 1;
        $ptg = 1;
        $harga = $data->harga;
        $wilayah = $this->getCustomInfo($pelanggan)['WILAYAH'];
        $nourut = $this->getNoUrut($nofak, 'JLDT');
        $nokey = $this->getNoKey($nofak, 'JLDT');
        $discd = $data->hargaDiskon;
        $qty = $data->qty;
        $nocab = $nofak;
        $deo = $_SESSION['userid'];
        $now = date('Y-m-d H:i:s');
        $noref = $data->header->noref;
        $disc1 = $data->diskon;
        $tgl2 = str_replace('/', '-', $data->header->tgljtp);
        $tgljtp = date('Y-m-d H:i:s', strtotime($tgl2));
        $tipe = $data->tipe;

        $noxx = isset($data->nofakso) ? $data->nofakso : $nofak;

        $dataD = array(
            'CABANG' => '01',
            'NOMOR'=> $nofak,
            'NOMORCB' => $nocab,
            'NOURUT' => $nourut,
            'NOKEY' => $nokey,
            'CBXX' => $cabang,
            'NOXX' => $noxx,
            'NOSUB' => $nokey,
            'CBYY' => '',
            'NOYY' => '',
            'NOREF' => $noref,
            'REF' => $noref,
            'REFYY' => '',
            'TANGGAL' => $tgl,
            'TGL' => $tgl,
            'TGLYY' => $tgl,
            'CUSTOM' => $pelanggan,
            'WILAYAH' => $wilayah,
            'SALESM' => $salesm,
            'GUDANG' => $gd,
            '[GROUP]' => $group,
            'BARANG' => $barang,
            'QTY' => $qty,
            'QTX' => 0,
            'QTZ' => 0,
            'SAT' => $satuan,
            'UANG' => 'RP',
            'KURS' => 1,
            'STDJUAL' => 0,
            'MINJUAL' => 0,
            'HARGA'=> $harga,
            'DISC1' => $disc1,
            'DISC2' => 0,
            'DISC3' => 0,
            'DISCD' => $discd,
            'DISCH' => 0,
            'DISCN' => $discd,
            'BIAYA' => 0,
            'TAXN' => 0,
            'LAIN' => 0,
            'FIFO' => 0,
            'RATA' => 0,
            'POSJ' => '',
            'POSP' => '',
            'FLAG'=>'',
            'DOE' => $now,
            'TOE' => date('H:i:s'),
            'LOE' => $now,
            'DEO'=> $deo,
            'SIGN' =>'',
            'CB' => $cabang,
            'HTG' => $htg,
            'PTG' => $ptg,
            'PAK' => $satuan,
            'HGPAK' => $harga,
            'VENDOR'=> '',
            'LOKASI'=> '',
            'KOTA'=> '',
            'REFUND'=> 0
        );

        $dataP3 = array(
            'CABANG' => '01',
            'NOMOR'=> $nofak,
            'NOMORCB' => $nocab,
            'NOURUT' => $nourut,
            'NOKEY' => $nokey,
            'CBXX' => $cabang,
            'NOXX' => $noxx,
            'NOSUB' => $nokey,
            'NOREF' => $noref,
            'REF' => $noref,
            'TANGGAL' => $tgl,
            'TGL' => $tgl,
            'VENDOR'=> '',
            'LOKASI'=> '',
            'CUSTOM' => $pelanggan,
            'WILAYAH' => $wilayah,
            'SALESM' => $salesm,
            'KOTA'=> '',
            'GUDANG' => $gd,
            '[GROUP]' => $group,
            'BARANG' => $barang,
            'TIPE'=> $tipe,
            'QTY' => $qty,
            'QTX' => 0,
            'QTZ' => 0,
            'SAT' => $satuan,
            'UANG' => 'RP',
            'KURS' => 1,
            'STDBELI'=>0,
            'STDJUAL' => 0,
            'MINJUAL' => 0,
            'HARGA'=> $harga,
            'DISCN' => $discd,
            'BIAYA' => 0,
            'TAXN' => 0,
            'LAIN' => 0,
            'XSTK' =>0,
            'STOK'=>0,
            'RATA' => 0,
            'FIFO' => $harga,
            'FLAG'=>'7',
            'DOE' => $now,
            'TOE' => date('H:i:s'),
            'LOE' => $now,
            'DEO'=> $deo,
            'SIGN' =>'',
            'REFUND'=> 0,
            'CB' => $cabang,
            'HTG' => $htg,
            'PTG' => $ptg,
            'PAK' => $satuan,
            'HGPAK' => $harga,
        );

        $dataPutang = array(
            "CABANG" => '01',
            "NOMOR" => $nofak,
            "NOMORCB" => $nocab,
            "NOREF" => $noref,
            "TANGGAL" => $tgl,
            "TBAYAR" => '',
            "TUNAI" => '',
            "CBXX" => $cabang,
            "NOXX" => $noxx,
            "NOKEY" => '',
            "REF" => $noref,
            "TGL" => $tgl,
            "TGLJTP" => $tgljtp,
            "CUSTOM" => $pelanggan,
            "WILAYAH" => $wilayah,
            "SALESM" => $salesm,
            "KOTA" => '',
            "UANG" => 'RP',
            "KURS" => 1,
            "AWAL" => 0,
            "DEPE" => 0,
            "CEER" => 0,
            "SGIRO" => 0,
            "NOGIRO" => 0,
            "JTGIRO" => $tgljtp,
            "GIRO" => 0,
            "CBTT" => '',
            "NOTT" => '',
            "POSJ" => '',
            "FLAG" => 'J',
            "DOE" => $now,
            "TOE" => date("H:i:s"),
            "LOE" => $now,
            "DEO" => $deo,
            "SIGN" => '',
            "CB" => $cabang
        );

        if($tipe == 1){

            $imei = $data->imei;
            foreach ($imei as $key => $noImei) {
                $qry = "SELECT IMEI FROM IMEIX WHERE IMEI = '$noImei' AND GUDANG = '$gd' AND [GROUP] = '$group' AND BARANG = '$barang'";
                $cekImeiX =  $this->query($qry)->fetchAll(PDO::FETCH_ASSOC);

                if(count($cekImeiX) > 0){
                    $whereImeiX = array("IMEI" => $noImei, "GUDANG" => $gd, "[GROUP]" => $group, "BARANG" => $barang );
                    $this->where($whereImeiX);
                    $this->delete('IMEIX');
                }

                $dataImeiX = array(
                    "CABANG" => "01","NOMOR" => $nofak,"NOMORCB" => $nocab,"NOKEY" => $nokey,"CBXX" => $cabang,"NOXX" => $nofak,"NOSUB" => $nokey,"NOREF" => $noref,"REF" => $noref,"TANGGAL" => $tgl,"TGL" => $tgl,"VENDOR" => "","LOKASI" => "","CUSTOM" => $pelanggan,"WILAYAH" => $wilayah,"SALESM" => $salesm,"GUDANG" => $gd,"[GROUP]" => $group,"BARANG" => $barang,"IMEI" => $noImei,"UANG" => "RP","KURS" => 1,"HARGA" => $harga,"DISCN" => $discd,"BIAYA" => 0,"TAXN" => 0,"LAIN" => 0,"STOK" => 0,"RATA" => "","FIFO" => $harga,"FLAG" => 7,"DOE" => $now,"TOE" => date("H:i:s"),"LOE" => $now,"DEO" => $deo,"SIGN" => "","CB" => $cabang,"FLAGSRV" => "","NAMACUSTOM" => "","STATUS" => "","TGLEXP" => $now,"NOBATCH" => "");
                
                $dataP2 = array(
                    "CABANG" => "01","NOMOR" => $nofak,"NOMORCB" => $nocab,"NOKEY" => $nokey,"CBXX" => $cabang,"NOXX" => $nofak,"NOSUB" => $nokey,"BEFORE" => "","NOREF" => $noref,"REF" => $noref,"TANGGAL" => $tgl,"TGL" => $tgl,"VENDOR" => "","LOKASI" => "","CUSTOM" => $pelanggan,"WILAYAH" => $wilayah,"SALESM" => $salesm,"GUDANG" => $gd,"[GROUP]" => $group,"BARANG" => $barang,"IMEI" => $noImei,"UANG" => "RP","KURS" => 1,"HARGA" => $harga,"DISCN" => $discd,"BIAYA" => 0,"TAXN" => 0,"LAIN" => 0,"STOK" => 0,"RATA" => 0,"FIFO" => $harga,"FLAG" => 7,"DOE" => $now,"TOE" => date("H:i:s"),"LOE" => $now,"DEO" => $deo,"SIGN" => "","REFUND" => 0,"CB" => $cabang,"KET1" => "","KET2" => "","FLAGSRV" => "","NAMACUSTOM" => "","STATUS" => "","TGLEXP" => $now,"NOBATCH" => "");
                $dataJlim = array(
                    "CABANG" => "01","NOMOR" => $nofak,"NOKEY" => $nokey,"NOURUT" => $nourut,"IMEI" => $noImei,"POSJ" => "","FLAG" => "","DOE" => $now,"TOE" => date("H:i:s"),"LOE" => $now,"DEO" => $deo,"SIGN" => "","TGLEXP" => $now,"NOBATCH" => "");

                $this->insert($dataImeiX, 'IMEIX');
                $this->insert($dataP2, 'PROD2');
                $this->insert($dataJlim, 'JLIM');
            }
        }

        $dataDetail = $this->getDetailTransaksi($nofak, 'JLDT');

        $totqty  = $qty;
        $totharga= $harga * $qty;
        $totdiscd= $discd * $qty;
        $totdiscn= $discd;
        // $totprice=$net;
        foreach ($dataDetail as $key => $value) {
            $harga = $value['QTY'] * $value['HARGA'];

            $totharga += $harga;
            
            $totqty += $value['QTY'];
            $totdiscd += $value['DISCD'] * $value['QTY'];
            $totdiscn += $value['DISCN'];
        }

        $dataHeader = array(
            'QTY'=>$totqty,
            'HRGTOT'=>$totharga,
            'DISCD'=>$totdiscd,
            'DISCN'=>$totdiscn,
            'HRGNET'=>$totharga
        );


        $result = $this->insert($dataD, 'JLDT');


        if($result){
            $whereInfo = array('barang'=>$barang, 'group'=>$group, 'gd'=>$gd, 'qty'=>$qty, 'htg'=>$htg, 'qtyJldt'=> 0, 'nofak'=> $nofak, 'nourut', $nourut, 'nokey'=>$nokey, 'pelanggan'=>$pelanggan, 'hrgnet'=>$totharga);

            $dataPutang['DEBE']= $totharga;
            $dataPutang['SALDO']= $totharga;

            $this->updateProd1($whereInfo);
            $this->updateWare1($whereInfo);
            $this->updateProd3($whereInfo, $dataP3);
            $this->updatePutang($whereInfo, $dataPutang);


            $where = array("NOMOR"=>$nofak);
            $this->where($where);
            $result2 = $this->update($dataHeader, 'JLHD');
            $status = array("nofak"=>$nofak);
        }
        else{
            $status = null;
        }

        // print_r($result);
        return $status;
    }

    function getDetail($data){
        $dbmaster = $_SESSION['dbname']."SHRDBF";
        $nofak = $data->nofak;

        $sql = "SELECT S.NOXX, S.NOKEY, S.QTY, S.SAT, S.HARGA, S.HGPAK, S.HTG, S.NOURUT, S.DISCD, S.DISC1, G.KODE AS KODEGUDANG, ((S.HARGA - S.DISCN + S.TAXN + S.BIAYA) * S.QTY) AS TOTALHARGA, S.[GROUP], P.NAMA AS NAMABARANG, P.KODE AS KODEBARANG,  G.NAMA AS GUDANG, P.TIPE
                FROM JLDT S, PROD1 P, $dbmaster..GUDANG G
                WHERE S.BARANG+S.[GROUP] = P.KODE+P.[GROUP] AND S.GUDANG=G.KODE AND S.NOMOR = '$nofak'";

        if (isset($data->nourut) && $data->nourut !== '') {
            $sql .= " AND NOURUT='".$data->nourut."'";
        }

        //ambil imei
        if(isset($data->nokey) && $data->nokey != '') {
            $sqlImei = "SELECT IMEI FROM IMEIX WHERE NOMOR = '$nofak' AND NOKEY = '$data->nokey'";
            $imei = $this->query($sqlImei)->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $sql .=" ORDER BY S.NOURUT";

        $dt = $this->query($sql);
        $detail = $dt->fetchAll(PDO::FETCH_ASSOC);

        if (isset($data->nourut) && $data->nourut !== '') {
            $imeiN = array_map(function($item){
                        return $item['IMEI'];
                    }, $imei);

            return array('detail'=>$detail, 'imei'=>$imeiN);
        }

        $sql = "SELECT HRGTOT, HRGNET, QTY, DISCD FROM JLHD WHERE NOMOR='$nofak'";
        $hd = $this->query($sql);
        $header = current($hd->fetchAll(PDO::FETCH_ASSOC));

        $whereC = array('nofak'=> $nofak, 'cabang'=>$data->cabang, 'pelanggan'=>$data->pelanggan);
        $cekPelunasan = $this->cekPelunasan($whereC);

        $header['lunas'] = $cekPelunasan;

        return array('detail'=> $detail, 'header'=> $header);

    }

    function updateData($id, $data){
        if (!isset($data->faktur)) {
            return $this->updateD($id, $data);
        }
        else{
            $update = $this->updateD($id, $data);

            // $query = ""

            if (!$update['status']) {
                return $update;
            }
            else{
                $noxx = $data->detail[0]->NOXX;

                $qrySo = $this->query("SELECT QTY, QTZ, NOURUT FROM SODT WHERE NOMOR='$noxx' AND BARANG='$data->barang'
                 AND [GROUP]='$data->group' AND GUDANG='$data->gudang'");
                $dataSO = $qrySo->fetchAll(PDO::FETCH_ASSOC);

                foreach ($dataSO as $key => $value) {
                    $qtz = $value['QTZ'] - $data->qtyawal + $data->qty;

                    $where = array(
                        "NOURUT" => $value['NOURUT'],
                        "NOMOR" => $noxx
                    );

                    $this->where($where);
                    // $qtz = $qtz + $value['QTZ'];

                    $this->update(array('QTZ' => $qtz), 'SODT');
                }

                $this->query("UPDATE SOHD SET QTZ = QTZ - $data->qtyawal + $data->qty WHERE NOMOR = '$noxx'");


                return $update;
            }
        }
    }

    function updateD($id, $data){

        if(isset($data->fn) && $data->fn=='valid'){
            $res['status'] = $this->valid($id,$data);
            $res['error']='';
            return $res;
        }

        $cabang = isset($data->header) ? $data->header->cabang : $data->cabang;
        $pelanggan = isset($data->header) ?$data->header->pelanggan : $data->pelanggan;

        $whereC = array('nofak'=> $id, 'cabang'=>$cabang, 'pelanggan'=>$pelanggan);
        $cekPelunasan = $this->cekPelunasan($whereC);
        // echo $cekPelunasan;
        if($cekPelunasan > 0){
            return $result = array('status'=>false, 'error'=>"Data tidak dapat dirubah, karena sudah dilakukan pelunasan.");
        }

        if (!isset($data->fn)) {
            $result = $this->updateHeader($id, $data);
        }
        else{

            $tanggal = $data->header->tgl;

            $group = isset($data->GROUP) ? $data->GROUP : $data->group;
            $gudang = isset($data->GROUP)? $data->KODEGUDANG : $data->gudang;
            $barang = isset($data->GROUP) ? $data->KODEBARANG : $data->barang;
            $nourut = isset($data->GROUP) ? $data->NOURUT : $data->nourut;

            $tgl1 = str_replace('/', '-', $tanggal);
            $tgl = date('Y-m-d H:i:s', strtotime($tgl1));

            $where = array('group'=>$group, 'gudang'=>$gudang, 'barang'=>$barang, 'tanggal'=>$tgl, 'nourut'=>$nourut, 'nofak'=>$id);

            $stokMasuk = $this->getStok($where, 'masuk');
            $stokKeluar = $this->getStok($where, 'keluar');
            $qtyBarang = $this->getQtyBarang($where, 'JLDT');

            $sisaStok = ($stokMasuk - $stokKeluar) + $qtyBarang - $data->qty;

            if($sisaStok < 0){
                $qtyAwal = $this->getStok($where, 'awal');
                $debe = $stokMasuk - $qtyAwal;
                $ceer = $stokKeluar;
                $stok = $qtyAwal + $debe - $ceer;
                
                // $result = array('message'=>"Gagal. Detail barang: DEBE = $debe, CEER = $ceer, STOK = $stok");
                return $result = array('status'=>false, 'error'=>"Gagal menambahkan detail. Stok barang tidak mencukupi, sisa stok: $stok");
            }
            else{

                $result = $this->updateDetail($id, $data);
            }
        }

        $res['status']=$result;
        $res['error']='';
        return $res;
    }

    function updateHeader($id, $data){
        $nofak = $id;
        $diskonD = $data->diskonD;
        $diskonH = $data->hrgDiskon;
        $diskonN = $diskonD+$diskonH;
        $ongkos = $data->ongkos;
        $taxn = $data->hrgPpn;
        $taxp = $data->ppn;
        $hrgnet = $data->hrgnet;
        $qty = $data->qty;
        $noref = $data->noref;
        $hrgtot = $data->hrgtot;
        $pelanggan = $data->pelanggan;

        $data = array(
            "NOREF" => $noref,
            "DISCD" => $diskonD,
            "DISCH" => $diskonH,
            "DISCN" => $diskonN,
            "BIAYA" => $ongkos,
            "TAXN" => $taxn,
            "TAXP" => $taxp,
            "HRGNET" => $hrgnet,
            "IP" => ''
        );

        $where = array(
            "NOMOR" => $nofak
        );
        $this->where($where);
        $result = $this->update($data, 'JLHD');

        if ($result) {

            $putangInfo = array('nofak'=> $nofak, 'pelanggan'=>$pelanggan, 'hrgnet'=> $hrgnet);                
            $this->updatePutang($putangInfo);

            $dataDetail = $this->getDetailTransaksi($nofak, 'JLDT');
            foreach ($dataDetail as $key => $value) {
                $disch    = $qty > 0 ? ($diskonH / $hrgtot) * ($value['HARGA']-$value['DISCD']) : 0;
                $discn = $value['DISCD'] + $disch;
                $ppnD    = $qty > 0 ? $taxn / ($hrgtot-$diskonH) * ($value['HARGA']-$discn) : 0;
                $ongkosD    = $qty > 0 ? $ongkos / ($hrgtot-$diskonH + $taxn) * ($value['HARGA']-$discn+$ppnD) : 0;

                $dataD = array(
                    "NOREF" => $noref,
                    "REF" => $noref,
                    "DISCH" => $disch,
                    "DISCN" => $discn,
                    "TAXN" => $ppnD,
                    "BIAYA" => $ongkosD
                );

                $whereD = array(
                    "NOURUT" => $value['NOURUT'],
                    "NOMOR" => $nofak
                );

                $this->where($whereD);
                $this->update($dataD, 'JLDT');

                //UPDATE PROD3
                unset($dataD['DISCH']);
                 $this->where($whereD);
                $this->update($dataD, 'PROD3');

                unset($dataD['NOREF']);
                unset($dataD['REF']);
                unset($dataD['DISCH']);

                
                $whereImei = array('NOMOR'=> $value['NOMOR'], 'GUDANG'=> $value['GUDANG'],'[GROUP]'=> $value['GROUP'],'BARANG'=>$value['BARANG']);
                $this->where($whereImei);
                $this->update($dataD, 'PROD2');
                $this->update($dataD, 'IMEIX');
            }

            return true;
        }

        return false;
    }


    function updateDetail($nofak, $data){
        
        if (isset($data->TIPE) && $data->TIPE == 1) { //jika hapus imei dr siHeader

            $nourut = $data->NOURUT;
            $nokey = $data->NOKEY;
            $imeiHapus = $data->imeiHapus;
            $qty = $data->qty;
            $tipe = $data->TIPE;
            
            $pelanggan = $data->header->pelanggan;
            $salesm = $data->header->salesman;
            $tgl1 = str_replace('/', '-', $data->header->tgl);
            $tgl = date('Y-m-d H:i:s', strtotime($tgl1));
            $cabang = $data->header->cabang;
            $noref = $data->header->noref;

            $gd = $data->KODEGUDANG;
            $satuan = $data->SAT;
            $group = $data->GROUP;
            $barang = $data->KODEBARANG;
            $htg = 1;
            $ptg = 1;
            $harga = $data->HARGA;
            $wilayah = $this->getCustomInfo($pelanggan)['WILAYAH'];
            $discd = $data->DISCD;
            $nocab = $nofak;
            $deo = $_SESSION['userid'];
            $now = date('Y-m-d H:i:s');
            $disc1 = $data->DISC1;
             
        }
        else{ // jika update dr siDetail

            $nofak = $nofak;
            $nourut = $data->nourut;
            $nokey = $data->nokey;

            $gd = $data->gudang;
            $pelanggan = $data->header->pelanggan;
            $salesm = $data->header->salesman;
            $tgl1 = str_replace('/', '-', $data->header->tgl);
            $tgl = date('Y-m-d H:i:s', strtotime($tgl1));
            $cabang = $data->header->cabang;
            $satuan = $data->satuan;
            $group = $data->group;
            $barang = $data->barang;
            $htg = 1;
            $ptg = 1;
            $harga = $data->harga;
            $wilayah = $this->getCustomInfo($pelanggan)['WILAYAH'];
            $discd = $data->hargaDiskon;
            $qty = $data->qty;
            $nocab = $nofak;
            $deo = $_SESSION['userid'];
            $now = date('Y-m-d H:i:s');
            $disc1 = $data->diskon;
            $noref = $data->header->noref;
            $tipe = $data->tipe;   
            $imeiHapus = $data->imeiHapus;
            $imeiBaru = $data->imeiBaru;
        }


        $dataDetail1 = current($this->getDetailTransaksi($nofak, 'JLDT', true, $nourut));
        $qtyJldt = $dataDetail1['QTY'];
        
        $diskonH = $data->header->diskon;
        $hrgtotH = $data->header->hrgtot;
        $ppnH = $data->header->hrgPpn;
        $ongkosH = $data->header->ongkos;

        $disch    = $qty > 0 && $diskonH > 0 ? ($diskonH / $hrgtotH) * ($harga-$discd) : 0;

        $discn = $discd + $disch;
        $ppnD    = $qty > 0 && $ppnH > 0 ? $ppnH / ($hrgtotH-$diskonH) * ($harga-$discn) : 0;

        $ongkosD    = $qty > 0 && $ongkosH > 0 ? $ongkosH / ($hrgtotH-$diskonH + $ppnH) * ($harga-$discn+$ppnD) : 0;


        $dataD = array(
            'QTY' => $qty,
            'QTX' => 0,
            'QTZ' => 0,
            'SAT' => $satuan,
            'HARGA'=> $harga,
            'DISC1' => $disc1,
            'DISCD' => $discd,
            'DISCN' => $discn,
            'LOE' => $now,
            'DEO'=> $deo,
            'PAK' => $satuan,
            'HGPAK' => $harga,
            'TAXN' => $ppnD,
            'BIAYA' => $ongkosD,
            'DISCH' => $disch
        );

        $dataP3 = array(
            'NOREF' => $noref,
            'REF' => $noref,
            'QTY' => $qty,
            'SAT' => $satuan,
            'HARGA'=> $harga,
            'DISCN' => $discn,
            'FIFO' => $harga,
            'LOE' => $now,
            'DEO'=> $deo,
            'HTG' => $htg,
            'PTG' => $ptg,
            'PAK' => $satuan,
            'HGPAK' => $harga
        );


        $whereDetail = array(
            "NOMOR"=>$nofak,
            "NOURUT"=>$nourut
        );
        $this->where($whereDetail);
        $result = $this->update($dataD, 'JLDT');

        if($tipe==1){
            if (count($imeiBaru) >0 ) { //jika hapus imei dr siDetail
                // print_r($imeiHapus);
                foreach ($imeiBaru as $key => $noImei) {
                    $qry = "SELECT IMEI FROM IMEIX WHERE IMEI = '$noImei' AND GUDANG = '$gd' AND [GROUP] = '$group' AND BARANG = '$barang'";
                    $cekImeiX =  $this->query($qry)->fetchAll(PDO::FETCH_ASSOC);

                    if(count($cekImeiX) > 0){
                        $whereImeiX = array("IMEI" => $noImei, "GUDANG" => $gd, "[GROUP]" => $group, "BARANG" => $barang );
                        $this->where($whereImeiX);
                        $this->delete('IMEIX');
                    }

                    $dataImeiX = array(
                        "CABANG" => "01","NOMOR" => $nofak,"NOMORCB" => $nocab,"NOKEY" => $nokey,"CBXX" => $cabang,"NOXX" => $nofak,"NOSUB" => $nokey,"NOREF" => $noref,"REF" => $noref,"TANGGAL" => $tgl,"TGL" => $tgl,"VENDOR" => "","LOKASI" => "","CUSTOM" => $pelanggan,"WILAYAH" => $wilayah,"SALESM" => $salesm,"GUDANG" => $gd,"[GROUP]" => $group,"BARANG" => $barang,"IMEI" => $noImei,"UANG" => "RP","KURS" => 1,"HARGA" => $harga,"DISCN" => $discd,"BIAYA" => 0,"TAXN" => 0,"LAIN" => 0,"STOK" => 0,"RATA" => "","FIFO" => $harga,"FLAG" => 7,"DOE" => $now,"TOE" => date("H:i:s"),"LOE" => $now,"DEO" => $deo,"SIGN" => "","CB" => $cabang,"FLAGSRV" => "","NAMACUSTOM" => "","STATUS" => "","TGLEXP" => $now,"NOBATCH" => "");
                    
                    $dataP2 = array(
                        "CABANG" => "01","NOMOR" => $nofak,"NOMORCB" => $nocab,"NOKEY" => $nokey,"CBXX" => $cabang,"NOXX" => $nofak,"NOSUB" => $nokey,"BEFORE" => "","NOREF" => $noref,"REF" => $noref,"TANGGAL" => $tgl,"TGL" => $tgl,"VENDOR" => "","LOKASI" => "","CUSTOM" => $pelanggan,"WILAYAH" => $wilayah,"SALESM" => $salesm,"GUDANG" => $gd,"[GROUP]" => $group,"BARANG" => $barang,"IMEI" => $noImei,"UANG" => "RP","KURS" => 1,"HARGA" => $harga,"DISCN" => $discd,"BIAYA" => 0,"TAXN" => 0,"LAIN" => 0,"STOK" => 0,"RATA" => 0,"FIFO" => $harga,"FLAG" => 7,"DOE" => $now,"TOE" => date("H:i:s"),"LOE" => $now,"DEO" => $deo,"SIGN" => "","REFUND" => 0,"CB" => $cabang,"KET1" => "","KET2" => "","FLAGSRV" => "","NAMACUSTOM" => "","STATUS" => "","TGLEXP" => $now,"NOBATCH" => "");
                    $dataJlim = array(
                        "CABANG" => "01","NOMOR" => $nofak,"NOKEY" => $nokey,"NOURUT" => $nourut,"IMEI" => $noImei,"POSJ" => "","FLAG" => "","DOE" => $now,"TOE" => date("H:i:s"),"LOE" => $now,"DEO" => $deo,"SIGN" => "","TGLEXP" => $now,"NOBATCH" => "");

                    $this->insert($dataImeiX, 'IMEIX');
                    $this->insert($dataP2, 'PROD2');
                    $this->insert($dataJlim, 'JLIM');
                }

            }
            
            if(count($imeiHapus) > 0){  //jika tambah imei baru
                
                foreach ($imeiHapus as $key => $imei) {
                    $where = array('NOMOR'=> $nofak, 'IMEI'=>$imei, 'GUDANG'=> $gd,'[GROUP]'=> $group,'BARANG'=>$barang);
                    $where2 = array('NOMOR'=> $nofak, 'IMEI'=>$imei);

                    $this->where($where);
                    $this->delete('IMEIX');
                    $this->delete('PROD2');

                    $this->where($where2);
                    $this->delete('JLIM');

                    $sql = "INSERT INTO IMEIX 
                                (CABANG, NOMOR, NOMORCB, NOKEY, CBXX, NOXX, NOSUB, NOREF, REF, TANGGAL, TGL, VENDOR, LOKASI, CUSTOM, WILAYAH, SALESM, GUDANG, [GROUP], BARANG, IMEI, UANG, KURS, HARGA, DISCN, BIAYA, TAXN, LAIN, STOK, RATA, FIFO, FLAG, DOE, TOE, LOE, DEO, SIGN, CB, FLAGSRV, NAMACUSTOM, HP, TOKO, RUSAK, CS, TK, LENGKAP, REKAN, JENIS, STATUS, GARANSI, KONFIRMASI, NMKONFIRMASI, STS, PIN, TGLEXP, NOBATCH)
                            SELECT TOP 1 CABANG, NOMOR, NOMORCB, NOKEY, CBXX, NOXX, NOSUB, NOREF, REF, TANGGAL, TGL, VENDOR, LOKASI, CUSTOM, WILAYAH, SALESM, GUDANG, [GROUP], BARANG, IMEI, UANG, KURS, HARGA, DISCN, BIAYA, TAXN, LAIN, STOK, RATA, FIFO, FLAG, DOE, TOE, LOE, DEO, SIGN, CB, FLAGSRV, NAMACUSTOM, HP, TOKO, RUSAK, CS, TK, LENGKAP, REKAN, JENIS, STATUS, GARANSI, KONFIRMASI, NMKONFIRMASI, STS, PIN, TGLEXP, NOBATCH 
                            FROM PROD2  WHERE IMEI = '$imei'
                            ORDER BY [GROUP], BARANG, IMEI, TANGGAL DESC, NURUT DESC";
                    $copy = $this->query($sql);
                }
            }
            else{
                $dataImei = array('HARGA'=> $harga, 'DISCN'=>$discn, 'LOE'=> $now,'DEO'=> $deo);
                $dataJlim = array('NOURUT'=>$nourut,'LOE'=> $now,'DEO'=> $deo);

                $whereImei = array('NOMOR'=>$nofak, 'NOKEY'=>$nokey);

                $this->where($whereImei);
                $this->update($dataImei, 'IMEIX');

                $this->update($dataImei, 'PROD2');

                $this->update($dataJlim, 'JLIM');
            }

        }

        $dataDetail = $this->getDetailTransaksi($nofak, 'JLDT', false, $nourut);
        $totqty  = $qty;
        $totharga= $harga * $qty;
        $totdiscd= $discd * $qty;
        $totdiscn= $discn;

        // print_r($dataDetail)
        // $totprice=$net;
        foreach ($dataDetail as $key => $value) {
            $harga = $value['QTY'] * $value['HARGA'];
            
            $totharga += $harga;
            
            $totqty += $value['QTY'];
            $totdiscd += $value['DISCD'];
            $totdiscn += $value['DISCN'];
        }

        $dataHeader = array(
            'QTY'=>$totqty,
            'HRGTOT'=>$totharga,
            'DISCD'=>$totdiscd,
            'DISCN'=>$totdiscn,
            'HRGNET'=>$totharga
        );

        if($result){
            $dataInfo = array('barang'=>$barang, 'group'=>$group, 'gd'=>$gd, 'qty'=>$qty, 'htg'=>$htg, 'qtyJldt'=> $qtyJldt, 'nofak'=> $nofak, 'nourut', $nourut, 'nokey'=>$nokey, 'pelanggan'=>$pelanggan, 'hrgnet'=>$totharga);

            $this->updateProd1($dataInfo);
            $this->updateWare1($dataInfo);
            $this->updateProd3($dataInfo, $dataP3);
            $this->updatePutang($dataInfo);

            $where = array("NOMOR"=>$nofak);
            $this->where($where);
            $result2 = $this->update($dataHeader, 'JLHD');
            $status = array("nofak"=>$nofak);
        }
        else{
            $status = null;
        }

        // print_r($result);
        return $status;
    }

    function deleteData($id, $data) {
        if ($data->faktur=='si') {
            return $this->deleteD($id, $data);
        }
        else{
            $delete = $this->deleteD($id, $data);

            // $query = ""

            if (!$delete['status']) {
                return $delete;
            }
            else{
                $noxx = $data->noxx;

                $qrySo = $this->query("SELECT QTY, QTZ, NOURUT FROM SODT WHERE NOMOR='$noxx' AND BARANG='$data->barang'
                 AND [GROUP]='$data->group' AND GUDANG='$data->gudang'");
                $dataSO = $qrySo->fetchAll(PDO::FETCH_ASSOC);

                foreach ($dataSO as $key => $value) {
                    $qtz = $value['QTZ'] - $data->qty;

                    $where = array(
                        "NOURUT" => $value['NOURUT'],
                        "NOMOR" => $noxx
                    );

                    $this->where($where);
                    // $qtz = $value['QTZ'] - $qtz;
                    $this->update(array('QTZ' => $qtz), 'SODT');
                }

                $this->query("UPDATE SOHD SET QTZ = QTZ - $data->qty WHERE NOMOR = '$noxx'");


                return $delete;
            }
        }
    }


    function deleteD($id, $data){

        $whereC = array('nofak'=> $id, 'cabang'=>$data->cabang, 'pelanggan'=>$data->pelanggan);
        $cekPelunasan = $this->cekPelunasan($whereC);
        // echo $cekPelunasan;
        if($cekPelunasan > 0){
            return $result = array('status'=>false, 'error'=>"Data tidak dapat dihapus, karena sudah dilakukan pelunasan.");
        }

        if (!isset($data->nourut)) {
            $result = $this->deleteHeader($id, $data);
        }
        else{
            $result = $this->deleteDetail($id, $data);
        }

        $res['status']=$result;
        $res['error']='';
        return $res;
    }

    function deleteHeader($nofak, $data){

        $detail = $this->getDetailTransaksi($nofak, 'JLDT');

        if (count($detail) > 0) {
            return false;
        }

        $where = array(
            "NOMOR" => $nofak
        );
        $this->where($where);
        
        $result = $this->delete('JLHD');

        if($result){
            $where2 = array(
                "NOMOR" => $nofak,
                "CUSTOM" => $data->pelanggan
            );
            $this->where($where2);
            $this->delete('PUTANG');
        }
        return true;
    }

    function deleteDetail($nofak, $data){
        $dataD = current($this->getDetailTransaksi($nofak, 'JLDT', true, $data->nourut));
        $qtyD = $dataD['QTY'];
        $hrgDetail = $dataD['HARGA'] * $qtyD;


        $dataHeader  = "SELECT HRGTOT, QTY, DISCD, DISCH, TAXN, TAXP, BIAYA, HRGNET FROM JLHD WHERE NOMOR = '$nofak'";
        $stmt        = $this->query($dataHeader);
        $header      = current($stmt->fetchAll(PDO::FETCH_ASSOC));
        $hrgtot      = $header['HRGTOT'] - $hrgDetail;
        $qtyHeader   = $header['QTY'] - $qtyD;
        $discdHeader = $header['DISCD'] - ($dataD['DISCD']*$qtyD);
        $discnHeader = $header['DISCH'] + $discdHeader;
        $taxnHeader  = ($hrgtot - $discnHeader) * ($header['TAXP']/100); 
        $hrgnet      = $hrgtot - $discnHeader + $taxnHeader + $header['BIAYA'];

        //UPDATE DATA HEADER
        $dataH = array(
            "DISCD" => $discdHeader,
            "DISCN" => $discnHeader,
            "TAXN" => $taxnHeader,
            "HRGTOT" => $hrgtot,
            "QTY" => $qtyHeader,
            "HRGNET" => $hrgnet,
            "LOE"=>date('Y-m-d H:i:s'),
            "ID3" =>$_SESSION['userid']
        );

        $where = array(
            "NOMOR" => $nofak
        );
        $this->where($where);
        $this->update($dataH, 'JLHD');

        $putangInfo = array('nofak'=> $nofak, 'pelanggan'=>$data->pelanggan, 'hrgnet'=> $hrgnet);
        $this->updatePutang($putangInfo);

        //DELETE DATA DETAIL
        $where = array(
            "NOMOR" => $nofak,
            "NOURUT" => $data->nourut
        );
        $this->where($where);
        $result = $this->delete('JLDT');

        if ($result) {
            // DELETE PROD3
            $this->where($where);
            $this->delete('PROD3');

            //UPDATE DATA DETAIL
            $dataDetail = $this->getDetailTransaksi($nofak, 'JLDT');
            $disch = $qtyHeader > 0 ? $header['DISCH'] / $qtyHeader : 0;
            $ongkosD = $qtyHeader > 0 ? $header['BIAYA'] / $qtyHeader : 0;
            $ppnD = $qtyHeader > 0 ? $taxnHeader / $qtyHeader : 0;

            foreach ($dataDetail as $key => $value) {
                $discn = $value['DISCD'] + $disch;

                $dataD = array(
                    "DISCH" => $disch,
                    "DISCN" => $discn,
                    "TAXN" => $ppnD,
                    "BIAYA" => $ongkosD,
                    "DEO" => $_SESSION['userid'],
                    "LOE"=>date('Y-m-d H:i:s')
                );

                $whereD = array(
                    "NOURUT" => $value['NOURUT'],
                    "NOMOR" => $nofak
                );

                $this->where($whereD);
                $this->update($dataD, 'JLDT');

                //UPDATE PROD3
                unset($dataD['DISCH']);
                $dataP3 = $dataD;
                 $this->where($whereD);
                $this->update($dataP3, 'PROD3');
            }
            
            $whereInfo = array('nofak'=> $nofak, 'nourut'=> $data->nourut, 'nokey'=> $data->nokey, 'barang'=> $data->barang, 'group'=> $data->group, 'gudang'=> $data->gudang, 'qty'=> $data->qty, 'pelanggan'=> $data->pelanggan);

            $this->updateNourut($whereInfo, 'JLDT');
            $this->updateNourut($whereInfo, 'PROD3');

            $this->updateProd1($whereInfo, true);
            $this->updateWare1($whereInfo, true);

            return true;
        }
        return false;
    }

    function updateProd1($where, $delete=false){
        extract($where);
        if($delete){
            $qry = "UPDATE PROD1 SET CEER = CEER - ".$qty.", STOK = STOK + ".$qty." WHERE KODE = '$barang' AND [GROUP] = '$group'";
            $update = $this->query($qry);

            //UPDATE STATUS PAKAI:
            $hasil = $this->cekBarang($barang, $group);
            if($hasil == 0){
                $qry = "UPDATE PROD1 SET PAKAI = 'N' WHERE KODE = '$barang' AND [GROUP] = '$group'";
                $this->query($qry);
            }
        } else{
            //get DEBE CEER
            $debeCeer = "SELECT DEBE, CEER, AWAL FROM PROD1 WHERE KODE = '$barang' AND [GROUP] = '$group'";
            $dc = current($this->query($debeCeer)->fetchAll(PDO::FETCH_ASSOC));
            // print_r($dc);
            // echo $qty.",".$qtyJldt;
            $ceer = $dc['CEER'] +  $qty - $qtyJldt;
            $stok = $dc['AWAL'] + $dc['DEBE'] - $ceer;

            $qry = "UPDATE PROD1 SET CEER = '$ceer', STOK = '$stok', PAKAI = 'Y' WHERE KODE = '$barang' AND [GROUP] = '$group'";
            $update = $this->query($qry);

        }

        return $update;
    }

    function updateWare1($where, $delete=false){
        extract($where);
        if($delete){
            $qry = "UPDATE WARE1 SET CEER = CEER - ".$qty.", STOK = STOK + ".$qty." WHERE BARANG = '$barang' AND [GROUP] = '$group' AND GUDANG = '$gudang'";
            $result = $this->query($qry);


        }
        else{
            $qtyFix = $qty * $htg;
            //cek gudang
            $qry = "SELECT DOE, AWAL, DEBE, CEER FROM WARE1 WHERE GUDANG = '$gd' AND [GROUP] = '$group' AND BARANG = '$barang'";
            $jumlah = $this->query($qry)->fetchAll(PDO::FETCH_ASSOC);
            $data = current($jumlah);
            
            if(count($jumlah) <= 0){
                $whereInfo = array('GUDANG'=> $gd, 'CEER'=> $qtyFix, 'STOK'=> $qtyFix, '[GROUP]'=> $group, 'BARANG'=> $barang, 'DOE'=> date('Y-m-d H:i:s'));
                $result = $this->insert($whereInfo, 'WARE1');
            }
            else{
                $ceer = $data['CEER'] +  $qty - $qtyJldt;
                $stok = $data['AWAL'] + $data['DEBE'] - $ceer;

                $dataInfo = array('CEER'=> $ceer, 'STOK'=> $stok, 'LOE'=> date('Y-m-d H:i:s'), 'DEO'=> $_SESSION['userid']);
                $this->where(array('GUDANG'=> $gd, '[GROUP]'=> $group, 'BARANG'=> $barang));
                $result = $this->update($dataInfo, 'WARE1');
            }
        }

        return $result;
    }

    function updateProd3($where, $data){
        extract($where);
        //cek prod3
        $qry = "SELECT NOMOR FROM PROD3 WHERE NOMOR = '$nofak' AND NOKEY = '$nokey' AND GUDANG = '$gd' AND [GROUP] = '$group' AND BARANG = '$barang'";
        $dataP3 = $this->query($qry)->fetchAll(PDO::FETCH_ASSOC);
        // print_r($qry);
        // echo count($dataP3); 
        if(count($dataP3) <= 0){
            $result = $this->insert($data, 'PROD3');
        }else{
            $this->where(array('NOMOR'=> $nofak, 'NOKEY'=>$nokey));
            $result = $this->update($data, 'PROD3');
        }
        return $result;
    }

    function updatePutang ($where, $data=null){
        extract($where);
        //cek putang
        $qry = "SELECT AWAL, DEPE, DEBE, CEER FROM PUTANG WHERE NOMOR = '$nofak' and CUSTOM = '$pelanggan'";
        $jumlah = $this->query($qry)->fetchAll(PDO::FETCH_ASSOC);
        $dataP = current($jumlah);

        // echo count($jumlah);
        if(count($jumlah) <= 0){
            $result = $this->insert($data, 'PUTANG');
        }else{
            $deo = $_SESSION['userid'];
            $now = date('Y-m-d H:i:s');
            $debe = $hrgnet;
            $saldo = $dataP['AWAL'] + $dataP['DEPE'] + $debe - $dataP['CEER'];

            $dataInfo = array("LOE" => $now, "DEO" => $deo, "DEBE" => $debe, "SALDO" => $saldo);
            $this->where(array('NOMOR'=> $nofak, 'CUSTOM'=>$pelanggan));
            $result = $this->update($dataInfo, 'PUTANG');
        }
        return $result;
    }

    function updateNourut($where, $table){
        extract($where);

        $query = "UPDATE $table SET NOURUT = NOURUT-1 WHERE NOMOR ='$nofak' AND NOURUT > '$nourut'";
        $result = $this->query($query);

        $query = "UPDATE $table SET NOURUT = RIGHT('00000'+NOURUT, 5) WHERE LEN(NOURUT) < 5";
        $result = $this->query($query);
    }

    function cekPelunasan ($where){
        extract($where);
        $qry = "SELECT NOMOR FROM PUTANG WHERE CBXX = $cabang AND NOXX = '$nofak' AND CUSTOM = '$pelanggan' AND LEFT(NOMOR,2) = 'DB'";
        $data = $this->query($qry)->fetchAll(PDO::FETCH_ASSOC);

        $result = count($data);

        return $result;
    }

    function valid($nofak,$data){
        $posj = $data->posj == true ? "V" : "";
        $dataP = array("POSJ" => $posj);
        
        $where = array(
            "NOMOR" => $nofak
        );
        
        $this->where($where);
        $result = $this->update($dataP, 'JLHD');
        return true;
    }

    function getImei($data, $search=false){
        //PAGINATION
        // $perpage = $this->perpage;
        $perpage = 30;
        $page = isset($data->page) ? $data->page : 0;
        $offset = $page > 1 ? (($page * $perpage) - $perpage)+1 : 1;
        $offset2 = ($offset + $perpage) - 1;
        $nofak = $data->nofak;
        $nokey = $data->nokey;
        $gudang = $data->gudang;
        $group = $data->group;
        $barang = $data->barang;

        $totalPage = 0;
        $keyword = '';

        if ($search) {
            $keyword = $data->keyword == '' ? '' : "AND IMEI LIKE '%$data->keyword%'";
        }

        $sql = "SELECT * FROM (
                    SELECT IMEI, HARGA, ROW_NUMBER() OVER (ORDER BY IMEI ASC) AS ROWNUM 
                    FROM IMEIX 
                    WHERE NOMOR = '$nofak' AND NOKEY = '$nokey' AND GUDANG = '$gudang' AND BARANG = '$barang' AND [GROUP] = '$group' AND FLAG = 7 $keyword
                ) AS DATA
                WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";
        // echo $sql;
        $brg = $this->query($sql);
        $list = $brg->fetchAll(PDO::FETCH_ASSOC);

        if($page=='1'){
            $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT IMEI, HARGA, ROW_NUMBER() OVER (ORDER BY IMEI ASC) AS ROWNUM 
                        FROM IMEIX 
                        WHERE NOMOR = '$nofak' AND NOKEY = '$nokey' AND GUDANG = '$gudang' AND BARANG = '$barang' AND [GROUP] = '$group' AND FLAG = 7 $keyword
                    ) AS DATA";
            $total = $this->query($sql);
            $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

            $totalPage = $totaldata['TOTALDATA'] / $perpage;
        }

        return ["list"=>$list, "total"=>$totalPage];
    }

}