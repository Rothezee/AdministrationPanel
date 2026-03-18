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

- `Backend/mercadopago/`  
  Integración con Mercado Pago (pagos, webhooks, comunicación con ESP32 cuando aplica).

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
3. **Instala dependencias MQTT** (si usás el listener): `composer require php-mqtt/client` en `src/devices/`.
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
  - Configura cada máquina (nombre visible, local, grupo, tipo y descripción).
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

