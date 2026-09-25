# Despliegue en IIS (WebHCPTOOLS)

## 1. Copiar archivos
Copia toda la carpeta `informe-instalacion` dentro del sitio, por ejemplo dentro de `it`:

```
W:\it\informe-instalacion\
  index.html
  web.config
  lib\
    jspdf.umd.min.js
    pdf-lib.min.js
    html2canvas.min.js
  includes\
    auth.php
  api\
    login.php
    logout.php
    session.php
    save.php
    update.php
    delete.php
    list.php
    get.php
    config.php
    config-save.php
    software-list.php
    software-download.php
    hcptoolkit-download.php
  data\        ← aquí se guardan los informes y la configuración (se crea sola)
```

## 2. Permisos de escritura
La carpeta `data` necesita permiso de **escritura** para el usuario/grupo con el que corre PHP en IIS
(normalmente `IIS_IUSRS` o el App Pool identity, p.ej. `IIS AppPool\DefaultAppPool`):

1. Clic derecho sobre `data` → Propiedades → Seguridad → Editar → Agregar `IIS_IUSRS`
2. Marca "Modificar" y "Escritura" → Aplicar

Sin este permiso, guardar/editar informes y la configuración fallará (aunque el navegador
seguirá guardando una copia local como respaldo).

## 3. Comprobar que PHP está activo en el sitio
En IIS Manager, con el sitio/carpeta seleccionado, entra en "Asignaciones de controladores" (Handler Mappings)
y confirma que existe una entrada para `*.php` apuntando a `php-cgi.exe` (vía FastCGI). Si no aparece, el
administrador del servidor debe habilitar PHP para ese sitio antes de que la API funcione.

También asegúrate de que las **sesiones de PHP** funcionan (necesarias para el login de admin): por defecto
PHP guarda las sesiones en una carpeta temporal del servidor; normalmente no requiere configuración extra,
pero si el login de admin no "recuerda" la sesión, revisa el valor de `session.save_path` en el `php.ini` del
servidor y que esa carpeta tenga permisos de escritura para el App Pool.

## 4. Acceso
Una vez copiado, se accede en:

```
http://192.168.0.101/it/informe-instalacion/
```
(ajusta la ruta según dónde lo coloques dentro del sitio)

## 5. Panel de administración
Botón **"🔒 Admin"** en la barra superior.

- Usuario: `admin`
- Contraseña: `Qaz123,.-`

La contraseña se compara siempre como hash (bcrypt), nunca en texto plano, y se verifica en el
servidor (`includes/auth.php`), no en el navegador — así que aunque alguien mire el código fuente de
la página no puede saltarse el login. Desde el panel se puede:

- **Informes**: editar (reabre el asistente con esos datos y guarda al final) o borrar cualquier
  informe guardado.
- **Software y carpeta compartida**: cambiar la ruta que ven los técnicos (`Y:\03_IT\02_SOFTWARE_BASICO`),
  la ruta UNC real que usa el servidor para listar/descargar, la ruta al ejecutable **HCPToolKit**, y
  añadir/editar/quitar programas del catálogo — se refleja al momento para todos los técnicos, sin
  tocar código. Los tres campos de ruta tienen un botón **"📁 Examinar carpeta/archivo"** que abre el
  explorador nativo del sistema para navegar y confirmar la carpeta o archivo correcto. Importante:
  por seguridad, **ningún navegador revela la ruta completa** de una carpeta o archivo seleccionado (ni
  de red ni local) — solo su nombre. El botón usa ese nombre para sustituir el último tramo de la ruta
  ya escrita (para evitar errores de tipeo), pero el resto de la ruta (unidad o
  `\\servidor\recurso\...`) hay que escribirlo a mano una vez.

Si más adelante quieres cambiar la contraseña, genera un nuevo hash (con PHP, en el propio servidor):

```
php -r "echo password_hash('NuevaContraseña', PASSWORD_DEFAULT);"
```

y sustituye el valor de `ADMIN_PASS_HASH` en `includes/auth.php`.

## 6. Descarga de instaladores desde el navegador (¡importante!)
La ruta `Y:\03_IT\02_SOFTWARE_BASICO` es una **letra de unidad mapeada en la sesión de Windows de
cada técnico** — el servidor (PHP/IIS) no tiene forma de saber a qué apunta esa letra, porque las
unidades de red son personales de cada sesión, no del servidor. Por eso hay **dos rutas distintas**
en la configuración (pestaña "Software y carpeta compartida" del panel de Admin):

1. **Ruta que ven los técnicos** — `Y:\03_IT\02_SOFTWARE_BASICO`. Solo es texto
   informativo/copiable en el paso 2 del asistente.
2. **Ruta UNC real** — ya está puesta por defecto: `\\cibeles\00_RECURSOS BIM\03_IT\02_SOFTWARE_BASICO`.
   Es la que usa el propio servidor para leer los archivos de esa carpeta y ofrecer el botón de
   descarga. Si en algún momento cambia, se actualiza desde el panel de Admin sin tocar código.

   ⚠️ Ojo: ese valor por defecto solo se aplica la **primera vez** que se crea `data/config.json`
   (por ejemplo, en una instalación nueva). Si ya habías desplegado una versión anterior de esta
   herramienta en el servidor, `config.json` ya existe con la ruta antigua ("pendiente") y no se
   sobrescribe solo — en ese caso entra al panel de Admin → "Software y carpeta compartida" y pega
   ahí la ruta UNC de arriba, o borra `data/config.json` para que se regenere con el valor nuevo.

Una vez esté esa ruta UNC configurada, hace falta además que **el proceso de PHP tenga permiso de
lectura sobre ese recurso de red**:

- Por defecto, el Application Pool de IIS corre como `ApplicationPoolIdentity`, una cuenta local que
  normalmente **no tiene acceso a recursos compartidos de red**.
- Para que funcione, en IIS Manager → Grupos de aplicaciones → (tu App Pool) → Configuración avanzada
  → Identidad, cambia la identidad a una cuenta de dominio/usuario que sí tenga permisos de lectura
  sobre esa carpeta compartida.
- Si no se configura esto, el paso 2 mostrará el aviso "No se pudo acceder a la carpeta" — no es un
  error de la web, es un permiso pendiente en el servidor.

Con eso listo:
- `api/software-list.php` lista los archivos de esa carpeta (nombre y tamaño) directamente en el
  paso 2 del asistente.
- Cada archivo tiene un botón **"⬇ Descargar"** que llama a `api/software-download.php`, el cual
  valida que el archivo pedido esté realmente dentro de esa carpeta (para que nadie pueda pedir otra
  ruta del servidor) y lo envía como descarga.

## 7. Cómo funciona el guardado compartido
- Al generar un informe, el navegador llama a `api/save.php`, que escribe un archivo
  `INF-XXXXXXX.json` dentro de `data/`.
- El historial (`api/list.php`) lee todos los `.json` de esa carpeta y los devuelve — así todos
  los técnicos que abran la página ven los mismos informes, guardados en el servidor.
- El navegador también guarda una copia en `localStorage` como respaldo, por si en algún momento
  no hay conexión con el servidor (por ejemplo se sigue viendo el historial local aunque falle la API).
- `web.config` oculta las carpetas `data` e `includes` para que nadie pueda entrar directamente a
  `.../informe-instalacion/data/` o `.../includes/` desde el navegador.
- Editar y borrar informes, y guardar la configuración, están protegidos en el servidor
  (`update.php`, `delete.php`, `config-save.php`) — exigen sesión de admin activa aunque alguien
  intente llamarlos directamente sin pasar por el panel.

## 8. Especificaciones del equipo — vía HCPToolKit (informe en PDF)
Este paso ya **no detecta nada automáticamente desde el navegador** ni tiene campos manuales de ficha
técnica. El flujo ahora es:

1. El técnico pulsa **"⬇ Descargar HCPToolKit"** en el paso "Especificaciones del equipo" — descarga
   el ejecutable desde la ruta que configures en el panel de Admin → "Software y carpeta compartida" →
   "Ejecutable HCPToolKit" (misma lógica de ruta UNC + permisos del App Pool que la carpeta de software,
   ver punto 6).
2. Lo ejecuta en el equipo. HCPToolKit genera un **informe en PDF** con el hardware.
3. El técnico pulsa **"📤 Subir informe de HCPToolKit"** y selecciona ese PDF — se previsualiza al
   momento ahí mismo (dentro de la página, sin descargar nada) y queda adjunto al informe.

Ese mismo PDF se muestra también embebido dentro del informe final (al ver o generar el informe), y
al pulsar **"📄 Exportar PDF"** se **fusiona de verdad** con el informe de instalación en un único
PDF descargable — no son dos archivos separados ni una captura de pantalla del PDF, son las páginas
reales del informe de HCPToolKit añadidas al final del PDF generado.

Esto se hace con dos librerías que van dentro de la carpeta `lib/` (ya incluidas en el zip, no hace
falta internet en el servidor ni en el navegador del técnico):
- **jsPDF** — genera el PDF del informe de instalación a partir de la página HTML.
- **pdf-lib** — fusiona ese PDF recién generado con las páginas reales del PDF de HCPToolKit.

Solo hay que asegurarse de que la carpeta `lib/` viaje junto a `index.html` al desplegar (ver
estructura de carpetas en el punto 1); si falta, el botón "Exportar PDF" avisará con un mensaje claro
en vez de fallar en silencio.

## 9. Copias de seguridad
Como todo son archivos `.json` sueltos en `data/` (informes + `config.json`), basta con incluir esa
carpeta en el backup habitual del servidor (o del recurso compartido `WebHCPTOOLS1.0`) para no perder
nada.
