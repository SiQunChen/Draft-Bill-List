<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require_once('../vendor/autoload.php');
require_once('draft_bill_list_db.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (!isset($_GET['ids']) || empty($_GET['ids'])) {
    die("未選擇任何資料 (No IDs provided).");
}

$ids_raw = $_GET['ids'];
$ids_array = explode(',', $ids_raw);

try {
    $api_result = getData('', 'match', '', 'case_num', 'ASC', $ids_array);
    $rows = $api_result['rows'];
} catch (Exception $e) {
    ob_end_clean();
    die("資料讀取失敗: " . $e->getMessage());
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Draft Bill List');
$sheet->getSheetView()->setZoomScale(110);

$headers = [
    'A' => 'Created',
    'B' => 'Case Num',
    'C' => 'Manager',
    'D' => 'Debit Note',
    'E' => 'Legal Services',
    'F' => 'Disbs',
    'G' => 'Total',
    'H' => 'Billing Note',
    'I' => 'OC Invoice',
    'J' => 'ATI Category'
];

foreach ($headers as $col => $text) {
    $sheet->setCellValue($col . '1', $text);
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
    $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($col . '1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}

if (!empty($rows)) {
    $rowIdx = 2;
    foreach ($rows as $row) {
        // 判斷幣別
        $is_foreign = ($row['billing_currency'] == 'English (USD)' || $row['billing_currency'] == 'English (EUR)');

        if ($is_foreign) {
            $legal = $row['show_foreign_legal_services'];
            $disbs = $row['show_foreign_disbs'];
            $total = $row['foreign_total2'];
        } else {
            $legal = $row['show_legal_services'];
            $disbs = $row['show_disbs'];
            $total = $row['total'];
        }

        // OC Invoice 文字
        $oc_invoice_text = $row['show_oc'] == 1 && !empty($row['display_oc_status']) ? 'Expected' : '';

        // ATI Category 組合
        $ati_text = "";
        if (!empty($row['ati_cate1'])) {
            $ati_text .= $row['ati_cate1'];
            if (!empty($row['ati_cate2'])) $ati_text .= " - " . $row['ati_cate2'];
        }
        if (!empty($row['ati_cate12'])) {
            $ati_text .= ($ati_text ? "\n" : "") . $row['ati_cate12'];
            if (!empty($row['ati_cate22'])) $ati_text .= " - " . $row['ati_cate22'];
        }
        if (!empty($row['ati_cate13'])) {
            $ati_text .= ($ati_text ? "\n" : "") . $row['ati_cate13'];
            if (!empty($row['ati_cate23'])) $ati_text .= " - " . $row['ati_cate23'];
        }

        // 填入資料
        $sheet->setCellValue('A' . $rowIdx, $row['draft_created']);
        $sheet->setCellValue('B' . $rowIdx, $row['case_num']);
        $sheet->setCellValue('C' . $rowIdx, $row['case_manager']);
        $sheet->setCellValue('D' . $rowIdx, $row['deb_num']);

        $sheet->setCellValue('E' . $rowIdx, $legal);
        $sheet->setCellValue('F' . $rowIdx, $disbs);
        $sheet->setCellValue('G' . $rowIdx, $total);
        $formatCode = $is_foreign ? '#,##0.00' : '#,##0';
        $sheet->getStyle('E' . $rowIdx . ':G' . $rowIdx)->getNumberFormat()->setFormatCode($formatCode);

        $sheet->setCellValue('H' . $rowIdx, $row['billing_note']);
        $sheet->getStyle('H' . $rowIdx)->getAlignment()->setWrapText(true);

        $sheet->setCellValue('I' . $rowIdx, $oc_invoice_text);

        $sheet->setCellValue('J' . $rowIdx, $ati_text);
        $sheet->getStyle('J' . $rowIdx)->getAlignment()->setWrapText(true);

        // 設定所有資料列垂直置上 (Vertical Top)，這樣多行文字時比較好閱讀
        $sheet->getStyle('A' . $rowIdx . ':J' . $rowIdx)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $rowIdx++;
    }
}

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'I'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$sheet->getColumnDimension('H')->setWidth(100);
$sheet->getColumnDimension('J')->setWidth(40);

// 輸出檔案
ob_end_clean();
$filename = 'Draft_Bill_Export_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
