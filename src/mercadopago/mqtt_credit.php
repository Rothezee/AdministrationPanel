<?php
/**
 * Publica crédito post-pago Mercado Pago por MQTT (misma convención de tópicos que mqtt_listener).
 *
 * Tópico: {prefix}/{device_id}/credit
 * Payload JSON: cmd, amount, machine_id, payment_id, source, ts
 *
 * Broker/puerto: env MQTT_BROKER, MQTT_PORT, MQTT_TOPIC_PREFIX, MQTT_CREDIT_SUBTOPIC
 * (por defecto alineado con src/devices/mqtt_listener.php).
 */
declare(strict_types=1);

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MQTTClient;

class MqttCreditPublisher
{
    private static function mqttAutoload(): ?string
    {
        $paths = [
            dirname(__DIR__) . '/devices/vendor/autoload.php',
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
     * @return array{success:bool, message?:string, error?:string, topic?:string}
     */
    public static function publishCredit(string $machineId, float $amount, string $paymentId): array
    {
        $autoload = self::mqttAutoload();
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
        $sub = trim((string) (getenv('MQTT_CREDIT_SUBTOPIC') ?: 'credit'), '/');

        $topic = $prefix . '/' . $machineId . '/' . $sub;
        $payload = json_encode([
            'cmd' => 'add_credit',
            'amount' => $amount,
            'machine_id' => $machineId,
            'payment_id' => $paymentId,
            'source' => 'mercadopago',
            'ts' => time(),
        ], JSON_UNESCAPED_UNICODE);

        $clientId = 'php_mp_credit_' . bin2hex(random_bytes(4));
        $mqtt = new MQTTClient($broker, $port, $clientId);

        try {
            $settings = new ConnectionSettings(0, false, false, 10, 60, 10);
            $mqtt->connect(null, null, $settings, true);
            $mqtt->publish($topic, $payload, MQTTClient::QOS_AT_MOST_ONCE, false);
            $mqtt->disconnect();
        } catch (Throwable $e) {
            error_log('MqttCreditPublisher: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'MQTT: ' . $e->getMessage(),
                'topic' => $topic,
            ];
        }

        return [
            'success' => true,
            'message' => 'Crédito publicado por MQTT',
            'topic' => $topic,
        ];
    }
}
