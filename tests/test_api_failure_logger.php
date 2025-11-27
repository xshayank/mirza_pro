<?php
/**
 * Unit tests for ApiFailureLogger
 * 
 * Tests the centralized API failure logging functionality including:
 * - Log entry building
 * - Sensitive data sanitization
 * - Message formatting
 * 
 * Run with: php tests/test_api_failure_logger.php
 */

// Simple test framework (reusing pattern from test_falco.php)
class TestResult {
    public $passed = 0;
    public $failed = 0;
    public $errors = [];
    
    public function pass($testName) {
        $this->passed++;
        echo "✓ {$testName}\n";
    }
    
    public function fail($testName, $message) {
        $this->failed++;
        $this->errors[] = "{$testName}: {$message}";
        echo "✗ {$testName}: {$message}\n";
    }
    
    public function summary() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Tests: " . ($this->passed + $this->failed) . " | ";
        echo "Passed: {$this->passed} | Failed: {$this->failed}\n";
        if (count($this->errors) > 0) {
            echo "\nErrors:\n";
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
        }
        return $this->failed === 0;
    }
}

// Include the logger class
require_once __DIR__ . '/../ApiFailureLogger.php';

// Run tests
$result = new TestResult();

echo "\n" . str_repeat("=", 50) . "\n";
echo "ApiFailureLogger Tests\n";
echo str_repeat("=", 50) . "\n\n";

// Test 1: Basic sanitization of password field
echo "Testing password sanitization...\n";
try {
    $data = [
        'username' => 'testuser',
        'password' => 'secret123',
        'email' => 'test@example.com'
    ];
    
    $sanitized = ApiFailureLogger::sanitizeData($data);
    
    if ($sanitized['username'] === 'testuser' &&
        $sanitized['password'] === '[REDACTED]' &&
        $sanitized['email'] === 'test@example.com') {
        $result->pass("Password field is sanitized");
    } else {
        $result->fail("Password sanitization", "Password not properly redacted");
    }
} catch (Exception $e) {
    $result->fail("Password sanitization", $e->getMessage());
}

// Test 2: Sanitization of token field
echo "Testing token sanitization...\n";
try {
    $data = [
        'username' => 'admin',
        'access_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
        'status' => 'active'
    ];
    
    $sanitized = ApiFailureLogger::sanitizeData($data);
    
    if ($sanitized['access_token'] === '[REDACTED]' &&
        $sanitized['username'] === 'admin' &&
        $sanitized['status'] === 'active') {
        $result->pass("Token field is sanitized");
    } else {
        $result->fail("Token sanitization", "Token not properly redacted");
    }
} catch (Exception $e) {
    $result->fail("Token sanitization", $e->getMessage());
}

// Test 3: Sanitization of api_key field
echo "Testing api_key sanitization...\n";
try {
    $data = [
        'api_key' => 'sk-12345abcdef',
        'data' => ['nested' => 'value']
    ];
    
    $sanitized = ApiFailureLogger::sanitizeData($data);
    
    if ($sanitized['api_key'] === '[REDACTED]') {
        $result->pass("API key field is sanitized");
    } else {
        $result->fail("API key sanitization", "API key not properly redacted");
    }
} catch (Exception $e) {
    $result->fail("API key sanitization", $e->getMessage());
}

// Test 4: Nested array sanitization
echo "Testing nested array sanitization...\n";
try {
    $data = [
        'user' => [
            'name' => 'John',
            'login_info' => [
                'password' => 'secret',
                'token' => 'abc123'
            ]
        ]
    ];
    
    $sanitized = ApiFailureLogger::sanitizeData($data);
    
    if ($sanitized['user']['name'] === 'John' &&
        $sanitized['user']['login_info']['password'] === '[REDACTED]' &&
        $sanitized['user']['login_info']['token'] === '[REDACTED]') {
        $result->pass("Nested arrays are properly sanitized");
    } else {
        $result->fail("Nested sanitization", "Nested sensitive fields not properly redacted");
    }
} catch (Exception $e) {
    $result->fail("Nested sanitization", $e->getMessage());
}

// Test 5: JSON string sanitization
echo "Testing JSON string sanitization...\n";
try {
    $jsonData = json_encode([
        'username' => 'user1',
        'password' => 'mysecret',
        'api_key' => 'key123'
    ]);
    
    $sanitized = ApiFailureLogger::sanitizeData($jsonData);
    $decoded = json_decode($sanitized, true);
    
    if ($decoded['username'] === 'user1' &&
        $decoded['password'] === '[REDACTED]' &&
        $decoded['api_key'] === '[REDACTED]') {
        $result->pass("JSON strings are properly sanitized");
    } else {
        $result->fail("JSON sanitization", "JSON data not properly sanitized");
    }
} catch (Exception $e) {
    $result->fail("JSON sanitization", $e->getMessage());
}

// Test 6: URL-encoded data sanitization
echo "Testing URL-encoded data sanitization...\n";
try {
    $urlData = "username=testuser&password=secret123&action=login";
    
    $sanitized = ApiFailureLogger::sanitizeData($urlData);
    parse_str($sanitized, $parsed);
    
    if ($parsed['username'] === 'testuser' &&
        $parsed['password'] === '[REDACTED]') {
        $result->pass("URL-encoded data is properly sanitized");
    } else {
        $result->fail("URL-encoded sanitization", "URL data not properly sanitized");
    }
} catch (Exception $e) {
    $result->fail("URL-encoded sanitization", $e->getMessage());
}

// Test 7: Case-insensitive sanitization
echo "Testing case-insensitive field matching...\n";
try {
    $data = [
        'PASSWORD' => 'secret1',
        'Token' => 'secret2',
        'API_KEY' => 'secret3',
        'Secret_Code' => 'secret4'
    ];
    
    $sanitized = ApiFailureLogger::sanitizeData($data);
    
    if ($sanitized['PASSWORD'] === '[REDACTED]' &&
        $sanitized['Token'] === '[REDACTED]' &&
        $sanitized['API_KEY'] === '[REDACTED]' &&
        $sanitized['Secret_Code'] === '[REDACTED]') {
        $result->pass("Case-insensitive field matching works");
    } else {
        $result->fail("Case-insensitive matching", "Not all fields were redacted");
    }
} catch (Exception $e) {
    $result->fail("Case-insensitive matching", $e->getMessage());
}

// Test 8: Non-sensitive data preservation
echo "Testing non-sensitive data is preserved...\n";
try {
    $data = [
        'id' => 12345,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'active' => true,
        'balance' => 100.50
    ];
    
    $sanitized = ApiFailureLogger::sanitizeData($data);
    
    if ($sanitized['id'] === 12345 &&
        $sanitized['name'] === 'Test User' &&
        $sanitized['email'] === 'test@example.com' &&
        $sanitized['active'] === true &&
        $sanitized['balance'] === 100.50) {
        $result->pass("Non-sensitive data is preserved");
    } else {
        $result->fail("Data preservation", "Some non-sensitive data was modified");
    }
} catch (Exception $e) {
    $result->fail("Data preservation", $e->getMessage());
}

// Test 9: Generic error message generation
echo "Testing generic error message generation...\n";
try {
    $message = ApiFailureLogger::getGenericErrorMessage();
    $messageWithPanel = ApiFailureLogger::getGenericErrorMessage('marzban');
    
    if (strpos($message, 'external panel') !== false &&
        strpos($message, 'administrators') !== false &&
        strpos($messageWithPanel, 'marzban') !== false) {
        $result->pass("Generic error messages are generated correctly");
    } else {
        $result->fail("Generic messages", "Message format is incorrect");
    }
} catch (Exception $e) {
    $result->fail("Generic messages", $e->getMessage());
}

// Test 10: Persian error message generation
echo "Testing Persian error message generation...\n";
try {
    $message = ApiFailureLogger::getGenericErrorMessagePersian();
    $messageWithPanel = ApiFailureLogger::getGenericErrorMessagePersian('مرزبان');
    
    if (strpos($message, 'پنل') !== false &&
        strpos($messageWithPanel, 'مرزبان') !== false) {
        $result->pass("Persian error messages are generated correctly");
    } else {
        $result->fail("Persian messages", "Message format is incorrect");
    }
} catch (Exception $e) {
    $result->fail("Persian messages", $e->getMessage());
}

// Test 11: Log entry structure
echo "Testing log entry structure...\n";
try {
    // Capture error_log output by temporarily redirecting
    $originalErrorLog = ini_get('error_log');
    $tempLogFile = sys_get_temp_dir() . '/test_api_logger_' . uniqid() . '.log';
    ini_set('error_log', $tempLogFile);
    
    $params = [
        'method' => 'POST',
        'url' => 'https://panel.example.com/api/users',
        'request_body' => ['username' => 'test', 'password' => 'secret'],
        'http_status' => 500,
        'response_body' => '{"error": "Internal Server Error"}',
        'user_id' => 12345,
        'panel_name' => 'test_panel',
        'context' => 'Creating user'
    ];
    
    // Disable admin alerts for this test
    $logEntry = ApiFailureLogger::log($params, ['enable_admin_alerts' => false]);
    
    // Restore error log
    ini_set('error_log', $originalErrorLog);
    
    // Clean up temp file
    if (file_exists($tempLogFile)) {
        unlink($tempLogFile);
    }
    
    if ($logEntry['type'] === 'API_FAILURE' &&
        $logEntry['method'] === 'POST' &&
        $logEntry['http_status'] === 500 &&
        $logEntry['user_id'] === 12345 &&
        $logEntry['panel_name'] === 'test_panel' &&
        isset($logEntry['timestamp'])) {
        $result->pass("Log entry structure is correct");
    } else {
        $result->fail("Log entry structure", "Missing or incorrect fields in log entry");
    }
} catch (Exception $e) {
    $result->fail("Log entry structure", $e->getMessage());
}

// Test 12: Exception information in log entry
echo "Testing exception information in log entry...\n";
try {
    $originalErrorLog = ini_get('error_log');
    $tempLogFile = sys_get_temp_dir() . '/test_api_logger_exc_' . uniqid() . '.log';
    ini_set('error_log', $tempLogFile);
    
    $exception = new Exception("Test error message", 123);
    
    $params = [
        'method' => 'GET',
        'url' => 'https://panel.example.com/api/test',
        'exception' => $exception,
    ];
    
    $logEntry = ApiFailureLogger::log($params, ['enable_admin_alerts' => false]);
    
    // Restore error log
    ini_set('error_log', $originalErrorLog);
    
    // Clean up temp file
    if (file_exists($tempLogFile)) {
        unlink($tempLogFile);
    }
    
    if (isset($logEntry['exception']) &&
        $logEntry['exception']['class'] === 'Exception' &&
        $logEntry['exception']['message'] === 'Test error message' &&
        $logEntry['exception']['code'] === 123 &&
        isset($logEntry['exception']['file']) &&
        isset($logEntry['exception']['line']) &&
        isset($logEntry['exception']['trace'])) {
        $result->pass("Exception information is correctly captured");
    } else {
        $result->fail("Exception capture", "Exception information is incomplete");
    }
} catch (Exception $e) {
    $result->fail("Exception capture", $e->getMessage());
}

// Test 13: Authorization header sanitization
echo "Testing authorization header sanitization...\n";
try {
    $headers = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
        'Accept' => 'application/json'
    ];
    
    $sanitized = ApiFailureLogger::sanitizeData($headers);
    
    if ($sanitized['Content-Type'] === 'application/json' &&
        $sanitized['Authorization'] === '[REDACTED]' &&
        $sanitized['Accept'] === 'application/json') {
        $result->pass("Authorization headers are sanitized");
    } else {
        $result->fail("Header sanitization", "Authorization not properly redacted");
    }
} catch (Exception $e) {
    $result->fail("Header sanitization", $e->getMessage());
}

// Test 14: Empty and null data handling
echo "Testing empty and null data handling...\n";
try {
    $nullResult = ApiFailureLogger::sanitizeData(null);
    $emptyArrayResult = ApiFailureLogger::sanitizeData([]);
    $emptyStringResult = ApiFailureLogger::sanitizeData('');
    
    if ($nullResult === null &&
        $emptyArrayResult === [] &&
        $emptyStringResult === '') {
        $result->pass("Empty and null data handled correctly");
    } else {
        $result->fail("Empty data handling", "Unexpected transformation of empty data");
    }
} catch (Exception $e) {
    $result->fail("Empty data handling", $e->getMessage());
}

// Test 15: Response body truncation
echo "Testing response body truncation...\n";
try {
    $longResponse = str_repeat('A', 3000);
    
    $params = [
        'method' => 'GET',
        'url' => 'https://panel.example.com/api/test',
        'response_body' => $longResponse,
    ];
    
    $originalErrorLog = ini_get('error_log');
    $tempLogFile = sys_get_temp_dir() . '/test_api_logger_trunc_' . uniqid() . '.log';
    ini_set('error_log', $tempLogFile);
    
    $logEntry = ApiFailureLogger::log($params, ['enable_admin_alerts' => false]);
    
    ini_set('error_log', $originalErrorLog);
    if (file_exists($tempLogFile)) {
        unlink($tempLogFile);
    }
    
    if (strlen($logEntry['response_body']) < strlen($longResponse) &&
        strpos($logEntry['response_body'], '[TRUNCATED]') !== false) {
        $result->pass("Long response bodies are truncated");
    } else {
        $result->fail("Response truncation", "Response was not truncated as expected");
    }
} catch (Exception $e) {
    $result->fail("Response truncation", $e->getMessage());
}

// Print summary
$success = $result->summary();

// Exit with appropriate code
exit($success ? 0 : 1);
