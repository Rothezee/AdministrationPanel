<?php
class ESP32Communication {
    private $db;
    private $timeout;

    public function __construct($db, $timeout = 10) {
        $this->db = $db;
        $this->timeout = $timeout;
    }

    /**
     * Envía crédito a una máquina ESP32
     */
    public function sendCreditToMachine($machineId, $amount) {
        $machineIP = $this->getMachineIP($machineId);
        
        if (!$machineIP) {
            return [
                'success' => false, 
                'error' => "Máquina $machineId no encontrada o sin IP registrada"
            ];
        }

        // Validar IP
        if (!filter_var($machineIP, FILTER_VALIDATE_IP)) {
            return [
                'success' => false,
                'error' => "IP inválida para máquina $machineId: $machineIP"
            ];
        }

        $url = "http://$machineIP/add-credit";
        $data = [
            'amount' => floatval($amount),
            'timestamp' => time(),
            'machine_id' => $machineId,
            'source' => 'mercadopago'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: MaquinasBonus-PaymentSystem/1.0'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log de la comunicación
        $logData = [
            'machine_id' => $machineId,
            'ip' => $machineIP,
            'amount' => $amount,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log("ESP32 Communication: " . json_encode($logData));

        if ($error) {
            $this->logCreditTransaction($machineId, $amount, 'failed', "cURL Error: $error");
            return [
                'success' => false,
                'error' => "Error de conexión con ESP32: $error"
            ];
        }

        if ($httpCode === 200) {
            // Intentar decodificar la respuesta
            $responseData = json_decode($response, true);
            
            if ($responseData && isset($responseData['success']) && $responseData['success']) {
                $this->logCreditTransaction($machineId, $amount, 'success', 'Crédito enviado correctamente');
                return [
                    'success' => true,
                    'message' => 'Crédito enviado exitosamente',
                    'response' => $responseData
                ];
            } else {
                $this->logCreditTransaction($machineId, $amount, 'failed', "Respuesta ESP32: $response");
                return [
                    'success' => false,
                    'error' => 'ESP32 reportó error: ' . ($responseData['error'] ?? 'Respuesta inválida')
                ];
            }
        } else {
            $this->logCreditTransaction($machineId, $amount, 'failed', "HTTP $httpCode: $response");
            return [
                'success' => false,
                'error' => "Error HTTP $httpCode comunicándose con ESP32"
            ];
        }
    }

    /**
     * Obtiene la IP de una máquina desde la base de datos
     */
    private function getMachineIP($machineId) {
        try {
            $stmt = $this->db->prepare("SELECT ip_address FROM machine_ips WHERE device_id = ?");
            $stmt->execute([$machineId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['ip_address'] : null;
        } catch (Exception $e) {
            error_log("Error getting machine IP: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Registra una transacción de crédito en la base de datos
     */
    private function logCreditTransaction($machineId, $amount, $status, $details = '') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mercadopago_transactions (machine_id, amount, status, details, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$machineId, $amount, $status, $details]);
        } catch (Exception $e) {
            error_log("Error logging credit transaction: " . $e->getMessage());
        }
    }

    /**
     * Verifica el estado de conexión de una máquina
     */
    public function checkMachineStatus($machineId) {
        $machineIP = $this->getMachineIP($machineId);
        
        if (!$machineIP) {
            return ['online' => false, 'error' => 'IP no encontrada'];
        }

        $url = "http://$machineIP/status";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            return ['online' => false, 'error' => $error ?: "HTTP $httpCode"];
        }

        return ['online' => true, 'response' => $response];
    }

    /**
     * Obtiene el historial de transacciones de crédito
     */
    public function getCreditHistory($machineId, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM mercadopago_transactions 
                WHERE machine_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$machineId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting credit history: " . $e->getMessage());
            return [];
        }
    }
}