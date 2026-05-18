<?php

namespace XPLabs\Services;

use XPLabs\Lib\Database;

class KioskDeviceService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tableExists(): bool
    {
        return $this->db->tableExists('kiosk_devices');
    }

    public static function normalizeMac(string $mac): string
    {
        $m = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $mac));
        if (strlen($m) !== 12) {
            return '';
        }
        return implode(':', str_split($m, 2));
    }

    /**
     * Full token format: kdev{id}-{64 hex secret}
     */
    public static function hashToken(string $fullToken): string
    {
        return hash('sha256', $fullToken);
    }

    public function listDevices(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT kd.*, lf.name AS floor_name
             FROM kiosk_devices kd
             INNER JOIN lab_floors lf ON kd.floor_id = lf.id
             ORDER BY kd.label ASC, kd.id ASC'
        );
    }

    public function getDevice(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        return $this->db->fetch(
            'SELECT kd.*, lf.name AS floor_name
             FROM kiosk_devices kd
             INNER JOIN lab_floors lf ON kd.floor_id = lf.id
             WHERE kd.id = ?',
            [$id]
        );
    }

    public function createDevice(string $label, string $macAddress, int $floorId): array
    {
        if (!$this->tableExists()) {
            return ['success' => false, 'error' => 'kiosk_devices table missing; run migrations'];
        }
        $mac = self::normalizeMac($macAddress);
        if ($mac === '') {
            return ['success' => false, 'error' => 'Invalid MAC address (expect 12 hex digits)'];
        }
        if ($floorId <= 0) {
            return ['success' => false, 'error' => 'Floor is required'];
        }
        $floor = $this->db->fetch('SELECT id FROM lab_floors WHERE id = ? AND is_active = 1', [$floorId]);
        if (!$floor) {
            return ['success' => false, 'error' => 'Invalid floor'];
        }
        try {
            $id = (int) $this->db->insert('kiosk_devices', [
                'label' => $label ?: 'Kiosk',
                'mac_address' => $mac,
                'floor_id' => $floorId,
                'is_active' => 1,
            ]);
            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return ['success' => false, 'error' => 'MAC address already registered'];
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function updateDevice(int $id, array $data): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $updates = [];
        if (isset($data['label'])) {
            $updates['label'] = $data['label'];
        }
        if (isset($data['mac_address'])) {
            $mac = self::normalizeMac((string) $data['mac_address']);
            if ($mac === '') {
                return false;
            }
            $updates['mac_address'] = $mac;
        }
        if (isset($data['floor_id'])) {
            $updates['floor_id'] = (int) $data['floor_id'];
        }
        if (isset($data['is_active'])) {
            $updates['is_active'] = (int) (bool) $data['is_active'];
        }
        if (empty($updates)) {
            return true;
        }
        return $this->db->update('kiosk_devices', $updates, 'id = ?', [$id]) >= 0;
    }

    /**
     * Create new token; returns plaintext once.
     */
    public function rotateApiToken(int $id): array
    {
        if (!$this->tableExists()) {
            return ['success' => false, 'error' => 'kiosk_devices table missing'];
        }
        $row = $this->db->fetch('SELECT id FROM kiosk_devices WHERE id = ?', [$id]);
        if (!$row) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        $secret = bin2hex(random_bytes(16));
        $fullToken = 'kdev' . $id . '-' . $secret;
        $hash = self::hashToken($fullToken);
        $this->db->update('kiosk_devices', [
            'token_hash' => $hash,
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
        ], 'id = ?', [$id]);

        return ['success' => true, 'token' => $fullToken];
    }

    /**
     * Short pairing code (10 min). Returns plaintext once.
     */
    public function createPairingCode(int $id): array
    {
        if (!$this->tableExists()) {
            return ['success' => false, 'error' => 'kiosk_devices table missing'];
        }
        $row = $this->db->fetch('SELECT id FROM kiosk_devices WHERE id = ?', [$id]);
        if (!$row) {
            return ['success' => false, 'error' => 'Device not found'];
        }
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $plain = '';
        for ($i = 0; $i < 8; $i++) {
            $plain .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $exp = date('Y-m-d H:i:s', time() + 600);
        $this->db->update('kiosk_devices', [
            'pairing_code_hash' => $hash,
            'pairing_expires_at' => $exp,
        ], 'id = ?', [$id]);

        return ['success' => true, 'pairing_code' => $plain, 'expires_at' => $exp];
    }

    /**
     * Exchange pairing code for API token.
     */
    public function pairWithCode(string $plainCode): array
    {
        if (!$this->tableExists()) {
            return ['success' => false, 'error' => 'Kiosk feature not installed'];
        }
        $plainCode = strtoupper(trim($plainCode));
        if (strlen($plainCode) < 6) {
            return ['success' => false, 'error' => 'Invalid pairing code'];
        }
        $rows = $this->db->fetchAll(
            "SELECT * FROM kiosk_devices
             WHERE is_active = 1
               AND pairing_code_hash IS NOT NULL
               AND pairing_expires_at IS NOT NULL
               AND pairing_expires_at > NOW()"
        );
        foreach ($rows as $row) {
            if (!password_verify($plainCode, $row['pairing_code_hash'])) {
                continue;
            }
            $id = (int) $row['id'];
            $out = $this->rotateApiToken($id);
            if (!($out['success'] ?? false)) {
                return $out;
            }
            return [
                'success' => true,
                'token' => $out['token'],
                'floor_id' => (int) $row['floor_id'],
                'label' => $row['label'],
                'device_id' => $id,
            ];
        }
        return ['success' => false, 'error' => 'Invalid or expired pairing code'];
    }

    public function verifyApiToken(string $fullToken): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $fullToken = trim($fullToken);
        if (!preg_match('/^kdev(\d+)-([a-f0-9]{32})$/i', $fullToken, $m)) {
            return null;
        }
        $id = (int) $m[1];
        $row = $this->db->fetch(
            'SELECT * FROM kiosk_devices WHERE id = ? AND is_active = 1',
            [$id]
        );
        if (!$row || empty($row['token_hash'])) {
            return null;
        }
        if (!hash_equals($row['token_hash'], self::hashToken($fullToken))) {
            return null;
        }
        return $row;
    }

    public function touchDevice(int $id, ?string $ip): void
    {
        $this->db->update('kiosk_devices', [
            'last_used_at' => date('Y-m-d H:i:s'),
            'last_seen_ip' => $ip ?: null,
        ], 'id = ?', [$id]);
    }
}
