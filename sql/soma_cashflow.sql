-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 26, 2026 at 07:18 PM
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
-- Database: `soma_cashflow`
--

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`id`, `organization_id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 'SomaSpace Africa', 'EdTech', '2026-08-12 07:02:25', '2026-08-12 07:02:25');

-- --------------------------------------------------------

--
-- Table structure for table `fund_transfers`
--

CREATE TABLE `fund_transfers` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `from_type` enum('personal','business') NOT NULL,
  `from_business_id` int(10) UNSIGNED DEFAULT NULL,
  `to_type` enum('personal','business') NOT NULL,
  `to_business_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `description` text DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `owner_user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `name`, `owner_user_id`, `created_at`, `updated_at`) VALUES
(1, 'Johnson Antipas Berege\'s Workspace', 1, '2026-08-11 12:36:34', '2026-08-11 12:36:34'),
(2, 'Somaspace\'s Workspace', 2, '2026-08-26 17:16:07', '2026-08-26 17:16:07');

-- --------------------------------------------------------

--
-- Table structure for table `organization_members`
--

CREATE TABLE `organization_members` (
  `id` int(10) UNSIGNED NOT NULL,
  `organization_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `invited_email` varchar(190) NOT NULL,
  `role` enum('admin','viewer') NOT NULL,
  `business_id` int(10) UNSIGNED DEFAULT NULL,
  `invited_by` int(10) UNSIGNED NOT NULL,
  `invite_token` varchar(64) DEFAULT NULL,
  `status` enum('pending','active') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organization_members`
--

INSERT INTO `organization_members` (`id`, `organization_id`, `user_id`, `invited_email`, `role`, `business_id`, `invited_by`, `invite_token`, `status`, `created_at`, `accepted_at`) VALUES
(2, 1, 2, 'somaspace26@gmail.com', 'viewer', NULL, 1, NULL, 'active', '2026-08-26 17:17:15', '2026-08-26 17:17:15');

-- --------------------------------------------------------

--
-- Table structure for table `personal_transactions`
--

CREATE TABLE `personal_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `business_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('income','expense','loan_received','loan_given') NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `business_id`, `user_id`, `type`, `category`, `amount`, `description`, `transaction_date`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'income', 'Materials / Purchase', 320000.00, 'Cpanel this month', '2026-08-12', '2026-08-12 09:59:09', '2026-08-12 09:59:09'),
(2, 1, 1, 'expense', 'Transport', 25000.00, NULL, '2026-08-12', '2026-08-12 10:00:29', '2026-08-12 10:00:29'),
(3, 1, 1, 'loan_received', 'Capital Injection', 25000.00, NULL, '2026-08-12', '2026-08-12 10:01:10', '2026-08-12 10:01:10'),
(4, 1, 1, 'income', 'Materials / Purchase', 100000000.00, NULL, '2026-08-15', '2026-08-15 17:49:51', '2026-08-15 17:49:51');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `created_at`, `updated_at`) VALUES
(1, 'Johnson Antipas Berege', 'jonsoncosmas@gmail.com', '$2y$10$USjZovL5Y96PWj7lw9SF3OwfB.ALjwbdl2G3bno5vVrt59XlM8jW.', '2026-08-11 12:36:34', '2026-08-11 12:36:34'),
(2, 'Somaspace', 'somaspace26@gmail.com', '$2y$10$1spsmEQSxlXcyxOERe0oD.QrSXT1EoKtm6rpKBhKIQ5k9nuTQVBI6', '2026-08-26 17:16:07', '2026-08-26 17:16:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_business_org_name` (`organization_id`,`name`),
  ADD KEY `idx_business_org` (`organization_id`);

--
-- Indexes for table `fund_transfers`
--
ALTER TABLE `fund_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transfer_user` (`user_id`),
  ADD KEY `idx_transfer_org` (`organization_id`),
  ADD KEY `idx_transfer_from_business` (`from_business_id`),
  ADD KEY `idx_transfer_to_business` (`to_business_id`),
  ADD KEY `idx_transfer_date` (`transfer_date`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_org_owner` (`owner_user_id`);

--
-- Indexes for table `organization_members`
--
ALTER TABLE `organization_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_member_org_email_business` (`organization_id`,`invited_email`,`business_id`),
  ADD KEY `fk_member_business` (`business_id`),
  ADD KEY `fk_member_invited_by` (`invited_by`),
  ADD KEY `idx_member_org` (`organization_id`),
  ADD KEY `idx_member_user` (`user_id`),
  ADD KEY `idx_member_token` (`invite_token`);

--
-- Indexes for table `personal_transactions`
--
ALTER TABLE `personal_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ptxn_user` (`user_id`),
  ADD KEY `idx_ptxn_date` (`transaction_date`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_txn_user` (`user_id`),
  ADD KEY `idx_txn_business` (`business_id`),
  ADD KEY `idx_txn_date` (`transaction_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fund_transfers`
--
ALTER TABLE `fund_transfers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `organization_members`
--
ALTER TABLE `organization_members`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `personal_transactions`
--
ALTER TABLE `personal_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `businesses`
--
ALTER TABLE `businesses`
  ADD CONSTRAINT `fk_business_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fund_transfers`
--
ALTER TABLE `fund_transfers`
  ADD CONSTRAINT `fk_transfer_from_business` FOREIGN KEY (`from_business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_to_business` FOREIGN KEY (`to_business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transfer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `organizations`
--
ALTER TABLE `organizations`
  ADD CONSTRAINT `fk_org_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `organization_members`
--
ALTER TABLE `organization_members`
  ADD CONSTRAINT `fk_member_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_member_invited_by` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_member_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_member_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `personal_transactions`
--
ALTER TABLE `personal_transactions`
  ADD CONSTRAINT `fk_ptxn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_txn_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_txn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
