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

require_once dirname(__DIR__) . '/devices/MqttDevicePublisher.php';

class MqttCreditPublisher
{
    /**
     * @return array{success:bool, message?:string, error?:string, topic?:string}
     */
    public static function publishCredit(string $machineId, float $amount, string $paymentId): array
    {
        $sub = trim((string) (getenv('MQTT_CREDIT_SUBTOPIC') ?: 'credit'), '/');
        return MqttDevicePublisher::publishJson($machineId, $sub, [
            'cmd' => 'add_credit',
            'amount' => $amount,
            'machine_id' => $machineId,
            'payment_id' => $paymentId,
            'source' => 'mercadopago',
            'ts' => time(),
        ]);
    }
}
