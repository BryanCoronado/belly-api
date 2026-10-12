import { createServer } from 'node:http'
import { existsSync, readFileSync } from 'node:fs'
import { timingSafeEqual } from 'node:crypto'
import { Server } from 'socket.io'

const EVENTOS = new Set(['mensaje', 'conversacion', 'contadores'])
const LIMITE_CUERPO = 64 * 1024

const config = leerConfig()
if (!config.clave) {
  console.error('[socket] Falta SOCKET_CLAVE en el .env del API.')
  process.exit(1)
}

const servidor = createServer(recibirDelApi)
const io = new Server(servidor, {
  cors: config.origenes.length ? { origin: config.origenes } : undefined,
})

io.use(async (socket, siguiente) => {
  const token = socket.handshake.auth?.token
  if (typeof token !== 'string' || token === '') {
    return siguiente(new Error('no_autorizado'))
  }
  try {
    const sesion = await validarToken(token)
    if (!sesion) {
      return siguiente(new Error('no_autorizado'))
    }
    socket.data = sesion
    siguiente()
  } catch (error) {
    console.error('[socket] No se pudo validar la sesión:', error.message)
    siguiente(new Error('api_no_disponible'))
  }
})

io.on('connection', (socket) => {
  socket.join(salaEmpresa(socket.data.idEmpresa))
})

servidor.listen(config.puerto, config.host, () => {
  console.log(`[socket] Escuchando en http://${config.host}:${config.puerto}`)
})

/** Solo el API conoce la clave: es el único que puede publicar eventos. */
async function recibirDelApi(peticion, respuesta) {
  if (peticion.method !== 'POST' || peticion.url !== '/emitir') {
    return responder(respuesta, 404, { ok: false })
  }
  if (!claveValida(peticion.headers['x-socket-clave'])) {
    return responder(respuesta, 401, { ok: false })
  }

  let cuerpo
  try {
    cuerpo = JSON.parse(await leerCuerpo(peticion))
  } catch {
    return responder(respuesta, 400, { ok: false })
  }

  const idEmpresa = Number(cuerpo?.id_empresa)
  if (!EVENTOS.has(cuerpo?.evento) || !Number.isInteger(idEmpresa) || idEmpresa <= 0) {
    return responder(respuesta, 422, { ok: false })
  }

  io.to(salaEmpresa(idEmpresa)).emit(cuerpo.evento, cuerpo.datos ?? {})
  responder(respuesta, 200, { ok: true })
}

async function validarToken(token) {
  const respuesta = await fetch(`${config.apiUrl}/v1/auth/tiempo-real`, {
    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    signal: AbortSignal.timeout(5000),
  })
  if (respuesta.status === 401) {
    return null
  }
  if (!respuesta.ok) {
    throw new Error(`HTTP ${respuesta.status}`)
  }
  const datos = (await respuesta.json())?.data
  const idEmpresa = Number(datos?.id_empresa)
  return idEmpresa > 0 ? { idEmpresa, idUsuario: Number(datos.id_usuario) } : null
}

function salaEmpresa(idEmpresa) {
  return `empresa:${idEmpresa}`
}

function claveValida(recibida) {
  if (typeof recibida !== 'string') {
    return false
  }
  const a = Buffer.from(recibida)
  const b = Buffer.from(config.clave)
  return a.length === b.length && timingSafeEqual(a, b)
}

function leerCuerpo(peticion) {
  return new Promise((resolver, rechazar) => {
    let total = 0
    const partes = []
    peticion.on('data', (parte) => {
      total += parte.length
      if (total > LIMITE_CUERPO) {
        rechazar(new Error('cuerpo_muy_grande'))
        peticion.destroy()
        return
      }
      partes.push(parte)
    })
    peticion.on('end', () => resolver(Buffer.concat(partes).toString('utf8')))
    peticion.on('error', rechazar)
  })
}

function responder(respuesta, estado, cuerpo) {
  respuesta.writeHead(estado, { 'Content-Type': 'application/json' })
  respuesta.end(JSON.stringify(cuerpo))
}

/** Lee la configuración del .env del API para no duplicar la clave. */
function leerConfig() {
  const archivo = new URL('../.env', import.meta.url)
  const env = existsSync(archivo) ? parsearEnv(readFileSync(archivo, 'utf8')) : {}
  const valor = (clave, porDefecto = '') => process.env[clave] ?? env[clave] ?? porDefecto

  return {
    clave: valor('SOCKET_CLAVE'),
    puerto: Number(valor('SOCKET_PUERTO', '4000')),
    host: valor('SOCKET_HOST', '127.0.0.1'),
    apiUrl: valor('SOCKET_API_URL', 'http://belly-api.test').replace(/\/+$/, ''),
    origenes: valor('SOCKET_ORIGENES')
      .split(',')
      .map((origen) => origen.trim())
      .filter(Boolean),
  }
}

function parsearEnv(texto) {
  const resultado = {}
  for (const linea of texto.split(/\r?\n/)) {
    const coincidencia = linea.match(/^\s*([\w.]+)\s*=\s*(.*?)\s*$/)
    if (coincidencia) {
      resultado[coincidencia[1]] = coincidencia[2].replace(/^(['"])(.*)\1$/, '$2')
    }
  }
  return resultado
}
