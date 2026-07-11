<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| Chỉ nhận yêu cầu POST từ trang confirm.php
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Kiểm tra thông tin bàn
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['ma_ban'])
    || (int) $_SESSION['ma_ban'] <= 0
) {
    exit(
        'Không xác định được bàn đặt món. '
        . 'Vui lòng quét lại mã QR tại bàn.'
    );
}

$tableId = (int) $_SESSION['ma_ban'];

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

$orderItems = [];
$totalPrice = 0.0;
$orderId = 0;
$transactionStarted = false;

try {
    /*
    |--------------------------------------------------------------------------
    | Bắt đầu transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();
    $transactionStarted = true;

    /*
    |--------------------------------------------------------------------------
    | Chuẩn bị truy vấn lấy thông tin món
    |--------------------------------------------------------------------------
    */

    $foodSql = "
        SELECT
            ma_mon,
            ten_mon,
            don_gia,
            trang_thai
        FROM mon_an
        WHERE ma_mon = ?
        LIMIT 1
    ";

    $foodStatement = $conn->prepare($foodSql);

    /*
    |--------------------------------------------------------------------------
    | Kiểm tra từng món và tính lại tổng tiền
    |--------------------------------------------------------------------------
    | Không sử dụng giá gửi từ trình duyệt.
    | Đơn giá phải được lấy lại trực tiếp từ MySQL.
    |--------------------------------------------------------------------------
    */

    foreach ($cart as $foodId => $quantity) {
        $foodId = (int) $foodId;
        $quantity = (int) $quantity;

        if (
            $foodId <= 0
            || $quantity < 1
            || $quantity > 99
        ) {
            throw new RuntimeException(
                'Dữ liệu món ăn hoặc số lượng không hợp lệ.'
            );
        }

        $foodStatement->bind_param(
            'i',
            $foodId
        );

        $foodStatement->execute();

        $foodResult = $foodStatement->get_result();

        $food = $foodResult->fetch_assoc();

        if (!$food) {
            throw new RuntimeException(
                'Món ăn có mã '
                . $foodId
                . ' không tồn tại.'
            );
        }

        if ($food['trang_thai'] !== 'Còn món') {
            throw new RuntimeException(
                'Món '
                . $food['ten_mon']
                . ' hiện không còn phục vụ.'
            );
        }

        $price = (float) $food['don_gia'];
        $subtotal = $price * $quantity;

        $orderItems[] = [
            'ma_mon' => $foodId,
            'ten_mon' => (string) $food['ten_mon'],
            'so_luong' => $quantity,
            'don_gia' => $price,
            'thanh_tien' => $subtotal,
        ];

        $totalPrice += $subtotal;
    }

    $foodStatement->close();

    if (empty($orderItems) || $totalPrice <= 0) {
        throw new RuntimeException(
            'Đơn hàng không có món ăn hợp lệ.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Thêm một bản ghi vào bảng don_hang
    |--------------------------------------------------------------------------
    */

    $status = 'Mới đặt';

    $orderSql = "
        INSERT INTO don_hang (
            ma_ban,
            tong_tien,
            trang_thai
        )
        VALUES (?, ?, ?)
    ";

    $orderStatement = $conn->prepare($orderSql);

    $orderStatement->bind_param(
        'ids',
        $tableId,
        $totalPrice,
        $status
    );

    $orderStatement->execute();

    /*
     * Lấy mã đơn hàng MySQL vừa tự sinh.
     */
    $orderId = (int) $conn->insert_id;

    $orderStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Thêm từng món vào bảng chi_tiet_don_hang
    |--------------------------------------------------------------------------
    */

    $detailSql = "
        INSERT INTO chi_tiet_don_hang (
            ma_don,
            ma_mon,
            so_luong,
            don_gia
        )
        VALUES (?, ?, ?, ?)
    ";

    $detailStatement = $conn->prepare($detailSql);

    foreach ($orderItems as $item) {
        $foodId = (int) $item['ma_mon'];
        $quantity = (int) $item['so_luong'];
        $price = (float) $item['don_gia'];

        $detailStatement->bind_param(
            'iiid',
            $orderId,
            $foodId,
            $quantity,
            $price
        );

        $detailStatement->execute();
    }

    $detailStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Xác nhận toàn bộ dữ liệu
    |--------------------------------------------------------------------------
    */

    $conn->commit();
    $transactionStarted = false;

    /*
    |--------------------------------------------------------------------------
    | Xóa giỏ hàng sau khi lưu thành công
    |--------------------------------------------------------------------------
    */

    $_SESSION['cart'] = [];

    /*
     * Giữ ma_ban và ten_ban để khách có thể gọi thêm món.
     */
    $_SESSION['last_order_id'] = $orderId;

    /*
    |--------------------------------------------------------------------------
    | Chuyển đến trang thành công
    |--------------------------------------------------------------------------
    */

    header(
        'Location: success.php?ma_don='
        . $orderId
    );

    exit;
} catch (Throwable $exception) {
    /*
     * Nếu bất kỳ bước nào lỗi, hủy toàn bộ thay đổi.
     */
    if ($transactionStarted) {
        $conn->rollback();
    }

    $_SESSION['error_message'] =
        'Đặt món thất bại: '
        . $exception->getMessage();

    header('Location: cart.php');
    exit;
}