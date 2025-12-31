<?php
/**
 * Plugin Name: Push CLI
 * Plugin URI: https://github.com/your-repo/push
 * Description: Enhanced WordPress CLI tool inspired by Drupal's Drush. Provides uli, sql-sync, and more.
 * Version: 1.0.0
 * Author: Push CLI
 * License: MIT
 * 
 * INSTALLATION:
 * 1. Drop the entire 'push' folder into wp-content/mu-plugins/
 * 2. Create a loader in mu-plugins root (see below)
 * 
 * Create wp-content/mu-plugins/load-push.php with this content:
 * <?php require_once __DIR__ . '/push/push-loader.php';
 * 
 * Or run: ln -s push/push-loader.php load-push.php
 */

defined('ABSPATH') || exit;

/**
 * Push CLI WordPress Integration
 * 
 * Handles:
 * - Automatic password restoration for `push uli` command
 * - Symlink creation to make `push` command available globally
 */
class Push_CLI_WordPress {
	
	/**
	 * Path to the push CLI entry point
	 */
	private static $pushPath;

	/**
	 * Password swap lifetime in seconds (1 hour)
	 */
	const SWAP_LIFETIME = 3600;

	/**
	 * Option keys
	 */
	const OPTION_SYMLINK_STATUS = 'push_cli_symlink_status';
	const OPTION_PUSH_PATH = 'push_cli_path';

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		self::$pushPath = dirname(__FILE__) . '/push.php';
		
		// Store push path for reference
		if (get_option(self::OPTION_PUSH_PATH) !== self::$pushPath) {
			update_option(self::OPTION_PUSH_PATH, self::$pushPath, false);
		}

		// Restore expired passwords on every request
		add_action('init', [__CLASS__, 'restoreExpiredPasswords'], 1);
		
		// Attempt symlink installation on admin init (once)
		if (is_admin()) {
			add_action('admin_init', [__CLASS__, 'maybeInstallSymlink'], 1);
			add_action('admin_notices', [__CLASS__, 'showAdminNotices']);
		}

		// AJAX handler for dismissing notices
		add_action('wp_ajax_push_cli_dismiss_notice', [__CLASS__, 'dismissNotice']);
	}

	/**
	 * Attempt to install symlink on first admin load
	 */
	public static function maybeInstallSymlink() {
		$status = get_option(self::OPTION_SYMLINK_STATUS);
		
		// Already attempted
		if ($status && isset($status['attempted'])) {
			return;
		}

		$result = self::installSymlink();
		
		update_option(self::OPTION_SYMLINK_STATUS, [
			'attempted' => true,
			'attempted_at' => time(),
			'success' => $result['success'],
			'path' => $result['path'],
			'message' => $result['message'],
		], false);
	}

	/**
	 * Install symlink to user's bin path
	 *
	 * @return array Result with success, path, and message
	 */
	protected static function installSymlink() {
		$pushPath = self::$pushPath;
		
		if (!file_exists($pushPath)) {
			return [
				'success' => false,
				'path' => null,
				'message' => 'push.php not found at: ' . $pushPath,
			];
		}

		// Make push.php executable
		@chmod($pushPath, 0755);

		// Get home directory
		$home = getenv('HOME');
		if (!$home) {
			$home = posix_getpwuid(posix_getuid())['dir'] ?? null;
		}
		
		if (!$home) {
			return [
				'success' => false,
				'path' => null,
				'message' => 'Could not determine home directory',
			];
		}

		// Bin paths to try (in order of preference)
		$binPaths = [
			$home . '/bin',           // Traditional user bin
			'/usr/local/bin',         // System-wide (usually needs sudo)
		];

		$errors = [];

		foreach ($binPaths as $binPath) {
			$symlinkPath = $binPath . '/push';

			// Check if symlink already exists and points to us
			if (is_link($symlinkPath)) {
				$target = @readlink($symlinkPath);
				if ($target === $pushPath) {
					return [
						'success' => true,
						'path' => $symlinkPath,
						'message' => 'Symlink already exists',
					];
				}
				$errors[] = "{$symlinkPath} exists but points to: {$target}";
				continue;
			}

			if (file_exists($symlinkPath)) {
				$errors[] = "{$symlinkPath} exists (not a symlink)";
				continue;
			}

			// Create bin directory if needed
			if (!is_dir($binPath)) {
				if (!@mkdir($binPath, 0755, true)) {
					$errors[] = "Cannot create directory: {$binPath}";
					continue;
				}
			}

			// Check if writable
			if (!is_writable($binPath)) {
				$errors[] = "{$binPath} is not writable";
				continue;
			}

			// Create symlink
			if (@symlink($pushPath, $symlinkPath)) {
				return [
					'success' => true,
					'path' => $symlinkPath,
					'message' => 'Symlink created successfully',
				];
			}

			$errors[] = "Failed to create symlink at {$symlinkPath}";
		}

		return [
			'success' => false,
			'path' => null,
			'message' => implode('; ', $errors),
		];
	}

	/**
	 * Show admin notices about symlink status
	 */
	public static function showAdminNotices() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$status = get_option(self::OPTION_SYMLINK_STATUS);
		if (!$status || !is_array($status)) {
			return;
		}

		// Only show for 24 hours after attempt
		if (time() > ($status['attempted_at'] + 86400)) {
			return;
		}

		// Check if dismissed
		if (get_transient('push_cli_notice_dismissed')) {
			return;
		}

		if ($status['success']) {
			?>
			<div class="notice notice-success is-dismissible" data-push-cli-notice>
				<p>
					<strong>Push CLI installed!</strong> 
					The <code>push</code> command is now available at: <code><?php echo esc_html($status['path']); ?></code>
				</p>
				<p>Try running: <code>push version</code></p>
			</div>
			<?php
		} else {
			$pushPath = esc_html(self::$pushPath);
			?>
			<div class="notice notice-warning is-dismissible" data-push-cli-notice>
				<p><strong>Push CLI:</strong> Could not automatically install the <code>push</code> command.</p>
				<p><?php echo esc_html($status['message']); ?></p>
				<p>To install manually, run one of these commands:</p>
				<pre style="background:#f0f0f0;padding:10px;overflow-x:auto;">
# Option 1: User bin (recommended)
mkdir -p ~/.local/bin
ln -s <?php echo $pushPath; ?> ~/.local/bin/push

# Option 2: System-wide (requires sudo)
sudo ln -s <?php echo $pushPath; ?> /usr/local/bin/push</pre>
				<p>Make sure <code>~/.local/bin</code> is in your PATH. Add to your <code>~/.bashrc</code> or <code>~/.zshrc</code>:</p>
				<pre style="background:#f0f0f0;padding:10px;">export PATH="$HOME/.local/bin:$PATH"</pre>
			</div>
			<?php
		}
		?>
		<script>
		jQuery(function($) {
			$('[data-push-cli-notice]').on('click', '.notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'push_cli_dismiss_notice',
					_wpnonce: '<?php echo wp_create_nonce('push_cli_dismiss'); ?>'
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler for dismissing notice
	 */
	public static function dismissNotice() {
		check_ajax_referer('push_cli_dismiss');
		set_transient('push_cli_notice_dismissed', 1, WEEK_IN_SECONDS);
		wp_die();
	}

	/**
	 * Get the path to push.php
	 *
	 * @return string
	 */
	public static function getPushPath() {
		return self::$pushPath;
	}
}

// Initialize
Push_CLI_WordPress::init();

