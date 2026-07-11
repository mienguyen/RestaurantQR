<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

function escapeHtml(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Lấy danh sách bàn và đường dẫn QR từ cơ sở dữ liệu
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        ma_ban,
        ten_ban,
        ma_qr,
        trang_thai
    FROM ban_an
    ORDER BY ma_ban ASC
";

$result = $conn->query($sql);

$tables = [];

while ($table = $result->fetch_assoc()) {
    $tables[] = $table;
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Danh sách mã QR bàn ăn</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f4f4;
            color: #222222;
        }

        .header {
            padding: 25px 20px;
            background-color: #202428;
            color: #ffffff;
            text-align: center;
        }

        .header h1 {
            margin: 0 0 10px;
        }

        .header p {
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .qr-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(230px, 1fr)
            );
            gap: 22px;
        }

        .qr-card {
            padding: 22px;
            background-color: #ffffff;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
        }

        .qr-card h2 {
            margin: 0 0 15px;
            color: #981d1d;
        }

        .qr-card img {
            width: 210px;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto 15px;
        }

        .qr-description {
            margin: 0 0 10px;
            color: #555555;
            line-height: 1.5;
        }

        .status {
            font-size: 14px;
            font-weight: bold;
        }

        .print-button {
            display: block;
            margin: 30px auto 0;
            padding: 13px 24px;
            border: none;
            border-radius: 7px;
            background-color: #198754;
            color: #ffffff;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
        }

        .empty-message {
            padding: 35px;
            background-color: #ffffff;
            border-radius: 10px;
            text-align: center;
        }

        @media print {
            body {
                background-color: #ffffff;
            }

            .header,
            .print-button {
                display: none;
            }

            .container {
                max-width: none;
                padding: 0;
            }

            .qr-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .qr-card {
                box-shadow: none;
                border: 1px solid #cccccc;
                page-break-inside: avoid;
            }
        }

        @media (max-width: 600px) {
            .qr-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<header class="header">

    <h1>Mã QR gọi món</h1>

    <p>
        In mã QR và đặt đúng tại bàn tương ứng
    </p>

</header>

<main class="container">

    <?php if (empty($tables)): ?>

        <section class="empty-message">
            Chưa có bàn ăn trong cơ sở dữ liệu.
        </section>

    <?php else: ?>

        <section class="qr-grid">

            <?php foreach ($tables as $table): ?>

                <?php
                $qrPath = trim(
                    (string) ($table['ma_qr'] ?? '')
                );

                $qrExists =
                    $qrPath !== ''
                    && is_file(__DIR__ . '/' . $qrPath);
                ?>

                <article class="qr-card">

                    <h2>
                        <?= escapeHtml($table['ten_ban']) ?>
                    </h2>

                    <?php if ($qrExists): ?>

                        <img
                            src="<?= escapeHtml($qrPath) ?>"
                            alt="Mã QR <?= escapeHtml($table['ten_ban']) ?>"
                        >

                    <?php else: ?>

                        <p>
                            Chưa tạo mã QR cho bàn này.
                        </p>

                    <?php endif; ?>

                    <p class="qr-description">
                        Quét mã để xem thực đơn và gọi món tại
                        <?= escapeHtml($table['ten_ban']) ?>.
                    </p>

                    <div class="status">
                        Trạng thái:
                        <?= escapeHtml($table['trang_thai']) ?>
                    </div>

                </article>

            <?php endforeach; ?>

        </section>

        <button
            class="print-button"
            type="button"
            onclick="window.print()"
        >
            In mã QR
        </button>

    <?php endif; ?>

</main>

</body>

</html>