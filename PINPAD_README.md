# Módulo Pin Pad Medianet — Documentación completa

> **Última actualización**: 2026-04-28
> **Versión del manual de mensajería implementado**: v1.4 (Sept 2024)
> **Librería oficial usada**: `Libreria_integracion_tramas_V1.2.jar`

---

## Tabla de contenidos

1. [Qué es este módulo](#1-qué-es-este-módulo)
2. [Arquitectura general](#2-arquitectura-general)
3. [Estructura de archivos](#3-estructura-de-archivos)
4. [Configuración (.env y config/pinpad.php)](#4-configuración)
5. [Endpoints REST disponibles](#5-endpoints-rest-disponibles)
6. [Componentes Angular](#6-componentes-angular)
7. [Capa Java (TramaJarBridge)](#7-capa-java-tramajarbridge)
8. [Flujo de una transacción](#8-flujo-de-una-transacción)
9. [Cache de reverso](#9-cache-de-reverso)
10. [Validaciones por operación](#10-validaciones-por-operación)
11. [Deploy en producción](#11-deploy-en-producción)
12. [Troubleshooting](#12-troubleshooting)
13. [Resumen de la documentación oficial](#13-resumen-de-la-documentación-oficial)

---

## 1. Qué es este módulo

Integra un **Pin Pad físico de Medianet** (lectura de tarjetas de crédito/débito) con el CRM Almacenes España. Permite:

- **Cobros** con tarjeta (corriente, diferido, anulación, reverso, maxidolar)
- **Operaciones administrativas** (cierre de turno, lectura de tarjeta, configuración)
- **Avance en efectivo** (cash advance)
- **Modo "librería oficial"**: abre la UI JavaFX oficial de Medianet para validar contra ella

El módulo está diseñado para **convivir con el CRM principal** sin tocar otros componentes. Toda su funcionalidad vive bajo `/api/almacenesespana/pinpad/*` en backend y `pages/pinpad/*` en Angular.

---

## 2. Arquitectura general

```
┌──────────────────┐  HTTP/JSON   ┌──────────────────┐  TCP    ┌──────────────────┐
│  Angular         │ ───────────▶ │  Laravel         │ ──────▶ │  Pin Pad físico  │
│  (browser)       │              │  (PHP backend)   │ ◀────── │  192.168.1.242   │
│                  │              │                  │         │  :6500           │
└──────────────────┘              └──────────────────┘         └────────┬─────────┘
                                          │                             │ TCP
                                          │ shell_exec                  │
                                          ▼                             ▼
                                  ┌──────────────────┐         ┌──────────────────┐
                                  │  Java JavaFX     │         │  Switch Medianet │
                                  │  Trama Builder   │         │  10.10.3.200     │
                                  │  (UI oficial)    │         │  (privado)       │
                                  └──────────────────┘         └──────────────────┘
```

### Por qué dos rutas (Laravel directo vs JAR)

- **Vía Laravel directo** (`/cobrar`, `/diferido`, etc.): el backend arma la trama **en PHP** siguiendo el manual oficial v1.4. Rápido, sin UI extra.
- **Vía JAR** (`/jar/abrir`): abre la UI oficial JavaFX. Útil para:
  - Validar que tu PHP genera tramas idénticas a la oficial
  - Casos donde el cajero prefiere los formularios oficiales
  - Operaciones nuevas que aún no implementaste en PHP

---

## 3. Estructura de archivos

### Backend (Laravel)

```
crm-back/
├── jars/                                          ← carpeta nueva, todo aquí dentro
│   ├── Libreria_integracion_tramas_V1.2.jar     ← librería oficial Medianet
│   ├── TramaJarBridge.java                       ← código fuente del wrapper
│   ├── TramaJarBridge.class                      ← compilado
│   ├── COMPILAR.md                               ← cómo recompilar el wrapper
│   └── javafx-sdk/                               ← SDK JavaFX 17 (Windows)
│       ├── lib/                                  ← módulos JavaFX (.jar)
│       └── bin/                                  ← DLLs nativas Windows
│
├── app/
│   ├── Servicios/
│   │   ├── Pinpad/                               ← ENGINE PHP (operaciones nativas)
│   │   │   ├── Conexion.php                      ← cliente TCP al Pin Pad
│   │   │   ├── CifradoTramas.php                 ← 3DES con phpseclib
│   │   │   ├── Trama.php                         ← builder + parser de tramas
│   │   │   └── TramaCache.php                    ← cache de reverso (Laravel Cache)
│   │   └── PinpadJar/                            ← ENGINE JavaFX
│   │       └── JarBridge.php                     ← lanza Java + lee archivos
│   │
│   └── Http/Controllers/
│       ├── pinpad/
│       │   └── PinpadController.php              ← endpoints PHP nativos
│       └── pinpadjar/
│           └── PinpadJarController.php           ← endpoints del JAR bridge
│
├── config/
│   └── pinpad.php                                ← config (lee de .env)
│
├── routes/
│   └── api.php                                   ← rutas (agregadas al grupo "almacenesespana")
│
├── storage/
│   └── app/
│       └── pinpad-sessions/                      ← se crea sola: archivos {uuid}.txt/.done
│
└── .env                                          ← variables de entorno
```

### Frontend (Angular)

```
crm2-front/src/app/
├── service/
│   └── pinpad/
│       ├── pinpad.service.ts                     ← cliente HTTP del engine PHP
│       └── pinpad-jar.service.ts                 ← cliente HTTP del engine JAR
│
└── pages/
    └── pinpad/
        ├── operaciones-pinpad/                   ← UI con tabs (PP/CT/LT/PC/RA/CP)
        │   ├── operaciones-pinpad.component.ts
        │   ├── operaciones-pinpad.component.html
        │   └── operaciones-pinpad.component.css
        └── libreria-jar/                         ← UI para abrir el JAR oficial
            ├── libreria-jar.component.ts
            ├── libreria-jar.component.html
            └── libreria-jar.component.css
```

---

## 4. Configuración

### `.env` (variables de entorno)

```env
# ---- PIN PAD MEDIANET ----
# Conexión al Pin Pad físico
PINPAD_IP=192.168.1.242         # IP del Pin Pad (CAMBIAR por sucursal)
PINPAD_PORT=6500                # Puerto TCP del Pin Pad
PINPAD_HOST=10.10.3.200         # IP del switch Medianet (informativo, lo usa el Pin Pad internamente)

# Identificadores del comercio (los entrega Medianet/Banco Bolivariano)
PINPAD_MID=000000836060         # Merchant ID (CAMBIAR por sucursal)
PINPAD_TID=AEP00101             # Terminal ID (CAMBIAR por sucursal)
PINPAD_CID_TERMINAL=            # CID Asociado Pinpad (opcional)

# Cache de reverso (en minutos) — duración del turno típico
PINPAD_REVERSO_TTL_MINUTES=480

# JAR Bridge — DESARROLLO (Windows + XAMPP)
JAVA_BIN='C:\Program Files\Eclipse Adoptium\jdk-17.0.18.8-hotspot\bin\java.exe'
JAVAFX_LIB_PATH='C:\xampp\htdocs\desarrollo\crm\crm-back\jars\javafx-sdk\lib'
LIBRERIA_TRAMAS_JAR='C:\xampp\htdocs\desarrollo\crm\crm-back\jars\Libreria_integracion_tramas_V1.2.jar'

# JAR Bridge — PRODUCCION (AlmaLinux). Comentar las de Windows y descomentar estas:
# JAVA_BIN='java'
# JAVAFX_LIB_PATH='/var/www/crm-back/jars/javafx-sdk/lib'
# LIBRERIA_TRAMAS_JAR='/var/www/crm-back/jars/Libreria_integracion_tramas_V1.2.jar'
```

> **Importante**: usa **comillas simples** (`'`) para paths con backslashes. Las dobles (`"`) intentan procesar `\E`, `\j` como escape sequences y rompen el parser.

### `config/pinpad.php` (lee del .env)

Este archivo NO se modifica directamente. Si necesitas cambiar un valor, edita el `.env` y luego:

```bash
php artisan config:clear
```

### Qué cambiar y dónde

| Necesidad | Dónde |
|---|---|
| Cambiar IP/puerto del Pin Pad | `.env` → `PINPAD_IP`, `PINPAD_PORT` |
| Cambiar MID/TID al desplegar en otra sucursal | `.env` → `PINPAD_MID`, `PINPAD_TID` |
| Aumentar timeout TCP | `.env` → `PINPAD_TIMEOUT_MS` (default 30000) |
| Pasar a producción Linux | `.env` → comentar líneas Windows, descomentar Linux |
| Modificar el TTL del cache de reverso | `.env` → `PINPAD_REVERSO_TTL_MINUTES` |
| Modificar el formato de las tramas | `app/Servicios/Pinpad/Trama.php` |
| Cambiar las llaves 3DES | `app/Servicios/Pinpad/CifradoTramas.php` (constantes `FIXED_DATA`, `FIXED_KEY`) |
| Agregar nuevos endpoints | `app/Http/Controllers/pinpad/PinpadController.php` + `routes/api.php` |
| Agregar nuevos códigos de respuesta | `app/Servicios/Pinpad/Trama.php` → `descripcionCodigoRespuesta()` |

---

## 5. Endpoints REST disponibles

Todos bajo el prefijo `/api/almacenesespana/`.

### Engine PHP nativo

| Método | URL | Operación | Body de ejemplo |
|---|---|---|---|
| GET | `/pinpad/probe` | Verifica conexión TCP al Pin Pad | — |
| GET | `/pinpad/hash` | Genera y valida un hash 3DES (debug) | — |
| POST | `/pinpad/raw` | Envía una trama YA construida | `{trama: "00d4PP01..."}` |
| POST | `/pinpad/cobrar` | **PP corriente** | `{total, base15, base0, iva, servicio?, propina?}` |
| POST | `/pinpad/diferido` | PP diferido (cuotas) | `{...amounts, modalidad, plazo, gracia_meses?}` |
| POST | `/pinpad/anular` | PP anulación | `{referencia}` |
| POST | `/pinpad/reverso` | PP reverso (usa cache si hay) | `{}` |
| GET | `/pinpad/reverso-disponible` | Si hay un reverso cacheado | — |
| POST | `/pinpad/maxidolar` | Maxidolar consulta/pago | `{modalidad, total?}` |
| POST | `/pinpad/cierre-turno` | CT - Cierre de turno | — |
| POST | `/pinpad/lectura` | LT - Lectura de tarjeta | `{monto?}` |
| POST | `/pinpad/cierre-lote` | PC - Cierre de lote | `{batch?, reference?}` |
| POST | `/pinpad/cambio-parametros` | CP - Configuración Pin Pad | `{ip, mask, gateway, listening_port, ...}` |
| POST | `/pinpad/reimpresion` | RA - Avance en efectivo | `{modalidad, serial, total, ...}` |

### Engine JAR (TramaBuilder JavaFX)

| Método | URL | Operación |
|---|---|---|
| GET | `/pinpad/jar/diag` | Diagnóstico (verifica paths) |
| POST | `/pinpad/jar/abrir` | Abre la ventana JavaFX, devuelve `session_id` |
| GET | `/pinpad/jar/leer/{sessionId}` | Polling: pending / ready / cancelled |
| DELETE | `/pinpad/jar/limpiar/{sessionId}` | Borra archivos de sesión |

### Formato de respuesta (estándar `RespuestaApi`)

```json
{
  "code": 200,
  "status": "success" | "error",
  "message": "Texto humano",
  "icon": "success" | "error",
  "color": "#99ff99" | "#ff4000",
  "data": { ... payload específico ... }
}
```

---

## 6. Componentes Angular

### `OperacionesPinpadComponent` — `/pinpad`

Pantalla con **6 pestañas**, una por cada operación del manual oficial:

| Tab | Operación | Validaciones |
|---|---|---|
| **PP** | Transacciones de Venta (corriente, diferido, anulación, reverso, maxidolar) | Total > 0; longitud ≤ 18; plazo 1-99; gracia 0-99 |
| **CT** | Consulta de Tarjeta | Sin parámetros |
| **LT** | Lectura de Tarjeta | Monto > 0 si "agregar monto" |
| **PC** | Proceso de Control (cierre de lote) | batch/reference numéricos requeridos |
| **RA** | Avance en Efectivo | Serial alfanum 10-15 chars; total > 0 (corriente/diferido) |
| **CP** | Configuración Pin Pad | IPv4 válida; puertos 1024-65535 |

Las validaciones replican las que hace la librería oficial JavaFX (extraídas del bytecode), más las que dicta el manual.

### `LibreriaJarComponent` — `/pinpad-libreria-jar`

Pantalla con un solo botón **"Abrir Generador de Tramas"**. Flujo:

1. Click → Laravel lanza el JAR vía `shell_exec`
2. Aparece la ventana JavaFX oficial en la pantalla del cajero
3. Cajero llena formularios y da Generar
4. La trama se escribe a un archivo `{session_id}.txt`
5. Angular hace polling cada 1.5s al endpoint `/jar/leer/`
6. Cuando aparece la trama, se muestra al usuario
7. Botón **"Enviar al Pin Pad"** la transmite vía `/pinpad/raw`

---

## 7. Capa Java (TramaJarBridge)

### `jars/TramaJarBridge.java`

Wrapper minimal (45 líneas) que:

1. Recibe `session_id` y `storage_path` como argumentos
2. Inicializa JavaFX y abre la ventana del Trama Builder oficial
3. Escucha `TramaHolder.tramaProperty()` (la propiedad donde la librería oficial publica las tramas generadas)
4. Cuando se publica una trama → la escribe en `{storage}/{session_id}.txt`
5. Cuando el usuario cierra la ventana → escribe `{storage}/{session_id}.done`

### Compilación

Ya está compilado en `jars/TramaJarBridge.class`. Solo necesitas recompilar si modificas el `.java`. Instrucciones en `jars/COMPILAR.md`.

---

## 8. Flujo de una transacción

### Caso 1: Cobro corriente vía PHP nativo (lo más común)

```
1. Usuario en Angular tab PP, modalidad="corriente":
   total=1.12, base15=1.00, iva=0.12

2. Click "Ejecutar"
   → POST /api/almacenesespana/pinpad/cobrar
   → Body: { total: 1.12, base15: 1.00, base0: 0, iva: 0.12, ... }

3. PinpadController::cobrar
   → Valida los inputs
   → Construye payload incluyendo MID/TID del .env
   → Llama a Trama::buildPp('corriente', payload)

4. Trama::buildPp
   → Construye los 212 chars del cuerpo según el manual:
     [PP][01][2][00][00][00][ ][totales][refs][TIME][DATE][MID][TID][CID][spaces][HASH]
   → Antepone "00d4" (longitud en hex)
   → Devuelve string de 216 chars

5. Conexion::sendRecv
   → Abre TCP a 192.168.1.242:6500
   → Envía la trama
   → Espera respuesta

6. Pin Pad procesa con el cliente:
   - Lee tarjeta
   - Pide PIN
   - Conecta a switch Medianet (10.10.3.200)
   - Recibe aprobación/rechazo
   - Imprime voucher

7. Pin Pad responde:
   "0202PP00...AUTORIZACION OK..."

8. Trama::parseResponse
   → Detecta "00" como código aprobado
   → Extrae mensaje, hash, etc.

9. TramaCache::storeReverso(TID, trama)
   → Guarda la versión reverso (con char[7]='4') en cache
   → TTL = 480 min

10. Respuesta JSON al frontend con cod_resp="00", mensaje="AUTORIZACION OK"

11. Angular muestra Swal "Aprobada"
```

### Caso 2: Reverso (después de un timeout)

```
1. Usuario va a tab PP, modalidad="reverso"
   → Componente llama GET /pinpad/reverso-disponible
   → Backend responde: { disponible: true }
   → UI muestra: "✓ Hay una trama de reverso cacheada"

2. Click "Ejecutar"
   → POST /pinpad/reverso

3. PinpadController::reverso
   → TramaCache::getReverso(TID)
   → Recupera la trama original con char[7]='4' (TXN=04 = Reverso)

4. Conexion::sendRecv
   → Envía la MISMA trama de antes (mismo hash, hora, fecha, montos)
   → Solo cambia el TXN a "04"
   → Pin Pad puede matchearla con la transacción original

5. Si responde "00" → cache se borra (no hay nada que reversar dos veces)
```

### Caso 3: Vía JAR (UI oficial)

```
1. Usuario en /pinpad-libreria-jar, click "Abrir Generador"

2. POST /pinpad/jar/abrir
   → Genera UUID
   → shell_exec: java ... TramaJarBridge {uuid} {storage}
   → Devuelve { session_id: uuid }

3. Java se levanta:
   - JVM inicializa JavaFX
   - TramaBuilderApp.showWindow() abre la ventana
   - Listener escucha TramaHolder.tramaProperty()

4. Angular inicia polling cada 1.5s:
   → GET /pinpad/jar/leer/{uuid}
   → Backend: lee {storage}/{uuid}.txt
   → Si no existe → "pending"

5. Cajero llena la UI JavaFX:
   - Pestaña PP, modalidad Corriente
   - Total $1.12
   - Click "Generar"
   - Aparece popup ✓

6. La librería oficial:
   - Construye la trama (con SU lógica interna)
   - Llama a TramaHolder.setTrama(...)
   - Mi listener recibe el evento
   - Escribe la trama en {storage}/{uuid}.txt

7. Próximo polling:
   → Backend: encuentra el .txt, lo lee
   → Devuelve { status: 'ready', trama: '...' }

8. Angular cambia a estado "ready"
   - Muestra la trama
   - Botón "Enviar al Pin Pad"

9. Click → POST /pinpad/raw con la trama
   → Mismo flujo que Caso 1 desde el paso 5
```

---

## 9. Cache de reverso

### Por qué existe

El manual oficial (sección 4.1.4 y respuesta `TO – Timeout`) indica que cuando una transacción no recibe respuesta del switch, **debe enviarse un reverso** para no quedar en estado inconsistente. El reverso debe contener **exactamente los mismos datos** (montos, hora, fecha, hash) que la transacción original.

### Cómo funciona

Cuando una transacción PP/RA exitosa se envía:

1. `enviarYParsear()` (en el controlador) construye la trama
2. `TramaCache::storeReverso($tid, $trama)` la guarda con un cambio:
   - Sobreescribe `char[7]` (segundo char del TXN) con `'4'`
   - Resultado: TXN "01" → "04" (que es el código de Reverso)
3. Se almacena en `Cache::put('pinpad:reverso:{TID}', $tramaModificada, ttl)`

Cuando el usuario solicita reverso:

1. `TramaCache::getReverso($tid)` recupera la trama cacheada
2. Se envía tal cual (mismo hash, mismos montos, solo TXN distinto)
3. El switch Medianet la matchea con la original y la reversa

### Configuración

| Variable | Default | Descripción |
|---|---|---|
| `PINPAD_REVERSO_TTL_MINUTES` | 480 (8h) | Tiempo que vive el cache. Un turno típico de cajero. |

Limpiar manualmente:

```php
// PHP
\App\Servicios\Pinpad\TramaCache::clearReverso('AEP00101');

// Artisan tinker
php artisan tinker
>>> Cache::forget('pinpad:reverso:AEP00101')

// Limpiar TODO el cache
php artisan cache:clear
```

---

## 10. Validaciones por operación

Replican las del bytecode oficial + las del manual v1.4.

### PP (Transacciones de Venta)

| Campo | Tipo | Requerido | Longitud | Notas |
|---|---|---|---|---|
| MID | alfanumérico | Sí | 15 | Del .env |
| TID | alfanumérico | Sí | 8 (exacto) | Del .env |
| Total | numérico | Sí | ≤ 18 chars | > 0 |
| Base15/Base0/IVA | numérico | Sí | 12 | ≥ 0 |
| Servicio/Propina/Fijo | numérico | No | 12 | Blanco si vacío |
| Plazo (diferido) | numérico | Sí (si diferido) | 2 | 1-99 |
| Gracia (diferido) | numérico | No | 2 | 0-99 |
| Referencia (anulación) | numérico | Sí (si anulación) | 6 | |

### CT, LT, PC, RA, CP

Ver tabla detallada en el componente `OperacionesPinpadComponent`. Los validadores están en métodos `is*Valid()` del componente y duplicados en `$req->validate()` del controller (defensa en profundidad).

---

## 11. Deploy en producción

### Arquitectura recomendada

⚠ **El módulo Pin Pad es local por naturaleza** — el Pin Pad está físicamente en cada sucursal. Por eso:

```
┌─ AlmaLinux (servidor central) ──┐    ┌─ PC Cajero 1 ─────────────┐
│  • Frontend Angular              │    │  • Laravel local           │
│  • Backend CRM general           │    │  • Java 17                 │
│  • DB Oracle                     │    │  • Pin Pad físico en LAN   │
└──────────────────────────────────┘    └────────────────────────────┘

         ▲                                      ▲
         │                                      │
   browser CRM ───── llama a ambos ─────────────┘
```

### Pasos de deploy en una caja nueva (Windows)

```powershell
# 1. Instalar Java JDK 17 (Adoptium Temurin)
#    Descarga .msi de https://adoptium.net/

# 2. Instalar PHP 7.3+ (o XAMPP)

# 3. Instalar Composer

# 4. Clonar el proyecto
git clone <repo-url> C:\medianet-pinpad\crm-back
cd C:\medianet-pinpad\crm-back

# 5. Instalar dependencias
composer install --no-dev --optimize-autoloader

# 6. Configurar .env
cp .env.example .env
notepad .env
# Ajustar:
#   - PINPAD_IP, PINPAD_PORT (de la sucursal)
#   - PINPAD_MID, PINPAD_TID (del comercio)
#   - JAVA_BIN, JAVAFX_LIB_PATH, LIBRERIA_TRAMAS_JAR (rutas absolutas)

# 7. Generar key y limpiar
php artisan key:generate
php artisan config:clear

# 8. Verificar
php artisan serve --host=0.0.0.0 --port=8009
# Abrir http://localhost:8009/api/almacenesespana/pinpad/jar/diag
# Todos los *_existe deben ser true
```

### Auto-arranque

Crea `C:\medianet-pinpad\start.bat`:

```bat
@echo off
title Pin Pad Service
cd /d C:\medianet-pinpad\crm-back
php artisan serve --host=0.0.0.0 --port=8009
```

Y agrégalo a `shell:startup` (Win+R → escribir `shell:startup` → arrastrar el .bat).

⚠ **NO lo configures como servicio Windows** si quieres usar el módulo `/pinpad-libreria-jar` — los servicios no pueden abrir GUIs (Session 0 Isolation).

### Firewall

```powershell
# Permitir el puerto 8009
New-NetFirewallRule -DisplayName "Pin Pad Service" -Direction Inbound -LocalPort 8009 -Protocol TCP -Action Allow

# Permitir Java outbound al Pin Pad y al switch
New-NetFirewallRule -DisplayName "Java Pin Pad" -Direction Outbound -Program "C:\Program Files\Eclipse Adoptium\jdk-17.0.18.8-hotspot\bin\java.exe" -Action Allow
```

---

## 12. Troubleshooting

| Síntoma | Causa | Solución |
|---|---|---|
| `php artisan serve` falla con "Failed to parse dotenv" | Comillas dobles en `.env` con backslashes | Usar comillas simples `'C:\path'` |
| `/jar/diag` devuelve `bridge_class_existe: false` | No se compiló `TramaJarBridge.class` | Seguir `jars/COMPILAR.md` |
| `/jar/abrir` responde 200 pero no aparece ventana | Laravel corriendo como servicio Windows | Usar `php artisan serve` con usuario interactivo |
| `/cobrar` devuelve `ERROR EN TRAMA` | Layout posicional incorrecto | Comparar trama enviada vs trama del JAR oficial |
| `/cobrar` devuelve `ERROR SEGURIDAD` | Hash 3DES con llaves incorrectas | Verificar `FIXED_DATA` y `FIXED_KEY` en `CifradoTramas.php` |
| `Connection refused` al probar Pin Pad | Pin Pad apagado o IP/puerto incorrectos | Verificar con `Test-NetConnection IP -Port PUERTO` |
| Timeout TCP | Pin Pad recibió pero no responde | Aumentar `PINPAD_TIMEOUT_MS` o reverso obligatorio |
| CORS bloquea peticiones | Frontend en otro dominio | Configurar `config/cors.php` o ajustar `URL_SERVICIOS` |
| `Status 0 Unknown Error` en Angular | artisan serve crasheó o no recogió rutas | `php artisan route:clear` y reiniciar serve |

### Logs útiles

```powershell
# Laravel
Get-Content C:\medianet-pinpad\crm-back\storage\logs\laravel.log -Wait -Tail 30

# Cache de reverso (lo que está guardado)
php artisan tinker
>>> Cache::get('pinpad:reverso:AEP00101')
```

---

## 13. Resumen de la documentación oficial

He leído los siguientes documentos de Medianet/WPOSS y aquí dejo un resumen de qué contiene cada uno:

### A. `Mensajeria_Caja_Pinpad_v1.4.pdf` (15 páginas) — **el contrato técnico**

> Es el **estándar oficial** de mensajería entre el sistema de caja y el Pin Pad. Define los **layouts byte por byte** de cada mensaje. Sin este doc, no podríamos haber armado las tramas correctas.

**Contenido**:
- **Pág 1-2**: introducción y resumen de transacciones soportadas
- **Pág 3**: layout de `LT` (Lectura/Consulta de Tarjeta) — cuerpo: `"LT" + Monto(12)`
- **Pág 4**: layout de `CT` (Consulta de Tarjeta sola) — cuerpo: solo `"CT"`
- **Pág 5-10**: layout completo de `PP` (Proceso de Pago, **el más importante**) con **21 campos posicionales**:
  - Header: TIPO(2), TXN(2), FILLER(1), MOD(2), PERIODO(2), GRACIA(2), separator(1)
  - Montos: 7 amounts × 12 chars
  - Refs: REF(6), TIME(6 HHmmss), DATE(8 yyyyMMdd), AUTH(6)
  - IDs: MID(15), TID(8), CID(15)
  - Filler(20) + HASH(32)
  - Total: **212 bytes** de cuerpo + 4 prefijo
- **Pág 11-12**: personalización para **Maxidolar** (layout PP con modificaciones)
- **Pág 13**: layout de `PC` (Proceso de Control / cierre de lote)
- **Pág 14-15**: layout de `CP` (Configuración Pin Pad)

**Códigos de respuesta** (los que vienen del Pin Pad):
- `00` = Ejecución Exitosa / Aprobada
- `01` = Error en Trama
- `02` = Error conexión Pinpad / Inicio de Día
- `03` = Error de Seguridad
- `20` = Error durante Proceso
- `TO` = Timeout (requiere reverso automático)
- `ER` = Error conexión Pinpad

### B. `Integración_Caja_Pinpad.pdf` (3 páginas) — **el manual de arranque**

> Explica el **modelo TCP/IP** y da un **ejemplo concreto** de trama. Útil para entender la arquitectura.

**Contenido**:
- Pin Pad escucha en puerto **6500** (default), IP fija asignada por la cadena
- Caja se conecta vía TCP/IP, envía la trama, recibe respuesta
- Existen 2 modos: offline (LT) y online (PP, PC)
- **Composición**: `Talla(4 hex) + ID(2) + Mensaje + Llave Seguridad(32 hex)`
- **Ejemplo real**: una trama PP corriente con $11.20:
  ```
  00D4PP012000000 000000001120000000001000000000000000000000000120  ...  10174020200603 ...  000000871703   29000102 ...
  ```
  De aquí derivé los códigos exactos: `FILLER=2` (Medianet), `MOD=00` (corriente), formato DATE = `yyyyMMdd`.

### C. `MANUAL DE USO LIBRERIA V1.pdf` (58 páginas) — **el manual de la librería oficial**

> Describe **la UI de la librería JavaFX** (`Libreria_integracion_tramas_V1.2.jar`) y cómo integrarla. La usé para entender qué validaciones hace la librería en el frontend y mapear los nombres de las pestañas.

**Contenido**:
- Cap 1: requisitos (JDK 11+, JavaFX 17.0.2+)
- Cap 2-3: cómo agregar la librería al proyecto Maven
- Cap 4: **flujos detallados de cada operación** (lo más útil):
  - 4.1.1 Venta corriente
  - 4.1.2 Venta diferida
  - 4.1.3 Anulación de transacción
  - 4.1.4 Reverso de transacción ← clave para el cache de reverso
  - 4.1.5 Maxidolar
  - 4.1.6 Avance corriente (RA)
  - 4.1.7 Avance diferido (RA)
  - 4.1.8 Anulación cash advance
  - 4.1.9 Reverso cash advance
  - 4.1.10 Configuración de Pinpad (CP)
  - 4.1.11 Proceso de control (PC)
  - 4.1.12 Lectura de tarjeta (LT)
  - 4.1.13 Consulta de tarjeta (CT)
- Cap 5: restricciones

**Comportamiento del cache de reverso** (sec 4.1.4):
> "El sistema intentará reversar **la última transacción** generada por la librería"
> "En caso de que no exista una transacción previa: 'No existe transacción para reversar.'"

Esto confirmó que el cache vive durante la sesión activa (sin TTL explícito en la librería).

### D. `CatalogoResp.txt` — códigos de autorizador

Tabla de **~120 códigos** del autorizador bancario (Visa/MasterCard/etc.) más errores internos del Pin Pad/VAP. Están **todos implementados** en `Trama::descripcionCodigoRespuesta()`, organizados por familia:

| Familia | Rango | Ejemplo | Cantidad |
|---|---|---|---|
| Errores Pin Pad / VAP | `@1`–`@R` | `@1 Error longitud`, `@B Timeout`, `@F BIN no existe` | 17 |
| Pin Pad propios | `TO`, `ER` | `TO Timeout (requiere reverso)` | 2 |
| Estándar autorizador | `00`–`98` | `00 Aprobada`, `51 Fondos insuficientes`, `54 Tarjeta expirada` | 56 |
| Tarjetas / emisores | `B1`, `e1` | `e1 Fondos insuficientes en extracupo` | 2 |
| MasterCard Stand-In | `M0`–`MK` | `M2 Emisor responde tarde` | 21 |
| STIP forzado | `N0`–`N7` | `N7 Negada por CVV2 invalido` | 4 |
| PVID (PIN) | `P0`–`P6` | `P6 PIN inseguro` | 5 |
| Autenticación | `Q1` | `Q1 Autenticacion de la tarjeta fallida` | 1 |
| Revocaciones | `R0`–`R3` | `R0 Orden de pago suspendida` | 3 |
| Visa Stand-In | `V1`–`V9` | `V1 Sistema del Emisor no responde` | 9 |
| Reenvíos | `XA`, `XD`, `Z3` | `Z3 Declinada, no disponible en linea` | 3 |

**Total**: ~120 códigos cubiertos. Si Medianet agrega códigos nuevos en futuros catálogos, solo edita el array `$tabla` en `Trama::descripcionCodigoRespuesta()`.

> El método retorna `"Codigo desconocido (XX)"` cuando recibe un código no listado, mostrando el código real para debug.

### E. `Adquirente_Emisor.txt` — códigos de bancos

Tabla con códigos de **~50 bancos emisores ecuatorianos** (Banco Bolivariano, Pichincha, Pacífico, etc.). No la usé directamente — está disponible para reportería futura.

### F. Decompilación del bytecode (no es doc oficial pero la usé)

Con **CFR decompiler** descompilé las clases del JAR oficial:

- **`cajapinpad.Conexion`** — TCP client (sendData, getDataRecived). 60 líneas, simple.
- **`cajapinpad.ProccessData`** — utilidades hex/BCD/padding. 100 líneas.
- **`cajapinpad.CifradoTramas`** — el verdadero algoritmo del hash. **Crítico**: descubrí que NO es SHA-256 sino **3DES EDE-2 con llaves embebidas**:
  - DATA fija = `EF12178E06711C05`
  - KEY fija = `BA0078E12733F411`
  - Cada hash genera 16 chars hex random como key adicional
- **`com.wposs.libreria_integracion_tramas.model.{a,b,c,d,e,f}`** — los layouts posicionales de cada mensaje (CP, CT, LT, PC, PP, RA respectivamente).
- **`com.wposs.libreria_integracion_tramas.viewmodel.*`** — controladores que mapean inputs de UI a campos del modelo.
- **`com.wposs.libreria_integracion_tramas.util.*`** — validadores y helpers.

Sin esta decompilación no hubiera podido implementar el hash correctamente ni los layouts exactos.

---

## Apéndice — Mantenimiento

### Compilar el wrapper Java cuando se modifique

```powershell
cd C:\xampp\htdocs\desarrollo\crm\crm-back\jars
& "C:\Program Files\Eclipse Adoptium\jdk-17.0.18.8-hotspot\bin\javac.exe" `
  -cp "Libreria_integracion_tramas_V1.2.jar" `
  --module-path "javafx-sdk\lib" `
  --add-modules javafx.controls,javafx.fxml `
  -d . `
  TramaJarBridge.java
```

### Agregar un nuevo código de respuesta

Editar `app/Servicios/Pinpad/Trama.php` → método `descripcionCodigoRespuesta()`.

### Cambiar la implementación del hash 3DES

Editar `app/Servicios/Pinpad/CifradoTramas.php`. Las constantes `FIXED_DATA` y `FIXED_KEY` provienen del bytecode oficial — solo cambiarlas si Medianet rota las llaves (avisarán por correo).

### Agregar un nuevo idioma de respuesta

Si Medianet agrega códigos en inglés (ej: "APPROVED" en vez de "AUTORIZADO"), edita el array `$patrones` en `Trama::parseResponse()`.

---

## Contactos

- **Anthony Bejarano** — Técnico Medianet — `0993180217` — `abejarano@medianet.com.ec`
- **Silvia Brito** — Medianet — `sbrito@medianet.com.ec`
- **María Gabriela Ordoñez** — Banco Bolivariano — `mordonec@bolivariano.com`
