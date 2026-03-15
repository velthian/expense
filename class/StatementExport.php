<?php

class StatementExport
{
    public static function userOwnsStatement(mysqli $conn, int $userId, int $statementId): bool
    {
        $sql = "SELECT 1 FROM bankStatements 
                WHERE statement_id = ? AND username = ?";
        $stmt = $conn->prepare($sql);
        $username = (string)$userId; // or map id→username as you actually do

        $stmt->bind_param("is", $statementId, $username);
        $stmt->execute();
        $stmt->store_result();
        $owns = $stmt->num_rows > 0;
        $stmt->close();

        return $owns;
    }

    public static function getStatementMeta(mysqli $conn, int $statementId): array
    {
        $sql = "SELECT statement_date, opening_balance, closing_balance 
                FROM bankStatements WHERE statement_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $statementId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return $res;
    }

    public static function buildStatementXls(mysqli $conn, int $statementId): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set sheet title
        $sheet->setTitle('Bank Statement');

        // Add header row
        $headers = ['Date', 'Description', 'Debit', 'Credit', 'Balance'];
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col++, 1, $header);
        }

        // Fetch data from DB
        $sql = "SELECT date, merchant_name, amount, reconciled 
                FROM bank_statement_lines
                WHERE statement_id = ?
                ORDER BY date ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $statementId);
        $stmt->execute();
        $result = $stmt->get_result();

        $rowIndex = 2;
        while ($row = $result->fetch_assoc()) {
            // Split amount into debit/credit for display
            $debit = $row['amount'] < 0 ? abs($row['amount']) : '';
            $credit = $row['amount'] > 0 ? $row['amount'] : '';

            $sheet->setCellValueByColumnAndRow(1, $rowIndex, $row['date']);
            $sheet->setCellValueByColumnAndRow(2, $rowIndex, $row['merchant_name']);
            $sheet->setCellValueByColumnAndRow(3, $rowIndex, $debit);
            $sheet->setCellValueByColumnAndRow(4, $rowIndex, $credit);
            $sheet->setCellValueByColumnAndRow(5, $rowIndex, ''); // You can compute running balance later

            $rowIndex++;
        }

        $stmt->close();

        // Apply basic styling (optional)
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);

        // Return spreadsheet object (not written to file yet)
        return $spreadsheet;
    }
}

?>