<?php
/**
 * Plugin Name: Push CLI Loader
 * Description: Loads the Push CLI mu-plugin from the push/ subdirectory.
 * Version: 1.0.0
 * 
 * INSTALLATION:
 * 1. Drop the 'push' folder into wp-content/mu-plugins/
 * 2. Copy THIS FILE to wp-content/mu-plugins/ (alongside the push folder)
 * 
 * That's it! WordPress will auto-load this file, which loads the rest.
 */

defined('ABSPATH') || exit;

$push_loader = __DIR__ . '/push/push-loader.php';

if (file_exists($push_loader)) {
	require_once $push_loader;
}

