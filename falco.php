<?php
/**
 * Falco Panel Integration
 * 
 * This module provides integration with Falco panel API for managing VPN configs.
 * 
 * API Endpoints:
 * - GET /api/v1/panels - Lists panels the API key has access to
 * - GET /api/v1/configs - Lists configs owned by reseller (with pagination)
 * - GET /api/v1/configs/{name} - Get detail of a config
 * - POST /api/v1/configs - Create config
 * - PUT /api/v1/configs/{name} - Update config
 * - DELETE /api/v1/configs/{name} - Delete config
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/request.php';

/**
 * Get Falco panel configuration from database
 * 
 * @param string $location Panel name/location
 * @return array Panel configuration data
 */
function getFalcoPanelConfig($location)
{
    $panel = select("marzban_panel", "*", "name_panel", $location, "select");
    return $panel;
}

/**
 * List all panels accessible by the API key
 * 
 * @param string $location Panel name in database
 * @return array Response containing panels list with id, name, panel_type, nodes, services
 */
function listFalcoPanels($location)
{
    $panel = getFalcoPanelConfig($location);
    $url = rtrim($panel['url_panel'], '/') . '/api/v1/panels';
    
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $panel['secret_code']
    );
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->get();
    
    return $response;
}

/**
 * List configs with pagination
 * 
 * @param string $location Panel name in database
 * @param int $page Page number (default: 1)
 * @param int $per_page Results per page (default: 20)
 * @return array Response containing configs list and pagination meta
 */
function listFalcoConfigs($location, $page = 1, $per_page = 20)
{
    $panel = getFalcoPanelConfig($location);
    $url = rtrim($panel['url_panel'], '/') . '/api/v1/configs';
    $url .= '?page=' . intval($page) . '&per_page=' . intval($per_page);
    
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $panel['secret_code']
    );
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->get();
    
    return $response;
}

/**
 * Get detailed information about a specific config
 * 
 * @param string $location Panel name in database
 * @param string $name Config name/username
 * @return array Response containing config details (traffic_limit_bytes, traffic_limit_gb, 
 *               usage_bytes, expires_at, status, panel_id, panel_type, subscription_url)
 */
function getFalcoConfig($location, $name)
{
    $panel = getFalcoPanelConfig($location);
    $url = rtrim($panel['url_panel'], '/') . '/api/v1/configs/' . urlencode($name);
    
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $panel['secret_code']
    );
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->get();
    
    return $response;
}

/**
 * Create a new config
 * 
 * @param string $location Panel name in database
 * @param int $panel_id Panel ID to create config on
 * @param int $traffic_limit_gb Traffic limit in gigabytes
 * @param int $expires_days Number of days until expiration
 * @param string|null $comment Optional comment for the config
 * @return array Response containing created config details
 */
function createFalcoConfig($location, $panel_id, $traffic_limit_gb, $expires_days, $comment = null)
{
    $panel = getFalcoPanelConfig($location);
    $url = rtrim($panel['url_panel'], '/') . '/api/v1/configs';
    
    $data = array(
        'panel_id' => intval($panel_id),
        'traffic_limit_gb' => intval($traffic_limit_gb),
        'expires_days' => intval($expires_days)
    );
    
    if ($comment !== null && !empty($comment)) {
        $data['comment'] = $comment;
    }
    
    $payload = json_encode($data);
    
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $panel['secret_code']
    );
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->post($payload);
    
    return $response;
}

/**
 * Update an existing config
 * 
 * @param string $location Panel name in database
 * @param string $name Config name/username
 * @param array $params Update parameters (traffic_limit_gb, expires_at, status, etc.)
 * @return array Response containing updated config and remote_sync flag
 */
function updateFalcoConfig($location, $name, array $params)
{
    $panel = getFalcoPanelConfig($location);
    $url = rtrim($panel['url_panel'], '/') . '/api/v1/configs/' . urlencode($name);
    
    $payload = json_encode($params);
    
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $panel['secret_code']
    );
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->put($payload);
    
    return $response;
}

/**
 * Delete a config
 * 
 * @param string $location Panel name in database
 * @param string $name Config name/username
 * @return array Response indicating success/failure
 */
function deleteFalcoConfig($location, $name)
{
    $panel = getFalcoPanelConfig($location);
    $url = rtrim($panel['url_panel'], '/') . '/api/v1/configs/' . urlencode($name);
    
    $headers = array(
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $panel['secret_code']
    );
    
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $response = $req->delete();
    
    return $response;
}

/**
 * Add a new user/config via Falco panel (compatible with ManagePanel interface)
 * 
 * @param string $location Panel name in database
 * @param int $data_limit Data limit in bytes
 * @param string $username_ac Username for the config
 * @param int $timestamp Expiration timestamp
 * @param string $name_product Product name
 * @param string $note Optional note/comment
 * @param string $data_limit_reset Data limit reset strategy
 * @return array Response from Falco API
 */
function addUserFalco($location, $data_limit, $username_ac, $timestamp, $name_product = '', $note = '', $data_limit_reset = 'no_reset')
{
    global $pdo;
    
    $panel = getFalcoPanelConfig($location);
    
    // Convert data_limit from bytes to GB
    $traffic_limit_gb = $data_limit > 0 ? intval($data_limit / pow(1024, 3)) : 0;
    
    // Calculate expires_days from timestamp
    if ($timestamp == 0) {
        $expires_days = 0; // Unlimited
    } else {
        $expires_days = max(1, intval(($timestamp - time()) / 86400));
    }
    
    // Get panel_id from panel config (stored in inboundid field)
    $panel_id = isset($panel['inboundid']) ? intval($panel['inboundid']) : 1;
    
    // Use note as comment
    $comment = !empty($note) ? $note : null;
    
    return createFalcoConfig($location, $panel_id, $traffic_limit_gb, $expires_days, $comment);
}

/**
 * Get user data from Falco panel (compatible with ManagePanel interface)
 * 
 * @param string $username Config name/username
 * @param string $location Panel name in database
 * @return array Parsed user data with status, data_limit, expire, used_traffic, etc.
 */
function getUserFalco($username, $location)
{
    $response = getFalcoConfig($location, $username);
    
    if (!empty($response['error'])) {
        return array(
            'status' => 'Unsuccessful',
            'msg' => $response['error']
        );
    }
    
    if (!empty($response['status']) && $response['status'] != 200) {
        return array(
            'status' => 'Unsuccessful',
            'msg' => 'HTTP Error: ' . $response['status']
        );
    }
    
    $data = json_decode($response['body'], true);
    
    if (isset($data['detail'])) {
        return array(
            'status' => 'Unsuccessful',
            'msg' => $data['detail']
        );
    }
    
    return $data;
}

/**
 * Remove user/config from Falco panel (compatible with ManagePanel interface)
 * 
 * @param string $location Panel name in database
 * @param string $username Config name/username
 * @return array Response from Falco API
 */
function removeUserFalco($location, $username)
{
    return deleteFalcoConfig($location, $username);
}

/**
 * Modify user/config in Falco panel (compatible with ManagePanel interface)
 * 
 * @param string $location Panel name in database
 * @param string $username Config name/username
 * @param array $data Update parameters
 * @return array Response from Falco API
 */
function modifyUserFalco($location, $username, array $data)
{
    return updateFalcoConfig($location, $username, $data);
}

/**
 * Reset user data usage in Falco panel
 * 
 * @param string $username Config name/username
 * @param string $location Panel name in database
 * @return array Response from Falco API
 */
function resetUserDataUsageFalco($username, $location)
{
    // Reset usage by updating config with traffic reset
    return updateFalcoConfig($location, $username, array('reset_usage' => true));
}
