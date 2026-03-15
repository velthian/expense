<?php
session_start();
date_default_timezone_set('Asia/Calcutta');

//require '../investo/vendor/autoload.php';
require 'vendor/autoload.php';
require 'db.php';                         // your $conn (if transactions uses it)
include_once('class/transactions.php');   // your existing logic

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

// -------------------------
// 1) Read input parameters
// -------------------------
$chosen_month = isset($_POST['chosen_month']) ? $_POST['chosen_month'] : '';
$categories   = filter_input(INPUT_POST, 'categories', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
$filter       = filter_input(INPUT_POST, 'filter', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

// normalize month the same way as getTransactions.php
if ($chosen_month) {
    $date         = new DateTime($chosen_month);
    $chosen_month = $date->format('Y-m') . '-01';
}

$loginid = $_SESSION['loginid'] ?? null;
if (!$loginid) {
    die("Not authorized");
}

// -------------------------
// 2) Call existing logic
// -------------------------
$obj = new transactions();
$res = $obj->getTransactions($categories, $chosen_month, $filter);
// $res has the same structure as your JSON response:
// [
//   'key_expenses' => ...,
//   'total_monthly_spend' => ...,
//   'data' => [...],       // sup categories + categories + txns
//   'allSupCats' => [...]
// ]

// -------------------------
// 3) Build spreadsheet
// -------------------------
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Transactions');

// Header row
$row = 1;
$sheet->fromArray(
    [
        'Sup Cat Desc',
        'Cat Desc',
        'Merchant',
        'Amount',
        'Date (Full)',
        'Mode',
        'Reconciled'
    ],
    null,
    "A{$row}"
);
$row++;

// Safety: handle if 'data' not present
$dataArray = isset($res['data']) && is_array($res['data']) ? $res['data'] : [];

// Loop through sup-categories → categories → transactions
foreach ($dataArray as $supCat) {

    $supCatDesc = $supCat['sup_cat_desc'] ?? '';

    $allCatTxnData = $supCat['all_cat_txn_data'] ?? [];

    foreach ($allCatTxnData as $cat) {

        // You started returning cat_desc in PHP; JSON sample sometimes didn’t have it
        $catDesc = $cat['cat_desc'] ?? '';

        $catTxns = $cat['cat_txn_data'] ?? [];

        foreach ($catTxns as $txn) {

            $merchant       = $txn['merchant_name']  ?? '';
            $amount         = isset($txn['amount']) ? (float)$txn['amount'] : 0;
            $dateFull       = $txn['dateFullFormat'] ?? '';
            $mode           = $txn['mode']           ?? '';
            $reconciledFlag = isset($txn['reconciled_flag']) ? (int)$txn['reconciled_flag'] : 0;

            $sheet->fromArray(
                [
                    $supCatDesc,
                    $catDesc,
                    $merchant,
                    $amount,
                    $dateFull,
                    $mode,
                    $reconciledFlag
                ],
                null,
                "A{$row}"
            );

            $row++;
        }
    }
}

// Basic numeric formatting
$lastRow = $row - 1;
if ($lastRow >= 2) {
    $sheet->getStyle("G2:G{$lastRow}")
          ->getNumberFormat()
          ->setFormatCode('#,##0.00');
    $sheet->getStyle("H2:H{$lastRow}")
          ->getNumberFormat()
          ->setFormatCode('#,##0.00');
}

// Auto-size columns
foreach (range('A', 'M') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// -------------------------
// 4) Stream to browser
// -------------------------
$filename = 'transactions_' . ($chosen_month ?: date('Y_m')) . '.xlsx';

// Clear any existing output buffer
if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"{$filename}\"");
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;
?>