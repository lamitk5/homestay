-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 21, 2025 lúc 11:02 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `khachsan`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `datphong`
--

CREATE TABLE `datphong` (
  `madp` int(11) NOT NULL,
  `makhs` int(11) DEFAULT NULL,
  `maphong` int(11) DEFAULT NULL,
  `ngaynhan` date DEFAULT NULL,
  `ngaytra` date DEFAULT NULL,
  `trangthai_dat` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hoadon`
--

CREATE TABLE `hoadon` (
  `mahd` int(11) NOT NULL,
  `madp` int(11) DEFAULT NULL,
  `maql` int(11) DEFAULT NULL,
  `ngaylaphd` datetime DEFAULT NULL,
  `tongtien` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khachhang`
--

CREATE TABLE `khachhang` (
  `makhs` int(11) NOT NULL,
  `hoten` varchar(150) NOT NULL,
  `diachi` varchar(255) DEFAULT NULL,
  `sdt` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `user` varchar(50) DEFAULT NULL,
  `matkhau` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khachsan_images`
--

CREATE TABLE `khachsan_images` (
  `image_id` int(11) NOT NULL,
  `makhs` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khachsan_images`
--

INSERT INTO `khachsan_images` (`image_id`, `makhs`, `image_path`, `is_primary`) VALUES
(1, 1, '1763718803_1_0_⚝•.jpg', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khachsan_info`
--

CREATE TABLE `khachsan_info` (
  `makhs` int(11) NOT NULL,
  `tenks` varchar(100) NOT NULL,
  `diachi` varchar(255) DEFAULT NULL,
  `mo_ta_chi_tiet` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khachsan_info`
--

INSERT INTO `khachsan_info` (`makhs`, `tenks`, `diachi`, `mo_ta_chi_tiet`) VALUES
(1, 'ter', 'Hà Nội', 'gegegegseg');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phong`
--

CREATE TABLE `phong` (
  `maphong` int(11) NOT NULL,
  `sophong` varchar(10) NOT NULL,
  `giaphong` decimal(10,2) DEFAULT NULL,
  `loaiphong` varchar(50) DEFAULT NULL,
  `trangthai` varchar(50) DEFAULT NULL,
  `makhs` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `phong`
--

INSERT INTO `phong` (`maphong`, `sophong`, `giaphong`, `loaiphong`, `trangthai`, `makhs`) VALUES
(1, '3', 44444.00, 'Standard', '1', 0),
(2, '3', 100000.00, 'Deluxe', '1', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `quanly`
--

CREATE TABLE `quanly` (
  `maql` int(11) NOT NULL,
  `hoten` varchar(150) NOT NULL,
  `diachi` varchar(255) DEFAULT NULL,
  `sdt` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `user` varchar(50) DEFAULT NULL,
  `matkhau` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `datphong`
--
ALTER TABLE `datphong`
  ADD PRIMARY KEY (`madp`),
  ADD KEY `makh` (`makhs`),
  ADD KEY `maphong` (`maphong`);

--
-- Chỉ mục cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  ADD PRIMARY KEY (`mahd`),
  ADD KEY `madp` (`madp`),
  ADD KEY `maql` (`maql`);

--
-- Chỉ mục cho bảng `khachhang`
--
ALTER TABLE `khachhang`
  ADD PRIMARY KEY (`makhs`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user` (`user`);

--
-- Chỉ mục cho bảng `khachsan_images`
--
ALTER TABLE `khachsan_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `makhs` (`makhs`);

--
-- Chỉ mục cho bảng `khachsan_info`
--
ALTER TABLE `khachsan_info`
  ADD PRIMARY KEY (`makhs`);

--
-- Chỉ mục cho bảng `phong`
--
ALTER TABLE `phong`
  ADD PRIMARY KEY (`maphong`);

--
-- Chỉ mục cho bảng `quanly`
--
ALTER TABLE `quanly`
  ADD PRIMARY KEY (`maql`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user` (`user`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `khachsan_images`
--
ALTER TABLE `khachsan_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `khachsan_info`
--
ALTER TABLE `khachsan_info`
  MODIFY `makhs` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `phong`
--
ALTER TABLE `phong`
  MODIFY `maphong` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `datphong`
--
ALTER TABLE `datphong`
  ADD CONSTRAINT `datphong_ibfk_1` FOREIGN KEY (`makhs`) REFERENCES `khachhang` (`makhs`),
  ADD CONSTRAINT `datphong_ibfk_2` FOREIGN KEY (`maphong`) REFERENCES `phong` (`maphong`);

--
-- Các ràng buộc cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  ADD CONSTRAINT `hoadon_ibfk_1` FOREIGN KEY (`madp`) REFERENCES `datphong` (`madp`),
  ADD CONSTRAINT `hoadon_ibfk_2` FOREIGN KEY (`maql`) REFERENCES `quanly` (`maql`);

--
-- Các ràng buộc cho bảng `khachsan_images`
--
ALTER TABLE `khachsan_images`
  ADD CONSTRAINT `khachsan_images_ibfk_1` FOREIGN KEY (`makhs`) REFERENCES `khachsan_info` (`makhs`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
