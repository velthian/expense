<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once(__DIR__ . '/../db.php');
require_once(__DIR__ . '/../class/bankStmt.php');
require_once(__DIR__ . '/../class/convertToIndian.php');

$response = ['success' => false];

try 
{
    if (empty($_POST['statement_date'])) 
    {
        throw new Exception('Missing parameter: statement_date');
    }

    $balances = bankStmt::getBalances($conn, trim($_POST['statement_date']));
    if (!$balances) 
    {
        throw new Exception('No record found for that date.');
    }

    $response = [
        'success' => true,
        'opening_balance' => convertToIndian::convertToIndianCurrency($balances['opening_balance']),
        'closing_balance' => convertToIndian::convertToIndianCurrency($balances['closing_balance']),
        'amount_spent' => convertToIndian::convertToIndianCurrency($balances['opening_balance'] - $balances['closing_balance']),
    ];
} 
catch (Throwable $e) 
{
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>