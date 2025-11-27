<?php

// Include the API Failure Logger if available
if (file_exists(__DIR__ . '/ApiFailureLogger.php')) {
    require_once __DIR__ . '/ApiFailureLogger.php';
}

class CurlRequest {
    private $url;
    private $headers = [];
    private $timeout = null;
    private $authToken = null;
    private $api_key = null;
    private $cookie = null;
    
    // Context information for logging
    private $panelName = null;
    private $userId = null;
    private $username = null;
    private $context = null;
    
    public function __construct($url) {
        $this->url = $url;
    }

    public function setTimeout($seconds) {
        $this->timeout = $seconds;
    }

    public function setHeaders(array $headers) {
        $this->headers = array_merge($this->headers, $headers);
    }

    public function setBearerToken($token) {
        $this->authToken = $token;
    }
    
    public function api_key($token) {
        $this->api_key = $token;
    }

    public function setCookie($cookieStr) {
        $this->cookie = $cookieStr;
    }
    
    /**
     * Set context information for logging purposes
     * 
     * @param array $context Context array with optional keys:
     *   - panel_name: Name of the panel being accessed
     *   - user_id: ID of the user making the request
     *   - username: Username of the user
     *   - context: Additional context description
     */
    public function setLoggingContext(array $context) {
        $this->panelName = $context['panel_name'] ?? null;
        $this->userId = $context['user_id'] ?? null;
        $this->username = $context['username'] ?? null;
        $this->context = $context['context'] ?? null;
    }

    private function prepareHeaders() {
        $headers = $this->headers;

        if ($this->authToken) {
            $headers[] = "Authorization: Bearer {$this->authToken}";
        }
        if ($this->api_key) {
            $headers[] = $this->authToken;
        }

        return $headers;
    }

    private function execute($method, $data = null) {
        $this->timeout = !$this->timeout  ?  8000 : $this->timeout;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $finalHeaders = $this->prepareHeaders();
        if (!empty($finalHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);
        }
        if ($this->cookie) {
         curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);   
        }
        if ($data) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            
            // Log curl errors
            $this->logApiFailure($method, $data, null, null, new \Exception("cURL Error: " . $error));
            
            return ['error' => $error];
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = [
            'status' => $httpCode,
            'body' => $response
        ];
        
        // Log non-2xx responses as failures
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logApiFailure($method, $data, $httpCode, $response, null);
        }

        return $result;
    }
    
    /**
     * Log an API failure using ApiFailureLogger if available
     */
    private function logApiFailure($method, $requestData, $httpStatus, $responseBody, $exception) {
        // Only log if ApiFailureLogger class exists
        if (!class_exists('ApiFailureLogger')) {
            // Fallback to basic error_log
            $logMsg = "[API_FAILURE] {$method} {$this->url} - Status: " . ($httpStatus ?? 'N/A');
            if ($exception) {
                $logMsg .= " - Error: " . $exception->getMessage();
            }
            error_log($logMsg);
            return;
        }
        
        ApiFailureLogger::log([
            'method' => $method,
            'url' => $this->url,
            'request_body' => $requestData,
            'request_headers' => $this->headers,
            'http_status' => $httpStatus,
            'response_body' => $responseBody,
            'exception' => $exception,
            'user_id' => $this->userId,
            'username' => $this->username,
            'panel_name' => $this->panelName,
            'context' => $this->context,
        ]);
    }

    public function get() {
        return $this->execute("GET");
    }

    public function post($data) {
        return $this->execute("POST", $data);
    }

    public function put($data) {
        return $this->execute("PUT", $data);
    }

    public function delete($data = null) {
        return $this->execute("DELETE", $data);
    }
    public function PATCH($data = null){
        return $this->execute('PATCH',$data);
    }
}