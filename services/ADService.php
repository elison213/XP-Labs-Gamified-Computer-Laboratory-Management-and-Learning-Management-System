<?php
/**
 * XPLabs - Active Directory Service
 * Provides methods to query AD for computers, users, and groups.
 * Used for monitoring and integration with lab management system.
 */
class ADService {
    private $conn;
    private $baseDn;
    private $bindDn;
    private $bindPw;

    public function __construct() {
        // Configure LDAP connection parameters
        $this->baseDn = 'dc=example,dc=com';          // CHANGE TO YOUR DOMAIN
        $this->bindDn = 'cn=monitor,dc=example,dc=com'; // Service account DN
        $this->bindPw = 'P@ssw0rd!';                  // Service account password        // Establish LDAP connection
        $this->conn = ldap_connect('ldap://ad.example.com'); // CHANGE TO YOUR LDAP HOST
        if (!$this->conn) {
            throw new Exception('LDAP connection failed');
        }

        // Set LDAP options
        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);

        // Bind to LDAP server
        if (!@ldap_bind($this->conn, $this->bindDn, $this->bindPw)) {
            throw new Exception('LDAP bind failed');
        }
    }

    /**
     * Get all computers from AD
     * @return array Array of computer objects
     */
    public function getComputers() {
        $filter = '(objectClass=computer)';
        $attrs = ['cn', 'dNSHostName', 'operatingSystem', 'lastLogonTimestamp'];
        $result = ldap_search($this->conn, $this->baseDn, $filter, $attrs);
        return ldap_get_entries($this->conn, $result);
    }

    /**
     * Get all users from AD
     * @return array Array of user objects
     */
    public function getUsers() {
        $filter = '(objectClass=user)';
        $attrs = ['sAMAccountName', 'displayName', 'memberOf'];
        $result = ldap_search($this->conn, $this->baseDn, $filter, $attrs);
        return ldap_get_entries($this->conn, $result);
    }

    /**
     * Get all groups from AD     * @return array Array of group objects     */
    public function getGroups() {
        $filter = '(objectClass=group)';
        $attrs = ['cn', 'member'];
        $result = ldap_search($this->conn, $this->baseDn, $filter, $attrs);
        return ldap_get_entries($this->conn, $result);
    }

    /**
     * Close LDAP connection     */
    public function close() {
        if (is_resource($this->conn)) {
            ldap_unbind($this->conn);
        }
    }
}