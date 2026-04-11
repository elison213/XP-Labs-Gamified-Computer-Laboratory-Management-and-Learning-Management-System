<?php
/**
 * AD Sync Script
 * Synchronizes Active Directory computers, users, and groups to local database
 * Run via cron or Task Scheduler every 15-30 minutes
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../services/ADService.php';

use XPLabs\Lib\Database;
use XPLabs\Services\ADService;

$db = Database::getInstance();
$ad = new ADService();

try {
    // Sync computers
    $computers = $ad->getComputers();
    $computerCount = 0;
    foreach ($computers as $c) {
        if (!isset($c['dn'][0])) continue;
        $dn = $c['dn'][0];
        $hostname = $c['dNSHostName'][0] ?? $c['cn'][0] ?? '';
        $os = $c['operatingSystem'][0] ?? null;
        $lastLogon = $c['lastLogonTimestamp'][0] ?? null;

        $stmt = $db->prepare("
            INSERT INTO ad_computers (ad_dn, hostname, os, last_logon)
            VALUES (:dn, :host, :os, :logon)
            ON DUPLICATE KEY UPDATE 
                hostname = VALUES(hostname),
                os = VALUES(os),
                last_logon = VALUES(last_logon),
                last_sync = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':dn'    => $dn,
            ':host'  => $hostname,
            ':os'    => $os,
            ':logon' => $lastLogon
        ]);
        $computerCount++;
    }

    // Sync users
    $users = $ad->getUsers();
    $userCount = 0;
    foreach ($users as $u) {
        if (!isset($u['dn'][0])) continue;
        $dn = $u['dn'][0];
        $sam = $u['sAMAccountName'][0] ?? null;
        $display = $u['displayName'][0] ?? null;

        $stmt = $db->prepare("
            INSERT INTO ad_users (ad_dn, samaccountname, displayname)
            VALUES (:dn, :sam, :display)
            ON DUPLICATE KEY UPDATE 
                samaccountname = VALUES(samaccountname),
                displayname = VALUES(displayname),
                last_sync = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':dn'      => $dn,
            ':sam'     => $sam,
            ':display' => $display
        ]);
        $userCount++;
    }

    // Sync groups
    $groups = $ad->getGroups();
    $groupCount = 0;
    foreach ($groups as $g) {
        if (!isset($g['dn'][0])) continue;
        $dn = $g['dn'][0];
        $cn = $g['cn'][0] ?? null;

        $stmt = $db->prepare("
            INSERT INTO ad_groups (ad_dn, cn)
            VALUES (:dn, :cn)
            ON DUPLICATE KEY UPDATE 
                cn = VALUES(cn),
                last_sync = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':dn' => $dn,
            ':cn' => $cn
        ]);
        $groupCount++;
    }

    // Log success
    $logMsg = "AD Sync completed: {$computerCount} computers, {$userCount} users, {$groupCount} groups";
    error_log($logMsg);
    // Optionally store in admin_logs table
    $stmt = $db->prepare("INSERT INTO admin_logs (user_id, action, details) VALUES (0, 'AD_SYNC', :details)");
    $stmt->execute([':details' => $logMsg]);

} catch (Exception $e) {
    $errorMsg = "AD Sync failed: " . $e->getMessage();
    error_log($errorMsg);
    // Log error
    $stmt = $db->prepare("INSERT INTO admin_logs (user_id, action, details) VALUES (0, 'AD_SYNC_ERROR', :details)");
    $stmt->execute([':details' => $errorMsg]);
    exit(1);
} finally {
    $ad->close();
}