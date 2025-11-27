<?php
/**
 * ApiFailureLogger - Centralized logging and alerting for external API failures
 * 
 * This class provides a reusable way to log API failures to the server error log
 * and optionally send notifications to admins via Telegram.
 * 
 * Usage:
 *   ApiFailureLogger::log([
 *       'method' => 'POST',
 *       'url' => 'https://panel.example.com/api/users',
 *       'request_body' => ['username' => 'test'],
 *       'http_status' => 500,
 *       'response_body' => '{"error": "Internal Server Error"}',
 *       'exception' => $e,  // Optional Throwable
 *   ]);
 */

class ApiFailureLogger
{
    /**
     * Sensitive fields to sanitize in logs
     */
    private static $sensitiveFields = [
        'password',
        'token',
        'api_key',
        'apikey',
        'secret',
        'authorization',
        'bearer',
        'access_token',
        'refresh_token',
        'secret_code',
        'secret_key',
        'private_key',
        'credentials',
        'auth',
        'passwd',
        'pass',
    ];

    /**
     * Default configuration values
     */
    private static $defaultConfig = [
        'enable_admin_alerts' => true,
        'alert_throttle_seconds' => 60,  // Minimum seconds between alerts for same endpoint
        'max_response_length' => 2000,   // Truncate response body if longer
        'admin_chat_id' => null,         // Will use $adminnumber from config.php
        'alert_channel_report' => null,  // Will use Channel_Report from database
    ];

    /**
     * Track last alert times to implement throttling
     */
    private static $lastAlertTimes = [];

    /**
     * Log an API failure and optionally send admin notification
     * 
     * @param array $params Parameters describing the API failure:
     *   - method (string): HTTP method (GET, POST, PUT, DELETE, etc.)
     *   - url (string): Full request URL or endpoint path
     *   - request_body (mixed): Request body/payload (array or string)
     *   - request_headers (array): Optional request headers
     *   - http_status (int|null): HTTP status code
     *   - response_body (string|null): Response body
     *   - exception (Throwable|null): Exception that occurred
     *   - user_id (int|null): Authenticated user ID
     *   - username (string|null): Authenticated username
     *   - panel_name (string|null): Name of the panel being accessed
     *   - context (string|null): Additional context about the operation
     * @param array $config Optional configuration overrides
     * @return array The sanitized log entry that was written
     */
    public static function log(array $params, array $config = []): array
    {
        // Merge with default config
        $config = array_merge(self::$defaultConfig, $config);
        
        // Build the log entry
        $logEntry = self::buildLogEntry($params);
        
        // Write to error log
        self::writeToErrorLog($logEntry);
        
        // Send admin notification if enabled and not throttled
        if ($config['enable_admin_alerts'] && self::shouldSendAlert($params['url'] ?? '', $config)) {
            self::sendAdminAlert($logEntry, $config);
        }
        
        return $logEntry;
    }

    /**
     * Build a structured log entry from parameters
     */
    private static function buildLogEntry(array $params): array
    {
        $timestamp = date('Y-m-d H:i:s');
        
        // Sanitize request body
        $requestBody = $params['request_body'] ?? null;
        if ($requestBody !== null) {
            $requestBody = self::sanitizeData($requestBody);
        }
        
        // Sanitize request headers
        $requestHeaders = $params['request_headers'] ?? null;
        if ($requestHeaders !== null) {
            $requestHeaders = self::sanitizeData($requestHeaders);
        }
        
        // Build exception info if present
        $exceptionInfo = null;
        if (isset($params['exception']) && $params['exception'] instanceof Throwable) {
            $e = $params['exception'];
            $exceptionInfo = [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => self::formatStackTrace($e->getTraceAsString()),
            ];
        }
        
        // Truncate response body if too long
        $responseBody = $params['response_body'] ?? null;
        if ($responseBody !== null && strlen($responseBody) > self::$defaultConfig['max_response_length']) {
            $responseBody = substr($responseBody, 0, self::$defaultConfig['max_response_length']) . '... [TRUNCATED]';
        }
        
        return [
            'type' => 'API_FAILURE',
            'timestamp' => $timestamp,
            'method' => $params['method'] ?? 'UNKNOWN',
            'url' => $params['url'] ?? 'UNKNOWN',
            'request_body' => $requestBody,
            'request_headers' => $requestHeaders,
            'http_status' => $params['http_status'] ?? null,
            'response_body' => $responseBody,
            'exception' => $exceptionInfo,
            'user_id' => $params['user_id'] ?? null,
            'username' => $params['username'] ?? null,
            'panel_name' => $params['panel_name'] ?? null,
            'context' => $params['context'] ?? null,
        ];
    }

    /**
     * Sanitize sensitive data from arrays or strings
     * 
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    public static function sanitizeData($data)
    {
        if (is_string($data)) {
            // Try to decode JSON
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return json_encode(self::sanitizeArray($decoded), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            // Sanitize URL-encoded data
            if (strpos($data, '=') !== false) {
                parse_str($data, $parsed);
                if (!empty($parsed)) {
                    return http_build_query(self::sanitizeArray($parsed));
                }
            }
            return $data;
        }
        
        if (is_array($data)) {
            return self::sanitizeArray($data);
        }
        
        return $data;
    }

    /**
     * Recursively sanitize an array, replacing sensitive values
     */
    private static function sanitizeArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);
            
            // Check if this key is sensitive
            $isSensitive = false;
            foreach (self::$sensitiveFields as $sensitiveField) {
                if (strpos($lowerKey, $sensitiveField) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Format stack trace for logging (limit length)
     */
    private static function formatStackTrace(string $trace): string
    {
        $maxLength = 1000;
        if (strlen($trace) > $maxLength) {
            return substr($trace, 0, $maxLength) . "\n... [TRUNCATED]";
        }
        return $trace;
    }

    /**
     * Write the log entry to PHP error log
     */
    private static function writeToErrorLog(array $logEntry): void
    {
        $logMessage = "[API_FAILURE] " . json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        error_log($logMessage);
    }

    /**
     * Check if we should send an alert (throttling logic)
     */
    private static function shouldSendAlert(string $endpoint, array $config): bool
    {
        $throttleKey = md5($endpoint);
        $now = time();
        $throttleSeconds = $config['alert_throttle_seconds'] ?? 60;
        
        if (isset(self::$lastAlertTimes[$throttleKey])) {
            $elapsed = $now - self::$lastAlertTimes[$throttleKey];
            if ($elapsed < $throttleSeconds) {
                return false;
            }
        }
        
        self::$lastAlertTimes[$throttleKey] = $now;
        return true;
    }

    /**
     * Send admin notification via Telegram
     */
    private static function sendAdminAlert(array $logEntry, array $config): void
    {
        // Try to get admin chat ID from various sources
        $adminChatId = self::getAdminChatId($config);
        if ($adminChatId === null) {
            return;
        }
        
        // Build alert message
        $message = self::buildAlertMessage($logEntry);
        
        // Send via Telegram
        self::sendTelegramAlert($adminChatId, $message, $config);
    }

    /**
     * Get admin chat ID for sending alerts
     */
    private static function getAdminChatId(array $config): ?string
    {
        // Check config override first
        if (!empty($config['admin_chat_id'])) {
            return $config['admin_chat_id'];
        }
        
        // Try to get from global $adminnumber
        global $adminnumber;
        if (!empty($adminnumber)) {
            return $adminnumber;
        }
        
        // Try to get Channel_Report from database
        try {
            if (function_exists('select')) {
                $setting = select("setting", "*", null, null, "select");
                if (!empty($setting['Channel_Report'])) {
                    return $setting['Channel_Report'];
                }
            }
        } catch (Throwable $e) {
            // Silently fail - we can't log about logging failures
        }
        
        return null;
    }

    /**
     * Build a human-readable alert message for Telegram
     */
    private static function buildAlertMessage(array $logEntry): string
    {
        $method = $logEntry['method'];
        $url = $logEntry['url'];
        
        // Parse URL to get just the endpoint path
        $parsedUrl = parse_url($url);
        $endpoint = $parsedUrl['path'] ?? $url;
        
        $message = "🚨 <b>[ALERT] External API Failure</b>\n\n";
        $message .= "📅 <b>Time:</b> {$logEntry['timestamp']}\n";
        $message .= "🔗 <b>Method:</b> {$method}\n";
        $message .= "📍 <b>Endpoint:</b> <code>{$endpoint}</code>\n";
        
        if (!empty($logEntry['panel_name'])) {
            $message .= "🖥 <b>Panel:</b> {$logEntry['panel_name']}\n";
        }
        
        if ($logEntry['http_status'] !== null) {
            $message .= "📊 <b>Status Code:</b> {$logEntry['http_status']}\n";
        }
        
        if ($logEntry['user_id'] !== null) {
            $message .= "👤 <b>User ID:</b> {$logEntry['user_id']}\n";
        }
        
        if (!empty($logEntry['context'])) {
            $message .= "📝 <b>Context:</b> {$logEntry['context']}\n";
        }
        
        // Add request body (sanitized, truncated)
        if ($logEntry['request_body'] !== null) {
            $requestStr = is_string($logEntry['request_body']) 
                ? $logEntry['request_body'] 
                : json_encode($logEntry['request_body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (strlen($requestStr) > 500) {
                $requestStr = substr($requestStr, 0, 500) . '...';
            }
            $message .= "\n📤 <b>Request:</b>\n<pre>" . htmlspecialchars($requestStr) . "</pre>\n";
        }
        
        // Add response body (truncated)
        if (!empty($logEntry['response_body'])) {
            $responseStr = $logEntry['response_body'];
            if (strlen($responseStr) > 500) {
                $responseStr = substr($responseStr, 0, 500) . '...';
            }
            $message .= "\n📥 <b>Response:</b>\n<pre>" . htmlspecialchars($responseStr) . "</pre>\n";
        }
        
        // Add exception info
        if (!empty($logEntry['exception'])) {
            $exc = $logEntry['exception'];
            $message .= "\n⚠️ <b>Exception:</b> {$exc['class']}\n";
            $message .= "💬 <b>Message:</b> " . htmlspecialchars($exc['message']) . "\n";
            $message .= "📁 <b>File:</b> {$exc['file']}:{$exc['line']}\n";
        }
        
        // Telegram has a 4096 character limit
        if (strlen($message) > 4000) {
            $message = substr($message, 0, 3950) . "\n\n[Message truncated - see error log for full details]";
        }
        
        return $message;
    }

    /**
     * Send message via Telegram
     */
    private static function sendTelegramAlert(string $chatId, string $message, array $config): void
    {
        try {
            // Check if telegram function exists
            if (function_exists('telegram')) {
                // Try to get error report topic ID from database
                $threadId = null;
                try {
                    if (function_exists('select')) {
                        $topicId = select("topicid", "idreport", "report", "errorreport", "select");
                        if (!empty($topicId['idreport'])) {
                            $threadId = $topicId['idreport'];
                        }
                    }
                } catch (Throwable $e) {
                    // Silently continue without thread ID
                }
                
                $params = [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ];
                
                if ($threadId !== null) {
                    $params['message_thread_id'] = $threadId;
                }
                
                telegram('sendmessage', $params);
            }
        } catch (Throwable $e) {
            // Log the failure to send alert, but don't recurse
            error_log("[API_FAILURE_LOGGER] Failed to send Telegram alert: " . $e->getMessage());
        }
    }

    /**
     * Get a safe, generic error message suitable for end users
     * 
     * @param string $panelType Optional panel type for slightly more specific messages
     * @return string Safe error message
     */
    public static function getGenericErrorMessage(string $panelType = ''): string
    {
        if (!empty($panelType)) {
            return "There was an error communicating with the external panel ({$panelType}). The administrators have been notified.";
        }
        return "There was an error communicating with the external panel. The administrators have been notified.";
    }

    /**
     * Get a safe, generic error message in Persian (for this project's primary language)
     * 
     * @param string $panelType Optional panel type for slightly more specific messages  
     * @return string Safe error message in Persian
     */
    public static function getGenericErrorMessagePersian(string $panelType = ''): string
    {
        if (!empty($panelType)) {
            return "خطایی در ارتباط با پنل خارجی ({$panelType}) رخ داد. مدیران مطلع شده‌اند.";
        }
        return "خطایی در ارتباط با پنل خارجی رخ داد. مدیران مطلع شده‌اند.";
    }
}
