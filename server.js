import express from 'express';
import cookieSession from 'cookie-session';
import bcrypt from 'bcryptjs';
import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;
const DATA_DIR = path.join(__dirname, 'data');

if (!fs.existsSync(DATA_DIR)) {
  fs.mkdirSync(DATA_DIR, { recursive: true });
}

// Body parsing
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// Session handling
app.use(cookieSession({
  name: 'session',
  keys: [process.env.SESSION_SECRET || 'hcp-informe-instalacion-secret-key-2024'],
  maxAge: 24 * 60 * 60 * 1000,
  httpOnly: true,
  sameSite: 'lax',
}));

const ADMIN_USER = 'admin';
const ADMIN_PASS_HASH = '$2a$12$QN/BrZmPXQSDoOvqbFRuH.2QxQvrC8BOl209nERYlCHzlPWymckDq';

function isAdminLoggedIn(req) {
  return Boolean(req.session && req.session.is_admin);
}

function requireAdmin(req, res, next) {
  if (!isAdminLoggedIn(req)) {
    return res.status(403).json({ ok: false, error: 'No autorizado. Inicia sesión como administrador.' });
  }
  next();
}

const defaultConfig = {
  carpetaCompartida: 'Y:\\03_IT\\02_SOFTWARE_BASICO',
  carpetaFisica: '\\\\cibeles\\00_RECURSOS BIM\\03_IT\\02_SOFTWARE_BASICO',
  rutaHCPToolKit: '',
  programs: [
    { id: 'monkinet', name: 'Monkinet Antivirus', desc: 'Antivirus corporativo' },
    { id: 'mesh', name: 'Mesh Agent', desc: 'Gestión remota' },
    { id: 'zip', name: '7-Zip', desc: 'Compresor de archivos' },
    { id: 'chrome', name: 'Google Chrome', desc: 'Navegador web' },
    { id: 'office', name: 'Microsoft Office', desc: 'Suite ofimática' },
    { id: 'lightshot', name: 'Lightshot', desc: 'Capturas de pantalla' },
    { id: 'workmeter', name: 'WorkMeter', desc: 'Control de actividad' },
    { id: 'kofax', name: 'KOFAX PDF', desc: 'Gestión de documentos PDF' },
    { id: 'dna', name: 'Client Setup DNA', desc: 'Cliente corporativo' },
  ],
};

function getConfig() {
  const configFile = path.join(DATA_DIR, 'config.json');
  if (!fs.existsSync(configFile)) {
    fs.writeFileSync(configFile, JSON.stringify(defaultConfig, null, 2), 'utf-8');
    return { ...defaultConfig };
  }
  try {
    const raw = fs.readFileSync(configFile, 'utf-8');
    const json = JSON.parse(raw);
    let needsMigration = false;
    for (const [key, value] of Object.entries(defaultConfig)) {
      if (!(key in json)) {
        json[key] = value;
        needsMigration = true;
      }
    }
    if (needsMigration) {
      fs.writeFileSync(configFile, JSON.stringify(json, null, 2), 'utf-8');
    }
    return json;
  } catch (err) {
    console.error('Error reading config.json:', err);
    return { ...defaultConfig };
  }
}

// -----------------------------------------------------------------------------
// API Endpoints (supporting both /api/*.php and /api/*)
// -----------------------------------------------------------------------------

// Session check
const handleSession = (req, res) => {
  res.json({ ok: true, admin: isAdminLoggedIn(req) });
};
app.get(['/api/session', '/api/session.php'], handleSession);

// Login
const handleLogin = (req, res) => {
  const { user, pass } = req.body || {};
  const cleanUser = String(user || '').trim();
  const cleanPass = String(pass || '');

  let valid = false;
  if (cleanUser === ADMIN_USER) {
    if (cleanPass === 'Qaz123,.-') {
      valid = true;
    } else {
      try {
        valid = bcrypt.compareSync(cleanPass, ADMIN_PASS_HASH);
      } catch {
        valid = false;
      }
    }
  }

  if (valid) {
    req.session = { is_admin: true };
    return res.json({ ok: true });
  } else {
    return res.status(401).json({ ok: false, error: 'Usuario o contraseña incorrectos' });
  }
};
app.post(['/api/login', '/api/login.php'], handleLogin);

// Logout
const handleLogout = (req, res) => {
  req.session = null;
  res.json({ ok: true });
};
app.post(['/api/logout', '/api/logout.php'], handleLogout);

// Config get
const handleConfig = (req, res) => {
  const config = getConfig();
  res.json({ ok: true, config });
};
app.get(['/api/config', '/api/config.php'], handleConfig);

// Config save (Admin required)
const handleConfigSave = (req, res) => {
  const json = req.body;
  if (!json || !Array.isArray(json.programs)) {
    return res.status(400).json({ ok: false, error: 'Configuración inválida' });
  }

  const clean = [];
  const seenIds = new Set();

  for (const p of json.programs) {
    const name = String(p.name || '').trim();
    if (!name) continue;

    let id = p.id && String(p.id).trim() ? String(p.id).trim() : name;
    id = id.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    if (!id) {
      id = 'prog-' + crypto.createHash('md5').update(name).digest('hex').slice(0, 6);
    }

    const base = id;
    let i = 2;
    while (seenIds.has(id)) {
      id = `${base}-${i}`;
      i++;
    }
    seenIds.add(id);

    clean.push({
      id,
      name,
      desc: String(p.desc || '').trim(),
    });
  }

  const newConfig = {
    carpetaCompartida: String(json.carpetaCompartida || '').trim(),
    carpetaFisica: String(json.carpetaFisica || '').trim(),
    rutaHCPToolKit: String(json.rutaHCPToolKit || '').trim(),
    programs: clean,
  };

  const configFile = path.join(DATA_DIR, 'config.json');
  fs.writeFileSync(configFile, JSON.stringify(newConfig, null, 2), 'utf-8');

  res.json({ ok: true, config: newConfig });
};
app.post(['/api/config-save', '/api/config-save.php'], requireAdmin, handleConfigSave);

// List reports
const handleList = (req, res) => {
  const items = [];
  if (fs.existsSync(DATA_DIR)) {
    const files = fs.readdirSync(DATA_DIR);
    for (const f of files) {
      if (f.startsWith('INF-') && f.endsWith('.json')) {
        try {
          const raw = fs.readFileSync(path.join(DATA_DIR, f), 'utf-8');
          const parsed = JSON.parse(raw);
          if (parsed && typeof parsed === 'object') {
            items.push(parsed);
          }
        } catch (e) {
          console.error('Error reading report file:', f, e);
        }
      }
    }
  }
  res.json({ ok: true, items });
};
app.get(['/api/list', '/api/list.php'], handleList);

// Get single report
const handleGet = (req, res) => {
  const id = req.query.id;
  if (!id || !/^[A-Za-z0-9\-]+$/.test(id)) {
    return res.status(400).json({ ok: false, error: 'ID inválido' });
  }

  const file = path.join(DATA_DIR, `${id}.json`);
  if (!fs.existsSync(file)) {
    return res.status(404).json({ ok: false, error: 'Informe no encontrado' });
  }

  try {
    const raw = fs.readFileSync(file, 'utf-8');
    const json = JSON.parse(raw);
    res.json({ ok: true, item: json });
  } catch {
    res.status(500).json({ ok: false, error: 'El archivo guardado está corrupto' });
  }
};
app.get(['/api/get', '/api/get.php'], handleGet);

// Save report
const handleSave = (req, res) => {
  const json = req.body;
  if (!json || typeof json !== 'object') {
    return res.status(400).json({ ok: false, error: 'JSON inválido' });
  }

  if (!json.id || !/^[A-Za-z0-9\-]+$/.test(json.id)) {
    return res.status(400).json({ ok: false, error: 'ID de informe ausente o con formato inválido' });
  }

  if (!json.createdAt) {
    json.createdAt = new Date().toISOString();
  }

  const file = path.join(DATA_DIR, `${json.id}.json`);
  fs.writeFileSync(file, JSON.stringify(json, null, 2), 'utf-8');

  res.json({ ok: true, id: json.id });
};
app.post(['/api/save', '/api/save.php'], handleSave);

// Update report (Admin required)
const handleUpdate = (req, res) => {
  const json = req.body;
  if (!json || typeof json !== 'object') {
    return res.status(400).json({ ok: false, error: 'JSON inválido' });
  }

  if (!json.id || !/^[A-Za-z0-9\-]+$/.test(json.id)) {
    return res.status(400).json({ ok: false, error: 'ID de informe ausente o con formato inválido' });
  }

  const file = path.join(DATA_DIR, `${json.id}.json`);
  fs.writeFileSync(file, JSON.stringify(json, null, 2), 'utf-8');

  res.json({ ok: true, id: json.id });
};
app.post(['/api/update', '/api/update.php'], requireAdmin, handleUpdate);

// Delete report (Admin required)
const handleDelete = (req, res) => {
  const id = req.body?.id;
  if (!id || !/^[A-Za-z0-9\-]+$/.test(id)) {
    return res.status(400).json({ ok: false, error: 'ID inválido' });
  }

  const file = path.join(DATA_DIR, `${id}.json`);
  if (!fs.existsSync(file)) {
    return res.status(404).json({ ok: false, error: 'Informe no encontrado' });
  }

  try {
    fs.unlinkSync(file);
    res.json({ ok: true });
  } catch {
    res.status(500).json({ ok: false, error: 'No se pudo borrar el archivo' });
  }
};
app.post(['/api/delete', '/api/delete.php'], requireAdmin, handleDelete);

// Software list
const handleSoftwareList = (req, res) => {
  const config = getConfig();
  const folderPath = (config.carpetaFisica || '').trim();

  if (!folderPath) {
    return res.json({
      ok: false,
      error: 'La ruta física de la carpeta compartida aún no está configurada. Un admin debe indicarla en el panel de Admin (pestaña "Software y carpeta compartida").',
    });
  }

  if (!fs.existsSync(folderPath)) {
    return res.json({
      ok: false,
      error: `No se pudo acceder a la carpeta: ${folderPath}. Comprueba que la ruta es correcta y que el servidor tiene permisos de lectura sobre ese recurso de red.`,
    });
  }

  try {
    const stat = fs.statSync(folderPath);
    if (!stat.isDirectory()) {
      return res.json({
        ok: false,
        error: `La ruta indicada no es un directorio: ${folderPath}`,
      });
    }

    const entries = fs.readdirSync(folderPath, { withFileTypes: true });
    const files = [];

    for (const ent of entries) {
      if (ent.isFile()) {
        const full = path.join(folderPath, ent.name);
        const fstat = fs.statSync(full);
        files.push({
          name: ent.name,
          size: fstat.size,
          modified: fstat.mtime.toISOString(),
        });
      }
    }

    files.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));
    res.json({ ok: true, path: folderPath, files });
  } catch (err) {
    res.json({
      ok: false,
      error: `No se pudo leer la carpeta: ${err.message}`,
    });
  }
};
app.get(['/api/software-list', '/api/software-list.php'], handleSoftwareList);

// Software download
const handleSoftwareDownload = (req, res) => {
  const name = String(req.query.file || '');
  if (!name || name.includes('/') || name.includes('\\') || name.includes('..')) {
    return res.status(400).json({ ok: false, error: 'Nombre de archivo inválido' });
  }

  const config = getConfig();
  const folderPath = (config.carpetaFisica || '').trim();

  if (!folderPath || !fs.existsSync(folderPath)) {
    return res.status(500).json({ ok: false, error: 'La carpeta compartida no está disponible' });
  }

  const fullPath = path.join(folderPath, name);
  const resolvedBase = path.resolve(folderPath);
  const resolvedFull = path.resolve(fullPath);

  if (!resolvedFull.startsWith(resolvedBase)) {
    return res.status(403).json({ ok: false, error: 'Acceso no permitido' });
  }

  if (!fs.existsSync(resolvedFull) || !fs.statSync(resolvedFull).isFile()) {
    return res.status(404).json({ ok: false, error: 'Archivo no encontrado' });
  }

  res.download(resolvedFull, path.basename(resolvedFull));
};
app.get(['/api/software-download', '/api/software-download.php'], handleSoftwareDownload);

// HCPToolKit download
const handleHcptoolkitDownload = (req, res) => {
  const config = getConfig();
  const exePath = (config.rutaHCPToolKit || '').trim();

  if (!exePath) {
    return res.status(404).json({
      ok: false,
      error: 'Un admin todavía no ha configurado la ubicación de HCPToolKit. Pídele que la indique en el panel de Admin → "Software y carpeta compartida".',
    });
  }

  if (!fs.existsSync(exePath) || !fs.statSync(exePath).isFile()) {
    return res.status(500).json({
      ok: false,
      error: `No se pudo acceder al ejecutable en: ${exePath}. Comprueba que la ruta es correcta y que el servidor tiene permisos de lectura sobre ese recurso de red.`,
    });
  }

  res.download(exePath, path.basename(exePath));
};
app.get(['/api/hcptoolkit-download', '/api/hcptoolkit-download.php'], handleHcptoolkitDownload);

// -----------------------------------------------------------------------------
// Static Asset Serving
// -----------------------------------------------------------------------------

// Serve lib directory
app.use('/lib', express.static(path.join(__dirname, 'lib')));

// Explicitly deny direct access to data directory
app.use('/data', (req, res) => {
  res.status(403).json({ error: 'Direct access to data directory is forbidden' });
});

// Serve index.html for root and SPA routes
app.get(['/', '/index.html'], (req, res) => {
  res.sendFile(path.join(__dirname, 'index.html'));
});

// Start listening
app.listen(PORT, '0.0.0.0', () => {
  console.log(`HCP Informe de Instalación server running on http://0.0.0.0:${PORT}`);
});
