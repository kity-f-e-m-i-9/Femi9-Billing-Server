<?php
/**
 * User Type Configuration
 * Defines database tables and settings for each user type
 */

// Self-migrating — track_users has no other creation path, so every entry
// point into the standalone Track portal (login, session check, and
// Company's own account-management page) calls this before its first query.
function ensureTrackUsersTable(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS track_users (
          id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(255) NOT NULL,
          mobile VARCHAR(15) NOT NULL,
          password VARCHAR(255) NOT NULL,
          email VARCHAR(255) NULL,
          account_status VARCHAR(20) NOT NULL DEFAULT 'active',
          last_login TIMESTAMP NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uk_track_users_mobile (mobile)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function getUserConfig($userType) {
    $configs = [
        'super_stockiest' => [
            'table' => 'super_stockiest',
            'display_name' => 'Super Stockist',
            'folder' => 'super-stockist',
            'id_field' => 'temp_id',
            'username_field' => 'username',
            'mobile_field' => 'mobile_number',
            'password_field' => 'password',
            'status_field' => 'account_status',
            'status_active_value' => 'active',
            'name_field' => 'name',
            'email_field' => 'email'
        ],
        
        'stockiest' => [
            'table' => 'stockiest',
            'display_name' => 'Stockist',
            'folder' => 'stockist',
            'id_field' => 'temp_id',
            'username_field' => 'username',
            'mobile_field' => 'mobile_number',
            'password_field' => 'password',
            'status_field' => 'account_status',
            'status_active_value' => 'active',
            'name_field' => 'name',
            'email_field' => 'email'
        ],
        
        'distributor' => [
            'table' => 'distributor',
            'display_name' => 'Distributor',
            'folder' => 'distributor',
            'id_field' => 'temp_id',
            'username_field' => 'username',
            'mobile_field' => 'mobile_number',
            'password_field' => 'password',
            'status_field' => 'account_status',
            'status_active_value' => 'active',
            'name_field' => 'name',
            'email_field' => 'email'
        ],
        
        'super_distributor' => [
            'table' => 'super_distributor',
            'display_name' => 'Super Distributor',
            'folder' => 'super_distributor',
            'id_field' => 'temp_id',
            'username_field' => 'username',
            'mobile_field' => 'mobile_number',
            'password_field' => 'password',
            'status_field' => 'account_status',
            'status_active_value' => 'active',
            'name_field' => 'name',
            'email_field' => 'email'
        ],
        
        'marketing' => [
            'table' => 'marketing_staff',
            'display_name' => 'Marketing',
            'folder' => 'marketing',
            'id_field' => 'id',
            'username_field' => 'ms_mobile',
            'mobile_field' => 'ms_mobile',
            'password_field' => 'password',
            'status_field' => 'account_status',
            'status_active_value' => 'active',
            'name_field' => 'ms_name',
            'email_field' => 'ms_email'
        ],

        'channel_partner' => [
            'table' => 'channel_partners',
            'display_name' => 'Channel Partner',
            'folder' => 'channel-partner',
            'id_field' => 'id',
            'username_field' => 'mobile',
            'mobile_field' => 'mobile',
            'password_field' => 'password',
            'status_field' => 'is_active',
            'status_active_value' => '1',
            'name_field' => 'name',
            'email_field' => null
        ],

        'candf' => [
            'table' => 'c_and_f',
            'display_name' => 'C & F',
            'folder' => 'c-and-f',
            'id_field' => 'temp_id',
            'username_field' => 'username',
            'mobile_field' => 'username',
            'password_field' => 'password',
            'status_field' => 'account_status',
            'status_active_value' => 'active',
            'name_field' => 'name',
            'email_field' => 'email'
        ],

        'company' => [
            'table' => 'admin_log',
            'display_name' => 'Company',
            'folder' => 'company',
            'id_field' => 'id',
            'username_field' => 'username',
            'password_field' => 'password',
        ],

        'salesbdm' => [
            'table'               => 'sales_bdm_staff',
            'display_name'        => 'Sales BDM',
            'folder'              => 'salesbdm',
            'id_field'            => 'id',
            'username_field'      => 'bdm_mobile',
            'mobile_field'        => 'bdm_mobile',
            'password_field'      => 'password',
            'status_field'        => 'account_status',
            'status_active_value' => 'active',
            'name_field'          => 'bdm_name',
            'email_field'         => 'bdm_email',
        ],

        'track' => [
            'table'               => 'track_users',
            'display_name'        => 'Track',
            'folder'              => 'track',
            'id_field'            => 'id',
            'username_field'      => 'mobile',
            'mobile_field'        => 'mobile',
            'password_field'      => 'password',
            'status_field'        => 'account_status',
            'status_active_value' => 'active',
            'name_field'          => 'name',
            'email_field'         => 'email',
        ],

        'territory_partner' => [
            'table'               => 'territory_partners',
            'display_name'        => 'Territory Partner',
            'folder'              => 'territory-partner',
            'id_field'            => 'id',
            'username_field'      => 'mobile',   // login is by mobile number
            'mobile_field'        => 'mobile',
            'password_field'      => 'password',
            'status_field'        => 'is_active',
            'status_active_value' => '1',
            'name_field'          => 'name',
            'email_field'         => 'email',
        ],
    ];
    
    return $configs[$userType] ?? null;
}

/**
 * User types eligible for the central mobile-based login lookup.
 * 'company' is intentionally excluded — it keeps its own separate login.
 */
function getCentralLoginTypes() {
    return [
        'candf',
        'channel_partner',
        'distributor',
        'marketing',
        'stockiest',
        'super_stockiest',
        'super_distributor',
        'territory_partner',
    ];
}
?>