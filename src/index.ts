import Fastify from 'fastify';

type Sistema = 'contable';

const app = Fastify({ logger: true });
const sistema: Sistema = 'contable';
const isProduction = process.env.NODE_ENV === 'production';
const adminPassword =
  process.env.ADMIN_PASSWORD || (!isProduction ? 'admin' : '');
const sessionDurationMs = 6 * 60 * 60_000;
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
  if (!adminPassword || usuario !== 'admin' || password !== adminPassword) {
    return reply
      .code(401)
      .send({ message: 'Usuario o contraseña incorrectos.' });
  }
  return {
    token: `contable-dev-${Date.now()}`,
    expiresAt: Date.now() + sessionDurationMs,
    usuario: {
      nombre: 'Administrador',
      rol: 'Administración',
      permisos: ['productos.crear', 'inventario.crear', 'servicios.crear'],
    },
  };
});
app.listen({ port: Number(process.env.PORT ?? 5055), host: '0.0.0.0' });
