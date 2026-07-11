<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| Hàm hiển thị dữ liệu an toàn
|--------------------------------------------------------------------------
*/

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
| Kiểm tra thông tin bàn trong session
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['ma_ban'])
    || !isset($_SESSION['ten_ban'])
) {
    exit(
        'Không xác định được bàn. '
        . 'Vui lòng quét lại mã QR tại bàn.'
    );
}

$currentTableId = (int) $_SESSION['ma_ban'];
$currentTableName = (string) $_SESSION['ten_ban'];

/*
|--------------------------------------------------------------------------
| Nhận mã đơn từ URL
|--------------------------------------------------------------------------
| Ví dụ:
| success.php?ma_don=1
|--------------------------------------------------------------------------
*/

$orderId = filter_input(
    INPUT_GET,
    'ma_don',
    FILTER_VALIDATE_INT
);

if (
    $orderId === false
    || $orderId === null
    || $orderId <= 0
) {
    exit('Mã đơn hàng không hợp lệ.');
}

/*
|--------------------------------------------------------------------------
| Kiểm tra đây có phải đơn vừa được tạo trong session không
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['last_order_id'])
    || (int) $_SESSION['last_order_id'] !== $orderId
) {
    exit(
        'Không có quyền xem đơn hàng này '
        . 'hoặc phiên làm việc đã hết hạn.'
    );
}

/*
|--------------------------------------------------------------------------
| Lấy thông tin đơn hàng
|--------------------------------------------------------------------------
| Đồng thời kiểm tra đơn thuộc đúng bàn hiện tại.
|--------------------------------------------------------------------------
*/

$orderSql = "
    SELECT
        ma_don,
        ma_ban,
        thoi_gian_dat,
        tong_tien,
        trang_thai
    FROM don_hang
    WHERE ma_don = ?
      AND ma_ban = ?
    LIMIT 1
";

$orderStatement = $conn->prepare($orderSql);

$orderStatement->bind_param(
    'ii',
    $orderId,
    $currentTableId
);

$orderStatement->execute();

$orderResult = $orderStatement->get_result();

$order = $orderResult->fetch_assoc();

$orderStatement->close();

if (!$order) {
    exit('Không tìm thấy đơn hàng.');
}

/*
|--------------------------------------------------------------------------
| Lấy chi tiết các món thuộc đơn hàng
|--------------------------------------------------------------------------
*/

$detailSql = "
    SELECT
        ct.ma_chi_tiet,
        ct.ma_mon,
        m.ten_mon,
        ct.so_luong,
        ct.don_gia,
        ct.so_luong * ct.don_gia AS thanh_tien
    FROM chi_tiet_don_hang AS ct
    INNER JOIN mon_an AS m
        ON m.ma_mon = ct.ma_mon
    WHERE ct.ma_don = ?
    ORDER BY ct.ma_chi_tiet ASC
";

$detailStatement = $conn->prepare($detailSql);

$detailStatement->bind_param(
    'i',
    $orderId
);

$detailStatement->execute();

$detailResult = $detailStatement->get_result();

$orderDetails = [];

while ($detail = $detailResult->fetch_assoc()) {
    $orderDetails[] = $detail;
}

$detailStatement->close();

/*
|--------------------------------------------------------------------------
| Đường dẫn quay lại menu đúng bàn
|--------------------------------------------------------------------------
*/

$menuUrl = 'menu.php?table=' . $currentTableId;

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Đặt món thành công</title>

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
            background-color: #202428;
            color: #ffffff;
        }

        .header-inner {
            max-width: 1000px;
            margin: 0 auto;
            padding: 22px 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 26px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 35px 20px;
        }

        .success-card {
            overflow: hidden;
            background-color: #ffffff;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, 0.1);
        }

        .success-heading {
            padding: 35px 24px;
            background-color: #198754;
            color: #ffffff;
            text-align: center;
        }

        .success-icon {
            margin-bottom: 10px;
            font-size: 52px;
        }

        .success-heading h2 {
            margin: 0 0 10px;
            font-size: 32px;
        }

        .success-heading p {
            margin: 0;
            line-height: 1.5;
        }

        .order-content {
            padding: 28px;
        }

        .information-grid {
            display: grid;
            grid-template-columns: repeat(
                2,
                minmax(0, 1fr)
            );
            gap: 15px;
            margin-bottom: 28px;
        }

        .information-item {
            padding: 17px;
            background-color: #f5f5f5;
            border-radius: 8px;
        }

        .information-label {
            display: block;
            margin-bottom: 7px;
            color: #666666;
            font-size: 14px;
        }

        .information-value {
            font-size: 18px;
            font-weight: bold;
        }

        .status {
            color: #d98200;
        }

        .detail-title {
            margin: 0 0 18px;
            font-size: 23px;
        }

        .table-wrapper {
            overflow-x: auto;
            border: 1px solid #dddddd;
            border-radius: 9px;
        }

        .order-table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
        }

        .order-table th,
        .order-table td {
            padding: 14px;
            border-bottom: 1px solid #dddddd;
            text-align: left;
        }

        .order-table th {
            background-color: #202428;
            color: #ffffff;
        }

        .order-table tbody tr:last-child td {
            border-bottom: none;
        }

        .money {
            white-space: nowrap;
        }

        .total {
            margin-top: 22px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            background-color: #fff3cd;
            border-radius: 8px;
            color: #c82020;
            font-size: 23px;
            font-weight: bold;
        }

        .message {
            margin-top: 22px;
            padding: 17px;
            background-color: #e7f3ff;
            border-left: 5px solid #0d6efd;
            line-height: 1.6;
        }

        .actions {
            margin-top: 25px;
            text-align: center;
        }

        .button {
            display: inline-block;
            padding: 13px 22px;
            background-color: #198754;
            color: #ffffff;
            border-radius: 7px;
            text-decoration: none;
            font-weight: bold;
        }

        @media (max-width: 650px) {
            .container {
                padding: 20px 12px;
            }

            .order-content {
                padding: 18px;
            }

            .information-grid {
                grid-template-columns: 1fr;
            }

            .success-heading h2 {
                font-size: 27px;
            }

            .total {
                font-size: 19px;
            }

            .button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header class="header">
    <div class="header-inner">
        <h1>Restaurant QR Order</h1>
    </div>
</header>

<main class="container">

    <section class="success-card">

        <div class="success-heading">

            <div class="success-icon">
                ✅
            </div>

            <h2>Đặt món thành công</h2>

            <p>
                Đơn hàng đã được gửi đến nhà hàng.
            </p>

        </div>

        <div class="order-content">

            <section class="information-grid">

                <div class="information-item">

                    <span class="information-label">
                        Mã đơn hàng
                    </span>

                    <span class="information-value">
                        #<?= escapeHtml($order['ma_don']) ?>
                    </span>

                </div>

                <div class="information-item">

                    <span class="information-label">
                        Bàn đặt món
                    </span>

                    <span class="information-value">
                        <?= escapeHtml($currentTableName) ?>
                    </span>

                </div>

                <div class="information-item">

                    <span class="information-label">
                        Thời gian đặt
                    </span>

                    <span class="information-value">
                        <?= escapeHtml(
                            date(
                                'd/m/Y H:i:s',
                                strtotime(
                                    (string) $order['thoi_gian_dat']
                                )
                            )
                        ) ?>
                    </span>

                </div>

                <div class="information-item">

                    <span class="information-label">
                        Trạng thái
                    </span>

                    <span class="information-value status">
                        <?= escapeHtml($order['trang_thai']) ?>
                    </span>

                </div>

            </section>

            <h3 class="detail-title">
                Chi tiết đơn hàng
            </h3>

            <div class="table-wrapper">

                <table class="order-table">

                    <thead>
                        <tr>
                            <th>Món ăn</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th>Thành tiền</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($orderDetails as $detail): ?>

                            <tr>

                                <td>
                                    <?= escapeHtml($detail['ten_mon']) ?>
                                </td>

                                <td class="money">
                                    <?= number_format(
                                        (float) $detail['don_gia'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                    VNĐ
                                </td>

                                <td>
                                    <?= escapeHtml($detail['so_luong']) ?>
                                </td>

                                <td class="money">
                                    <?= number_format(
                                        (float) $detail['thanh_tien'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                    VNĐ
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <div class="total">

                <span>Tổng tiền:</span>

                <span>
                    <?= number_format(
                        (float) $order['tong_tien'],
                        0,
                        ',',
                        '.'
                    ) ?>
                    VNĐ
                </span>

            </div>

            <div class="message">
                Đơn hàng đang ở trạng thái
                <strong>
                    <?= escapeHtml($order['trang_thai']) ?>
                </strong>.
                Vui lòng chờ nhân viên tiếp nhận và phục vụ.
            </div>

            <div class="actions">

                <a
                    class="button"
                    href="<?= escapeHtml($menuUrl) ?>"
                >
                    Gọi thêm món
                </a>

            </div>

        </div>

    </section>

</main>

</body>

</html>