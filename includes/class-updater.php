<?php
/**
 * Updates from the public GitHub repository.
 *
 * @package WebAula\Charts
 */

namespace WebAula\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks plugin-update-checker to GitHub Releases.
 *
 * The repository is public, so no token is needed. If WA_CHARTS_GITHUB_TOKEN is
 * defined, it is sent to raise GitHub's API rate limit for busy servers.
 */
final class Updater {

	public const REPO_URL = 'https://github.com/rejver007/wa-charts-plugin/';

	/**
	 * Whether the updater can run.
	 *
	 * @param bool $library_available Whether vendor/autoload.php exists.
	 * @return bool
	 */
	public static function should_boot( bool $library_available ): bool {
		return $library_available;
	}

	/**
	 * Optional GitHub token from WA_CHARTS_GITHUB_TOKEN.
	 *
	 * @param mixed $value Value of the constant, or null when undefined.
	 * @return string|null Token, or null when not set.
	 */
	public static function auth_token( $value ): ?string {
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Boots the update checker.
	 *
	 * @return void
	 */
	public static function maybe_boot(): void {
		$autoload = WA_CHARTS_DIR . 'vendor/autoload.php';
		if ( ! self::should_boot( is_readable( $autoload ) ) ) {
			return;
		}
		require_once $autoload;
		if ( ! class_exists( \YahnisElsts\PluginUpdateChecker\v5\PucFactory::class ) ) {
			return;
		}
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( self::REPO_URL, WA_CHARTS_FILE, 'wa-charts' );
		$token   = self::auth_token( defined( 'WA_CHARTS_GITHUB_TOKEN' ) ? constant( 'WA_CHARTS_GITHUB_TOKEN' ) : null );
		if ( null !== $token ) {
			$checker->setAuthentication( $token );
		}
		$checker->getVcsApi()->enableReleaseAssets( '/^wa-charts\.zip$/' );
	}
}
