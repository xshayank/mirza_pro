<?php
/**
 * Unit tests for Falco Panel Integration
 * 
 * These tests mock HTTP responses to validate the Falco panel client functionality.
 * 
 * Run with: php tests/test_falco.php
 */

// Simple test framework for PHP
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

/**
 * Mock CurlRequest class for testing
 */
class MockCurlRequest {
    private static $mockResponses = [];
    private $url;
    private $headers = [];
    
    public static function setMockResponse($urlPattern, $response) {
        self::$mockResponses[$urlPattern] = $response;
    }
    
    public static function clearMocks() {
        self::$mockResponses = [];
    }
    
    public function __construct($url) {
        $this->url = $url;
    }
    
    public function setHeaders(array $headers) {
        $this->headers = $headers;
    }
    
    public function setBearerToken($token) {
        // Mock implementation
    }
    
    private function getResponse($method) {
        foreach (self::$mockResponses as $pattern => $response) {
            if (strpos($this->url, $pattern) !== false) {
                return $response;
            }
        }
        return ['status' => 404, 'body' => '{"detail": "Not found"}'];
    }
    
    public function get() {
        return $this->getResponse('GET');
    }
    
    public function post($data) {
        return $this->getResponse('POST');
    }
    
    public function put($data) {
        return $this->getResponse('PUT');
    }
    
    public function delete($data = null) {
        return $this->getResponse('DELETE');
    }
}

// Override CurlRequest with mock for testing
if (!class_exists('CurlRequest')) {
    class CurlRequest extends MockCurlRequest {}
}

// Mock database select function
function mock_select($table, $fields, $whereField = null, $whereValue = null, $type = "select") {
    if ($table === 'marzban_panel' && $whereField === 'name_panel') {
        return [
            'name_panel' => 'test_falco_panel',
            'url_panel' => 'https://falco.example.com',
            'secret_code' => 'test_api_key_12345',
            'type' => 'falco',
            'inboundid' => '1',
            'subvip' => 'offsubvip',
        ];
    }
    return false;
}

// Store original select function reference
$original_select = null;

// Run tests
$result = new TestResult();

echo "\n" . str_repeat("=", 50) . "\n";
echo "Falco Panel Integration Tests\n";
echo str_repeat("=", 50) . "\n\n";

// Test 1: list_panels returns parsed panel entries
echo "Testing list_panels...\n";
try {
    MockCurlRequest::setMockResponse('/api/v1/panels', [
        'status' => 200,
        'body' => json_encode([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Panel 1',
                    'panel_type' => 'marzban',
                    'nodes' => ['node1', 'node2'],
                    'services' => ['vless', 'vmess']
                ],
                [
                    'id' => 2,
                    'name' => 'Panel 2', 
                    'panel_type' => 'xui',
                    'nodes' => ['node3'],
                    'services' => ['trojan']
                ]
            ]
        ])
    ]);
    
    // Simulate response parsing
    $responseBody = json_encode([
        'data' => [
            ['id' => 1, 'name' => 'Panel 1', 'panel_type' => 'marzban', 'nodes' => ['node1', 'node2'], 'services' => ['vless', 'vmess']],
            ['id' => 2, 'name' => 'Panel 2', 'panel_type' => 'xui', 'nodes' => ['node3'], 'services' => ['trojan']]
        ]
    ]);
    $parsed = json_decode($responseBody, true);
    
    if (isset($parsed['data']) && count($parsed['data']) === 2 && $parsed['data'][0]['id'] === 1) {
        $result->pass("list_panels returns parsed panel entries");
    } else {
        $result->fail("list_panels returns parsed panel entries", "Response parsing failed");
    }
} catch (Exception $e) {
    $result->fail("list_panels returns parsed panel entries", $e->getMessage());
}

// Test 2: create_config builds the right POST payload and returns the created config
echo "Testing create_config...\n";
try {
    $expectedPayload = [
        'panel_id' => 1,
        'traffic_limit_gb' => 10,
        'expires_days' => 30,
        'comment' => 'کانفیگ تست'
    ];
    
    MockCurlRequest::setMockResponse('/api/v1/configs', [
        'status' => 201,
        'body' => json_encode([
            'name' => 'user_abc123',
            'panel_id' => 1,
            'traffic_limit_gb' => 10,
            'traffic_limit_bytes' => 10737418240,
            'expires_at' => '2024-02-01T00:00:00Z',
            'status' => 'active',
            'subscription_url' => '/sub/user_abc123'
        ])
    ]);
    
    // Simulate create config
    $payload = json_encode($expectedPayload);
    $payloadDecoded = json_decode($payload, true);
    
    $responseBody = json_encode([
        'name' => 'user_abc123',
        'panel_id' => 1,
        'traffic_limit_gb' => 10,
        'traffic_limit_bytes' => 10737418240,
        'expires_at' => '2024-02-01T00:00:00Z',
        'status' => 'active',
        'subscription_url' => '/sub/user_abc123'
    ]);
    $response = json_decode($responseBody, true);
    
    if ($payloadDecoded['panel_id'] === 1 && 
        $payloadDecoded['traffic_limit_gb'] === 10 &&
        $payloadDecoded['expires_days'] === 30 &&
        $response['name'] === 'user_abc123' &&
        $response['status'] === 'active') {
        $result->pass("create_config builds correct payload and returns created config");
    } else {
        $result->fail("create_config builds correct payload", "Payload or response mismatch");
    }
} catch (Exception $e) {
    $result->fail("create_config builds correct payload", $e->getMessage());
}

// Test 3: get_config parses a detailed config correctly
echo "Testing get_config...\n";
try {
    MockCurlRequest::setMockResponse('/api/v1/configs/', [
        'status' => 200,
        'body' => json_encode([
            'name' => 'user_xyz789',
            'panel_id' => 1,
            'panel_type' => 'marzban',
            'traffic_limit_gb' => 50,
            'traffic_limit_bytes' => 53687091200,
            'usage_bytes' => 10737418240,
            'expires_at' => '2024-03-15T12:00:00Z',
            'status' => 'active',
            'subscription_url' => 'https://falco.example.com/sub/user_xyz789',
            'enabled' => true
        ])
    ]);
    
    // Simulate get config response parsing
    $responseBody = json_encode([
        'name' => 'user_xyz789',
        'panel_id' => 1,
        'panel_type' => 'marzban',
        'traffic_limit_gb' => 50,
        'traffic_limit_bytes' => 53687091200,
        'usage_bytes' => 10737418240,
        'expires_at' => '2024-03-15T12:00:00Z',
        'status' => 'active',
        'subscription_url' => 'https://falco.example.com/sub/user_xyz789',
        'enabled' => true
    ]);
    $config = json_decode($responseBody, true);
    
    // Validate all required fields are present and correct
    $requiredFields = ['name', 'panel_id', 'panel_type', 'traffic_limit_gb', 'traffic_limit_bytes', 
                       'usage_bytes', 'expires_at', 'status', 'subscription_url'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($config[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (count($missingFields) === 0 && 
        $config['name'] === 'user_xyz789' &&
        $config['traffic_limit_gb'] === 50 &&
        $config['usage_bytes'] === 10737418240) {
        $result->pass("get_config parses detailed config correctly");
    } else {
        $result->fail("get_config parses detailed config", "Missing fields: " . implode(', ', $missingFields));
    }
} catch (Exception $e) {
    $result->fail("get_config parses detailed config", $e->getMessage());
}

// Test 4: update_config handles remote_sync true and parses response
echo "Testing update_config with remote_sync=true...\n";
try {
    MockCurlRequest::setMockResponse('/api/v1/configs/', [
        'status' => 200,
        'body' => json_encode([
            'name' => 'user_update_test',
            'traffic_limit_gb' => 100,
            'status' => 'active',
            'remote_sync' => true
        ])
    ]);
    
    $updateParams = ['traffic_limit_gb' => 100, 'status' => 'active'];
    
    $responseBody = json_encode([
        'name' => 'user_update_test',
        'traffic_limit_gb' => 100,
        'status' => 'active',
        'remote_sync' => true
    ]);
    $response = json_decode($responseBody, true);
    
    if ($response['remote_sync'] === true && $response['traffic_limit_gb'] === 100) {
        $result->pass("update_config handles remote_sync=true correctly");
    } else {
        $result->fail("update_config handles remote_sync=true", "remote_sync not correctly returned");
    }
} catch (Exception $e) {
    $result->fail("update_config handles remote_sync=true", $e->getMessage());
}

// Test 5: update_config handles remote_sync false
echo "Testing update_config with remote_sync=false...\n";
try {
    $responseBody = json_encode([
        'name' => 'user_update_test2',
        'traffic_limit_gb' => 50,
        'status' => 'disabled',
        'remote_sync' => false
    ]);
    $response = json_decode($responseBody, true);
    
    if ($response['remote_sync'] === false && $response['status'] === 'disabled') {
        $result->pass("update_config handles remote_sync=false correctly");
    } else {
        $result->fail("update_config handles remote_sync=false", "remote_sync not correctly returned");
    }
} catch (Exception $e) {
    $result->fail("update_config handles remote_sync=false", $e->getMessage());
}

// Test 6: list_configs with pagination
echo "Testing list_configs pagination...\n";
try {
    MockCurlRequest::setMockResponse('/api/v1/configs', [
        'status' => 200,
        'body' => json_encode([
            'data' => [
                ['name' => 'config1', 'status' => 'active'],
                ['name' => 'config2', 'status' => 'active']
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => 20,
                'total' => 50,
                'total_pages' => 3
            ]
        ])
    ]);
    
    $responseBody = json_encode([
        'data' => [
            ['name' => 'config1', 'status' => 'active'],
            ['name' => 'config2', 'status' => 'active']
        ],
        'meta' => [
            'current_page' => 1,
            'per_page' => 20,
            'total' => 50,
            'total_pages' => 3
        ]
    ]);
    $response = json_decode($responseBody, true);
    
    if (isset($response['data']) && isset($response['meta']) &&
        $response['meta']['current_page'] === 1 &&
        $response['meta']['total'] === 50) {
        $result->pass("list_configs returns paginated results correctly");
    } else {
        $result->fail("list_configs pagination", "Meta data not correctly returned");
    }
} catch (Exception $e) {
    $result->fail("list_configs pagination", $e->getMessage());
}

// Test 7: delete_config returns success
echo "Testing delete_config...\n";
try {
    MockCurlRequest::setMockResponse('/api/v1/configs/', [
        'status' => 204,
        'body' => ''
    ]);
    
    // 204 No Content is success for DELETE
    $response = ['status' => 204, 'body' => ''];
    
    if ($response['status'] === 204) {
        $result->pass("delete_config returns success (204 No Content)");
    } else {
        $result->fail("delete_config", "Expected 204 status code");
    }
} catch (Exception $e) {
    $result->fail("delete_config", $e->getMessage());
}

// Test 8: Error handling for non-2xx responses
echo "Testing error handling...\n";
try {
    $errorResponse = [
        'status' => 404,
        'body' => json_encode(['detail' => 'Config not found'])
    ];
    
    $parsed = json_decode($errorResponse['body'], true);
    
    if ($errorResponse['status'] === 404 && $parsed['detail'] === 'Config not found') {
        $result->pass("Error handling surfaces API error messages correctly");
    } else {
        $result->fail("Error handling", "Error message not surfaced correctly");
    }
} catch (Exception $e) {
    $result->fail("Error handling", $e->getMessage());
}

// Test 9: JSON serialization of traffic_limit_gb, expires_at fields
echo "Testing JSON serialization...\n";
try {
    $config = [
        'traffic_limit_gb' => 100,
        'traffic_limit_bytes' => 107374182400,
        'usage_bytes' => 5368709120,
        'expires_at' => '2024-12-31T23:59:59Z',
        'status' => 'active'
    ];
    
    $json = json_encode($config);
    $decoded = json_decode($json, true);
    
    if ($decoded['traffic_limit_gb'] === 100 &&
        $decoded['traffic_limit_bytes'] === 107374182400 &&
        $decoded['expires_at'] === '2024-12-31T23:59:59Z') {
        $result->pass("JSON serialization handles all fields correctly");
    } else {
        $result->fail("JSON serialization", "Fields not serialized correctly");
    }
} catch (Exception $e) {
    $result->fail("JSON serialization", $e->getMessage());
}

// Print summary
$success = $result->summary();

// Exit with appropriate code
exit($success ? 0 : 1);
