<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

set_time_limit(300);
ini_set('memory_limit', '512M');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

require_once("checksession.php");
require_once("config.php");

// Verify autoload exists
$autoloadPath = '../../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    die("Composer autoload not found: " . $autoloadPath);
}

require_once $autoloadPath;


// ============================================================================
// XLSX EXPORT HANDLER
// ============================================================================
$allowed_user_types_ex = ['super_stockiest', 'stockiest', 'super_distributor', 'distributor'];

$getinvuser_ex = $_REQUEST['femiusr'] ?? 'distributor';

if (!in_array($getinvuser_ex, $allowed_user_types_ex, true)) {
    die("Invalid user type");
}

$user_type_config_ex = [
    'super_stockiest'   => [
        'label' => 'Super Stockist',
        'table' => 'super_stockiest'
    ],
    'stockiest'         => [
        'label' => 'Stockist',
        'table' => 'stockiest'
    ],
    'super_distributor' => [
        'label' => 'Super Distributor',
        'table' => 'super_distributor'
    ],
    'distributor'       => [
        'label' => 'Distributor',
        'table' => 'distributor'
    ]
];

$tablename_ex = $user_type_config_ex[$getinvuser_ex]['table'];
$label_ex     = $user_type_config_ex[$getinvuser_ex]['label'];

date_default_timezone_set("Asia/Kolkata");

$cm_ex = date('m');
$nd_ex = (int)date('t');

function validateDateEx2(?string $date, string $default): string
{
    if (empty($date)) {
        return $default;
    }

    $ts = strtotime($date);

    return ($ts === false)
        ? $default
        : date('Y-m-d', $ts);
}

$from_ex = validateDateEx2(
    $_REQUEST['frdate'] ?? null,
    date("Y-{$cm_ex}-01")
);

$to_ex = validateDateEx2(
    $_REQUEST['todate'] ?? null,
    date("Y-{$cm_ex}-{$nd_ex}")
);

if (strtotime($from_ex) > strtotime($to_ex)) {
    [$from_ex, $to_ex] = [$to_ex, $from_ex];
}

try {

    $pdo_ex = new PDO(
        "mysql:host={$servername};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true
        ]
    );

    // =========================================================================
    // PURCHASE POINTS
    // =========================================================================
    $s = $pdo_ex->prepare("
        SELECT 
            invoice_data.to_user_id as user_id,
            COALESCE(SUM(invoice_data.purchase_points),0) as gross_purchase_points,
            COALESCE(SUM(return_data.return_points),0) as deducted_purchase_points,
            GREATEST(
                COALESCE(SUM(invoice_data.purchase_points),0) -
                COALESCE(SUM(return_data.return_points),0),
                0
            ) as net_purchase_points
        FROM (
            SELECT 
                uii.to_user_id,
                uii.inv_id,
                SUM(uii.subtotal)/100 as purchase_points
            FROM user_invoice_items uii
            INNER JOIN user_invoice ui 
                ON uii.inv_id = ui.inv_id
            WHERE 
                uii.date BETWEEN :fd1 AND :td1
                AND uii.to_user_type = :ut1
                AND ui.rwpoints_enable = 1
            GROUP BY uii.to_user_id, uii.inv_id
        ) invoice_data
        LEFT JOIN (
            SELECT 
                r.from_userid,
                r.invnumber,
                SUM(r.subtotal)/100 as return_points
            FROM user_return_stock_items r
            WHERE 
                r.invnumber IN (
                    SELECT DISTINCT uii.inv_id
                    FROM user_invoice_items uii
                    INNER JOIN user_invoice ui 
                        ON uii.inv_id = ui.inv_id
                    WHERE 
                        uii.date BETWEEN :fd2 AND :td2
                        AND uii.to_user_type = :ut2
                        AND ui.rwpoints_enable = 1
                )
                AND r.from_usertype = :ut3
            GROUP BY r.from_userid, r.invnumber
        ) return_data
            ON return_data.from_userid = invoice_data.to_user_id
            AND return_data.invnumber = invoice_data.inv_id
        GROUP BY invoice_data.to_user_id
    ");

    $s->execute([
        ':fd1' => $from_ex,
        ':td1' => $to_ex,
        ':ut1' => $getinvuser_ex,
        ':fd2' => $from_ex,
        ':td2' => $to_ex,
        ':ut2' => $getinvuser_ex,
        ':ut3' => $getinvuser_ex
    ]);

    $pur_ex = [];

    foreach ($s->fetchAll() as $r) {
        $pur_ex[$r['user_id']] = $r;
    }

    // =========================================================================
    // USER SALES POINTS
    // =========================================================================
    $s = $pdo_ex->prepare("
        SELECT 
            invoice_data.from_user_id as user_id,
            GREATEST(
                COALESCE(SUM(invoice_data.sales_points),0) -
                COALESCE(SUM(return_data.return_points),0),
                0
            ) as net_user_sales_points
        FROM (
            SELECT 
                uii.from_user_id,
                uii.inv_id,
                SUM(uii.subtotal)/100 as sales_points
            FROM user_invoice_items uii
            INNER JOIN user_invoice ui 
                ON uii.inv_id = ui.inv_id
            WHERE 
                uii.date BETWEEN :fd1 AND :td1
                AND uii.from_user_type = :ut1
                AND ui.rwpoints_enable = 1
            GROUP BY uii.from_user_id, uii.inv_id
        ) invoice_data
        LEFT JOIN (
            SELECT 
                r.to_userid,
                r.invnumber,
                SUM(r.subtotal)/100 as return_points
            FROM user_return_stock_items r
            WHERE 
                r.invnumber IN (
                    SELECT DISTINCT uii.inv_id
                    FROM user_invoice_items uii
                    INNER JOIN user_invoice ui 
                        ON uii.inv_id = ui.inv_id
                    WHERE 
                        uii.date BETWEEN :fd2 AND :td2
                        AND uii.from_user_type = :ut2
                        AND ui.rwpoints_enable = 1
                )
                AND r.to_usertype = :ut3
            GROUP BY r.to_userid, r.invnumber
        ) return_data
            ON return_data.to_userid = invoice_data.from_user_id
            AND return_data.invnumber = invoice_data.inv_id
        GROUP BY invoice_data.from_user_id
    ");

    $s->execute([
        ':fd1' => $from_ex,
        ':td1' => $to_ex,
        ':ut1' => $getinvuser_ex,
        ':fd2' => $from_ex,
        ':td2' => $to_ex,
        ':ut2' => $getinvuser_ex,
        ':ut3' => $getinvuser_ex
    ]);

    $usls_ex = [];

    foreach ($s->fetchAll() as $r) {
        $usls_ex[$r['user_id']] = (float)$r['net_user_sales_points'];
    }

    // =========================================================================
    // CUSTOMER SALES POINTS
    // =========================================================================
    $tbl_check = $pdo_ex->query("SHOW TABLES LIKE 'return_stock_items'")->fetch();

    $csls_ex = [];

    if ($tbl_check) {

        $s = $pdo_ex->prepare("
            SELECT 
                invoice_data.user_id,
                GREATEST(
                    COALESCE(SUM(invoice_data.sales_points),0) -
                    COALESCE(SUM(return_data.return_points),0),
                    0
                ) as net_customer_sales_points
            FROM (
                SELECT 
                    ii.user_id,
                    ii.inv_id,
                    SUM(ii.subtotal)/100 as sales_points
                FROM invoice_items ii
                WHERE 
                    ii.date BETWEEN :fd1 AND :td1
                    AND ii.user_type = :ut1
                GROUP BY ii.user_id, ii.inv_id
            ) invoice_data
            LEFT JOIN (
                SELECT 
                    r.user_id,
                    r.invnumber,
                    SUM(r.subtotal)/100 as return_points
                FROM return_stock_items r
                WHERE 
                    r.invnumber IN (
                        SELECT DISTINCT ii.inv_id
                        FROM invoice_items ii
                        WHERE 
                            ii.date BETWEEN :fd2 AND :td2
                            AND ii.user_type = :ut2
                    )
                    AND r.user_type = :ut3
                GROUP BY r.user_id, r.invnumber
            ) return_data
                ON return_data.user_id = invoice_data.user_id
                AND return_data.invnumber = invoice_data.inv_id
            GROUP BY invoice_data.user_id
        ");

        $s->execute([
            ':fd1' => $from_ex,
            ':td1' => $to_ex,
            ':ut1' => $getinvuser_ex,
            ':fd2' => $from_ex,
            ':td2' => $to_ex,
            ':ut2' => $getinvuser_ex,
            ':ut3' => $getinvuser_ex
        ]);

    } else {

        $s = $pdo_ex->prepare("
            SELECT 
                ii.user_id,
                SUM(ii.subtotal)/100 as net_customer_sales_points
            FROM invoice_items ii
            WHERE 
                ii.date BETWEEN :fd AND :td
                AND ii.user_type = :ut
            GROUP BY ii.user_id
        ");

        $s->execute([
            ':fd' => $from_ex,
            ':td' => $to_ex,
            ':ut' => $getinvuser_ex
        ]);
    }

    foreach ($s->fetchAll() as $r) {
        $csls_ex[$r['user_id']] = (float)$r['net_customer_sales_points'];
    }

    // =========================================================================
    // DAILY LOGIN
    // =========================================================================
    $s = $pdo_ex->prepare("
        SELECT 
            user_id,
            SUM(points_awarded) as daily_points
        FROM daily_login_rewards
        WHERE 
            user_type = :ut
            AND reward_date BETWEEN :fd AND :td
        GROUP BY user_id
        HAVING daily_points > 0
    ");

    $s->execute([
        ':ut' => $getinvuser_ex,
        ':fd' => $from_ex,
        ':td' => $to_ex
    ]);

    $daily_ex = [];

    foreach ($s->fetchAll() as $r) {
        $daily_ex[$r['user_id']] = (float)$r['daily_points'];
    }

    // =========================================================================
    // ADVANCE BONUS
    // =========================================================================
    $bonus_ex = [];

    if (in_array($getinvuser_ex, ['super_stockiest', 'stockiest'], true)) {

        $my_ex = [];

        $lts = strtotime(date('Y-m-01', strtotime($from_ex)));
        $ets = strtotime(date('Y-m-01', strtotime($to_ex)));

        while ($lts <= $ets) {
            $my_ex[] = date('Y-m', $lts);
            $lts = strtotime('+1 month', $lts);
        }

        if (!empty($my_ex)) {

            $mph = str_repeat('?,', count($my_ex) - 1) . '?';

            $s = $pdo_ex->prepare("
                SELECT 
                    user_id,
                    SUM(bonus_points_awarded) as advance_bonus_points
                FROM bonus_points_history
                WHERE 
                    user_type = ?
                    AND month_year IN ({$mph})
                    AND rolled_back_at IS NULL
                GROUP BY user_id
                HAVING advance_bonus_points > 0
            ");

            $s->execute(array_merge([$getinvuser_ex], $my_ex));

            foreach ($s->fetchAll() as $r) {
                $bonus_ex[$r['user_id']] = (float)$r['advance_bonus_points'];
            }
        }
    }

    // =========================================================================
    // COLLECT USER IDS
    // =========================================================================
    $all_ex = array_unique(array_merge(
        array_keys($pur_ex),
        array_keys($usls_ex),
        array_keys($csls_ex),
        array_keys($daily_ex),
        array_keys($bonus_ex)
    ));

    if (empty($all_ex)) {
        die("No data found.");
    }

    $ph_ex = str_repeat('?,', count($all_ex) - 1) . '?';

    // =========================================================================
    // USER DETAILS
    // =========================================================================
    $s = $pdo_ex->prepare("
        SELECT 
            u.temp_id,
            u.name,
            u.mobile_number,
            COALESCE(d.dist_name, u.district_id, 'Nil') as district_name
        FROM {$tablename_ex} u
        LEFT JOIN district d
            ON u.district_id = d.id
        WHERE u.temp_id IN ({$ph_ex})
    ");

    $s->execute($all_ex);

    $udet_ex = [];

    foreach ($s->fetchAll() as $r) {
        $udet_ex[$r['temp_id']] = $r;
    }

    // =========================================================================
    // STOCKIST CATEGORY
    // =========================================================================
    $cat_ex = [];

    if ($getinvuser_ex === 'stockiest') {

        try {

            $s = $pdo_ex->prepare("
                SELECT 
                    sr.stockist_id,
                    COALESCE(sc.catname,'') as catname
                FROM stockist_referral sr
                LEFT JOIN stockist_category sc
                    ON sr.st_cat_id = sc.id
                WHERE sr.stockist_id IN ({$ph_ex})
            ");

            $s->execute($all_ex);

            foreach ($s->fetchAll() as $r) {
                $cat_ex[$r['stockist_id']] = $r['catname'];
            }

        } catch (Exception $e) {
        }
    }

    // =========================================================================
    // BUILD ROWS
    // =========================================================================
    $rows_ex = [];

    foreach ($all_ex as $uid) {

        if (!isset($udet_ex[$uid])) {
            continue;
        }

        $pur_pts   = (float)($pur_ex[$uid]['net_purchase_points'] ?? 0);
        $pur_ded   = (float)($pur_ex[$uid]['deducted_purchase_points'] ?? 0);
        $daily_pts = (float)($daily_ex[$uid] ?? 0);
        $bonus_pts = (float)($bonus_ex[$uid] ?? 0);

        $sales_pts =
            ($usls_ex[$uid] ?? 0) +
            ($csls_ex[$uid] ?? 0);

        $total_pts =
            $pur_pts +
            $daily_pts +
            $bonus_pts;

        if (
            $pur_pts == 0 &&
            $sales_pts == 0 &&
            $daily_pts == 0 &&
            $bonus_pts == 0 &&
            $pur_ded == 0
        ) {
            continue;
        }

        $rows_ex[] = [
            'name'     => strtoupper($udet_ex[$uid]['name']),
            'mobile'   => $udet_ex[$uid]['mobile_number'],
            'district' => $udet_ex[$uid]['district_name'],
            'category' => $cat_ex[$uid] ?? '',
            'purchase' => $pur_pts,
            'daily'    => $daily_pts,
            'advance'  => $bonus_pts,
            'pur_ded'  => $pur_ded,
            'total'    => $total_pts,
            'sales'    => $sales_pts,
        ];
    }

    usort($rows_ex, fn($a, $b) => $b['total'] <=> $a['total']);

    // =========================================================================
    // SPREADSHEET
    // =========================================================================
    $spreadsheet = new Spreadsheet();

    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setTitle('Reward Points');

    $isStockist = ($getinvuser_ex === 'stockiest');

    $colOffset = $isStockist ? 1 : 0;

    $cols = [
        'sno'      => 'A',
        'name'     => 'B',
        'mobile'   => 'C',
        'district' => 'D',
        'category' => $isStockist ? 'E' : null,
        'purchase' => chr(ord('E') + $colOffset),
        'daily'    => chr(ord('F') + $colOffset),
        'advance'  => chr(ord('G') + $colOffset),
        'pur_ded'  => chr(ord('H') + $colOffset),
        'total'    => chr(ord('I') + $colOffset),
        'sales'    => chr(ord('J') + $colOffset),
    ];

    $lastCol = $cols['sales'];

    // =========================================================================
    // TITLE
    // =========================================================================
    $sheet->mergeCells("A1:{$lastCol}1");

    $sheet->setCellValue(
        'A1',
        "Reward Points - {$label_ex} ({$from_ex} to {$to_ex})"
    );

    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold'  => true,
            'size'  => 14,
            'color' => ['argb' => 'FFFFFFFF']
        ],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FF1E3A5F']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER
        ]
    ]);

    $sheet->getRowDimension(1)->setRowHeight(32);

    // =========================================================================
    // HEADERS
    // =========================================================================
    $headers = [
        'A' => 'S.No',
        'B' => 'Name',
        'C' => 'Mobile',
        'D' => 'District',
    ];

    if ($isStockist) {
        $headers['E'] = 'Category';
    }

    $headers[$cols['purchase']] = 'Purchase Points';
    $headers[$cols['daily']]    = 'Daily Login';
    $headers[$cols['advance']]  = 'Advance Bonus';
    $headers[$cols['pur_ded']]  = 'Purchase Returns';
    $headers[$cols['total']]    = 'Total Reward Points';
    $headers[$cols['sales']]    = 'Sales Points';

    foreach ($headers as $col => $label) {
        $sheet->setCellValue("{$col}2", $label);
    }

    $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
        'font' => [
            'bold'  => true,
            'size'  => 11,
            'color' => ['argb' => 'FFFFFFFF']
        ],
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FF2563EB']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => true
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['argb' => 'FFAAAAAA']
            ]
        ]
    ]);

    // =========================================================================
    // DATA
    // =========================================================================
    $rowNum = 3;

    foreach ($rows_ex as $i => $row) {

        $sn = $i + 1;

        $sheet->setCellValue("A{$rowNum}", $sn);
        $sheet->setCellValue("B{$rowNum}", $row['name']);
        $sheet->setCellValue("C{$rowNum}", $row['mobile']);
        $sheet->setCellValue("D{$rowNum}", $row['district']);

        if ($isStockist) {
            $sheet->setCellValue("E{$rowNum}", $row['category']);
        }

        $sheet->setCellValue("{$cols['purchase']}{$rowNum}", (float)$row['purchase']);
        $sheet->setCellValue("{$cols['daily']}{$rowNum}", (float)$row['daily']);
        $sheet->setCellValue("{$cols['advance']}{$rowNum}", (float)$row['advance']);
        $sheet->setCellValue("{$cols['pur_ded']}{$rowNum}", (float)$row['pur_ded']);
        $sheet->setCellValue("{$cols['total']}{$rowNum}", (float)$row['total']);
        $sheet->setCellValue("{$cols['sales']}{$rowNum}", (float)$row['sales']);

        $bgColor = ($sn % 2 === 0)
            ? 'FFF0F4FF'
            : 'FFFFFFFF';

        $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")
            ->applyFromArray([
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => $bgColor]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['argb' => 'FFDDDDDD']
                    ]
                ]
            ]);

        foreach (
            [
                $cols['purchase'],
                $cols['daily'],
                $cols['advance'],
                $cols['pur_ded'],
                $cols['total'],
                $cols['sales']
            ] as $nc
        ) {

            $sheet->getStyle("{$nc}{$rowNum}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');

            $sheet->getStyle("{$nc}{$rowNum}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->getStyle("{$cols['pur_ded']}{$rowNum}")
            ->getFont()
            ->setColor(new Color('FFDC2626'));

        $sheet->getStyle("{$cols['advance']}{$rowNum}")
            ->getFont()
            ->setColor(new Color('FF7C3AED'));

        $sheet->getStyle("{$cols['total']}{$rowNum}")
            ->getFont()
            ->setBold(true)
            ->setColor(new Color('FF2563EB'));

        $sheet->getStyle("{$cols['sales']}{$rowNum}")
            ->getFont()
            ->setColor(new Color('FF059669'));

        $rowNum++;
    }

    // =========================================================================
    // TOTALS ROW
    // =========================================================================
    $totalsRow = $rowNum;

    $dataStart = 3;
    $dataEnd   = $rowNum - 1;

    $sheet->setCellValue("A{$totalsRow}", 'TOTAL');

    if ($isStockist) {
        $sheet->mergeCells("A{$totalsRow}:E{$totalsRow}");
    } else {
        $sheet->mergeCells("A{$totalsRow}:D{$totalsRow}");
    }

    foreach (
        [
            $cols['purchase'],
            $cols['daily'],
            $cols['advance'],
            $cols['pur_ded'],
            $cols['total'],
            $cols['sales']
        ] as $nc
    ) {

        $sheet->setCellValue(
            "{$nc}{$totalsRow}",
            "=SUM({$nc}{$dataStart}:{$nc}{$dataEnd})"
        );

        $sheet->getStyle("{$nc}{$totalsRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        $sheet->getStyle("{$nc}{$totalsRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")
        ->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['argb' => 'FFFFFFFF']
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1E3A5F']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => 'FFAAAAAA']
                ]
            ]
        ]);

    // =========================================================================
    // COLUMN WIDTHS
    // =========================================================================
    $sheet->getColumnDimension('A')->setWidth(7);
    $sheet->getColumnDimension('B')->setWidth(28);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(20);

    if ($isStockist) {
        $sheet->getColumnDimension('E')->setWidth(18);
    }

    foreach (
        [
            $cols['purchase'],
            $cols['daily'],
            $cols['advance'],
            $cols['pur_ded'],
            $cols['total'],
            $cols['sales']
        ] as $nc
    ) {
        $sheet->getColumnDimension($nc)->setWidth(18);
    }

    // =========================================================================
    // FREEZE + FILTER
    // =========================================================================
    $sheet->freezePane('A3');

    $sheet->setAutoFilter("A2:{$lastCol}2");

    // =========================================================================
    // OUTPUT
    // =========================================================================
    $filename = "reward_points_{$getinvuser_ex}_{$from_ex}_to_{$to_ex}.xlsx";

    // IMPORTANT FIX
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new XlsxWriter($spreadsheet);

    $writer->save('php://output');

    exit;

} catch (PDOException $e) {

    error_log("DB Error: " . $e->getMessage());

    die("Database Error: " . htmlspecialchars($e->getMessage()));

} catch (Exception $e) {

    error_log("Spreadsheet Error: " . $e->getMessage());

    die("Export Error: " . htmlspecialchars($e->getMessage()));
}