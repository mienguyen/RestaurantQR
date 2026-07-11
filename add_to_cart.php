<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| Chỉ cho phép gửi dữ liệu bằng phương thức POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: menu.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Kiểm tra đã xác định bàn chưa
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['ma_ban'])
    || (int) $_SESSION['ma_ban'] <= 0
) {
    exit(
        'Chưa xác định được bàn. '
        . 'Vui lòng quét lại mã QR tại bàn.'
    );
}

/*
|--------------------------------------------------------------------------
| Nhận mã món và số lượng từ form
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Kiểm tra mã món
|--------------------------------------------------------------------------
*/

if (
    $foodId === false
    || $foodId === null
    || $foodId <= 0
) {
    exit('Mã món ăn không hợp lệ.');
}

/*
|--------------------------------------------------------------------------
| Kiểm tra số lượng
|--------------------------------------------------------------------------
*/

if (
    $quantity === false
    || $quantity === null
    || $quantity <= 0
    || $quantity > 99
) {
    exit('Số lượng món ăn phải nằm trong khoảng từ 1 đến 99.');
}

/*
|--------------------------------------------------------------------------
| Kiểm tra món ăn có tồn tại và còn bán không
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        ma_mon,
        ten_mon
    FROM mon_an
    WHERE ma_mon = ?
      AND trang_thai = 'Còn món'
    LIMIT 1
";

$statement = $conn->prepare($sql);

$statement->bind_param(
    'i',
    $foodId
);

$statement->execute();

$result = $statement->get_result();

$food = $result->fetch_assoc();

$statement->close();

if (!$food) {
    exit(
        'Món ăn không tồn tại hoặc hiện đã hết món.'
    );
}

/*
|--------------------------------------------------------------------------
| Khởi tạo giỏ hàng trong session
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['cart'])
    || !is_array($_SESSION['cart'])
) {
    $_SESSION['cart'] = [];
}

/*
|--------------------------------------------------------------------------
| Thêm món vào giỏ hàng
|--------------------------------------------------------------------------
| Cấu trúc:
|
| $_SESSION['cart'][ma_mon] = so_luong;
|
| Ví dụ:
| $_SESSION['cart'][4] = 2;
|--------------------------------------------------------------------------
*/

$currentQuantity = isset($_SESSION['cart'][$foodId])
    ? (int) $_SESSION['cart'][$foodId]
    : 0;

$newQuantity = $currentQuantity + $quantity;

$_SESSION['cart'][$foodId] = min(
    $newQuantity,
    99
);

/*
|--------------------------------------------------------------------------
| Tạo thông báo sau khi thêm món
|--------------------------------------------------------------------------
*/

$_SESSION['success_message'] =
    'Đã thêm '
    . $food['ten_mon']
    . ' vào giỏ hàng.';

/*
|--------------------------------------------------------------------------
| Quay lại trang menu
|--------------------------------------------------------------------------
*/

header('Location: menu.php');
exit;