-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 01/10/2025 às 22:13
-- Versão do servidor: 10.11.14-MariaDB-ubu2204
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `app_app`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `cnpj` varchar(18) DEFAULT NULL,
  `document_type` enum('cnpj','cpf') DEFAULT 'cnpj' COMMENT 'Tipo de documento: CNPJ ou CPF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Estrutura para tabela `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` datetime DEFAULT current_timestamp(),
  `category` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pendente','Pago') NOT NULL DEFAULT 'Pendente' COMMENT 'Status do pagamento da despesa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Estrutura para tabela `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'UN',
  `stock_quantity` int(11) DEFAULT 0,
  `supplier` varchar(255) DEFAULT NULL,
  `warranty_period` int(11) DEFAULT NULL,
  `status` enum('Ativo','Inativo','Descontinuado') DEFAULT 'Ativo',
  `notes` text DEFAULT NULL,
  `created_date` datetime DEFAULT current_timestamp(),
  `updated_date` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
--
-- Estrutura para tabela `service_orders`
--

CREATE TABLE `service_orders` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `status` varchar(50) DEFAULT 'Pendente',
  `value` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pendente','previsao','faturado','recebido') DEFAULT 'pendente',
  `payment_value` decimal(10,2) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `nfse_pdf_path` varchar(255) DEFAULT NULL COMMENT 'Caminho do arquivo PDF da Nota Fiscal de Serviços Eletrônica',
  `solution` text DEFAULT NULL,
  `open_date` datetime DEFAULT current_timestamp(),
  `close_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
--
-- Estrutura para tabela `service_order_items`
--

CREATE TABLE `service_order_items` (
  `id` int(11) NOT NULL,
  `service_order_id` int(11) NOT NULL COMMENT 'ID da ordem de serviço',
  `product_id` int(11) NOT NULL COMMENT 'ID do produto/serviço',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT 'Quantidade do item',
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Preço unitário no momento da venda',
  `total_price` decimal(10,2) NOT NULL COMMENT 'Preço total (quantity * unit_price)',
  `created_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Estrutura para tabela `service_order_photos`
--

CREATE TABLE `service_order_photos` (
  `id` int(11) NOT NULL,
  `service_order_id` int(11) NOT NULL COMMENT 'ID da ordem de serviço',
  `photo_path` varchar(255) NOT NULL COMMENT 'Caminho do arquivo da foto',
  `photo_name` varchar(255) NOT NULL COMMENT 'Nome original do arquivo',
  `uploaded_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Data e hora do upload'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$yRKWK0PbvC4vddKJYZ6CFOxuWu./oZIuD2LGivC71gkX1rp9yUtPe');

--
--
-- Índices de tabela `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Índices de tabela `service_orders`
--
ALTER TABLE `service_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Índices de tabela `service_order_items`
--
ALTER TABLE `service_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_order_id` (`service_order_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Índices de tabela `service_order_photos`
--
ALTER TABLE `service_order_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_order_photos_so_id` (`service_order_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT de tabela `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT de tabela `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `service_orders`
--
ALTER TABLE `service_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=193;

--
-- AUTO_INCREMENT de tabela `service_order_items`
--
ALTER TABLE `service_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=330;

--
-- AUTO_INCREMENT de tabela `service_order_photos`
--
ALTER TABLE `service_order_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `service_order_items`
--
ALTER TABLE `service_order_items`
  ADD CONSTRAINT `service_order_items_ibfk_1` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Restrições para tabelas `service_order_photos`
--
ALTER TABLE `service_order_photos`
  ADD CONSTRAINT `service_order_photos_ibfk_1` FOREIGN KEY (`service_order_id`) REFERENCES `service_orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
