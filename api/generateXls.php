<?php
// downloadStatementXls.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && !str_starts_with($origin, 'https://trackwealth.in')) {
    http_response_code(403);
    exit(json_encode(['error' => 'Invalid request origin.']));
}

if(!isset($_SESSION['authenticated']))
{
    $_SESSION['redirect']=true;
    $_SESSION['auth_error']="Please login first";
    header('Location: index.php');
    die();
}

$_SESSION['timestamp'] = time();

require '../investo/vendor/autoload.php';
//require 'vendor/autoload.php';

require_once __DIR__.'/../db.php';
require_once __DIR__.'/../class/StatementExport.php';

$statementId = isset($_GET['statement_id']) ? (int)$_GET['statement_id'] : 0;
if ($statementId <= 0) {
    http_response_code(400);
    exit('Invalid statement id');
}

// 2. Check ownership
$userId = $_SESSION['loginid']; // from session
if (!StatementExport::userOwnsStatement($conn, $userId, $statementId)) {
    http_response_code(403);
    exit('Forbidden');
}

// 3. Build spreadsheet in memory
$spreadsheet = StatementExport::buildStatementXls($conn, $statementId);

// 4. Unique, meaningful filename
$meta = StatementExport::getStatementMeta($conn, $statementId); // e.g. date, account
$baseName = sprintf(
    'statement_%s_%s_%s',
    $userId,
    $meta['statement_date'],   // 2025-10-09
    $statementId
);
$filename = $baseName.'.xlsx';

// 5. Stream to browser (no disk clutter)
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

?>