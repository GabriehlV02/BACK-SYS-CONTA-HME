import { registrarInventario } from './inventario.js';
import Fastify from 'fastify';

type Sistema = 'contable';

const app = Fastify({ logger: true });
const sistema: Sistema = 'contable';
const isProduction = process.env.NODE_ENV === 'production';
const adminPassword =
  process.env.ADMIN_PASSWORD || (!isProduction ? 'admin' : '');
const sessionDurationMs = 6 * 60 * 60_000;
const sesiones = new Map<string, { limitado: boolean; vence: number }>();
const asignacionPrueba = { sucursal: 'Hospital María Esperanza', caja: 'Caja 02 · Farmacia', almacen: 'Farmacia' };
app.addHook('preHandler', async (request, reply) => {
  if (request.url !== '/api/ventas' || request.method !== 'POST') return;
  const token = request.headers.authorization?.replace(/^Bearer /, '');
  const sesion = token ? sesiones.get(token) : undefined;
  if (!sesion || sesion.vence <= Date.now()) return reply.code(401).send({ message: 'Inicia sesión nuevamente.' });
  if (!sesion.limitado) return;
  if (request.url !== '/api/ventas' || request.method !== 'POST') return reply.code(403).send({ message: 'El usuario de prueba solo puede realizar ventas en su caja asignada.' });
  const datos = request.body as Record<string, unknown> | undefined;
  if (!datos || datos.sucursal !== asignacionPrueba.sucursal || datos.caja !== asignacionPrueba.caja || datos.almacen !== asignacionPrueba.almacen) return reply.code(403).send({ message: 'Sucursal, caja o almacén fuera de tus asignaciones.' });
});
app.get('/health', async () => ({ ok: true, sistema }));
app.get('/', async () => ({ servicio: sistema, estado: 'inicializado' }));
app.post('/api/login', async (request, reply) => {
  const body = request.body as
    { usuario?: unknown; password?: unknown } | undefined;
  const usuario =
    typeof body?.usuario === 'string' ? body.usuario.trim().toLowerCase() : '';
  const password = typeof body?.password === 'string' ? body.password : '';
  if (!usuario || !password)
    return reply
      .code(400)
      .send({ message: 'Usuario y contraseña son obligatorios.' });
  const limitado = !isProduction && usuario === 'contra' && password === 'contra';
  if (!limitado && (!adminPassword || usuario !== 'admin' || password !== adminPassword)) {
    return reply
      .code(401)
      .send({ message: 'Usuario o contraseña incorrectos.' });
  }
  const token = crypto.randomUUID();
  sesiones.set(token, { limitado, vence: Date.now() + sessionDurationMs });
  if (limitado) return { token, expiresAt: Date.now() + sessionDurationMs, usuario: { nombre: 'contra', rol: 'Cajero limitado', permisos: ['ventas.crear'], asignacion: asignacionPrueba } };
  return {
    token,
    expiresAt: Date.now() + sessionDurationMs,
    usuario: {
      nombre: 'Administrador',
      rol: 'Administración',
      permisos: ['productos.crear', 'inventario.crear', 'servicios.crear'],
    },
  };
});
registrarInventario(app);
app.listen({ port: Number(process.env.PORT ?? 5055), host: '0.0.0.0' });
