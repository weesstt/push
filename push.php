#!/usr/bin/env php
<?php

/**
 * Push CLI Tool
 *
 * This file is the entry point for the push command-line tool.
 */

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
	fwrite(STDERR, "push requires PHP 7.4 or higher. You are running PHP " . PHP_VERSION . ".\n");
	exit(1);
}

// Autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	fwrite(STDERR, "Composer dependencies not installed. Run 'composer install'.\n");
	exit(1);
}

use Push\Application;

// Create and run the application
$application = new Application();
$application->run();

