<?php
use Dompdf\Dompdf;
require_once("dompdf/autoload.inc.php");

$nama = 'fahmi';
$alamat = 'dsdsd';


$html =
  '<style type="text/css">*{       font-family: courier new, monospace;font-size:11px;}.barang{    margin-top: 15px;       width: 100%;    border-collapse: collapse;}#fakturheader tr td, .barang tr td{        padding: 0 !important;}.barang thead tr th{     padding: 5px;   border-top: 1px solid black;    border-bottom: 1px solid black;}.barang tbody tr td{  text-align: left;}.barang tbody tr:nth-last-child(1){   height: 20px;   vertical-align: text-top;}.barang tfoot tr:nth-child(1) td{border-top: 1px solid black;}.barang tfoot tr:nth-last-child(1) td{        border-bottom: 1px solid black;}#bersambung{display: block;text-align:right;}@page{size:cal(21.65*100) cal(13.97*100);}</style><table width="90%" style="text-align: left" height="100px" style="margin-top:0px !important; font-family:Monospace"><tr><th colspan="2">ORDER PENJUALAN</th><th>21 err 2019</th></tr><tr><th colspan="2">PT SHAN INFORMASI SYSTEM</th><th>FOX ACCESEORIES</th></tr><tr><td colspan="2">RUKAN MALIBU BLOK J NO 76</td><td></td></tr><tr><td colspan="2"CENGKARENG - JAKARTA BARAT</td><td></td></tr><tr><td colspan="2">021 - 56945002</td><td></td></tr><tr><td colspan="2">No. Faktur / 
  Referensi       : SO-2019/10-0009</td><td>Sales         : FADEL</td></tr></table><table class="barang"><thead><tr><th style="text-align:right !important;width:20px;">No</th><th>Nama Barang</th><th>Gd.</th><th style="text-align:right">Qty</th><th>&nbsp;</th><th style="text-align:right !important;">Harga</th><th style="text-align:right !important;">Jumlah</th></tr></thead><tbody><tr id="nomortabel0">          <td style="text-align:right !important">1</td>          <td>&emsp;CANON LV-X300</td>   
         <td>00001</td>          <td style="text-align:right">5.0&nbsp;</td><td>UNT</td>          <td style="text-align:right !important;">8,000,000</td>          <td style="text-align:right !important;">40,000,000</td>        </tr><tr><td>&emsp;</td></tr><tr><td>&emsp;</td></tr><tr><td>&emsp;</td></tr><tr><td>&emsp;</td></tr><tr><td>&emsp;</td></tr><tr><td>&emsp;</td></tr><tr><td>&emsp;</td></tr><tr><td>&emsp;</td></tr></tbody><tfoot><tr><td colspan="4">(14/01/2020 9:26:9  221/221)</td><td>890,000</td><td style="text-align:right !important;">Total &emsp;:</td><td style="text-align:right !important;">890,000</td></tr><tr><td colspan="6" style="text-align:right !important;">Diskon &emsp;:</td><td style="text-align:right !important;">7,890,000</td></tr><tr><td colspan="6" style="text-align:right !important;">Ongkos &emsp;:</td><td style="text-align:right !important;">9,899,080</td></tr><tr><td colspan="6" style="text-align:right !important;">Pajak &emsp;:</td><td style="text-align:right !important;">89,890,000</td></tr><tr><td colspan="6" style="text-align:right !important;">Netto &emsp;:</td><td style="text-align:right !important;">8,908,098</td></tr></tfoot></table><table width="100%" style="border-collapse: collapse; text-align: center; margin-bottom: 70px;"><tr><td>Dibukukan Oleh,</td><td>Diperiksa / Disetujui Oleh,</td><td>Dibuat Oleh,</td></tr></table>';

$dompdf = new Dompdf();
$dompdf->load_html($html);
$dompdf->render();
$dompdf->stream('laporan_'.$nama.'.pdf');

?>
