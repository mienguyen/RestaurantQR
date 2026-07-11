<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$sql = 'SELECT DATABASE() AS database_name';

$result = $conn->query($sql);

$row = $result->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Kiểm tra kết nối MySQL</title>
</head>

<body>

    <h1>Kết nối MySQL thành công</h1>

    <p>
        Cơ sở dữ liệu đang sử dụng:

        <strong>
            <?= htmlspecialchars(
                (string) $row['database_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </p>

</body>

</html>