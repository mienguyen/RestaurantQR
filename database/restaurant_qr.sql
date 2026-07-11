CREATE DATABASE restaurant_qr
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE restaurant_qr;

CREATE TABLE ban_an (
    ma_ban INT AUTO_INCREMENT PRIMARY KEY,
    ten_ban VARCHAR(50) NOT NULL,
    ma_qr VARCHAR(255) NULL,
    trang_thai VARCHAR(50) NOT NULL DEFAULT 'Trống'
) ENGINE=InnoDB;

CREATE TABLE mon_an (
    ma_mon INT AUTO_INCREMENT PRIMARY KEY,
    ten_mon VARCHAR(100) NOT NULL,
    don_gia DECIMAL(10,2) NOT NULL,
    mo_ta TEXT NULL,
    hinh_anh VARCHAR(255) NULL,
    trang_thai VARCHAR(50) NOT NULL DEFAULT 'Còn món'
) ENGINE=InnoDB;

CREATE TABLE don_hang (
    ma_don INT AUTO_INCREMENT PRIMARY KEY,
    ma_ban INT NOT NULL,
    thoi_gian_dat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tong_tien DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    trang_thai VARCHAR(50) NOT NULL DEFAULT 'Mới đặt',

    CONSTRAINT fk_donhang_banan
        FOREIGN KEY (ma_ban)
        REFERENCES ban_an(ma_ban)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE chi_tiet_don_hang (
    ma_chi_tiet INT AUTO_INCREMENT PRIMARY KEY,
    ma_don INT NOT NULL,
    ma_mon INT NOT NULL,
    so_luong INT NOT NULL,
    don_gia DECIMAL(10,2) NOT NULL,

    CONSTRAINT fk_chitiet_donhang
        FOREIGN KEY (ma_don)
        REFERENCES don_hang(ma_don)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_chitiet_monan
        FOREIGN KEY (ma_mon)
        REFERENCES mon_an(ma_mon)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;