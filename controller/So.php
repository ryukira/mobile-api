<?php

class So extends My_db{
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
            switch ($data->fn) {
                case 'soDetail':
                    $result = $this->getDetail($data);
                    break;
                case 'sendEmail':
                    $data->message='Faktur Order Penjualan';
                    $data->subject='Faktur SO '.date('d-m-Y');
                    $result = $this->email($data);
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
        // $perpage = $this->perpage;
        $perpage = 8;
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
                    SELECT H.POSJ, H.ACC, H.NOMOR, H.TANGGAL, H.NOREF, (H.QTY-H.QTZ) AS OUTS, C.NAMA AS NAMACUSTOM, H.HRGNET AS HARGANET, CB.NAMA AS NAMACABANG, S.NAMA AS SALESMAN, ROW_NUMBER() OVER (ORDER BY H.NOMOR DESC) AS ROWNUM
                    FROM SOHD H, $dbmaster..SALESM S, $dbmaster..CABANG CB, CUSTOM C
                    WHERE S.KODE = H.SALESM AND CB.KODE = H.CB AND C.KODE = H.CUSTOM $cb $pelanggan $salesman $outs  $keyword
                    GROUP BY H.TANGGAL,C.NAMA, S.NAMA, H.NOMOR, H.QTY, H.QTZ, H.HRGNET, CB.NAMA, H.NOREF, H.POSJ, H.ACC
                ) AS DATA
                WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

        $faktur = $this->query($sql);
        $list = $faktur->fetchAll(PDO::FETCH_ASSOC);

        if ($page == 1) {
            $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                        SELECT H.POSJ, H.ACC, H.NOMOR, H.TANGGAL, H.NOREF, (H.QTY-H.QTZ) AS OUTS, C.NAMA AS NAMACUSTOM, H.HRGNET AS HARGANET, CB.NAMA AS NAMACABANG, S.NAMA AS SALESMAN, ROW_NUMBER() OVER (ORDER BY H.NOMOR DESC) AS ROWNUM
                        FROM SOHD H, $dbmaster..SALESM S, $dbmaster..CABANG CB, CUSTOM C
                        WHERE S.KODE = H.SALESM AND CB.KODE = H.CB AND C.KODE = H.CUSTOM $cb $pelanggan $salesman $outs  $keyword
                        GROUP BY H.TANGGAL,C.NAMA, S.NAMA, H.NOMOR, H.QTY, H.QTZ, H.HRGNET, CB.NAMA, H.NOREF, H.POSJ, H.ACC
                    ) AS DATA";

            $total = $this->query($sql);
            $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

            $sql = "SELECT  
                    SUM(H.HRGNET) AS TOTALHARGA
                    FROM SOHD H, $dbmaster..SALESM S, $dbmaster..CABANG CB, CUSTOM C
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
        $sql = "SELECT S.NOMOR, S.NOMORCB, S.CB, S.DOE, S.NOREF, S.TANGGAL, S.TERM, S.TGLJTP,
                    S.CUSTOM, S.SALESM, V.NAMA AS NAMASALESM, C.NAMA AS NAMACUSTOM, 
                    S.TAXP, S.TAXN, S.BIAYA, S.DISCH, S.DISCN, S.DISCD, S.HRGNET, S.HRGTOT, 
                    S.KET1, S.KET3, S.KET4, CB.ALAMAT1 AS C_ALAMAT1, CB.ALAMAT2 AS C_ALAMAT2,
                    CB.TELP AS C_TELP, CB.NAMA AS NAMACABANG, CB.GAMBAR, C.ALAMAT1, 
                    C.ALAMAT2, C.TELP, S.QTZ , S.POSJ,S.ACC
                FROM SOHD S
                    LEFT JOIN $dbmaster..SALESM V ON S.SALESM = V.KODE
                    LEFT JOIN CUSTOM C ON S.CUSTOM = C.KODE
                    LEFT JOIN $dbmaster..CABANG CB ON CB.KODE = S.CB
                WHERE NOMOR='$param->nofak'";
        $data['header'] = current($this->query($sql)->fetchAll(PDO::FETCH_ASSOC));


        $detail = "SELECT S.NOURUT, S.GUDANG, S.BARANG, S.QTY, S.SAT, S.HARGA, S.DISC1, S.[GROUP], G.NAMA AS NAMAGUDANG, P.
                        NAMA AS NAMABARANG, S.DISCN, S.BIAYA, S.TAXN, G.KODE AS KODEGUDANG
                   FROM SODT S
                        JOIN $dbmaster..GUDANG G ON S.GUDANG = G.KODE 
                        JOIN PROD1 P ON S.BARANG = P.KODE AND S.[GROUP] = P.[GROUP]
                   WHERE NOMOR = '$param->nofak'";

        $data['detail'] = $this->query($detail)->fetchAll(PDO::FETCH_ASSOC);

    
        return $data;
    }

    function post($data){
        if ($data->nofak == '') {
            $nofak = $this->insertHeader($data->header);
            $result = $this->insertDetail($data, $nofak);
        }
        else{
            $result = $this->insertDetail($data, $data->nofak);
        }
        $res['error']='';
        $res['status']=$result;

        return $res;
    }

    function insertHeader($data){
        $cb = $data->cabang;
        $pelanggan = $data->pelanggan;
        $salesman = $data->salesman;
        $noref = $data->noref;
        $nofak = $this->getNoFak($data->tempnofak, 'SOHD');
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
            'CB'=>$cb
        );

        // print_r($data);
        $result = $this->insert($data, 'SOHD');

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
        $nourut = $this->getNoUrut($nofak, 'SODT');
        $nokey = $this->getNoKey($nofak, 'SODT');
        $discd = $data->hargaDiskon;
        $disc1 = $data->diskon;
        $qty = $data->qty;
        $nocab = $nofak;
        $deo = $_SESSION['userid'];
        $now = date('Y-m-d H:i:s');
        $noref = $data->header->noref;

        $dataD = array(
            'CABANG' => '01',
            'NOMOR'=> $nofak,
            'NOMORCB' => $nocab,
            'NOURUT' => $nourut,
            'NOKEY' => $nokey,
            'CBXX' => $cabang,
            'NOXX' => $nofak,
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
            'HGPAK' => $harga
        );

        $dataDetail = $this->getDetailTransaksi($nofak, 'SODT');

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


        $result = $this->insert($dataD, 'SODT');

        $where3 = array("KODE" => $barang, "[GROUP]"=>$group);
        $this->where($where3);
        $this->update(["PAKAI"=>"Y"], 'PROD1');

        if($result){
            $where = array("NOMOR"=>$nofak);
            $this->where($where);
            $result2 = $this->update($dataHeader, 'SOHD');
            $status = array("nofak"=>$nofak);
        }
        else{
            $status = null;
        }

        // print_r($result);
        return $status;
    }

    function getDetail ($data){
        $dbmaster = $_SESSION['dbname']."SHRDBF";
        $nofak = $data->nofak;

        $sql = "SELECT S.NOURUT, S.QTY, S.SAT, S.HARGA, S.HGPAK, S.HTG, S.NOURUT, S.DISCD, S.DISC1, G.KODE AS KODEGUDANG, ((S.HARGA - S.DISCN + S.TAXN + S.BIAYA) * S.QTY) AS TOTALHARGA, S.[GROUP], P.NAMA AS NAMABARANG, P.KODE AS KODEBARANG,  G.NAMA AS GUDANG 
                FROM SODT S, PROD1 P, $dbmaster..GUDANG G
                WHERE S.BARANG+S.[GROUP] = P.KODE+P.[GROUP] AND S.GUDANG=G.KODE AND S.NOMOR = '$nofak'";

        if (isset($data->nourut) && $data->nourut !== '') {
            $sql .= " AND NOURUT='".$data->nourut."'";
        }
        
        $sql .=" ORDER BY S.NOURUT";

        $dt = $this->query($sql);
        $detail = $dt->fetchAll(PDO::FETCH_ASSOC);

        if (isset($data->nourut) && $data->nourut !== '') {
            return array('detail'=>$detail);
        }

        $sql = "SELECT HRGTOT, HRGNET, QTY, DISCD, QTZ FROM SOHD WHERE NOMOR='$nofak'";
        $hd = $this->query($sql);
        $header = current($hd->fetchAll(PDO::FETCH_ASSOC));

        return array('detail'=> $detail, 'header'=> $header);

    }

    function updateData($id, $data){
        if (!isset($data->fn)) {
            $result = $this->updateHeader($id, $data);
        }
        else{
            if($data->fn=='valid'){
                $res['status'] = $this->valid($id,$data);
                $res['error']='';
                return $res;
            }
            $result = $this->updateDetail($id, $data);
        }
        $res['error']='';
        $res['status']=$result;

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
        
        $result = $this->update($data, 'SOHD');

        if ($result) {
            $dataDetail = $this->getDetailTransaksi($nofak, 'SODT');
            
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
                $this->update($dataD, 'SODT');

            }

            return true;
        }

        return false;
    }


    function updateDetail($nofak, $data){

        $nofak = $nofak;
        $nourut = $data->nourut;

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
        $nokey = $this->getNoKey($nofak, 'SODT');
        $discd = $data->hargaDiskon;
        $disc1 = $data->diskon;
        $qty = $data->qty;
        $nocab = $nofak;
        $deo = $_SESSION['userid'];
        $now = date('Y-m-d H:i:s');
        
        $dataDetail = current($this->getDetailTransaksi($nofak, 'SODT', true, $nourut));
        
        $diskonH = $data->header->diskon;
        $hrgtotH = $data->header->hrgtot;
        $ppnH = $data->header->hrgPpn;
        $ongkosH = $data->header->ongkos;

        $disch    = $qty > 0 && $diskonH > 0 ? ($diskonH / $hrgtotH) * ($harga-$discd) : 0;

        $discn = $discd + $disch;
        $ppnD    = $qty > 0 && $ppnH > 0 ? $ppnH / ($hrgtotH-$diskonH) * ($harga-$discn) : 0;

        $ongkosD    = $qty > 0 && $ongkosH > 0 ? $ongkosH / ($hrgtotH-$diskonH + $ppnH) * ($harga-$discn+$ppnD) : 0;


        $dataD = array(
            'GUDANG' => $gd,
            '[GROUP]' => $group,
            'BARANG' => $barang,
            'QTY' => $qty,
            'QTX' => 0,
            'QTZ' => 0,
            'SAT' => $satuan,
            'HARGA'=> $harga,
            'DISC1' => $disc1,
            'DISCD' => $discd,
            'DISCN' => $discn,
            'TOE' => date('H:i:s'),
            'LOE' => $now,
            'DEO'=> $deo,
            'PAK' => $satuan,
            'HGPAK' => $harga,
            'TAXN' => $ppnD,
            'BIAYA' => $ongkosD,
            'DISCH' => $disch
        );


        $whereDetail = array(
            "NOMOR"=>$nofak,
            "NOURUT"=>$nourut
        );
        $this->where($whereDetail);
        $result = $this->update($dataD, 'SODT');

        $dataDetail = $this->getDetailTransaksi($nofak, 'SODT', false, $nourut);
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
            $where = array("NOMOR"=>$nofak);
            $this->where($where);
            $result2 = $this->update($dataHeader, 'SOHD');
            $status = array("nofak"=>$nofak);
        }
        else{
            $status = null;
        }

        // print_r($result);
        return $status;
    }

    function deleteData($id, $data){
        if (!isset($data->nourut)) {
            $result = $this->deleteHeader($id);
        }
        else{
            $result = $this->deleteDetail($id, $data);
        }

        $res['status']=$result;
        $res['error']='';
        return $res;
    }

    function deleteHeader($nofak){

        $detail = $this->getDetailTransaksi($nofak, 'SODT');

        if (count($detail) > 0) {
            return false;
        }

        $where = array(
            "NOMOR" => $nofak
        );
        $this->where($where);
        
        $result = $this->delete('SOHD');
        return true;
    }

    function deleteDetail($nofak, $data){
        $dataD = current($this->getDetailTransaksi($nofak, 'SODT', true, $data->nourut));
        $qtyD = $dataD['QTY'];
        $hrgDetail = $dataD['HARGA'] * $qtyD;


        $dataHeader  = "SELECT HRGTOT, QTY, DISCD, DISCH, TAXN, TAXP, BIAYA, HRGNET FROM SOHD WHERE NOMOR = '$nofak'";
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

        // print_r($dataH);

        $where = array(
            "NOMOR" => $nofak
        );
        $this->where($where);
        $result = $this->update($dataH, 'SOHD');

        //DELETE DATA DETAIL
        $where = array(
            "NOMOR" => $nofak,
            "NOURUT" => $data->nourut
        );
        $this->where($where);
        $result = $this->delete('SODT');
        if ($result) {

            //UPDATE DATA DETAIL
            $dataDetail = $this->getDetailTransaksi($nofak, 'SODT');
            $disch     = $qtyHeader>0 ?  $header['DISCH'] / $qtyHeader : 0;
            $ongkosD   = $qtyHeader>0 ? $header['BIAYA'] / $qtyHeader : 0;
            $ppnD      = $qtyHeader>0 ? $taxnHeader / $qtyHeader :0;

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
                $this->update($dataD, 'SODT');

            }
            
            $query = "UPDATE SODT SET NOURUT = NOURUT-1 WHERE NOMOR ='$nofak' AND NOURUT > '$data->nourut'";
            $result = $this->query($query);

            $query = "UPDATE SODT SET NOURUT = RIGHT('00000'+NOURUT, 5) WHERE LEN(NOURUT) < 5";
            $result = $this->query($query);

            return true;
        }
        return false;
    }

    function valid($nofak,$data){
        $posj = $data->posj == true ? "V" : "";
        $dataP = array("POSJ" => $posj);

        $where = array(
            "NOMOR" => $nofak
        );

        $this->where($where);
        $result = $this->update($dataP, 'SOHD');
        return true;
    }

}
