<?php
/**
 * Publicación MQTT genérica hacia dispositivos: maquinas/{device_id}/{subtopic}
 * Reutiliza el mismo vendor/autoload que mqtt_listener y mqtt_credit.
 */
declare(strict_types=1);

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MQTTClient;

class MqttDevicePublisher
{
    public static function autoloadPath(): ?string
    {
        $paths = [
            __DIR__ . '/vendor/autoload.php',
            dirname(__DIR__, 2) . '/../esp32_project/vendor/autoload.php',
        ];
        foreach ($paths as $p) {
            if (is_readable($p)) {
                return $p;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $payload Se serializa con JSON_UNESCAPED_UNICODE
     * @return array{success:bool, message?:string, error?:string, topic?:string}
     */
    public static function publishJson(string $deviceId, string $subtopic, array $payload): array
    {
        $autoload = self::autoloadPath();
        if ($autoload === null) {
            return [
                'success' => false,
                'error' => 'Falta composer en src/devices: composer require php-mqtt/client',
            ];
        }
        require_once $autoload;

        $broker = getenv('MQTT_BROKER') ?: 'broker.emqx.io';
        $port = (int) (getenv('MQTT_PORT') ?: 1883);
        $prefix = trim((string) (getenv('MQTT_TOPIC_PREFIX') ?: 'maquinas'), '/');
        $sub = trim($subtopic, '/');
        if ($sub === '') {
            return ['success' => false, 'error' => 'subtopic vacío'];
        }

        $deviceId = trim($deviceId);
        if ($deviceId === '') {
            return ['success' => false, 'error' => 'device_id vacío'];
        }

        $topic = $prefix . '/' . $deviceId . '/' . $sub;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return ['success' => false, 'error' => 'JSON inválido'];
        }

        $clientId = 'php_mqtt_pub_' . bin2hex(random_bytes(4));
        $mqtt = new MQTTClient($broker, $port, $clientId);

        try {
            $settings = new ConnectionSettings(0, false, false, 10, 60, 10);
            $mqtt->connect(null, null, $settings, true);
            $mqtt->publish($topic, $json, MQTTClient::QOS_AT_MOST_ONCE, false);
            $mqtt->close();
        } catch (Throwable $e) {
            error_log('MqttDevicePublisher: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'MQTT: ' . $e->getMessage(),
                'topic' => $topic,
            ];
        }

        return [
            'success' => true,
            'message' => 'Publicado por MQTT',
            'topic' => $topic,
        ];
    }
}
