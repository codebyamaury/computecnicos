-- Limpieza de integración ePayco: elimina tablas y datos relacionados
-- Ejecuta este script en tu base de datos `computecnicos`.

SET FOREIGN_KEY_CHECKS = 0;

-- Eliminar logs de ePayco
DROP TABLE IF EXISTS epayco_logs;

-- Eliminar transacciones de ePayco
DROP TABLE IF EXISTS epayco_transactions;

SET FOREIGN_KEY_CHECKS = 1;

-- Nota: No se afecta la tabla `pedidos` ni `usuarios`.
-- Si deseas conservar histórico, exporta antes:
-- SELECT * FROM epayco_transactions INTO OUTFILE '/tmp/epayco_transactions.csv'
-- FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';