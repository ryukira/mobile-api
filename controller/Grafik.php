<?php

class Grafik extends My_db{
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
        if($data->grafik != 'stok' && $data->grafik != 'overview') {
            $tgl = $data->tanggal;
            $thn = substr($_SESSION['periode'], 0,2)+2000;
            $bln = substr($_SESSION['periode'], -2);
            $tgl = explode('-', $tgl);

            $awal = $thn.'-'.$bln.'-'.$tgl[0];
            $akhir = $thn.'-'.$bln.'-'.$tgl[1];

        }
        switch ($data->grafik) {
            case 'stok':
                $result = $this->grafikStok();
                break;          
            case 'penjualan':
                $result = $this->grafikPenjualan($awal,$akhir);
                break;
            case 'pembelian':
                $result = $this->grafikPembelian($awal,$akhir);
                break;  
            case 'laba/rugi':
                $result = $this->grafikLabaRugi($awal, $akhir);
                break;       
            case 'overview':
                $result = $this->grafikOverview();
                break;          
            default:
                # code...
            $result=[];
                break;
        }
        return $result;
    }

    function grafikStok(){
        $sql1 = "SELECT ISNULL(SUM(ISNULL(QTY*(HARGA-DISCN+BIAYA+TAXN+LAIN),0)*KURS),0) AS TOTALHARGA FROM PROD3 
                WHERE FLAG=1 AND TIPE != '3'";
        $stok = $this->query($sql1);
        $stok = current($stok->fetchAll(PDO::FETCH_ASSOC));

        $sql1 = "SELECT ISNULL(SUM(ISNULL(QTY*(HARGA-DISCN+BIAYA+TAXN+LAIN), 0)*KURS),0) AS TOTALHARGA FROM PROD3 
                WHERE FLAG>=2 AND FLAG <= 5 AND TIPE != '3'";
        $pb = $this->query($sql1);
        $pembelian = current($pb->fetchAll(PDO::FETCH_ASSOC));

        $sql1 = "SELECT ISNULL(SUM(ISNULL(FIFO, 0)),0) AS TOTALHARGA FROM PROD3 
                WHERE FLAG>=6 AND FLAG <= 9";
        $hpp = $this->query($sql1);
        $hpp =current($hpp->fetchAll(PDO::FETCH_ASSOC));

        $akhir = $stok['TOTALHARGA'] + $pembelian['TOTALHARGA'] - $hpp['TOTALHARGA'];

        $data = array(
            "stokawal" => $stok['TOTALHARGA'],
            "pembelian" => $pembelian['TOTALHARGA'],
            "hpp"=> $hpp['TOTALHARGA'],
            "stokakhir" => $akhir
        );
        return $data;
    }

    function grafikPenjualan($awal, $akhir){
        $penjualan = "SELECT CONVERT(VARCHAR(10), TANGGAL, 105) AS TANGGAL, ISNULL(SUM(HRGNET*KURS),0) AS TOTALHARGA
                        FROM JLHD 
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir' 
                        GROUP BY TANGGAL ORDER BY TANGGAL ASC";
        // echo $penjualan;
        $penjualan = $this->query($penjualan);
        $penjualan = $penjualan->fetchAll(PDO::FETCH_ASSOC);

        $putang = "SELECT CONVERT(VARCHAR(10), TANGGAL, 105) AS TANGGAL, ISNULL(SUM(SALDO*KURS),0) AS TOTALHARGA 
                        FROM PUTANG
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir' AND NOMOR = NOXX AND LEFT(NOMOR,2) != 'DP'
                        GROUP BY TANGGAL ORDER BY TANGGAL ASC ";
        $putang = $this->query($putang);
        $putang = $putang->fetchAll(PDO::FETCH_ASSOC);


        $data = array(
            "data1" => $penjualan,
            "data2" => $putang
        );


        return $data;
    }

    function grafikPembelian($awal, $akhir){
        $pembelian = "SELECT CONVERT(VARCHAR(10), TANGGAL, 105) AS TANGGAL, ISNULL(SUM(HRGNET*KURS),0) AS TOTALHARGA
                        FROM BLHD 
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir' 
                        GROUP BY TANGGAL ORDER BY TANGGAL ASC";
        $pembelian = $this->query($pembelian);
        $pembelian = $pembelian->fetchAll(PDO::FETCH_ASSOC);

        $hutang = "SELECT CONVERT(VARCHAR(10), TANGGAL, 105) AS TANGGAL, ISNULL(SUM(SALDO*KURS),0) AS TOTALHARGA 
                        FROM HUTANG
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir' AND NOMOR = NOXX AND LEFT(NOMOR,2) != 'PD'
                        GROUP BY TANGGAL ORDER BY TANGGAL ASC ";
        $hutang = $this->query($hutang);
        $hutang = $hutang->fetchAll(PDO::FETCH_ASSOC);


        $data = array(
            "data1" => $pembelian,
            "data2" => $hutang
        );

        return $data;
    }

    function grafikLabaRugi($awal, $akhir){
        $penjualan = "SELECT 
                            CONVERT(VARCHAR(10), P.TANGGAL, 105) AS TANGGAL,
                            ISNULL(SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*KURS),0) AS TOTALHARGA
                        FROM 
                            PROD3 P
                        WHERE P.TANGGAL BETWEEN '$awal' AND '$akhir' AND LEFT(NOMOR,2) = 'SI'
                        GROUP BY P.TANGGAL ORDER BY TANGGAL ASC";
        $penjualan = $this->query($penjualan);
        $penjualan = $penjualan->fetchAll(PDO::FETCH_ASSOC);

        $hpp = "SELECT 
                            CONVERT(VARCHAR(10), P.TANGGAL, 105) AS TANGGAL, ISNULL(SUM(P.FIFO),0) AS TOTALHARGA
                        FROM 
                            PROD3 P
                        WHERE P.TANGGAL BETWEEN '$awal' AND '$akhir' 
                        GROUP BY P.TANGGAL ORDER BY TANGGAL ASC";
        $hpp = $this->query($hpp);
        $hpp = $hpp->fetchAll(PDO::FETCH_ASSOC);


        $data = array(
            "data1" => $penjualan,
            "data2" => $hpp
        );

        return $data;
    }

    
    function grafikOverview (){
        $dbmaster = $_SESSION['dbname']."SHRDBF";
        
        $bulan = substr($_SESSION['periode'], 2,2);
        $tahun = 2000 + substr($_SESSION['periode'], 0,2);
        $awal = date('Y-m-d', strtotime($tahun."-".$bulan."-01"));
        $akhir = date('Y-m-t', strtotime($awal));

        // grafik penjualan
        $penjualan = "SELECT ISNULL(SUM(ISNULL(HRGNET,0) * KURS),0) AS TOTALHARGA
                        FROM JLHD 
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir' ";
        // echo $penjualan;
        $penjualan = $this->query($penjualan);
        $penjualan = current($penjualan->fetchAll(PDO::FETCH_ASSOC));

        $rPenjualan = "SELECT ISNULL(SUM(ISNULL(HRGNET,0) * KURS),0) AS TOTALHARGA
                        FROM RJLHD 
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir' ";
        $rPenjualan = $this->query($rPenjualan);
        $rPenjualan = current($rPenjualan->fetchAll(PDO::FETCH_ASSOC));

        $grafikPenjualan = $penjualan['TOTALHARGA'] - $rPenjualan['TOTALHARGA'];


        // grafik pembelian
        $pembelian = "SELECT ISNULL(SUM(ISNULL(HRGNET,0) * KURS),0) AS TOTALHARGA
                        FROM BLHD 
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir' ";
        // echo $pembelian;
        $pembelian = $this->query($pembelian);
        $pembelian = current($pembelian->fetchAll(PDO::FETCH_ASSOC));

        $rPembelian = "SELECT ISNULL(SUM(ISNULL(HRGNET,0) * KURS),0) AS TOTALHARGA
                        FROM RBLHD 
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir' ";
        $rPembelian = $this->query($rPembelian);
        $rPembelian = current($rPembelian->fetchAll(PDO::FETCH_ASSOC));

        $grafikPembelian = $pembelian['TOTALHARGA'] - $rPembelian['TOTALHARGA'];


        // piutang
        $putang = "SELECT ISNULL(SUM(ISNULL(SALDO,0) * KURS),0) AS TOTALHARGA 
                        FROM PUTANG
                        WHERE TANGGAL <= '$akhir' AND NOMOR = NOXX AND LEFT(NOMOR,2) != 'DP'";
        $putang = $this->query($putang);
        $putang = current($putang->fetchAll(PDO::FETCH_ASSOC));

        // pembayaran hutang
        $p_putang = "SELECT ISNULL(SUM(ISNULL(BAYAR+DISCN,0) * KURS),0) AS TOTALHARGA 
                        FROM PPTGDT
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir'";
        $p_putang = $this->query($p_putang);
        $p_putang = current($p_putang->fetchAll(PDO::FETCH_ASSOC));


        // hutang
        $hutang = "SELECT ISNULL(SUM(ISNULL(SALDO,0) * KURS),0) AS TOTALHARGA 
                        FROM HUTANG
                        WHERE TANGGAL <= '$akhir' AND NOMOR = NOXX AND LEFT(NOMOR,2) != 'PD' 
                        AND VENDOR IN (SELECT KODE FROM VENDOR) AND LOKASI != ''";
        $hutang = $this->query($hutang);
        $hutang = current($hutang->fetchAll(PDO::FETCH_ASSOC));

        // pembayaran hutang
        $p_hutang = "SELECT ISNULL(SUM(ISNULL(BAYAR+DISCN,0) * KURS),0) AS TOTALHARGA 
                        FROM PHTGDT
                        WHERE TANGGAL BETWEEN '$awal' AND '$akhir'";
        $p_hutang = $this->query($p_hutang);
        $p_hutang = current($p_hutang->fetchAll(PDO::FETCH_ASSOC));

        $stok = $this->grafikStok()['stokakhir'];

        $sql = "SELECT ISNULL(SUM(ISNULL((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)* KURS)-P.FIFO,0)),0) AS LR
                     FROM PROD3 P WHERE LEFT(NOMOR,2)='SI' AND FLAG='7' ";
        $lr = $this->query($sql);
        $lr = current($lr->fetchAll(PDO::FETCH_ASSOC));

        return array(
                        'penjualan' => $grafikPenjualan,
                        'pembelian' => $grafikPembelian,
                        'piutang'   => $putang['TOTALHARGA'],
                        'hutang'   => $hutang['TOTALHARGA'],
                        'p_hutang'   => $p_hutang['TOTALHARGA'],
                        'p_putang'   => $p_putang['TOTALHARGA'],
                        'stok'      => $stok,
                        'lr'        => $lr['LR']
                    );

    }
}
