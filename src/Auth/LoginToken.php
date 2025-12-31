<?php

namespace Push\Auth;

/**
 * Handles one-time login token generation and validation
 */
class LoginToken
{
	/**
	 * Login token lifetime in seconds (1 hour)
	 */
	const TOKEN_LIFETIME = 3600;

	/**
	 * Query parameter for login token
	 */
	const TOKEN_PARAM = 'push_login';

	/**
	 * Transient prefix for login tokens
	 */
	const TOKEN_TRANSIENT_PREFIX = 'push_uli_token_';

	/**
	 * Handle login token from URL
	 * 
	 * When a user visits ?push_login=TOKEN, validate and log them in.
	 * Should be called on 'init' hook with priority 0.
	 */
	public static function handleLoginToken(): void
	{
		// Check if token is present in URL
		if (!isset($_GET[self::TOKEN_PARAM])) {
			return;
		}

		$token = sanitize_text_field($_GET[self::TOKEN_PARAM]);
		if (empty($token)) {
			return;
		}

		// Look up the token in transients
		$transientKey = self::TOKEN_TRANSIENT_PREFIX . $token;
		$tokenData = get_transient($transientKey);

		// Token not found or expired - delete and show error
		if ($tokenData === false) {
			delete_transient($transientKey);
			
			wp_die(
				'<h1>Login Link Expired</h1>' .
				'<p>This login link has expired or has already been used.</p>' .
				'<p><a href="' . esc_url(home_url()) . '">Go to homepage</a></p>',
				'Login Link Expired',
				['response' => 403]
			);
			return;
		}

		// Validate token data structure
		if (!is_array($tokenData) || !isset($tokenData['user_id'])) {
			delete_transient($transientKey);
			wp_die('Invalid login token.', 'Error', ['response' => 403]);
			return;
		}

		// Check expiration (transient should handle this)
		if (isset($tokenData['expires']) && time() > $tokenData['expires']) {
			delete_transient($transientKey);
			wp_die(
				'<h1>Login Link Expired</h1>' .
				'<p>This login link has expired.</p>' .
				'<p><a href="' . esc_url(home_url()) . '">Go to homepage</a></p>',
				'Login Link Expired',
				['response' => 403]
			);
			return;
		}

		$userId = (int) $tokenData['user_id'];

		// Delete the token immediately (one-time use)
		delete_transient($transientKey);

		// Get the user
		$user = get_user_by('ID', $userId);
		if (!$user) {
			wp_die('User not found.', 'Error', ['response' => 403]);
			return;
		}

		// Log the user in
		wp_set_current_user($userId, $user->user_login);
		wp_set_auth_cookie($userId, true);
		do_action('wp_login', $user->user_login, $user);

		// Redirect to admin dashboard (remove token from URL)
		$redirectTo = isset($tokenData['redirect']) ? $tokenData['redirect'] : admin_url();
		wp_safe_redirect($redirectTo);
		exit;
	}

	/**
	 * Create a login token for a user
	 * 
	 * @param int $userId User ID to create token for
	 * @param string|null $redirect URL to redirect to after login
	 * @return string The generated token
	 */
	public static function createLoginToken(int $userId, ?string $redirect = null): string
	{
		// Generate secure random token
		$token = bin2hex(random_bytes(32));
		
		// Token data
		$tokenData = [
			'user_id' => $userId,
			'created' => time(),
			'expires' => time() + self::TOKEN_LIFETIME,
			'redirect' => $redirect ?: admin_url(),
		];

		// Store in transient (auto-expires after TOKEN_LIFETIME)
		$transientKey = self::TOKEN_TRANSIENT_PREFIX . $token;
		set_transient($transientKey, $tokenData, self::TOKEN_LIFETIME);

		return $token;
	}

	/**
	 * Get the login URL for a token
	 * 
	 * @param string $token The login token
	 * @return string The login URL
	 */
	public static function getLoginUrl(string $token): string
	{
		return add_query_arg(self::TOKEN_PARAM, $token, home_url('/'));
	}

	/**
	 * Delete a login token
	 * 
	 * @param string $token The token to delete
	 * @return bool True if deleted, false otherwise
	 */
	public static function deleteToken(string $token): bool
	{
		$transientKey = self::TOKEN_TRANSIENT_PREFIX . $token;
		return delete_transient($transientKey);
	}

	/**
	 * Get the token lifetime in seconds
	 * 
	 * @return int Token lifetime
	 */
	public static function getTokenLifetime(): int
	{
		return self::TOKEN_LIFETIME;
	}
}

