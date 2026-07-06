<?php
/**
 * REUSABLE — registers a page template into Elementor's Templates library
 * (Templates → Saved Templates) so it can be inserted with no manual file
 * import. Idempotent: created once; if deleted it re-appears; if edited it is
 * left untouched. COPY VERBATIM into includes/; only change the `namespace`.
 *
 * Init from the main plugin (admin only):
 *   \PluginNS\Template_Registrar::init([
 *     'dir'      => PLUGIN_DIR,                 // plugin_dir_path( __FILE__ )
 *     'template' => 'templates/your-page.json', // the exported page JSON
 *     'title'    => 'Your Brand Pillar Page',
 *   ]);
 */
namespace PluginNS; // <-- CHANGE to your plugin namespace, e.g. Acme_EW

defined( 'ABSPATH' ) || exit;

class Template_Registrar {

	private static $cfg = [];

	public static function init( array $cfg ) {
		self::$cfg = $cfg;
		add_action( 'admin_init', [ __CLASS__, 'maybe_register' ] );
	}

	private static function option_key() {
		return 'ew_lib_tpl_' . md5( ( self::$cfg['dir'] ?? '' ) . ( self::$cfg['title'] ?? '' ) );
	}

	public static function maybe_register() {
		$opt      = self::option_key();
		$existing = (int) get_option( $opt );
		if ( $existing && 'elementor_library' === get_post_type( $existing ) && 'trash' !== get_post_status( $existing ) ) {
			return; // already present (and possibly user-edited) — leave it alone
		}

		$json = ( self::$cfg['dir'] ?? '' ) . ( self::$cfg['template'] ?? '' );
		if ( ! $json || ! is_readable( $json ) ) {
			return;
		}
		$tpl = json_decode( file_get_contents( $json ), true );
		if ( empty( $tpl['content'] ) ) {
			return;
		}

		$id = wp_insert_post( [
			'post_title'  => self::$cfg['title'] ?? 'Page',
			'post_status' => 'publish',
			'post_type'   => 'elementor_library',
		] );
		if ( is_wp_error( $id ) || ! $id ) {
			return;
		}

		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tpl['content'] ) ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'page' );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		}
		// Categorise it as a "page" template so it lands in the right library tab.
		wp_set_object_terms( $id, 'page', 'elementor_library_type' );

		update_option( $opt, $id );
	}
}
