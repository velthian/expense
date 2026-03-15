<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
    include(__DIR__ .'/../db.php');

    // Accept either JSON body or form-POST
    $input = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($input, 'application/json') !== false) 
    {
        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $uid_in  = $payload['uid'] ?? null;
    } 
    else 
    {
        $uid_in  = $_POST['uid'] ?? null;
    }

    // Validate inputs
    // --- uid: required, non-negative integer only ---
    if ($uid_in === null || $uid_in === '') {
        throw new InvalidArgumentException('uid is required');
    }

    if (is_int($uid_in)) 
    {
        $uid = $uid_in;
    } 
    elseif (is_string($uid_in) && ctype_digit($uid_in)) 
    {
        // ctype_digit allows '0', '123' (no signs/decimals/spaces)
        $uid = (int)$uid_in;
    } 
    else 
    {
        throw new InvalidArgumentException('uid must be a non-negative integer');
    }

    // Call service
    $loginid = $_SESSION['loginid'];
    require_once(__DIR__ . '/../class/transactions.php');
    $svc = new transactions();
    $res = $svc->deleteTransaction($uid, $loginid);
    echo json_encode($res);

} catch (RuntimeException $e) {
    http_response_code(400);
    error_log('[reconcile_line] Bad request: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);

}
catch (InvalidArgumentException $e) {
    http_response_code(400);
    error_log('[reconcile_line] Invalid argument: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);

}
?>