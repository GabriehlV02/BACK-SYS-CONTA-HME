# Ítems globales y lotes

Un ítem conserva el código, nombre de facturación, unidad de salida y precio único. La marca se indica al registrar cada línea de adquisición; se incorpora a las marcas conocidas del ítem. Cada recepción crea un lote independiente, incluso cuando se repiten marca y número de lote.

Cantidad y costo se introducen por unidad de salida. Para comprar una caja de 100 tabletas por Bs 100, registrar 100 unidades a Bs 1. No hay conversión automática de empaques.

Las ventas descuentan FIFO por fecha de recepción dentro del almacén seleccionado. A igual fecha se usa el orden de registro. No se consumen lotes vencidos. La venta conserva nombre y precio del ítem, además de los lotes y costos usados para trazabilidad interna.

Un costo superior al precio de venta eleva este último hasta el costo y activa revisión pendiente. Es un precio provisional sin margen añadido. Una compra más barata nunca reduce el precio ni elimina la revisión. Para resolverla, editar el ítem y marcar Confirmar precio de venta revisado; el precio debe cubrir los lotes disponibles. Las alertas históricas permanecen en Notificaciones.

Los ingresos confirmados son inmutables. No se implementaron anulaciones, devoluciones ni ajustes de ingresos. La operación se valida completa antes de guardar y admite reintentos con el mismo identificador sin duplicar movimientos.

Persistencia local en data/inventario.json con escritura temporal y reemplazo. Ejecutar una única instancia del backend sobre ese archivo; una instalación con varios procesos requiere una base de datos transaccional. El registro de venta no emite factura fiscal.

Pruebas aisladas: node --import tsx --test src/inventario.test.ts
