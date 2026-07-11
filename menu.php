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
| Nhận mã bàn từ URL
|--------------------------------------------------------------------------
| Ví dụ:
| menu.php?table=1
*/

if (isset($_GET['table'])) {
    $tableId = filter_input(
        INPUT_GET,
        'table',
        FILTER_VALIDATE_INT
    );

    if (
        $tableId === false
        || $tableId === null
        || $tableId <= 0
    ) {
        exit(
            'Mã bàn không hợp lệ. '
            . 'Vui lòng quét lại mã QR tại bàn.'
        );
    }

    /*
     * Kiểm tra bàn có tồn tại trong cơ sở dữ liệu hay không.
     */
    $tableSql = "
        SELECT
            ma_ban,
            ten_ban
        FROM ban_an
        WHERE ma_ban = ?
        LIMIT 1
    ";

    $tableStatement = $conn->prepare($tableSql);

    $tableStatement->bind_param(
        'i',
        $tableId
    );

    $tableStatement->execute();

    $tableResult = $tableStatement->get_result();

    $table = $tableResult->fetch_assoc();

    $tableStatement->close();

    if (!$table) {
        exit(
            'Bàn không tồn tại trong hệ thống. '
            . 'Vui lòng quét lại mã QR hoặc liên hệ nhân viên.'
        );
    }

    /*
     * Lưu bàn vào session để sử dụng ở các trang tiếp theo.
     */
    $_SESSION['ma_ban'] = (int) $table['ma_ban'];
    $_SESSION['ten_ban'] = (string) $table['ten_ban'];
}

/*
|--------------------------------------------------------------------------
| Kiểm tra session bàn
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['ma_ban'])
    || !isset($_SESSION['ten_ban'])
) {
    exit(
        'Chưa xác định được bàn. '
        . 'Vui lòng quét mã QR được đặt tại bàn.'
    );
}

$currentTableId = (int) $_SESSION['ma_ban'];
$currentTableName = (string) $_SESSION['ten_ban'];
$successMessage = isset($_SESSION['success_message'])
    ? (string) $_SESSION['success_message']
    : '';

unset($_SESSION['success_message']);

$cartQuantity = isset($_SESSION['cart'])
    && is_array($_SESSION['cart'])
        ? array_sum($_SESSION['cart'])
        : 0;

/*
|--------------------------------------------------------------------------
| Lấy danh sách món còn bán
|--------------------------------------------------------------------------
*/

$foodSql = "
    SELECT
        ma_mon,
        ten_mon,
        don_gia,
        mo_ta,
        hinh_anh,
        trang_thai
    FROM mon_an
    WHERE trang_thai = 'Còn món'
    ORDER BY ma_mon ASC
";

$foodResult = $conn->query($foodSql);

$foods = [];

while ($food = $foodResult->fetch_assoc()) {
    $foods[] = $food;
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

    <title>Thực đơn nhà hàng</title>

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
            padding: 22px;
            background-color: #981d1d;
            color: #ffffff;
        }

        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header h1 {
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 20px;
        }

        .table-information {
            margin-bottom: 25px;
            padding: 18px 20px;
            background-color: #fff3cd;
            border: 1px solid #ffda6a;
            border-radius: 8px;
            font-size: 18px;
        }

        .food-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(250px, 1fr)
            );
            gap: 22px;
        }

        .food-card {
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
        }

        .food-image {
            width: 100%;
            height: 210px;
            display: block;
            object-fit: cover;
            background-color: #eeeeee;
        }

        .image-placeholder {
            width: 100%;
            height: 210px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e9e9e9;
            color: #666666;
        }

        .food-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .food-name {
            margin: 0 0 10px;
            font-size: 22px;
        }

        .food-description {
            flex: 1;
            margin: 0 0 14px;
            color: #606060;
            line-height: 1.5;
        }

        .food-price {
            margin-bottom: 16px;
            color: #d02020;
            font-size: 21px;
            font-weight: bold;
        }

        .add-form {
            display: flex;
            gap: 10px;
        }

        .quantity-input {
            width: 75px;
            padding: 11px;
            border: 1px solid #cccccc;
            border-radius: 6px;
            text-align: center;
        }

        .add-button {
            flex: 1;
            padding: 11px;
            border: none;
            border-radius: 6px;
            background-color: #198754;
            color: #ffffff;
            font-weight: bold;
            cursor: pointer;
        }

        .empty-message {
            padding: 30px;
            background-color: #ffffff;
            border-radius: 8px;
            text-align: center;
        }

        @media (max-width: 600px) {
            .food-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 18px 14px;
            }
        }
        .success-message {
            margin-bottom: 20px;
            padding: 16px 20px;
            background-color: #d1e7dd;
            border: 1px solid #a3cfbb;
            border-radius: 8px;
            color: #0f5132;
        }
        .cart-summary {
            margin-top: 10px;
            font-size: 16px;
        }
    </style>
</head>

<body>

<header class="header">
    <div class="header-inner">
        <h1>Thực đơn nhà hàng</h1>
    </div>
</header>

<main class="container">

    <section class="table-information">

    <div>
        Bạn đang gọi món tại:

        <strong>
            <?= escapeHtml($currentTableName) ?>
        </strong>
    </div>

    <div class="cart-summary">
        Giỏ hàng hiện có:

        <strong>
            <?= escapeHtml($cartQuantity) ?> món
        </strong>
    </div>

</section>

<?php if ($successMessage !== ''): ?>

    <section class="success-message">
        <?= escapeHtml($successMessage) ?>
    </section>

<?php endif; ?>

    <?php if (empty($foods)): ?>

        <div class="empty-message">
            Hiện tại chưa có món ăn nào đang được phục vụ.
        </div>

    <?php else: ?>

        <section class="food-grid">

            <?php foreach ($foods as $food): ?>

                <?php
                $imageName = basename(
                    (string) ($food['hinh_anh'] ?? '')
                );

                $imagePhysicalPath =
                    __DIR__
                    . '/assets/images/'
                    . $imageName;

                $hasImage =
                    $imageName !== ''
                    && is_file($imagePhysicalPath);
                ?>

                <article class="food-card">

                    <?php if ($hasImage): ?>

                        <img
                            class="food-image"
                            src="assets/images/<?= escapeHtml($imageName) ?>"
                            alt="<?= escapeHtml($food['ten_mon']) ?>"
                        >

                    <?php else: ?>

                        <div class="image-placeholder">
                            Chưa có hình ảnh
                        </div>

                    <?php endif; ?>

                    <div class="food-content">

                        <h2 class="food-name">
                            <?= escapeHtml($food['ten_mon']) ?>
                        </h2>

                        <p class="food-description">
                            <?= escapeHtml(
                                $food['mo_ta']
                                ?? 'Chưa có mô tả.'
                            ) ?>
                        </p>

                        <div class="food-price">
                            <?= number_format(
                                (float) $food['don_gia'],
                                0,
                                ',',
                                '.'
                            ) ?>
                            VNĐ
                        </div>

                        <form
                            class="add-form"
                            action="add_to_cart.php"
                            method="post"
                        >
                            <input
                                type="hidden"
                                name="ma_mon"
                                value="<?= escapeHtml($food['ma_mon']) ?>"
                            >

                            <input
                                class="quantity-input"
                                type="number"
                                name="so_luong"
                                value="1"
                                min="1"
                                max="99"
                                required
                            >

                            <button
                                class="add-button"
                                type="submit"
                            >
                                Thêm vào giỏ
                            </button>
                        </form>

                    </div>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>

</body>

</html>