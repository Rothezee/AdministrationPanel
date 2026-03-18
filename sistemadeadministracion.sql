-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 17-03-2026 a las 12:37:27
-- Versión del servidor: 8.0.17
-- Versión de PHP: 7.3.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistemadeadministracion`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cajeros`
--

CREATE TABLE `cajeros` (
  `id_cajero` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL,
  `usuario_cajero` varchar(50) NOT NULL,
  `pin_acceso` varchar(255) NOT NULL,
  `estado` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cierres_diarios`
--

CREATE TABLE `cierres_diarios` (
  `id_cierre_diario` int(11) NOT NULL,
  `id_dispositivo` int(11) NOT NULL,
  `fichas_totales` int(11) NOT NULL DEFAULT '0',
  `dinero` decimal(10,2) NOT NULL DEFAULT '0.00',
  `p1` int(11) DEFAULT '0',
  `p2` int(11) DEFAULT '0',
  `p3` int(11) DEFAULT '0',
  `fichas_promo` int(11) DEFAULT '0',
  `fichas_devolucion` int(11) DEFAULT '0',
  `fichas_cambio` int(11) DEFAULT '0',
  `fecha_apertura` datetime NOT NULL,
  `fecha_cierre` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cierres_parciales`
--

CREATE TABLE `cierres_parciales` (
  `id_cierre_parcial` int(11) NOT NULL,
  `id_dispositivo` int(11) NOT NULL,
  `id_cajero` int(11) NOT NULL,
  `id_cierre_diario` int(11) DEFAULT NULL,
  `fichas_totales` int(11) NOT NULL DEFAULT '0',
  `dinero` decimal(10,2) NOT NULL DEFAULT '0.00',
  `p1` int(11) DEFAULT '0',
  `p2` int(11) DEFAULT '0',
  `p3` int(11) DEFAULT '0',
  `fichas_promo` int(11) DEFAULT '0',
  `fichas_devolucion` int(11) DEFAULT '0',
  `fichas_cambio` int(11) DEFAULT '0',
  `fecha_apertura_turno` datetime NOT NULL,
  `fecha_cierre_turno` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dispositivos`
--

CREATE TABLE `dispositivos` (
  `id_dispositivo` int(11) NOT NULL,
  `id_admin` int(11) NOT NULL,
  `codigo_hardware` varchar(50) NOT NULL,
  `tipo_maquina` enum('Expendedora','Grua','Ticketera','Videojuego') NOT NULL,
  `ip_local` varchar(15) DEFAULT NULL,
  `estado_conexion` enum('online','offline','mantenimiento') DEFAULT 'offline',
  `ultimo_heartbeat` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `telemetria_expendedoras`
--

CREATE TABLE `telemetria_expendedoras` (
  `id_lectura` bigint(20) NOT NULL,
  `id_dispositivo` int(11) NOT NULL,
  `fichas` int(11) NOT NULL,
  `dinero` decimal(10,2) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `telemetria_gruas`
--

CREATE TABLE `telemetria_gruas` (
  `id_lectura` bigint(20) NOT NULL,
  `id_dispositivo` int(11) NOT NULL,
  `pago` int(11) NOT NULL,
  `coin` int(11) NOT NULL,
  `premios` int(11) NOT NULL,
  `banco` int(11) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `telemetria_ticketeras`
--

CREATE TABLE `telemetria_ticketeras` (
  `id_lectura` bigint(20) NOT NULL,
  `id_dispositivo` int(11) NOT NULL,
  `fichas` int(11) NOT NULL,
  `tickets` int(11) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `telemetria_videojuegos`
--

CREATE TABLE `telemetria_videojuegos` (
  `id_lectura` bigint(20) NOT NULL,
  `id_dispositivo` int(11) NOT NULL,
  `fichas` int(11) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones_mp`
--

CREATE TABLE `transacciones_mp` (
  `id_transaccion` int(11) NOT NULL,
  `id_dispositivo` int(11) NOT NULL,
  `mp_preference_id` varchar(100) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `estado` enum('pendiente','aprobado','rechazado','cancelado') DEFAULT 'pendiente',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_admin`
--

CREATE TABLE `usuarios_admin` (
  `id_admin` int(11) NOT NULL,
  `dni` varchar(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_superadmin` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
--
-- Estructura de tabla para la tabla `invite_keys`
-- Claves únicas de activación para crear cuentas de administradores humanos
--

CREATE TABLE `invite_keys` (
  `id_key` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `used_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `used_by_admin` int(11) DEFAULT NULL,
  `subscription_period` enum('mensual','anual') NOT NULL DEFAULT 'mensual',
  `paused` tinyint(1) NOT NULL DEFAULT '0',
  `contact_name` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `webhooks_mp_logs`
--

CREATE TABLE `webhooks_mp_logs` (
  `id_log` int(11) NOT NULL,
  `id_transaccion` int(11) DEFAULT NULL,
  `payload_json` json NOT NULL,
  `fecha_recepcion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cajeros`
--
ALTER TABLE `cajeros`
  ADD PRIMARY KEY (`id_cajero`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Indices de la tabla `cierres_diarios`
--
ALTER TABLE `cierres_diarios`
  ADD PRIMARY KEY (`id_cierre_diario`),
  ADD KEY `id_dispositivo` (`id_dispositivo`);

--
-- Indices de la tabla `cierres_parciales`
--
ALTER TABLE `cierres_parciales`
  ADD PRIMARY KEY (`id_cierre_parcial`),
  ADD KEY `id_dispositivo` (`id_dispositivo`),
  ADD KEY `id_cajero` (`id_cajero`),
  ADD KEY `id_cierre_diario` (`id_cierre_diario`);

--
-- Indices de la tabla `dispositivos`
--
ALTER TABLE `dispositivos`
  ADD PRIMARY KEY (`id_dispositivo`),
  ADD UNIQUE KEY `codigo_hardware` (`codigo_hardware`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Indices de la tabla `telemetria_expendedoras`
--
ALTER TABLE `telemetria_expendedoras`
  ADD PRIMARY KEY (`id_lectura`),
  ADD KEY `id_dispositivo` (`id_dispositivo`);

--
-- Indices de la tabla `telemetria_gruas`
--
ALTER TABLE `telemetria_gruas`
  ADD PRIMARY KEY (`id_lectura`),
  ADD KEY `id_dispositivo` (`id_dispositivo`);

--
-- Indices de la tabla `telemetria_ticketeras`
--
ALTER TABLE `telemetria_ticketeras`
  ADD PRIMARY KEY (`id_lectura`),
  ADD KEY `id_dispositivo` (`id_dispositivo`);

--
-- Indices de la tabla `telemetria_videojuegos`
--
ALTER TABLE `telemetria_videojuegos`
  ADD PRIMARY KEY (`id_lectura`),
  ADD KEY `id_dispositivo` (`id_dispositivo`);

--
-- Indices de la tabla `transacciones_mp`
--
ALTER TABLE `transacciones_mp`
  ADD PRIMARY KEY (`id_transaccion`),
  ADD UNIQUE KEY `mp_preference_id` (`mp_preference_id`),
  ADD KEY `id_dispositivo` (`id_dispositivo`);

--
-- Indices de la tabla `usuarios_admin`
--
ALTER TABLE `usuarios_admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `invite_keys`
--
ALTER TABLE `invite_keys`
  ADD PRIMARY KEY (`id_key`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `used_by_admin` (`used_by_admin`);

--
-- Indices de la tabla `webhooks_mp_logs`
--
ALTER TABLE `webhooks_mp_logs`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_transaccion` (`id_transaccion`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `cajeros`
--
ALTER TABLE `cajeros`
  MODIFY `id_cajero` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cierres_diarios`
--
ALTER TABLE `cierres_diarios`
  MODIFY `id_cierre_diario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cierres_parciales`
--
ALTER TABLE `cierres_parciales`
  MODIFY `id_cierre_parcial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `dispositivos`
--
ALTER TABLE `dispositivos`
  MODIFY `id_dispositivo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `telemetria_expendedoras`
--
ALTER TABLE `telemetria_expendedoras`
  MODIFY `id_lectura` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `telemetria_gruas`
--
ALTER TABLE `telemetria_gruas`
  MODIFY `id_lectura` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `telemetria_ticketeras`
--
ALTER TABLE `telemetria_ticketeras`
  MODIFY `id_lectura` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `telemetria_videojuegos`
--
ALTER TABLE `telemetria_videojuegos`
  MODIFY `id_lectura` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transacciones_mp`
--
ALTER TABLE `transacciones_mp`
  MODIFY `id_transaccion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios_admin`
--
ALTER TABLE `usuarios_admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `invite_keys`
--
ALTER TABLE `invite_keys`
  MODIFY `id_key` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `webhooks_mp_logs`
--
ALTER TABLE `webhooks_mp_logs`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cajeros`
--
ALTER TABLE `cajeros`
  ADD CONSTRAINT `cajeros_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `usuarios_admin` (`id_admin`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cierres_diarios`
--
ALTER TABLE `cierres_diarios`
  ADD CONSTRAINT `cierres_diarios_ibfk_1` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id_dispositivo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cierres_parciales`
--
ALTER TABLE `cierres_parciales`
  ADD CONSTRAINT `cierres_parciales_ibfk_1` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id_dispositivo`) ON DELETE CASCADE,
  ADD CONSTRAINT `cierres_parciales_ibfk_2` FOREIGN KEY (`id_cajero`) REFERENCES `cajeros` (`id_cajero`) ON DELETE RESTRICT,
  ADD CONSTRAINT `cierres_parciales_ibfk_3` FOREIGN KEY (`id_cierre_diario`) REFERENCES `cierres_diarios` (`id_cierre_diario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `dispositivos`
--
ALTER TABLE `dispositivos`
  ADD CONSTRAINT `dispositivos_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `usuarios_admin` (`id_admin`) ON DELETE CASCADE;

--
-- Filtros para la tabla `telemetria_expendedoras`
--
ALTER TABLE `telemetria_expendedoras`
  ADD CONSTRAINT `telemetria_expendedoras_ibfk_1` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id_dispositivo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `telemetria_gruas`
--
ALTER TABLE `telemetria_gruas`
  ADD CONSTRAINT `telemetria_gruas_ibfk_1` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id_dispositivo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `telemetria_ticketeras`
--
ALTER TABLE `telemetria_ticketeras`
  ADD CONSTRAINT `telemetria_ticketeras_ibfk_1` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id_dispositivo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `telemetria_videojuegos`
--
ALTER TABLE `telemetria_videojuegos`
  ADD CONSTRAINT `telemetria_videojuegos_ibfk_1` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id_dispositivo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `transacciones_mp`
--
ALTER TABLE `transacciones_mp`
  ADD CONSTRAINT `transacciones_mp_ibfk_1` FOREIGN KEY (`id_dispositivo`) REFERENCES `dispositivos` (`id_dispositivo`) ON DELETE CASCADE;

--
-- Filtros para la tabla `webhooks_mp_logs`
--
ALTER TABLE `webhooks_mp_logs`
  ADD CONSTRAINT `webhooks_mp_logs_ibfk_1` FOREIGN KEY (`id_transaccion`) REFERENCES `transacciones_mp` (`id_transaccion`) ON DELETE SET NULL;

--
-- Filtros para la tabla `invite_keys`
--
ALTER TABLE `invite_keys`
  ADD CONSTRAINT `invite_keys_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `usuarios_admin` (`id_admin`) ON DELETE SET NULL,
  ADD CONSTRAINT `invite_keys_ibfk_2` FOREIGN KEY (`used_by_admin`) REFERENCES `usuarios_admin` (`id_admin`) ON DELETE SET NULL;

--
-- Usuario superadmin por defecto
--
INSERT INTO `usuarios_admin` (`dni`, `username`, `password_hash`, `email`, `phone`, `is_superadmin`)
VALUES ('00000000', 'admin', '', 'admin@example.com', NULL, 1);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
