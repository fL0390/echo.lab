<?php

define('APP_NAME', 'echo');
define('TESTING_MODE', false);

define('DB_HOST', 'localhost');
define('DB_NAME', 'echo_db');
define('DB_USER', 'echo_user');
define('DB_PASS', '123');

define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_PORT', 2525);
define('SMTP_USER', 'private');
define('SMTP_PASS', 'private');
define('SMTP_FROM', 'private');
define('APP_URL', 'https://echo.lab');

define('NODE_API_SECRET', 'echo-node-secret');

/* Roles */
define('ROLE_BANNED',       0);
define('ROLE_USER',         1);
define('ROLE_STUDENT',      2);
define('ROLE_TEACHER',      3);
define('ROLE_CENTER_ADMIN', 4);
define('ROLE_ADMIN',        5);

/* 
 * Define los permisos para cada rol.
 *
 * ROLE_USER         = 1
 * ROLE_STUDENT      = 2
 * ROLE_TEACHER      = 3
 * ROLE_CENTER_ADMIN = 4
 * ROLE_ADMIN        = 5
 */
define('PERMISSIONS', [

    /* Admin */
    'admin_panel'      => [ROLE_TEACHER, ROLE_CENTER_ADMIN, ROLE_ADMIN],

    /* Lista de usuarios */
    'view_all_users'   => [ROLE_CENTER_ADMIN, ROLE_ADMIN],

    /* Importación de usuarios */
    'import_users'     => [ROLE_ADMIN, ROLE_TEACHER, ROLE_CENTER_ADMIN],

    /* Nodes */
    'manage_nodes'     => [ROLE_ADMIN],

    /* Cambiar roles */
    'change_roles'     => [ROLE_CENTER_ADMIN, ROLE_ADMIN],

    /* Banear/desbanear */
    'ban_users'        => [ROLE_TEACHER, ROLE_CENTER_ADMIN, ROLE_ADMIN],

    /* Gestión de ISO */
    'manage_isos'      => [ROLE_TEACHER, ROLE_CENTER_ADMIN, ROLE_ADMIN],

    /* Gestión de VMs */
    'vm_view'          => [ROLE_STUDENT, ROLE_TEACHER, ROLE_CENTER_ADMIN, ROLE_ADMIN],
    'vm_create'        => [ROLE_TEACHER, ROLE_CENTER_ADMIN, ROLE_ADMIN],
    'vm_administration'=> [ROLE_TEACHER, ROLE_CENTER_ADMIN, ROLE_ADMIN],
    'vm_snapshot'      => [ROLE_CENTER_ADMIN, ROLE_ADMIN],

]);


define('PROXMOX_ENABLED',        true);         
define('PROXMOX_HOST',           'private'); 
define('PROXMOX_PORT',           8006);
define('PROXMOX_NODE',           'private');           
define('PROXMOX_TOKEN_ID',       'private'); 
define('PROXMOX_TOKEN_SECRET',   'private'); 
define('PROXMOX_STORAGE',        'private');     
define('PROXMOX_WAIT_FOR_CLONE',  true);
define('PROXMOX_LINKED_CLONES',   true); 
define('PROXMOX_ISO_STORAGE',    'private');      
define('PROXMOX_PROXY_PORT',      8081);        
define('PROXMOX_PROXY_HOST',     'localhost');  


if (php_sapi_name() !== 'cli' && !defined('SKIP_SESSION')) {
    $isApiRequest = (
        isset($_SERVER['HTTP_ACCEPT']) &&
        strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
    ) || (
        isset($_SERVER['CONTENT_TYPE']) &&
        strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
    ) || (
        isset($_SERVER['PHP_SELF']) &&
        str_contains($_SERVER['PHP_SELF'], '/api/')
    );

    if (!$isApiRequest) {
        session_start();
    } else {
        if (!empty($_COOKIE[session_name()])) {
            session_start();
        }
    }
}

function getRoleName(int $role): string {
    return [
        ROLE_BANNED       => 'Banned',
        ROLE_USER         => 'User',
        ROLE_STUDENT      => 'Student',
        ROLE_TEACHER      => 'Teacher',
        ROLE_CENTER_ADMIN => 'Center Admin',
        ROLE_ADMIN        => 'Administrator',
    ][$role] ?? 'Unknown';
}
