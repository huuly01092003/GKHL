-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 08, 2025 at 02:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dsvgkhl`
--

-- --------------------------------------------------------

--
-- Table structure for table `dskh`
--

CREATE TABLE `dskh` (
  `MaKH` varchar(50) NOT NULL,
  `Area` varchar(100) DEFAULT NULL,
  `MaGSBH` varchar(50) DEFAULT NULL,
  `MaNPP` varchar(50) DEFAULT NULL,
  `MaNVBH` varchar(50) DEFAULT NULL,
  `TenNVBH` varchar(150) DEFAULT NULL,
  `TenKH` varchar(200) DEFAULT NULL,
  `PhanLoaiNhomKH` varchar(100) DEFAULT NULL,
  `LoaiKH` varchar(100) DEFAULT NULL,
  `DiaChi` varchar(255) DEFAULT NULL,
  `QuanHuyen` varchar(100) DEFAULT NULL,
  `Tinh` varchar(100) DEFAULT NULL,
  `Location` varchar(255) DEFAULT NULL,
  `MaSoThue` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gkhl`
--

CREATE TABLE `gkhl` (
  `id` int(11) NOT NULL,
  `MaNVBH` varchar(50) DEFAULT NULL,
  `TenNVBH` varchar(150) DEFAULT NULL,
  `MaKHDMS` varchar(50) DEFAULT NULL,
  `TenQuay` varchar(200) DEFAULT NULL,
  `TenChuCuaHang` varchar(200) DEFAULT NULL,
  `NgaySinh` tinyint(4) DEFAULT NULL,
  `ThangSinh` tinyint(4) DEFAULT NULL,
  `NamSinh` smallint(6) DEFAULT NULL,
  `SDTZalo` varchar(20) DEFAULT NULL,
  `SDTDaDinhDanh` varchar(20) DEFAULT NULL,
  `KhopSDT` enum('Y','N') DEFAULT NULL,
  `DangKyChuongTrinh` varchar(200) DEFAULT NULL,
  `DangKyMucDoanhSo` varchar(200) DEFAULT NULL,
  `DangKyTrungBay` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orderdetail`
--

CREATE TABLE `orderdetail` (
  `ID` int(11) NOT NULL,
  `OrderNumber` varchar(50) NOT NULL,
  `OrderDate` date NOT NULL,
  `CustCode` varchar(50) DEFAULT NULL,
  `CustType` varchar(50) DEFAULT NULL,
  `DistCode` varchar(50) DEFAULT NULL,
  `DSRCode` varchar(50) DEFAULT NULL,
  `DistGroup` varchar(50) DEFAULT NULL,
  `DSRTypeProvince` varchar(50) DEFAULT NULL,
  `ProductSaleType` varchar(50) DEFAULT NULL,
  `ProductCode` varchar(50) DEFAULT NULL,
  `Qty` int(11) DEFAULT NULL,
  `TotalSchemeAmount` decimal(18,2) DEFAULT NULL,
  `TotalGrossAmount` decimal(18,2) DEFAULT NULL,
  `TotalNetAmount` decimal(18,2) DEFAULT NULL,
  `RptMonth` tinyint(4) DEFAULT NULL,
  `RptYear` smallint(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `dskh`
--
ALTER TABLE `dskh`
  ADD PRIMARY KEY (`MaKH`),
  ADD KEY `idx_ma_kh` (`MaKH`),
  ADD KEY `idx_tinh` (`Tinh`),
  ADD KEY `idx_quan_huyen` (`QuanHuyen`),
  ADD KEY `idx_loai_kh` (`LoaiKH`),
  ADD KEY `idx_tinh_quan` (`Tinh`,`QuanHuyen`);

--
-- Indexes for table `gkhl`
--
ALTER TABLE `gkhl`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_MaNVBH` (`MaNVBH`),
  ADD KEY `idx_MaKHDMS` (`MaKHDMS`),
  ADD KEY `idx_ma_kh_dms` (`MaKHDMS`),
  ADD KEY `idx_ma_nvbh` (`MaNVBH`),
  ADD KEY `idx_khop_sdt` (`KhopSDT`),
  ADD KEY `idx_nam_sinh` (`NamSinh`);

--
-- Indexes for table `orderdetail`
--
ALTER TABLE `orderdetail`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_orderdetail_dskh` (`CustCode`),
  ADD KEY `idx_rpt_month_year` (`RptMonth`,`RptYear`),
  ADD KEY `idx_cust_code` (`CustCode`),
  ADD KEY `idx_cust_rpt` (`CustCode`,`RptMonth`,`RptYear`),
  ADD KEY `idx_order_date` (`OrderDate`),
  ADD KEY `idx_main_query` (`RptMonth`,`RptYear`,`CustCode`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `gkhl`
--
ALTER TABLE `gkhl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orderdetail`
--
ALTER TABLE `orderdetail`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
