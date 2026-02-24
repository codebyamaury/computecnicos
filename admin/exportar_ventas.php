<?php
require '../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/bootstrap.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Mes');
$sheet->setCellValue('B1', 'Total Ventas');

$ventas = $pdo->query("SELECT MONTH(fecha) as mes, SUM(total) as total FROM pedidos GROUP BY mes ORDER BY mes")->fetchAll(PDO::FETCH_ASSOC);
$row = 2;
foreach ($ventas as $v) {
    $sheet->setCellValue('A'.$row, date('F', mktime(0, 0, 0, $v['mes'], 10)));
    $sheet->setCellValue('B'.$row, $v['total']);
    $row++;
}

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="ventas.xlsx"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;