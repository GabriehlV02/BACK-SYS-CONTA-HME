import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import Fastify from 'fastify';
import { registrarInventario } from './inventario.js';

test('opciones administrativas persistentes y registro con opciones nuevas', async () => {
  const archivo = join(mkdtempSync(join(tmpdir(), 'hme-opciones-')), 'inventario.json');
  const app = Fastify(); registrarInventario(app, archivo);
  const agregar = (payload: object) => app.inject({ method: 'POST', url: '/api/inventario/opciones', payload });
  for (const payload of [
    { campo: 'unidades', nombre: 'Litro' },
    { campo: 'tipos', nombre: 'Suministro', base: 'INSUMO' },
    { campo: 'categorias', nombre: 'Limpieza', prefijo: 'LIM' },
    { campo: 'subcategorias', categoria: 'LIMPIEZA', nombre: 'Desinfectante' },
    { campo: 'clasificaciones', nombre: 'Otra familia' },
  ]) assert.equal((await agregar(payload)).statusCode, 200);
  assert.equal((await agregar({ campo: 'unidades', nombre: ' litro ' })).statusCode, 400);
  assert.equal((await agregar({ campo: 'categorias', nombre: 'Duplicada', prefijo: 'LIM' })).statusCode, 400);
  assert.equal((await agregar({ campo: 'subcategorias', categoria: 'INEXISTENTE', nombre: 'Otra' })).statusCode, 400);
  await app.close();
  const reiniciada = Fastify(); registrarInventario(reiniciada, archivo);
  try {
    const config = (await reiniciada.inject('/api/inventario/opciones')).json().data;
    assert.ok(config.unidades.includes('LITRO'));
    const r = await reiniciada.inject({ method: 'POST', url: '/api/inventario', payload: { nombre: 'Ejemplo', descripcion: 'Descripción', tipo: 'INSUMO', tipoOpcion: 'SUMINISTRO', unidadMedida: 'LITRO', categoria: 'LIMPIEZA', grupo: 'DESINFECTANTE', clasificacion: 'OTRA FAMILIA', estado: 'ACTIVO', precioVenta: 10 } });
    assert.equal(r.statusCode, 200, r.body);
    assert.equal(r.json().data.codigo, 'LIM00001');
    assert.equal(r.json().data.tipo, 'INSUMO');
    assert.equal(r.json().data.tipoOpcion, 'SUMINISTRO');
  } finally { await reiniciada.close(); }
});

test('registro: correlativo global, categorías, descripción y clasificación', async () => {
  const archivo = join(mkdtempSync(join(tmpdir(), 'hme-registro-')), 'inventario.json');
  const app = Fastify(); registrarInventario(app, archivo);
  const base = { codigo: 'IGNORADO', nombre: 'Ejemplo', unidadMedida: 'UNITARIO', tipo: 'PRODUCTO', categoria: 'FARMACO', grupo: 'TABLETA', descripcion: 'Texto libre', estado: 'ACTIVO', precioVenta: 2, clasificacion: 'PENICILINAS', numeroSerie: '001234567890', usarNumeroSerie: true };
  try {
    const respuestas = await Promise.all(Array.from({ length: 21 }, () => app.inject({ method: 'POST', url: '/api/inventario', payload: base })));
    respuestas.forEach(r => assert.equal(r.statusCode, 200, r.body));
    const codigos = respuestas.map(r => r.json().data.codigo);
    assert.equal(new Set(codigos).size, 21); assert.ok(codigos.includes('FAR00021'));
    const insumo = await app.inject({ method: 'POST', url: '/api/inventario', payload: { ...base, categoria: 'INSUMO', grupo: 'DESCARTABLE' } });
    assert.equal(insumo.json().data.codigo, 'INS00022');
    assert.equal(insumo.json().data.numeroSerie, '001234567890');
    assert.equal(insumo.json().data.clasificacion, 'PENICILINAS');
    for (const cambios of [{ grupo: 'DESCARTABLE' }, { descripcion: 'x'.repeat(1001) }, { estado: 'OTRO' }, { unidadMedida: 'INVALIDA' }]) {
      const r = await app.inject({ method: 'POST', url: '/api/inventario', payload: { ...base, ...cambios } });
      assert.equal(r.statusCode, 400, r.body);
    }
    const siguiente = await app.inject('/api/inventario/siguiente-codigo');
    assert.equal(siguiente.json().numero, 23);
  } finally { await app.close(); }
});

test('precio global, alertas persistentes, FIFO, atomicidad e idempotencia', async () => {
  const archivo = join(mkdtempSync(join(tmpdir(), 'hme-stock-')), 'inventario.json');
  const app = Fastify(); registrarInventario(app, archivo);
  async function request(method: 'GET' | 'POST' | 'PUT', url: string, payload?: object, status = 200) {
    const r = await app.inject({ method, url, payload }); assert.equal(r.statusCode, status, r.body); return r.json().data;
  }
  const base = { codigo: '', nombre: 'Paracetamol 1 gramo', unidadMedida: 'UNITARIO', tipo: 'PRODUCTO', categoria: 'FARMACO', grupo: 'TABLETA', descripcion: '', estado: 'ACTIVO', precioVenta: 2 };
  base.codigo = (await request('POST', '/api/inventario', base)).codigo;
  const ingreso = (id: string, marca: string, fecha: string, costoUnitario: number, cantidad = 2, almacen = 'Farmacia') => ({ id, fecha, almacen, proveedor: marca, comprobante: id, lineas: [{ id, codigo: base.codigo, marca, lote: id, cantidad, costoUnitario, vence: '2099-12-31' }] });
  await request('POST', '/api/adquisiciones', ingreso('enero', 'Bago', '2026-01-01', 1));
  await request('POST', '/api/adquisiciones', ingreso('abril', 'Cofar', '2026-04-01', 2.5));
  let [item] = await request('GET', '/api/inventario');
  assert.equal(item.precioVenta, 3.13); assert.equal(item.revisionPrecio, true);
  await request('POST', '/api/adquisiciones', ingreso('septiembre', 'China', '2026-09-01', 0.5));
  await request('POST', '/api/adquisiciones', ingreso('septiembre', 'China', '2026-09-01', 0.5));
  [item] = await request('GET', '/api/inventario'); assert.equal(item.stock, 6); assert.equal(item.precioVenta, 3.13); assert.equal(item.revisionPrecio, true);
  assert.equal((await request('GET', '/api/inventario/avisos')).length, 2);
  await request('PUT', '/api/inventario/1', { ...item, precioVenta: 2, confirmarPrecio: true }, 400);
  await request('PUT', '/api/inventario/1', { ...item, precioVenta: 3.5 });
  [item] = await request('GET', '/api/inventario'); assert.equal(item.revisionPrecio, true);
  await request('PUT', '/api/inventario/1', { ...item, precioVenta: 3.5, confirmarPrecio: true });
  [item] = await request('GET', '/api/inventario'); assert.equal(item.revisionPrecio, false);
  const venta = (id: string, cantidad: number, precio = 3.5) => ({ id, almacen: 'Farmacia', lineas: [{ codigo: base.codigo, cantidad, precio }], descuento: 0, pagado: 100 });
  await request('POST', '/api/ventas', venta('precio-viejo', 1, 2), 400);
  await request('POST', '/api/ventas', venta('sin-stock', 7), 400);
  await request('POST', '/api/ventas', { ...venta('descuento', 3), descuento: 8 }, 400);
  const vencido = ingreso('vencido', 'X', '2026-01-01', 1);
  vencido.lineas[0].vence = '2020-01-01';
  await request('POST', '/api/adquisiciones', vencido, 400);
  assert.equal((await request('GET', '/api/inventario'))[0].stock, 6);
  const v = await request('POST', '/api/ventas', venta('venta-1', 3));
  assert.deepEqual(v.lineas[0].salidas.map((s: any) => [s.marca, s.cantidad]), [['Bago', 2], ['Cofar', 1]]);
  await request('POST', '/api/ventas', venta('venta-1', 3));
  assert.equal((await request('GET', '/api/inventario'))[0].stock, 3);
  const v2 = await request('POST', '/api/ventas', venta('venta-2', 2));
  assert.deepEqual(v2.lineas[0].salidas.map((s: any) => [s.marca, s.cantidad]), [['Cofar', 1], ['China', 1]]);
  await request('POST', '/api/adquisiciones', ingreso('otra', 'Bago', '2026-01-01', 1, 10, 'Otro'));
  await request('POST', '/api/ventas', venta('otro-almacen', 2), 400);
  const invalido = ingreso('invalido', 'X', '2026-01-01', 5); invalido.lineas.push({ ...invalido.lineas[0], codigo: 'NO-EXISTE' });
  await request('POST', '/api/adquisiciones', invalido, 400);
  assert.equal((await request('GET', '/api/inventario'))[0].precioVenta, 3.5);
  await app.close();
  const reinicio = Fastify(); registrarInventario(reinicio, archivo);
  const r = await reinicio.inject('/api/inventario'); assert.equal(r.json().data[0].stock, 11);
  await reinicio.close();
});
