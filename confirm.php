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
| Kiểm tra đã xác định bàn hay chưa
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['ma_ban'])
    || !isset($_SESSION['ten_ban'])
) {
    exit(
        'Chưa xác định được bàn. '
        . 'Vui lòng quét lại mã QR tại bàn.'
    );
}

$currentTableId = (int) $_SESSION['ma_ban'];
$currentTableName = (string) $_SESSION['ten_ban'];

/*
|--------------------------------------------------------------------------
| Kiểm tra giỏ hàng
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['cart'])
    || !is_array($_SESSION['cart'])
    || empty($_SESSION['cart'])
) {
    $_SESSION['error_message'] =
        'Vui lòng chọn món trước khi đặt.';

    header('Location: cart.php');
    exit;
}

$cart = $_SESSION['cart'];

/*
|--------------------------------------------------------------------------
| Lấy danh sách mã món hợp lệ
|--------------------------------------------------------------------------
*/

$foodIds = array_map(
    'intval',
    array_keys($cart)
);

$foodIds = array_filter(
    $foodIds,
    static fn(int $foodId): bool => $foodId > 0
);

if (empty($foodIds)) {
    $_SESSION['error_message'] =
        'Giỏ hàng không có món ăn hợp lệ.';

    header('Location: cart.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Lấy thông tin món ăn từ MySQL
|--------------------------------------------------------------------------
*/

$idList = implode(',', $foodIds);

$sql = "
    SELECT
        ma_mon,
        ten_mon,
        don_gia,
        trang_thai
    FROM mon_an
    WHERE ma_mon IN ($idList)
    ORDER BY ma_mon ASC
";

$result = $conn->query($sql);

$foods = [];
$totalQuantity = 0;
$totalPrice = 0.0;

while ($food = $result->fetch_assoc()) {
    $foodId = (int) $food['ma_mon'];
    $quantity = (int) ($cart[$foodId] ?? 0);

    if ($quantity <= 0) {
        continue;
    }

    if ($food['trang_thai'] !== 'Còn món') {
        $_SESSION['error_message'] =
            'Món '
            . $food['ten_mon']
            . ' hiện không còn phục vụ.';

        header('Location: cart.php');
        exit;
    }

    $price = (float) $food['don_gia'];
    $subtotal = $price * $quantity;

    $food['so_luong'] = $quantity;
    $food['thanh_tien'] = $subtotal;

    $foods[] = $food;

    $totalQuantity += $quantity;
    $totalPrice += $subtotal;
}

if (empty($foods)) {
    $_SESSION['error_message'] =
        'Không tìm thấy món ăn trong giỏ hàng.';

    header('Location: cart.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Lưu tổng tiền tạm thời vào session
|--------------------------------------------------------------------------
| order.php vẫn phải tính lại trước khi lưu database.
|--------------------------------------------------------------------------
*/

$_SESSION['order_total'] = $totalPrice;

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Xác nhận đơn hàng</title>

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

        button {
            font: inherit;
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
            font-size: 28px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .confirm-card {
            padding: 25px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.09);
        }

        .table-information {
            margin-bottom: 22px;
            padding: 17px 20px;
            background-color: #fff3cd;
            border: 1px solid #ffda6a;
            border-radius: 8px;
            font-size: 18px;
        }

        .notice {
            margin-bottom: 22px;
            padding: 16px 18px;
            background-color: #e7f3ff;
            border-left: 5px solid #0d6efd;
            line-height: 1.5;
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
            padding: 15px;
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

        .summary {
            margin-top: 22px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 9px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 12px;
            font-size: 18px;
        }

        .summary-total {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #cccccc;
            color: #d02020;
            font-size: 23px;
            font-weight: bold;
        }

        .actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: 7px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        .button-cancel {
            background-color: #6c757d;
            color: #ffffff;
        }

        .button-confirm {
            background-color: #198754;
            color: #ffffff;
        }

        @media (max-width: 600px) {
            .container {
                padding: 18px 12px;
            }

            .confirm-card {
                padding: 17px;
            }

            .actions {
                flex-direction: column;
            }

            .actions .button,
            .actions form,
            .actions form .button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<header class="header">
    <div class="header-inner">
        <h1>Xác nhận đơn hàng</h1>
    </div>
</header>

<main class="container">

    <section class="confirm-card">

        <div class="table-information">
            Đơn hàng của:

            <strong>
                <?= escapeHtml($currentTableName) ?>
            </strong>
        </div>

        <div class="notice">
            Vui lòng kiểm tra lại món ăn, số lượng và tổng tiền
            trước khi xác nhận đặt món.
        </div>

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

                    <?php foreach ($foods as $food): ?>

                        <tr>

                            <td>
                                <?= escapeHtml($food['ten_mon']) ?>
                            </td>

                            <td class="money">
                                <?= number_format(
                                    (float) $food['don_gia'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                                VNĐ
                            </td>

                            <td>
                                <?= escapeHtml($food['so_luong']) ?>
                            </td>

                            <td class="money">
                                <?= number_format(
                                    (float) $food['thanh_tien'],
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

        <section class="summary">

            <div class="summary-row">
                <span>Tổng số lượng:</span>

                <strong>
                    <?= escapeHtml($totalQuantity) ?> món
                </strong>
            </div>

            <div class="summary-row summary-total">
                <span>Tổng tiền:</span>

                <span>
                    <?= number_format(
                        $totalPrice,
                        0,
                        ',',
                        '.'
                    ) ?>
                    VNĐ
                </span>
            </div>

        </section>

        <section class="actions">

            <a
                class="button button-cancel"
                href="cart.php"
            >
                Hủy
            </a>

            <form
                action="order.php"
                method="post"
                onsubmit="
                    return confirm(
                        'Bạn xác nhận gửi đơn hàng này?'
                    );
                "
            >
                <button
                    class="button button-confirm"
                    type="submit"
                >
                    Xác nhận đặt món
                </button>
            </form>

        </section>

    </section>

</main>

</body>

</html>