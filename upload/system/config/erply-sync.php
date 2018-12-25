<?php
// Site
$_['site_url'] = HTTP_SERVER;
$_['site_ssl'] = HTTPS_SERVER;

// Database
$_['db_autostart'] = true;
$_['db_engine'] = DB_DRIVER; // mpdo, mssql, mysql, mysqli or postgre
$_['db_hostname'] = DB_HOSTNAME;
$_['db_username'] = DB_USERNAME;
$_['db_password'] = DB_PASSWORD;
$_['db_database'] = DB_DATABASE;
$_['db_port'] = DB_PORT;

// Session
$_['session_autostart'] = false;

// Template
$_['template_cache'] = true;

// Actions
$_['action_pre_action'] = array(
	'startup/startup',
	'startup/error',
	'startup/event',
	'startup/sass'
);

$_['action_default'] = 'extension/module/erply/sync';
