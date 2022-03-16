<?php

class Kas_harian extends My_db{
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
		$dbmaster = $_SESSION['dbname']."SHRDBF";
		$cb = $data->cabang == '' ? '' : "AND KAS.CB = '$data->cabang'";
		// $tgl = $data->tanggal == '' ? '' : "AND TANGGAL <= '$data->tanggal'";
		$tgl = $data->tanggal == '' && $data->tanggal2 == '' ? '' : "AND TANGGAL BETWEEN '$data->tanggal' AND '$data->tanggal2'";
		$tbayar = $data->tbayar == '' ? '' : "AND TBAYAR = '$data->tbayar'";
        
        $sql = "SELECT 
					'PENERIMAAN / PEMBAYARAN DARI PELANGGAN' AS KETERANGAN,TBAYAR.NAMA,
					SUM(KAS.BAYAR) AS TOTALHARGA,1 AS URUT 
				FROM 
					PPTGDT AS KAS,$dbmaster..TBAYAR AS TBAYAR
				WHERE
					KAS.TBAYAR=TBAYAR.KODE $cb $tbayar $tgl
				GROUP BY 
					TBAYAR.NAMA

				UNION ALL

				SELECT 
					'UANG MUKA PENJUALAN DARI PELANGGAN' AS KETERANGAN,
					TBAYAR.NAMA,SUM(KAS.SALDO) AS TOTALHARGA,2 AS URUT 
				FROM 
					UPTG AS KAS,$dbmaster..TBAYAR AS TBAYAR
				WHERE
					KAS.TBAYAR=TBAYAR.KODE $cb $tbayar $tgl
				GROUP BY 
					TBAYAR.NAMA

				UNION ALL

				SELECT 
					'PEMBAYARAN HUTANG / PENGELUARAN BIAYA' AS KETERANGAN,
					TBAYAR.NAMA,-SUM(KAS.BAYAR) AS TOTALHARGA,3 AS URUT 
				FROM 
					PHTGDT AS KAS,$dbmaster..TBAYAR AS TBAYAR
				WHERE
					KAS.TBAYAR=TBAYAR.KODE $cb $tbayar $tgl
				GROUP BY 
					TBAYAR.NAMA

				UNION ALL

				SELECT 
					'UANG MUKA PEMBELIAN KE SUPPLIER' AS KETERANGAN,
					TBAYAR.NAMA,-SUM(KAS.SALDO) AS TOTALHARGA,4 AS URUT 
				FROM 
					UHTG AS KAS,$dbmaster..TBAYAR AS TBAYAR
				WHERE
					KAS.TBAYAR=TBAYAR.KODE $cb $tbayar $tgl
				GROUP BY 
					TBAYAR.NAMA

				ORDER BY 
					URUT,KETERANGAN,TBAYAR.NAMA";
				

        //echo $sql;

        $harian = $this->query($sql);

        return $harian->fetchAll(PDO::FETCH_ASSOC);
    }
}
