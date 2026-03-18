<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once 'esp32_communication.php';

class MercadoPagoHandler {
    private $accessToken;
    private $db;
    private $esp32;

    public function __construct($db) {
        $this->accessToken = Config::MP_ACCESS_TOKEN;
        $this->db = $db;
        $this->esp32 = new ESP32Communication($db);
        
        // Configurar zona horaria
        date_default_timezone_set(Config::TIMEZONE);
    }

    /**
     * Crea una preferencia de pago en Mercado Pago
     */
    public function createPayment($amount, $machineId, $description = '') {
        // Validar monto
        if ($amount < Config::MIN_AMOUNT || $amount > Config::MAX_AMOUNT) {
            return [
                'success' => false,
                'error' => "El monto debe estar entre $" . Config::MIN_AMOUNT . " y $" . Config::MAX_AMOUNT
            ];
        }

        $url = 'https://api.mercadopago.com/checkout/preferences';

        $preference = [
            'items' => [
                [
                    'title' => $description ?: "Crédito para máquina $machineId",
                    'unit_price' => floatval($amount),
                    'quantity' => 1,
                    'currency_id' => Config::CURRENCY
                ]
            ],
            'external_reference' => $machineId,
            'notification_url' => Config::WEBHOOK_URL,
            'auto_return' => 'approved',
            'back_urls' => [
                'success' => Config::SUCCESS_URL . "?machine_id=$machineId&amount=$amount",
                'failure' => Config::FAILURE_URL . "?machine_id=$machineId",
                'pending' => Config::PENDING_URL . "?machine_id=$machineId"
            ],
            'payment_methods' => [
                'excluded_payment_types' => [],
                'installments' => 1
            ],
            'expires' => true,
            'expiration_date_from' => date('c'),
            'expiration_date_to' => date('c', strtotime('+1 hour'))
        ];

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preference));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            error_log("cURL Error: $error_msg");
            return [
                'success' => false,
                'error' => 'Error de conexión con MercadoPago'
            ];
        }

        curl_close($ch);

        if ($httpCode === 201) {
            $data = json_decode($response, true);

            if (!$data || !isset($data['id'])) {
                return [
                    'success' => false,
                    'error' => 'Respuesta inválida de MercadoPago'
                ];
            }

            // Guardar el intento en DB
            $this->savePaymentRequest($data['id'], $machineId, $amount);

            return [
                'success' => true,
                'preference_id' => $data['id'],
                'init_point' => Config::IS_PRODUCTION ? $data['init_point'] : $data['sandbox_init_point'],
                'qr_code' => $data['qr_code'] ?? null,
                'qr_code_base64' => $data['qr_code_base64'] ?? null,
                'external_reference' => $machineId,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
            ];
        } else {
            $errorData = json_decode($response, true);
            $errorMessage = isset($errorData['message']) ? $errorData['message'] : "HTTP $httpCode";
            
            error_log("MercadoPago API Error: $response");
            return [
                'success' => false,
                'error' => "Error creando preferencia: $errorMessage"
            ];
        }
    }

    /**
     * Guarda el pago pendiente en DB
     */
    private function savePaymentRequest($preferenceId, $machineId, $amount) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mercadopago_requests (preference_id, machine_id, amount, status, created_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$preferenceId, $machineId, $amount]);
            return true;
        } catch (Exception $e) {
            error_log("Error saving payment request: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Consulta un pago por su ID
     */
    public function getPaymentInfo($paymentId) {
        $url = "https://api.mercadopago.com/v1/payments/$paymentId";

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log("cURL Error getting payment info: " . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        error_log("Error getting payment info: HTTP $httpCode - $response");
        return null;
    }

    /**
     * Procesa el pago aprobado y envía crédito al ESP32
     */
    public function processApprovedPayment($paymentId, $machineId, $amount) {
        try {
            // Verificar si ya se procesó este pago
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM mercadopago_requests 
                WHERE payment_id = ? AND status = 'approved'
            ");
            $stmt->execute([$paymentId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing['count'] > 0) {
                return [
                    'success' => true,
                    'message' => 'Pago ya procesado anteriormente',
                    'already_processed' => true
                ];
            }

            // Actualizar estado en base de datos
            $stmt = $this->db->prepare("
                INSERT INTO mercadopago_requests (payment_id, machine_id, amount, status, created_at, updated_at)
                VALUES (?, ?, ?, 'approved', NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    status = 'approved', 
                    updated_at = NOW()
            ");
            $stmt->execute([$paymentId, $machineId, $amount]);

            // Enviar crédito al ESP32
            $result = $this->esp32->sendCreditToMachine($machineId, $amount);
            
            if ($result['success']) {
                // Registrar transacción exitosa
                $this->logTransaction($machineId, $amount, 'success', 'Crédito enviado correctamente');
                
                error_log("✅ Crédito enviado exitosamente a máquina $machineId por $$amount");
                return [
                    'success' => true,
                    'message' => 'Pago procesado y crédito enviado',
                    'machine_id' => $machineId,
                    'amount' => $amount,
                    'payment_id' => $paymentId
                ];
            } else {
                // Registrar transacción fallida
                $this->logTransaction($machineId, $amount, 'failed', $result['error']);
                
                error_log("❌ Error enviando crédito a máquina $machineId: " . $result['error']);
                return [
                    'success' => false,
                    'error' => 'Pago aprobado pero error enviando crédito: ' . $result['error']
                ];
            }
        } catch (Exception $e) {
            error_log("Error procesando pago aprobado: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error interno procesando el pago'
            ];
        }
    }

    /**
     * Registra una transacción en la base de datos
     */
    private function logTransaction($machineId, $amount, $status, $details = '') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mercadopago_transactions (machine_id, amount, status, details, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$machineId, $amount, $status, $details]);
        } catch (Exception $e) {
            error_log("Error logging transaction: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el historial de pagos de una máquina
     */
    public function getPaymentHistory($machineId, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM mercadopago_requests 
                WHERE machine_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$machineId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting payment history: " . $e->getMessage());
            return [];
        }
    }
}