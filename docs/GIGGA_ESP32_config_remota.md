# Gigga ESP32 — recibir configuración remota por MQTT

El panel publica en `maquinas/<ID_DISP>/config` un JSON con `cmd`, `pago`, `t_agarre`, `t_fuerte`, `fuerza`. **Sin el código de abajo, el ESP ignora esos mensajes.**

**Firmware:** repo [ReporteGrua](https://github.com/Rothezee/ReporteGrua) — `GruaMQTT.ino` (config MQTT + OTA según evolución del repo). Handoff para CI/manifiesto: [`REPORTEGRUA_REPO_HANDOFF.md`](REPORTEGRUA_REPO_HANDOFF.md).

Lo siguiente es la referencia por si querés pegar solo fragmentos en tu `.ino` actual.

## 1. Constante del tópico (junto a TOPIC_DATOS)

```cpp
const char* TOPIC_CONFIG = "maquinas/Grua_123/config";  // mismo ID que ID_DISP
```

Si cambiás `ID_DISP`, actualizá también esta cadena (o armala con `snprintf`).

## 2. Prototipo y callback (antes de `setup`)

```cpp
void aplicarConfigRemota(JsonDocument& doc);

void onMqttMessage(char* topic, byte* payload, unsigned int length) {
  if (strcmp(topic, TOPIC_CONFIG) != 0) return;
  char buf[384];
  if (length >= sizeof(buf)) return;
  memcpy(buf, payload, length);
  buf[length] = '\0';

  JsonDocument doc;
  DeserializationError err = deserializeJson(doc, buf);
  if (err) return;

  const char* cmd = doc["cmd"];
  if (!cmd || strcmp(cmd, "set_grua_params") != 0) return;

  aplicarConfigRemota(doc);
}

void aplicarConfigRemota(JsonDocument& doc) {
  int pg = (int)doc["pago"];
  int ta = (int)doc["t_agarre"];
  int tf = (int)doc["t_fuerte"];
  int fz = (int)doc["fuerza"];

  if (pg < 1 || pg > 99) return;
  if (ta < 500 || ta > 5000) return;
  if (tf < 0 || tf > 5000) return;
  if (fz < 5 || fz > 101) return;

  pago = (int16_t)pg;
  tAgarre = (int16_t)ta;
  tFuerte = (int16_t)tf;
  fuerza = (int16_t)fz;

  guardarInt(DIR_PAGO, pago);
  guardarInt(DIR_T_AGARRE, tAgarre);
  guardarInt(DIR_T_FUERTE, tFuerte);
  guardarInt(DIR_FUERZA, fuerza);

  Serial.println("Config remota aplicada");
  mostrarDisplay();
}
```

## 3. En `setup()`, después de `clienteMQTT.setServer(...)`

```cpp
  clienteMQTT.setCallback(onMqttMessage);
```

## 4. Tras cada `clienteMQTT.connect(...)` exitoso

PubSubClient **pierde la suscripción** si se desconecta. Volvé a suscribir al reconectar, por ejemplo dentro de `reconectarMQTT()` justo después del `if (clienteMQTT.connect(...)) {`:

```cpp
    clienteMQTT.subscribe(TOPIC_CONFIG);
```

Y en el **primer** arranque, si ya estás conectado al broker al final de `setup`, suscribí también ahí (o forzá una reconexión que ejecute el bloque de arriba).

## 5. `loop`

Ya llamás `clienteMQTT.loop()` en varios sitios; eso entrega los mensajes entrantes al callback. No hace falta cambiar la lógica si `loop()` se ejecuta con frecuencia.

---

**Resumen:** sí, hay que modificar el sketch: `setCallback`, `subscribe(TOPIC_CONFIG)` al conectar, y el callback que valida JSON y llama a `guardarInt` + variables globales.
