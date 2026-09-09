SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `clientes` (
  `id`         int          NOT NULL AUTO_INCREMENT,
  `nombre`     varchar(100) NOT NULL,
  `email`      varchar(100) NOT NULL,
  `no_celular` varchar(20)  NOT NULL,
  `visitas`    int          DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email`      (`email`),
  UNIQUE KEY `no_celular` (`no_celular`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `clientes_historial` (
  `telefono`   varchar(20)  NOT NULL,
  `nombre`     varchar(100) NOT NULL DEFAULT '',
  `email`      varchar(100) NOT NULL DEFAULT '',
  `reportes`   int          DEFAULT '0',
  `penalizada` tinyint(1)   DEFAULT '0',
  PRIMARY KEY (`telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `reservas` (
  `id`               int          NOT NULL AUTO_INCREMENT,
  `nombre`           varchar(100) NOT NULL,
  `email`            varchar(100) NOT NULL,
  `telefono`         varchar(20)  DEFAULT NULL,
  `fecha`            date         NOT NULL,
  `hora`             time         NOT NULL,
  `personas`         int          NOT NULL,
  `comentarios`      text,
  `es_alergico`      tinyint(1)   DEFAULT '0',
  `es_evento`        tinyint(1)   DEFAULT '0',
  `estado`           enum('pendiente','confirmada','cancelada','completada') DEFAULT 'pendiente',
  `fecha_creacion`   timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  `comentario_admin` text,
  `total`            decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_fecha`        (`fecha`),
  KEY `idx_estado`       (`estado`),
  KEY `idx_fecha_hora`   (`fecha`, `hora`, `estado`),
  KEY `idx_especiales`   (`es_alergico`, `es_evento`),
  KEY `idx_fecha_estado` (`fecha`, `estado`),
  KEY `idx_telefono`     (`telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `usuarios_admin` (
  `id`              int          NOT NULL AUTO_INCREMENT,
  `usuario`         varchar(50)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `email`           varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash`   varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre`          varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol`             enum('admin','supervisor','recepcion') COLLATE utf8mb4_unicode_ci DEFAULT 'recepcion',
  `activo`          tinyint(1)   DEFAULT '1',
  `creado_en`       timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en`  timestamp    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ultimo_login`    timestamp    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `email`   (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
