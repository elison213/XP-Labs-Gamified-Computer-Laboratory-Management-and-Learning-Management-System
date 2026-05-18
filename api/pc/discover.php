<?php
/**
 * XPLabs API - POST /api/pc/discover
 * Run server-side network discovery and upsert as unassigned PCs.
 */
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Csrf.php';
require_once __DIR__ . '/../../services/PCService.php';
require_once __DIR__ . '/../middleware/CorsMiddleware.php';

use XPLabs\Lib\Auth;
use XPLabs\Lib\Csrf;
use XPLabs\Services\PCService;
use XPLabs\Api\Middleware\CorsMiddleware;

header('Content-Type: application/json');
CorsMiddleware::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

Auth::requireRole(['admin', 'teacher']);
Csrf::requireValidToken();

/**
 * Parse output of `arp -a` into host entries.
 */
function discoverFromArp(): array
{
    $hosts = [];
    $out = @shell_exec('arp -a 2>&1');
    if (!is_string($out) || trim($out) === '') {
        return $hosts;
    }
    $lines = preg_split('/\r\n|\r|\n/', $out) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})\s+([0-9a-fA-F-]{17})\s+(\w+)$/', $line, $m)) {
            $ip = $m[1];
            $mac = strtolower(str_replace('-', ':', $m[2]));
            $hosts[] = [
                'hostname' => '',
                'ip_address' => $ip,
                'mac_address' => $mac,
            ];
        }
    }
    return $hosts;
}

/**
 * Try DHCP discovery (Windows Server DHCP role).
 * Falls back silently if DHCP module/cmdlet is unavailable.
 */
function discoverFromDhcp(): array
{
    $hosts = [];
    $cmd = 'powershell -NoProfile -Command "if (Get-Command Get-DhcpServerv4Scope -ErrorAction SilentlyContinue) { $scopes = Get-DhcpServerv4Scope; $items = @(); foreach ($s in $scopes) { $leases = Get-DhcpServerv4Lease -ScopeId $s.ScopeId -ErrorAction SilentlyContinue; foreach ($l in $leases) { $items += [PSCustomObject]@{ hostname = $l.HostName; ip_address = $l.IPAddress.IPAddressToString; mac_address = $l.ClientId } } }; $items | ConvertTo-Json -Compress }"';
    $out = @shell_exec($cmd);
    if (!is_string($out) || trim($out) === '') {
        return $hosts;
    }
    $decoded = json_decode($out, true);
    if (!$decoded) {
        return $hosts;
    }
    $rows = isset($decoded[0]) ? $decoded : [$decoded];
    foreach ($rows as $row) {
        $mac = strtolower(str_replace('-', ':', trim((string) ($row['mac_address'] ?? ''))));
        $hosts[] = [
            'hostname' => trim((string) ($row['hostname'] ?? '')),
            'ip_address' => trim((string) ($row['ip_address'] ?? '')),
            'mac_address' => $mac,
        ];
    }
    return $hosts;
}

/**
 * Deduplicate discovered hosts by MAC, fallback hostname/IP.
 */
function dedupeHosts(array $hosts): array
{
    $map = [];
    foreach ($hosts as $h) {
        $hostname = trim((string) ($h['hostname'] ?? ''));
        $ip = trim((string) ($h['ip_address'] ?? ''));
        $mac = trim((string) ($h['mac_address'] ?? ''));
        if ($hostname === '' && $ip === '' && $mac === '') {
            continue;
        }
        $key = $mac !== '' ? "mac:$mac" : ($hostname !== '' ? "host:$hostname" : "ip:$ip");
        if (!isset($map[$key])) {
            $map[$key] = ['hostname' => $hostname, 'ip_address' => $ip, 'mac_address' => $mac];
        } else {
            if ($map[$key]['hostname'] === '' && $hostname !== '') {
                $map[$key]['hostname'] = $hostname;
            }
            if ($map[$key]['ip_address'] === '' && $ip !== '') {
                $map[$key]['ip_address'] = $ip;
            }
            if ($map[$key]['mac_address'] === '' && $mac !== '') {
                $map[$key]['mac_address'] = $mac;
            }
        }
    }
    return array_values($map);
}

$hosts = dedupeHosts(array_merge(discoverFromDhcp(), discoverFromArp()));
$service = new PCService();
$created = 0;
$updated = 0;
$failed = 0;

foreach ($hosts as $host) {
    $result = $service->upsertDiscoveredPc($host);
    if (!empty($result['success'])) {
        if (!empty($result['created'])) {
            $created++;
        } else {
            $updated++;
        }
    } else {
        $failed++;
    }
}

echo json_encode([
    'success' => true,
    'discovered' => count($hosts),
    'created' => $created,
    'updated' => $updated,
    'failed' => $failed,
]);
