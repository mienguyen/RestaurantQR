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
| Kiểm tra đã xác định bàn chưa
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
| Khởi tạo giỏ hàng
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['cart'])
    || !is_array($_SESSION['cart'])
) {
    $_SESSION['cart'] = [];
}

$cart = $_SESSION['cart'];

/*
|--------------------------------------------------------------------------
| Xử lý cập nhật, xóa món và xóa toàn bộ
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /*
     * Cập nhật số lượng
     */
    if ($action === 'update') {
        $foodId = filter_input(
            INPUT_POST,
            'ma_mon',
            FILTER_VALIDATE_INT
        );

        $quantity = filter_input(
            INPUT_POST,
            'so_luong',
            FILTER_VALIDATE_INT
        );

        if (
            $foodId === false
            || $foodId === null
            || !isset($cart[$foodId])
        ) {
            $_SESSION['error_message'] =
                'Món ăn trong giỏ hàng không hợp lệ.';
        } elseif (
            $quantity === false
            || $quantity === null
            || $quantity < 1
            || $quantity > 99
        ) {
            $_SESSION['error_message'] =
                'Số lượng phải nằm trong khoảng từ 1 đến 99.';
        } else {
            $cart[$foodId] = $quantity;
            $_SESSION['cart'] = $cart;

            $_SESSION['success_message'] =
                'Đã cập nhật số lượng món ăn.';
        }

        header('Location: cart.php');
        exit;
    }

    /*
     * Xóa một món
     */
    if ($action === 'remove') {
        $foodId = filter_input(
            INPUT_POST,
            'ma_mon',
            FILTER_VALIDATE_INT
        );

        if (
            $foodId !== false
            && $foodId !== null
            && isset($cart[$foodId])
        ) {
            unset($cart[$foodId]);

            $_SESSION['cart'] = $cart;

            $_SESSION['success_message'] =
                'Đã xóa món khỏi giỏ hàng.';
        }

        header('Location: cart.php');
        exit;
    }

    /*
     * Xóa toàn bộ giỏ hàng
     */
    if ($action === 'clear') {
        $_SESSION['cart'] = [];

        $_SESSION['success_message'] =
            'Đã xóa toàn bộ giỏ hàng.';

        header('Location: cart.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Lấy thông báo từ session
|--------------------------------------------------------------------------
*/

$successMessage = isset($_SESSION['success_message'])
    ? (string) $_SESSION['success_message']
    : '';

$errorMessage = isset($_SESSION['error_message'])
    ? (string) $_SESSION['error_message']
    : '';

unset(
    $_SESSION['success_message'],
    $_SESSION['error_message']
);

/*
|--------------------------------------------------------------------------
| Lấy thông tin các món trong giỏ hàng
|--------------------------------------------------------------------------
*/

$foods = [];
$totalPrice = 0;
$totalQuantity = 0;

if (!empty($cart)) {
    /*
     * Các khóa trong cart là mã món.
     * Chuyển toàn bộ về số nguyên trước khi đưa vào câu SQL.
     */
    $foodIds = array_map(
        'intval',
        array_keys($cart)
    );

    $foodIds = array_filter(
        $foodIds,
        static fn(int $foodId): bool => $foodId > 0
    );

    if (!empty($foodIds)) {
        $idList = implode(',', $foodIds);

        $sql = "
            SELECT
                ma_mon,
                ten_mon,
                don_gia,
                mo_ta,
                hinh_anh
            FROM mon_an
            WHERE ma_mon IN ($idList)
            ORDER BY ma_mon ASC
        ";

        $result = $conn->query($sql);

        while ($food = $result->fetch_assoc()) {
            $foodId = (int) $food['ma_mon'];
            $quantity = (int) ($cart[$foodId] ?? 0);
            $price = (float) $food['don_gia'];
            $subtotal = $price * $quantity;

            $food['so_luong'] = $quantity;
            $food['thanh_tien'] = $subtotal;

            $foods[] = $food;

            $totalQuantity += $quantity;
            $totalPrice += $subtotal;
        }
    }
}

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

    <title>Giỏ hàng</title>

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

        button,
        input {
            font: inherit;
        }

        .header {
            background-color: #202428;
            color: #ffffff;
        }

        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 22px 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .table-information {
            margin-bottom: 22px;
            padding: 17px 20px;
            background-color: #fff3cd;
            border: 1px solid #ffda6a;
            border-radius: 8px;
            font-size: 18px;
        }

        .success-message,
        .error-message {
            margin-bottom: 20px;
            padding: 15px 18px;
            border-radius: 8px;
        }

        .success-message {
            background-color: #d1e7dd;
            border: 1px solid #a3cfbb;
            color: #0f5132;
        }

        .error-message {
            background-color: #f8d7da;
            border: 1px solid #f1aeb5;
            color: #842029;
        }

        .empty-cart {
            padding: 40px 20px;
            background-color: #ffffff;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        .table-wrapper {
            overflow-x: auto;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        .cart-table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
        }

        .cart-table th,
        .cart-table td {
            padding: 16px;
            border-bottom: 1px solid #dddddd;
            text-align: left;
            vertical-align: middle;
        }

        .cart-table th {
            background-color: #202428;
            color: #ffffff;
        }

        .food-name {
            font-weight: bold;
        }

        .money {
            white-space: nowrap;
        }

        .quantity-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quantity-input {
            width: 75px;
            padding: 9px;
            border: 1px solid #cccccc;
            border-radius: 6px;
            text-align: center;
        }

        .button {
            display: inline-block;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        .button-update {
            background-color: #0d6efd;
        }

        .button-remove,
        .button-clear {
            background-color: #dc3545;
        }

        .button-menu {
            background-color: #6c757d;
        }

        .button-confirm {
            background-color: #198754;
        }

        .summary {
            margin-top: 24px;
            padding: 22px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 12px;
            font-size: 18px;
        }

        .summary-total {
            margin-top: 17px;
            padding-top: 17px;
            border-top: 1px solid #dddddd;
            color: #d02020;
            font-size: 23px;
            font-weight: bold;
        }

        .actions {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .actions-left,
        .actions-right {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        @media (max-width: 650px) {
            .container {
                padding: 20px 14px;
            }

            .actions,
            .actions-left,
            .actions-right {
                width: 100%;
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
        <h1>🛒 Giỏ hàng</h1>
    </div>
</header>

<main class="container">

    <section class="table-information">
        Đơn hàng của:

        <strong>
            <?= escapeHtml($currentTableName) ?>
        </strong>
    </section>

    <?php if ($successMessage !== ''): ?>

        <section class="success-message">
            <?= escapeHtml($successMessage) ?>
        </section>

    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>

        <section class="error-message">
            <?= escapeHtml($errorMessage) ?>
        </section>

    <?php endif; ?>

    <?php if (empty($foods)): ?>

        <section class="empty-cart">

            <p>Giỏ hàng hiện đang trống.</p>

            <a
                class="button button-menu"
                href="<?= escapeHtml($menuUrl) ?>"
            >
                Quay lại thực đơn
            </a>

        </section>

    <?php else: ?>

        <div class="table-wrapper">

            <table class="cart-table">

                <thead>
                    <tr>
                        <th>Món ăn</th>
                        <th>Đơn giá</th>
                        <th>Số lượng</th>
                        <th>Thành tiền</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($foods as $food): ?>

                        <tr>

                            <td class="food-name">
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

                                <form
                                    class="quantity-form"
                                    action="cart.php"
                                    method="post"
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update"
                                    >

                                    <input
                                        type="hidden"
                                        name="ma_mon"
                                        value="<?= escapeHtml(
                                            $food['ma_mon']
                                        ) ?>"
                                    >

                                    <input
                                        class="quantity-input"
                                        type="number"
                                        name="so_luong"
                                        value="<?= escapeHtml(
                                            $food['so_luong']
                                        ) ?>"
                                        min="1"
                                        max="99"
                                        required
                                    >

                                    <button
                                        class="button button-update"
                                        type="submit"
                                    >
                                        Cập nhật
                                    </button>
                                </form>

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

                            <td>

                                <form
                                    action="cart.php"
                                    method="post"
                                    onsubmit="
                                        return confirm(
                                            'Bạn có chắc muốn xóa món này?'
                                        );
                                    "
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="remove"
                                    >

                                    <input
                                        type="hidden"
                                        name="ma_mon"
                                        value="<?= escapeHtml(
                                            $food['ma_mon']
                                        ) ?>"
                                    >

                                    <button
                                        class="button button-remove"
                                        type="submit"
                                    >
                                        Xóa
                                    </button>
                                </form>

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
                        (float) $totalPrice,
                        0,
                        ',',
                        '.'
                    ) ?>
                    VNĐ
                </span>
            </div>

        </section>

        <section class="actions">

            <div class="actions-left">

                <a
                    class="button button-menu"
                    href="<?= escapeHtml($menuUrl) ?>"
                >
                    Tiếp tục chọn món
                </a>

                <form
                    action="cart.php"
                    method="post"
                    onsubmit="
                        return confirm(
                            'Bạn có chắc muốn xóa toàn bộ giỏ hàng?'
                        );
                    "
                >
                    <input
                        type="hidden"
                        name="action"
                        value="clear"
                    >

                    <button
                        class="button button-clear"
                        type="submit"
                    >
                        Xóa toàn bộ
                    </button>
                </form>

            </div>

            <div class="actions-right">

                <a
                    class="button button-confirm"
                    href="confirm.php"
                >
                    Đặt món
                </a>

            </div>

        </section>

    <?php endif; ?>

</main>

</body>

</html>