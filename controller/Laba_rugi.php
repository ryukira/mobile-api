<?php

class Laba_rugi extends My_db{
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

        $cb = $data->cabang == '' ? '' : "AND CB = '$data->cabang'";
        $gd = $data->gudang == '' ? '' : "AND GUDANG = '$data->gudang'";
        $barang = $data->barang == '' ? '' : "AND G.INISIAL LIKE '%$data->barang%' ";
        $tgl = $data->tanggal == '' && $data->tanggal2 == '' ? '' : "AND TANGGAL BETWEEN '$data->tanggal' AND '$data->tanggal2'";

        $trans = $data->transaksi;
        
        if($trans == 'penjualan'){
            $transaksi = "AND (P.FLAG='7' AND LEFT(P.NOMOR,2)='SI')";
        } elseif($trans == 'retur'){
            $transaksi = "AND (P.FLAG='8' AND LEFT(P.NOMOR,2)='PR')";
        }elseif($trans == 'adjustment'){
            $transaksi = "AND (P.FLAG='9' AND LEFT(P.NOMOR,2)='XP')";
        }else{
            $transaksi = "AND (P.FLAG='7' AND LEFT(P.NOMOR,2)='SI')";
        }

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
            case 'tanggal':
                if ($search) {
                    $keyword = date('Y-m-d', strtotime($data->keyword));
                    $keyword = $data->keyword == '' ? '' : "AND P.TANGGAL = '$keyword'";
                }

                $sql = "SELECT P.TANGGAL AS REKAP, 
                        SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,
                        SUM(P.FIFO) AS HPP, 
                        SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR
                        FROM PROD3 P, $dbmaster..[GROUP] AS G ";
                $sql .= " WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE $cb $gd $barang $tgl $transaksi $keyword ";
                $sql .= " GROUP BY P.TANGGAL ORDER BY P.TANGGAL DESC";

                $datatgl = $this->query($sql);
                $list = $datatgl->fetchAll(PDO::FETCH_ASSOC);

                $sql = "SELECT 
                            SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,
                        SUM(P.FIFO) AS HPP, 
                        SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR
                        FROM PROD3 P, $dbmaster..[GROUP] AS G 
                        WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE $cb $gd $barang $tgl $transaksi";

                $totHrg = $this->query($sql);
                $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));
                
                break;
            case 'pelanggan':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND M.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT * FROM (
                            SELECT M.NAMA AS REKAP,
                            SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,SUM(P.FIFO) AS HPP,
                            SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR, ROW_NUMBER() OVER (ORDER BY M.NAMA ASC) AS ROWNUM
                            FROM PROD3 P,CUSTOM M, $dbmaster..[GROUP] AS G 
                            WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE AND P.CUSTOM=M.KODE $cb $gd $barang $tgl $transaksi $keyword 
                            GROUP BY M.NAMA
                        )AS DATA
                        WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

                $pelanggan = $this->query($sql);
                $list = $pelanggan->fetchAll(PDO::FETCH_ASSOC);

                if ($page == 1){
                    $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                                SELECT M.NAMA AS REKAP,
                                SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,SUM(P.FIFO) AS HPP,
                                SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR, ROW_NUMBER() OVER (ORDER BY M.NAMA ASC) AS ROWNUM
                                FROM PROD3 P,CUSTOM M, $dbmaster..[GROUP] AS G 
                                WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE AND P.CUSTOM=M.KODE $cb $gd $barang $tgl $transaksi $keyword 
                                GROUP BY M.NAMA
                            )AS DATA";

                    $total = $this->query($sql);
                    $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                    $sql = "SELECT 
                                SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,
                                SUM(P.FIFO) AS HPP,
                                SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR
                            FROM PROD3 P, $dbmaster..[GROUP] AS G 
                            WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE $cb $gd $barang $tgl $transaksi";

                    $totHrg = $this->query($sql);
                    $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                    // $totHrg = ['totPenjualan'=>$totHrgPlg['PENJUALAN'], 'totHpp'=>$totHrgPlg['HPP'], 'totLr'=>$totHrgPlg['LR']];

                    $totalPage = $totaldata['TOTALDATA'] / $perpage;
                }
                break;

            case 'salesman':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND M.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT * FROM (
                            SELECT M.NAMA AS REKAP,
                            SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,SUM(P.FIFO) AS HPP,
                            SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR, ROW_NUMBER() OVER (ORDER BY M.NAMA ASC) AS ROWNUM
                            FROM PROD3 P, $dbmaster..SALESM M, $dbmaster..[GROUP] AS G 
                            WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE AND P.SALESM=M.KODE $cb $gd $barang $tgl $transaksi $keyword 
                            GROUP BY M.NAMA
                        )AS DATA
                        WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

                $datasales = $this->query($sql);
                $list = $datasales->fetchAll(PDO::FETCH_ASSOC);

                if ($page == 1){
                    $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                                SELECT M.NAMA AS REKAP,
                                SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,
                                SUM(P.FIFO) AS HPP,
                                SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR, ROW_NUMBER() OVER (ORDER BY M.NAMA ASC) AS ROWNUM
                                FROM PROD3 P, $dbmaster..SALESM M, $dbmaster..[GROUP] AS G 
                                WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE AND P.SALESM=M.KODE $cb $gd $barang $tgl $transaksi $keyword 
                                GROUP BY M.NAMA
                            )AS DATA";

                    $total = $this->query($sql);
                    $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                    $sql = "SELECT 
                                SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,
                                SUM(P.FIFO) AS HPP,
                                SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR
                            FROM PROD3 P, $dbmaster..[GROUP] AS G 
                            WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE $cb $gd $barang $tgl $transaksi";

                    $totHrg = $this->query($sql);
                    $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                    // $totHrg = ['totPenjualan'=>$totHrgPlg['PENJUALAN'], 'totHpp'=>$totHrgPlg['HPP'], 'totLr'=>$totHrgPlg['LR']];

                    $totalPage = $totaldata['TOTALDATA'] / $perpage;
                }
                break;
            case 'barang':
                if ($search) {
                    $keyword = $data->keyword == '' ? '' : "AND M.NAMA LIKE '%$data->keyword%'";
                }

                $sql = "SELECT * FROM (
                            SELECT M.NAMA AS REKAP,
                            SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,SUM(P.FIFO) AS HPP,
                            SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR, ROW_NUMBER() OVER (ORDER BY M.NAMA ASC) AS ROWNUM
                            FROM PROD3 P,PROD1 M, $dbmaster..[GROUP] AS G 
                            WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE AND P.[GROUP]+P.BARANG=M.[GROUP]+M.KODE $cb $gd $barang $tgl $transaksi $keyword 
                            GROUP BY M.NAMA
                        )AS DATA
                        WHERE ROWNUM >= $offset AND ROWNUM<= $offset2";

                $databarang = $this->query($sql);
                $list = $databarang->fetchAll(PDO::FETCH_ASSOC);

                if ($page == 1){
                    $sql = "SELECT COUNT(*) AS TOTALDATA FROM (
                                SELECT M.NAMA AS REKAP,
                                SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,
                                SUM(P.FIFO) AS HPP,
                                SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR, ROW_NUMBER() OVER (ORDER BY M.NAMA ASC) AS ROWNUM
                                FROM PROD3 P,PROD1 M, $dbmaster..[GROUP] AS G 
                                WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE AND P.[GROUP]+P.BARANG=M.[GROUP]+M.KODE $cb $gd $barang $tgl $transaksi $keyword 
                                GROUP BY M.NAMA
                            )AS DATA";

                    $total = $this->query($sql);
                    $totaldata = current($total->fetchAll(PDO::FETCH_ASSOC));

                    $sql = "SELECT 
                                SUM(P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS) AS PENJUALAN,
                                SUM(P.FIFO) AS HPP,
                                SUM((P.QTY*(P.HARGA-P.DISCN+P.BIAYA+P.TAXN+P.LAIN)*P.KURS)-P.FIFO) AS LR
                            FROM PROD3 P, $dbmaster..[GROUP] AS G 
                            WHERE P.NOMOR <> '' AND P.[GROUP]=G.KODE $cb $gd $barang $tgl $transaksi";

                    $totHrg = $this->query($sql);
                    $totHrg = current($totHrg->fetchAll(PDO::FETCH_ASSOC));

                    // $totHrg = ['totPenjualan'=>$totHrgPlg['PENJUALAN'], 'totHpp'=>$totHrgPlg['HPP'], 'totLr'=>$totHrgPlg['LR']];

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

        
    }
}
