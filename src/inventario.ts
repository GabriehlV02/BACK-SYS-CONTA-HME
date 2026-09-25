import type { FastifyInstance } from 'fastify';
import { existsSync, mkdirSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

type Item = { id: number; codigo: string; nombre: string; tipo: string; categoria: string; grupo: string; unidadMedida: string; descripcion: string; estado: string; precioVenta: number; revisionPrecio: boolean; marcas: string[]; [key: string]: unknown };
type Linea = { id: string; codigo: string; marca: string; lote: string; cantidad: number; costoUnitario: number; vence: string; [key: string]: unknown };
type Ingreso = { id: string; fecha: string; almacen: string; proveedor: string; comprobante: string; lineas: Linea[] };
type Lote = Linea & { ingresoId: string; fecha: string; almacen: string; disponible: number; orden: number };
type Aviso = { id: number; codigo: string; detalle: string; prioridad: string; fecha: string };
type Estado = { items: Item[]; ingresos: Ingreso[]; lotes: Lote[]; avisos: Aviso[]; ventas: { id: string; [key: string]: unknown }[] };
const vacio = (): Estado => ({ items: [], ingresos: [], lotes: [], avisos: [], ventas: [] });
const centavos = (n: number) => Math.round(n * 100);
function exigir(ok: unknown, mensaje: string): asserts ok { if (!ok) throw Object.assign(new Error(mensaje), { statusCode: 400 }); }
const texto = (v: unknown) => typeof v === 'string' ? v.trim() : '';
const fechaValida = (v: string) => /^\d{4}-\d{2}-\d{2}$/.test(v) && Number.isFinite(Date.parse(v)) && new Date(v).toISOString().slice(0, 10) === v;
const hoy = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/La_Paz' }).format(new Date());
const util = (l: Lote) => l.disponible > 0 && (!l.vence || l.vence >= hoy());
const piso = (s: Estado, codigo: string) => Math.max(0, ...s.lotes.filter(l => l.codigo === codigo && util(l)).map(l => l.costoUnitario));
const costoMaximoReferencia = (s: Estado, codigo: string, costoNuevo = 0) => Math.max(costoNuevo, ...s.lotes.filter(l => l.codigo === codigo).map(l => l.costoUnitario));
const precioSugerido = (costo: number) => Math.ceil(costo * 125) / 100;

export function registrarInventario(app: FastifyInstance, archivo = resolve('data/inventario.json')) {
  let estado: Estado = existsSync(archivo) ? JSON.parse(readFileSync(archivo, 'utf8')) : vacio();
  function transaccion<T>(operar: (s: Estado) => T): T {
    const copia = structuredClone(estado);
    const resultado = operar(copia);
    mkdirSync(resolve(archivo, '..'), { recursive: true });
    writeFileSync(`${archivo}.tmp`, JSON.stringify(copia, null, 2));
    renameSync(`${archivo}.tmp`, archivo);
    estado = copia;
    return resultado;
  }
  const listar = () => estado.items.map(i => ({ ...i, costoMinimo: piso(estado, i.codigo), stock: estado.lotes.filter(l => l.codigo === i.codigo && util(l)).reduce((n, l) => n + l.disponible, 0) }));
  app.get('/api/inventario', async () => ({ data: listar() }));
  app.post('/api/inventario', async req => transaccion(s => {
    const b = req.body as Item;
    exigir(b && texto(b.codigo) && texto(b.nombre) && texto(b.unidadMedida), 'Código, nombre y unidad de salida son obligatorios.');
    exigir(['PRODUCTO', 'INSUMO', 'SERVICIO'].includes(texto(b.tipo).toUpperCase()), 'Tipo inválido.');
    exigir(Number.isFinite(b.precioVenta) && b.precioVenta >= 0, 'Precio inválido.');
    exigir(!s.items.some(i => i.codigo.toUpperCase() === b.codigo.trim().toUpperCase()), 'El código ya existe.');
    const item: Item = { ...b, id: Math.max(0, ...s.items.map(i => i.id)) + 1, codigo: b.codigo.trim(), tipo: b.tipo.toUpperCase(), precioVenta: centavos(b.precioVenta) / 100, revisionPrecio: false, marcas: [] };
    s.items.push(item); return { data: item };
  }));
  app.put<{ Params: { id: string } }>('/api/inventario/:id', async req => transaccion(s => {
    const item = s.items.find(i => String(i.id) === req.params.id || i.codigo === req.params.id);
    exigir(item, 'Ítem no encontrado.');
    const b = req.body as Item & { confirmarPrecio?: boolean };
    exigir(b && texto(b.nombre) && texto(b.unidadMedida), 'Nombre y unidad son obligatorios.');
    exigir(['PRODUCTO', 'INSUMO', 'SERVICIO'].includes(texto(b.tipo).toUpperCase()), 'Tipo inválido.');
    exigir(b.codigo === item.codigo, 'El código global no puede cambiar.');
    exigir(Number.isFinite(b.precioVenta) && centavos(b.precioVenta) >= centavos(piso(s, item.codigo)), 'El precio no puede quedar por debajo del costo de los lotes disponibles.');
    exigir(!s.lotes.some(l => l.codigo === item.codigo) || (b.unidadMedida === item.unidadMedida && b.tipo.toUpperCase() === item.tipo), 'Un ítem con ingresos debe conservar su tipo y unidad.');
    const revision = item.revisionPrecio && !b.confirmarPrecio;
    Object.assign(item, b, { id: item.id, tipo: b.tipo.toUpperCase(), marcas: item.marcas, revisionPrecio: revision, precioVenta: centavos(b.precioVenta) / 100 });
    delete item.confirmarPrecio;
    return { data: item };
  }));
  app.get('/api/adquisiciones', async () => ({ data: estado.ingresos }));
  app.get('/api/stock/lotes', async () => ({ data: [...estado.lotes].sort((a,b) => a.fecha.localeCompare(b.fecha) || a.orden - b.orden) }));
  app.get('/api/inventario/avisos', async () => ({ data: estado.avisos }));
  app.post('/api/adquisiciones', async req => transaccion(s => {
    const b = req.body as Ingreso;
    exigir(b && texto(b.id) && fechaValida(b.fecha) && b.fecha <= hoy() && texto(b.almacen) && texto(b.proveedor) && texto(b.comprobante), 'Completa fecha de recepción, almacén, proveedor y comprobante. No se permiten fechas futuras.');
    const existente = s.ingresos.find(i => i.id === b.id);
    if (existente) return { data: existente };
    exigir(Array.isArray(b.lineas) && b.lineas.length, 'Agrega al menos una línea.');
    const ingreso = structuredClone(b);
    for (const l of ingreso.lineas) {
      const item = s.items.find(i => i.codigo === l.codigo && i.tipo !== 'SERVICIO' && i.estado.toUpperCase() === 'ACTIVO');
      exigir(item, 'Ítem inexistente, inactivo o sin control de stock.');
      exigir(texto(l.marca) && texto(l.lote), 'Cada ingreso necesita marca y lote.');
      exigir(Number.isSafeInteger(l.cantidad) && l.cantidad > 0 && Number.isFinite(l.costoUnitario) && l.costoUnitario >= 0, 'Cantidad entera positiva y costo por unidad de salida válidos son obligatorios.');
      exigir(!l.vence || (fechaValida(l.vence) && l.vence >= hoy()), 'El lote no puede estar vencido.');
      l.costoUnitario = centavos(l.costoUnitario) / 100;
      const anteriores = s.lotes.filter(x => x.codigo === l.codigo).sort((a,b) => b.orden - a.orden);
      const anterior = anteriores[0]?.costoUnitario;
      const precioAnterior = item.precioVenta;
      const costoMayor = costoMaximoReferencia(s, l.codigo, l.costoUnitario);
      const nuevoPrecioSugerido = precioSugerido(costoMayor);
      const ajustarPrecio = nuevoPrecioSugerido > precioAnterior;
      let detalle = '';
      if (ajustarPrecio) {
        item.precioVenta = nuevoPrecioSugerido; item.revisionPrecio = true;
        detalle = `${item.nombre} · ${l.marca}: costo de compra Bs ${l.costoUnitario.toFixed(2)}. El costo maximo de sus marcas es Bs ${costoMayor.toFixed(2)}; precio global actualizado a Bs ${item.precioVenta.toFixed(2)} (25% sobre el mayor costo).`;
      } else if (anterior !== undefined && anterior !== l.costoUnitario) {
        detalle = `${item.nombre} · ${l.marca}: costo ${l.costoUnitario < anterior ? 'menor' : 'mayor'} (Bs ${l.costoUnitario.toFixed(2)}; anterior Bs ${anterior.toFixed(2)}). Se mantiene el precio de venta Bs ${item.precioVenta.toFixed(2)}.`;
      }
      if (detalle) s.avisos.unshift({ id: Math.max(0, ...s.avisos.map(a => a.id)) + 1, codigo: item.codigo, detalle, prioridad: ajustarPrecio ? 'alta' : 'normal', fecha: new Date().toISOString() });
      if (!item.marcas.includes(l.marca.trim())) item.marcas.push(l.marca.trim());
      s.lotes.push({ ...l, id: crypto.randomUUID(), ingresoId: b.id, fecha: b.fecha, almacen: b.almacen, disponible: l.cantidad, orden: Math.max(0, ...s.lotes.map(x => x.orden)) + 1 });
    }
    s.ingresos.unshift(ingreso); return { data: ingreso };
  }));
  app.post('/api/ventas', async req => transaccion(s => {
    const b = req.body as { id: string; almacen: string; lineas: { codigo: string; cantidad: number; precio: number }[]; descuento: number; pagado: number };
    exigir(b && texto(b.id), 'Identificador de venta obligatorio.');
    const existente = s.ventas.find(v => v.id === b.id); if (existente) return { data: existente };
    exigir(Array.isArray(b.lineas) && b.lineas.length && texto(b.almacen), 'Selecciona almacén y productos.');
    let subtotal = 0, costo = 0;
    const detalle = b.lineas.map(l => {
      const item = s.items.find(i => i.codigo === l.codigo && i.estado.toUpperCase() === 'ACTIVO');
      exigir(item && Number.isSafeInteger(l.cantidad) && l.cantidad > 0, 'Ítem o cantidad inválidos.');
      exigir(centavos(l.precio) === centavos(item.precioVenta), `Cambió el precio de ${item.nombre}. Actualiza el carrito.`);
      subtotal += centavos(item.precioVenta) * l.cantidad;
      let pendiente = l.cantidad;
      const salidas: { loteId: string; marca: string; lote: string; cantidad: number; costoUnitario: number }[] = [];
      if (item.tipo !== 'SERVICIO') {
        const lotes = s.lotes.filter(x => x.codigo === l.codigo && x.almacen === b.almacen && util(x)).sort((a,c) => a.fecha.localeCompare(c.fecha) || a.orden - c.orden);
        for (const lote of lotes) {
          const cantidad = Math.min(pendiente, lote.disponible);
          if (!cantidad) break;
          lote.disponible -= cantidad; pendiente -= cantidad; costo += centavos(lote.costoUnitario) * cantidad;
          salidas.push({ loteId: lote.id, marca: lote.marca, lote: lote.lote, cantidad, costoUnitario: lote.costoUnitario });
        }
        exigir(pendiente === 0, `Stock insuficiente de ${item.nombre} en el almacén seleccionado.`);
      }
      return { codigo: item.codigo, nombre: item.nombre, precio: item.precioVenta, cantidad: l.cantidad, salidas };
    });
    exigir(Number.isFinite(b.descuento) && b.descuento >= 0 && centavos(b.descuento) <= subtotal, 'Descuento inválido.');
    const total = subtotal - centavos(b.descuento);
    exigir(total >= costo, 'El descuento deja la venta por debajo del costo.');
    for (const linea of detalle) {
      const costoMayor = Math.max(0, ...linea.salidas.map(l => centavos(l.costoUnitario)));
      exigir(subtotal === 0 || centavos(linea.precio) * total >= costoMayor * subtotal, `El descuento deja ${linea.nombre} por debajo del costo de su lote.`);
    }
    exigir(Number.isFinite(b.pagado) && centavos(b.pagado) >= total, 'Pago insuficiente.');
    const venta = { id: b.id, fecha: new Date().toISOString(), almacen: b.almacen, lineas: detalle, total: total / 100, costo: costo / 100 };
    s.ventas.push(venta); return { data: venta };
  }));
}
