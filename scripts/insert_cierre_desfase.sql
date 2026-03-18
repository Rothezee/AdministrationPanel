-- INSERT: 1 cierre diario + 2 subcierres (uno con desfase 18:00-02:00)
-- Ejecutar en sistemadeadministracion
-- Requiere: dispositivo EXPENDEDORA_1, cajeros CAJERO_01 y CAJERO_02

USE sistemadeadministracion;

SET @id_admin = 1;

-- Dispositivo (si no existe)
INSERT IGNORE INTO `dispositivos` (`id_admin`, `codigo_hardware`, `tipo_maquina`) 
VALUES (@id_admin, 'EXPENDEDORA_1', 'Expendedora');
SET @id_disp = (SELECT id_dispositivo FROM dispositivos WHERE codigo_hardware = 'EXPENDEDORA_1' LIMIT 1);

-- Cajeros (si no existen)
INSERT IGNORE INTO `cajeros` (`id_admin`, `usuario_cajero`, `pin_acceso`) VALUES (@id_admin, 'CAJERO_01', '1234');
INSERT IGNORE INTO `cajeros` (`id_admin`, `usuario_cajero`, `pin_acceso`) VALUES (@id_admin, 'CAJERO_02', '1234');
SET @id_cajero1 = (SELECT id_cajero FROM cajeros WHERE usuario_cajero = 'CAJERO_01' LIMIT 1);
SET @id_cajero2 = (SELECT id_cajero FROM cajeros WHERE usuario_cajero = 'CAJERO_02' LIMIT 1);

-- 1 cierre diario (fecha_apertura = cuando el primer cajero abrió a las 11)
INSERT INTO `cierres_diarios` (`id_dispositivo`, `fichas_totales`, `dinero`, `p1`, `p2`, `p3`, `fichas_promo`, `fichas_devolucion`, `fichas_cambio`, `fecha_apertura`, `fecha_cierre`) VALUES
(@id_disp, 180, 4500.00, 8, 6, 4, 15, 2, 0, '2026-03-18 11:00:00', '2026-03-19 02:00:00');
SET @id_cierre = LAST_INSERT_ID();

-- 2 subcierres:
-- Cajero 1: 11:00 - 18:00 (mismo día)
-- Cajero 2: 18:00 - 02:00 del día siguiente (desfase)
INSERT INTO `cierres_parciales` (`id_dispositivo`, `id_cajero`, `id_cierre_diario`, `fichas_totales`, `dinero`, `p1`, `p2`, `p3`, `fichas_promo`, `fichas_devolucion`, `fichas_cambio`, `fecha_apertura_turno`, `fecha_cierre_turno`) VALUES
(@id_disp, @id_cajero1, @id_cierre, 85, 2125.00, 4, 3, 2, 7, 1, 0, '2026-03-18 11:00:00', '2026-03-18 18:00:00'),
(@id_disp, @id_cajero2, @id_cierre, 95, 2375.00, 4, 3, 2, 8, 1, 0, '2026-03-18 18:00:00', '2026-03-19 02:00:00');
