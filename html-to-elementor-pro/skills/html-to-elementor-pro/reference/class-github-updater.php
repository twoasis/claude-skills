<?php
/**
 * REUSABLE GitHub release updater — COPY THIS FILE VERBATIM into your plugin's
 * includes/ directory. Do NOT rewrite the logic from a description; the private-repo
 * download + redirect-without-auth + force-check cache bypass are easy to get wrong.
 *
 * The ONLY thing to change: the `namespace` on the next line → your plugin's namespace.
 * Everything is configured via constructor args (file, version, owner, repo, token, name).
 *
 * Surfaces GitHub releases as normal WordPress plugin updates (one-click "Update").
 * No external library; it only PULLS your published, tagged release zip.
 * Releases: tag vX.Y.Z and attach a built zip named "<slug>.zip" as a release asset
 * (else it falls back to GitHub's source zipball and fixes the extracted folder name).
 */
namespace PluginNS; // <-- CHANGE to your plugin namespace, e.g. Acme_EW

defined( 'ABSPATH' ) || exit;

class GitHub_Updater {

	private $file;       // plugin_basename, e.g. acme-elementor-widgets/acme-elementor-widgets.php
	private $slug;       // acme-elementor-widgets
	private $version;    // current installed version
	private $owner;
	private $repo;
	private $token;      // read-only PAT for private repos (from wp-config)
	private $name;       // display name for the "View details" modal
	private $cache_key;
	private $remote;

	public function __construct( array $args ) {
		$this->file      = plugin_basename( $args['file'] );
		$this->slug      = dirname( $this->file );
		$this->version   = $args['version'];
		$this->owner     = $args['owner'];
		$this->repo      = $args['repo'];
		$this->token     = $args['token'] ?? '';
		$this->name      = $args['name'] ?? $this->slug;
		$this->cache_key = 'ew_gh_' . md5( $this->owner . '/' . $this->repo );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'fix_source_dir' ], 10, 4 );
		// Private repos: download the package ourselves with auth (and DON'T
		// forward the auth header to GitHub's signed-redirect URL).
		add_filter( 'upgrader_pre_download', [ $this, 'download_package' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'flush' ], 10, 0 );
	}

	/** Fetch latest release from the GitHub API (cached 1h; bypassed on a manual "Check Again"). */
	private function get_remote() {
		if ( null !== $this->remote ) {
			return $this->remote;
		}
		// Honor WordPress's manual update check (Dashboard → Updates → Check Again
		// adds ?force-check=1) so a fresh release surfaces immediately.
		$force  = ! empty( $_GET['force-check'] );
		$cached = get_transient( $this->cache_key );
		if ( ! $force && false !== $cached ) {
			return $this->remote = $cached;
		}

		$url  = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases/latest";
		$args = [ 'timeout' => 15, 'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress-' . $this->slug ] ];
		if ( $this->token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}

		$res = wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			set_transient( $this->cache_key, [], 30 * MINUTE_IN_SECONDS ); // brief negative cache
			return $this->remote = [];
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['tag_name'] ) ) {
			set_transient( $this->cache_key, [], 30 * MINUTE_IN_SECONDS );
			return $this->remote = [];
		}

		// Prefer a built zip asset named "<slug>.zip"; else fall back to the zipball.
		// For a private repo we must hit the API URLs (which accept the token)
		// rather than browser_download_url, and download them ourselves.
		$package = $body['zipball_url'] ?? '';
		if ( ! empty( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && $asset['name'] === $this->slug . '.zip' ) {
					$package = $this->token ? $asset['url'] : $asset['browser_download_url'];
					break;
				}
			}
		}

		$data = [
			'version' => ltrim( $body['tag_name'], 'vV' ),
			'package' => $package,
			'url'     => $body['html_url'] ?? "https://github.com/{$this->owner}/{$this->repo}",
			'notes'   => $body['body'] ?? '',
		];
		set_transient( $this->cache_key, $data, HOUR_IN_SECONDS );
		return $this->remote = $data;
	}

	/** Inject an available update into the update transient. */
	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$remote = $this->get_remote();
		if ( empty( $remote ) || empty( $remote['package'] ) ) {
			return $transient;
		}

		$obj = (object) [
			'slug'        => $this->slug,
			'plugin'      => $this->file,
			'new_version' => $remote['version'],
			'url'         => $remote['url'],
			'package'     => $remote['package'],
		];

		if ( version_compare( $remote['version'], $this->version, '>' ) ) {
			$transient->response[ $this->file ] = $obj;
		} else {
			$transient->no_update[ $this->file ] = $obj;
		}
		return $transient;
	}

	/** Power the "View details" modal. */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}
		$remote = $this->get_remote();
		if ( empty( $remote ) ) {
			return $result;
		}
		return (object) [
			'name'          => $this->name,
			'slug'          => $this->slug,
			'version'       => $remote['version'],
			'homepage'      => $remote['url'],
			'download_link' => $remote['package'],
			'sections'      => [ 'changelog' => wpautop( $remote['notes'] ?: 'See GitHub releases.' ) ],
		];
	}

	/**
	 * GitHub source zipballs extract to "owner-repo-hash/". Rename the
	 * extracted folder to the plugin slug so WordPress updates in place.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->file ) {
			return $source;
		}
		global $wp_filesystem;
		$desired = trailingslashit( $remote_source ) . $this->slug;
		if ( $source !== trailingslashit( $desired ) && $wp_filesystem->move( $source, $desired ) ) {
			return trailingslashit( $desired );
		}
		return $source;
	}

	/**
	 * Download the package for a PRIVATE repo with auth. GitHub answers the
	 * authenticated API URL with a 302 to a signed URL that must be fetched
	 * WITHOUT the Authorization header — so we do the two hops manually and
	 * hand WordPress a local temp file.
	 */
	public function download_package( $reply, $package, $upgrader ) {
		if ( empty( $this->token ) ) {
			return $reply; // public repo — let WP download normally
		}
		$remote = $this->get_remote();
		if ( empty( $remote['package'] ) || $package !== $remote['package'] ) {
			return $reply; // not our package
		}

		$headers = [
			'Authorization' => 'Bearer ' . $this->token,
			'Accept'        => 'application/octet-stream',
			'User-Agent'    => 'WordPress-' . $this->slug,
		];
		$res = wp_remote_get( $package, [ 'timeout' => 60, 'redirection' => 0, 'headers' => $headers ] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = wp_remote_retrieve_response_code( $res );
		if ( $code >= 300 && $code < 400 ) {
			// Follow the redirect to the signed URL WITHOUT the auth header.
			$loc = wp_remote_retrieve_header( $res, 'location' );
			if ( ! $loc ) {
				return new \WP_Error( 'gh_no_location', 'GitHub download redirect had no Location.' );
			}
			$res = wp_remote_get( $loc, [ 'timeout' => 60 ] );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$code = wp_remote_retrieve_response_code( $res );
		}
		if ( 200 !== $code ) {
			return new \WP_Error( 'gh_download_failed', 'GitHub download failed (HTTP ' . $code . ').' );
		}

		$body = wp_remote_retrieve_body( $res );
		if ( '' === $body ) {
			return new \WP_Error( 'gh_download_empty', 'GitHub returned an empty package.' );
		}

		$tmp = wp_tempnam( $this->slug . '.zip' );
		if ( ! $tmp ) {
			return new \WP_Error( 'gh_tmp', 'Could not create a temp file for the update.' );
		}
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->put_contents( $tmp, $body );
		return $tmp;
	}

	public function flush() {
		delete_transient( $this->cache_key );
	}
}
