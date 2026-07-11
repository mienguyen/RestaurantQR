<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| Kiểm tra và nạp thư viện PHP QR Code
|--------------------------------------------------------------------------
*/

$libraryPath = __DIR__ . '/phpqrcode/qrlib.php';

if (!is_file($libraryPath)) {
    exit(
        'Không tìm thấy thư viện PHP QR Code tại: '
        . htmlspecialchars(
            $libraryPath,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}

require_once $libraryPath;

/*
|--------------------------------------------------------------------------
| Tạo thư mục lưu QR nếu chưa tồn tại
|--------------------------------------------------------------------------
*/

$qrDirectory = __DIR__ . '/qr';

if (!is_dir($qrDirectory)) {
    $created = mkdir(
        $qrDirectory,
        0777,
        true
    );

    if (!$created && !is_dir($qrDirectory)) {
        exit('Không thể tạo thư mục lưu mã QR.');
    }
}

/*
|--------------------------------------------------------------------------
| Địa chỉ được lưu trong mã QR
|--------------------------------------------------------------------------
| Trước mắt sử dụng localhost để kiểm tra trên máy tính.
| Sau này đổi localhost thành IPv4 để quét bằng điện thoại.
|--------------------------------------------------------------------------
*/

$baseUrl = 'http://localhost/RestaurantQR/menu.php';

/*
|--------------------------------------------------------------------------
| Lấy toàn bộ bàn ăn
|--------------------------------------------------------------------------
*/

$tableSql = "
    SELECT
        ma_ban,
        ten_ban
    FROM ban_an
    ORDER BY ma_ban ASC
";

$tableResult = $conn->query($tableSql);

/*
|--------------------------------------------------------------------------
| Chuẩn bị câu lệnh cập nhật đường dẫn QR
|--------------------------------------------------------------------------
*/

$updateSql = "
    UPDATE ban_an
    SET ma_qr = ?
    WHERE ma_ban = ?
";

$updateStatement = $conn->prepare($updateSql);

$generatedQrCodes = [];

/*
|--------------------------------------------------------------------------
| Tạo QR cho từng bàn
|--------------------------------------------------------------------------
*/

while ($table = $tableResult->fetch_assoc()) {
    $tableId = (int) $table['ma_ban'];
    $tableName = (string) $table['ten_ban'];

    $menuUrl = $baseUrl . '?table=' . $tableId;

    $relativeQrPath = 'qr/table' . $tableId . '.png';

    $absoluteQrPath =
        __DIR__
        . '/'
        . $relativeQrPath;

    /*
     * QR_ECLEVEL_M: mức sửa lỗi trung bình.
     * 6: kích thước mỗi điểm QR.
     * 2: khoảng trắng xung quanh QR.
     */
    QRcode::png(
        $menuUrl,
        $absoluteQrPath,
        QR_ECLEVEL_M,
        6,
        2
    );

    /*
     * Lưu đường dẫn QR vào bảng ban_an.
     */
    $updateStatement->bind_param(
        'si',
        $relativeQrPath,
        $tableId
    );

    $updateStatement->execute();

    $generatedQrCodes[] = [
        'ma_ban' => $tableId,
        'ten_ban' => $tableName,
        'menu_url' => $menuUrl,
        'qr_path' => $relativeQrPath,
    ];
}

$updateStatement->close();

function escapeHtml(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
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

    <title>Tạo mã QR cho bàn ăn</title>

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

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        h1 {
            text-align: center;
        }

        .message {
            margin-bottom: 25px;
            padding: 16px 20px;
            background-color: #d1e7dd;
            border: 1px solid #a3cfbb;
            border-radius: 8px;
            color: #0f5132;
        }

        .qr-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(220px, 1fr)
            );
            gap: 20px;
        }

        .qr-card {
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        .qr-card h2 {
            margin: 0 0 15px;
        }

        .qr-card img {
            width: 200px;
            max-width: 100%;
            height: auto;
        }

        .qr-url {
            margin-top: 12px;
            overflow-wrap: anywhere;
            color: #555555;
            font-size: 13px;
        }
    </style>
</head>

<body>

<main class="container">

    <h1>Tạo mã QR thành công</h1>

    <section class="message">
        Đã tạo
        <strong>
            <?= escapeHtml(count($generatedQrCodes)) ?>
        </strong>
        mã QR và cập nhật đường dẫn vào bảng
        <strong>ban_an</strong>.
    </section>

    <section class="qr-grid">

        <?php foreach ($generatedQrCodes as $qrCode): ?>

            <article class="qr-card">

                <h2>
                    <?= escapeHtml($qrCode['ten_ban']) ?>
                </h2>

                <img
                    src="<?= escapeHtml($qrCode['qr_path']) ?>"
                    alt="Mã QR <?= escapeHtml($qrCode['ten_ban']) ?>"
                >

                <div class="qr-url">
                    <?= escapeHtml($qrCode['menu_url']) ?>
                </div>

            </article>

        <?php endforeach; ?>

    </section>

</main>

</body>

</html>