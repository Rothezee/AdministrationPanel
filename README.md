ESP32 Project - Monitoreo de Máquinas de Premios y Videojuegos
===============================================================

Este proyecto consiste en una web de monitoreo en tiempo real para sistemas basados en ESP32, como grúas de peluches, maquinitas de premios, ticketeras y videojuegos similares. Permite visualizar el estado de cada máquina conectada (encendida, jugando, entrega de premio, etc.) y generar reportes de uso y actividades.

Características principales
--------------------------

- **Monitoreo en tiempo real** del funcionamiento de máquinas conectadas vía ESP32.
- **Detección de eventos**: encendido, actividad de juego, entrega de premios, etc.
- **Dashboard y reportes visuales** para administración.
- **Soporte para múltiples tipos de máquinas** (grúa, ticketera, videojuegos, expendedoras).
- **Acceso mediante sistema de login** solo para administradores.

Estructura general del proyecto
-------------------------------

Raíz del proyecto:

- `dashboard.php` / `dashboard.html`: panel principal de monitoreo de máquinas.
- `index.php`: pantalla de login de administradores.
- `report.php`, `report_videojuegos.php`, `report_videojuegos.html`, `report_ticketera.html` y `expendedora/report_expendedora.php`: pantallas de reportes por máquina/tipo.
- `api_receptor.php`: endpoint unificado que recibe la telemetría enviada por los ESP32 (heartbeat y datos de juego).
- `mqtt_listener.php`: proceso en PHP que se conecta al broker MQTT, escucha los tópicos de las máquinas y reenvía los mensajes a `api_receptor.php`.
- `get_all_devices.php`, `get_reports.php`, `check_status.php`, `delete_reports.php`: endpoints PHP usados por el dashboard y las pantallas de reportes para leer datos y gestionar borrados.
- `config.php`, `config.json`: configuración general del proyecto (conexiones y opciones varias).

Carpetas principales:

- `conn/`  
  Conexión a base de datos MySQL:
  - `connection.php`: crea la conexión compartida para los scripts que usan `mysqli`.

- `assets/`  
  Recursos estáticos del frontend:
  - `assets/css/style.css`: hoja de estilos principal (dashboard, login, reportes, modales, etc.).
  - `assets/js/main.js`: construcción del dashboard, sincronización con la API (`get_all_devices.php`, `get_data.php`, `check_status.php`) y gestión de la configuración de cada máquina en `localStorage`.
  - `assets/js/report.js`: lógica de reportes para máquinas de premios (cierres diarios/semanales/mensuales, gráficas, borrado por rango).
  - `assets/js/reportVideojuegos.js`: lógica específica de reportes para máquinas de videojuegos.
  - `assets/js/reportTicketera.js`: lógica específica de reportes para ticketeras.
  - `assets/js/reportExpendedora.js`: lógica específica de reportes para expendedoras (cierres, subcierres, tablas anidadas).
  - `assets/js/navbar.js`: comportamiento de la barra de navegación (menú responsive y dropdowns).

- `expendedora/`  
  Endpoints y vista de reportes específicos para expendedoras:
  - `report_expendedora.php`: página de reporte de una expendedora.
  - `get_report_expendedora.php`: obtiene movimientos crudos de fichas/dinero para esa expendedora.
  - `get_close_expendedora.php`: obtiene cierres diarios.
  - `get_subcierre_expendedora.php`: obtiene subcierres parciales por día.

- `src/mercadopago/`  
  Mercado Pago: crear preferencia (`create_payment.php`), webhook y acreditación por **MQTT** (`mqtt_credit.php` → tópico `maquinas/{device_id}/credit`). Requiere `composer install` en `src/devices/` (cliente MQTT PHP).

- **Configuración remota de grúa (MQTT)**  
  - Desde el dashboard, al configurar una máquina con tipo **Máquina (grúa)**, la sección *Parámetros en la máquina* envía un JSON por MQTT mediante `src/administracion/publish_grua_config.php`.  
  - **Tópico:** `{MQTT_TOPIC_PREFIX}/{codigo_hardware}/{MQTT_CONFIG_SUBTOPIC}` — por defecto `maquinas/<ID>/config`.  
  - **Payload:** `{"cmd":"set_grua_params","ts":<unix>,"pago":1-99,"t_agarre":500-5000,"t_fuerte":0-5000,"fuerza":5-101}` (mismos rangos que el menú físico; firmware: repo [ReporteGrua](https://github.com/Rothezee/ReporteGrua) — `GruaMQTT.ino`).  
  - **Variables de entorno:** `MQTT_BROKER`, `MQTT_PORT`, `MQTT_TOPIC_PREFIX` (igual que crédito MP); opcional `MQTT_CONFIG_SUBTOPIC` (default `config`).  
  - `mqtt_listener.php` **ignora** mensajes en ese subtopic para no insertar telemetría falsa en la API.  
  - **Firmware ESP32:** hay que `subscribe` al tópico `maquinas/<ID_DISP>/config` (mismo prefijo que datos/pulso), y en el callback: parsear JSON, validar `cmd === "set_grua_params"` y rangos, escribir EEPROM (`guardarInt` en `DIR_PAGO`, `DIR_T_AGARRE`, `DIR_FUERZA`, `DIR_T_FUERTE`) y actualizar variables en RAM (`pago`, `tAgarre`, `fuerza`, `tFuerte`). Sin esto, el panel publica pero la máquina no aplica cambios.  
  - **Seguridad:** en broker público, mitigar suplantación con un secreto compartido en el payload verificado por el firmware (mejora opcional).

- **OTA de firmware (GitHub + MQTT)**  
  - En el dashboard: **Administración → Actualizar firmware (OTA)** — elegís máquinas (checkboxes), **rama** del manifiesto y confirmación.  
  - **Backend:** `src/administracion/list_ota_branches.php` (ramas permitidas), `publish_ota_update.php` (publica JSON en `{MQTT_TOPIC_PREFIX}/{codigo}/{MQTT_OTA_SUBTOPIC}`, default subtopic `ota`).  
  - **Manifiesto:** JSON con `branches.<rama>.{version,url,sha256}` (URL solo `https://`, SHA-256 hex 64). Se descarga desde `OTA_MANIFEST_URL` o, si no está definida, desde `https://raw.githubusercontent.com/<GITHUB_REPO>/<OTA_MANIFEST_REF>/ota/manifest.json` (por defecto `GITHUB_REPO=Rothezee/ReporteGrua`, `OTA_MANIFEST_REF=main`).  
  - **Caché:** `OTA_MANIFEST_CACHE_TTL` segundos (default 120).  
  - **Opcional:** `GITHUB_TOKEN` (lectura/rate limit), `OTA_ALLOWED_BRANCHES` (lista separada por comas), `OTA_SHARED_SECRET` (se envía como `ota_secret` en el payload si el firmware lo valida).  
  - `mqtt_listener.php` **ignora** el subtopic OTA (igual que `config`) para no enviar esos mensajes a `api_receptor.php`.  
  - **CI y manifiesto:** viven en el repo del firmware [ReporteGrua](https://github.com/Rothezee/ReporteGrua) (workflow, `ota/manifest.json`). Contexto para otro agente: [`docs/REPORTEGRUA_REPO_HANDOFF.md`](docs/REPORTEGRUA_REPO_HANDOFF.md).

Requisitos
----------

- **Servidor web con soporte PHP y MySQL** (por ejemplo, AppServ, XAMPP, WAMP, etc.).
- Una o varias máquinas con ESP32, programadas para:
  - Enviar telemetría en formato JSON hacia el backend vía HTTP (por ejemplo, a `api_receptor.php`), o
  - Publicar mensajes en MQTT que luego recoge `mqtt_listener.php` y reenvía a `api_receptor.php`.
- Navegador web moderno para visualizar el dashboard y los reportes.

Instalación básica
------------------

1. **Clona o copia este proyecto** en el directorio público de tu servidor web (por ejemplo, `c:\AppServ\www\AdministrationPanel`).
2. **Configura la base de datos:**
   - Crea la BD MySQL e importa `sistemadeadministracion.sql`.
   - Copia `conn/config.example.php` → `conn/config.php` y completa usuario/contraseña.
   - Si usas código legacy con mysqli: copia `conn/connection.example.php` → `conn/connection.php`.
3. **Instala dependencias MQTT** (listener + acreditación MP + config grúa + OTA): en `src/devices/` ejecutá `composer install` o `composer require php-mqtt/client`. Variables opcionales: `MQTT_BROKER`, `MQTT_PORT`, `MQTT_TOPIC_PREFIX`, `MQTT_CREDIT_SUBTOPIC`, `MQTT_CONFIG_SUBTOPIC`, `MQTT_OTA_SUBTOPIC` (default `ota`), más las de manifiesto OTA en el apartado anterior.
4. **Configura tus ESP32** para que:
   - Envíen JSON al endpoint HTTP que expone el servidor (`api_receptor.php`), o
   - Publiquen en el broker MQTT (según la configuración de `mqtt_listener.php`), usando el formato de mensaje esperado.
5. Accede desde el navegador a `index.php` o `dashboard.php` para comenzar a usar el sistema.

Uso del sistema
---------------

- Inicia sesión como administrador.
- Desde el `dashboard`:
  - Visualiza el estado online/offline de todas las máquinas (según último heartbeat).
  - Consulta los valores actuales de contadores (pesos, coin, premios, banco, fichas, tickets, etc.).
  - Configura cada máquina (nombre visible, local, grupo, tipo y descripción); para tipo **Máquina**, si el dispositivo está en la BD como **grúa** (`tipo_maquina = 2`), podés enviar **pago, fuerza, tiempos** por MQTT.
  - **OTA:** desde *Administración → Actualizar firmware (OTA)*, elegí ramas según el manifiesto en GitHub y publicá por MQTT a las máquinas seleccionadas.  
  - Lanza reportes detallados por máquina (links "Ver Reporte").
- Desde cada página de reporte:
  - Consulta el historial de movimientos crudos.
  - Obtén cierres diarios/semanales/mensuales.
  - Visualiza gráficas comparativas (coin, premios, pesos, etc., según tipo de máquina).
  - Ejecuta borrados controlados de registros antiguos (por rango de fechas o global).

- Sistema de acceso:
  - El login de administradores está en `login.php`.
  - El alta de nuevos administradores se hace desde `register.php` utilizando una **clave única de activación** (`invite_keys.code`) que tú generas y entregas al cliente cuando le vendes el sistema.

Notas
-----

- El sistema está pensado para uso interno de administración: no expone registro público de usuarios.
- El diseño del dashboard y reportes es totalmente responsive y se puede personalizar desde `css/style.css`.
- Es posible extender la lógica de `api_receptor.php` y de las tablas de telemetría para soportar nuevos tipos de máquinas o sensores conectados al ESP32 sin modificar el frontend principal.

¿Dudas, sugerencias o quieres contribuir?
----------------------------------------

Puedes adaptar este proyecto a tu propia infraestructura de ESP32 y, si trabajas con control de versiones (Git), enviar cambios o mejoras según tu flujo de trabajo habitual.

