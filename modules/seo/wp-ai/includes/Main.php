<?php
/**
 * The main plugin file.
 *
 * @package WordPress\AI
 *
 * @since 0.8.0
 */

declare( strict_types=1 );

namespace WordPress\AI;

use WordPress\AI\Experiments\Experiments;
use WordPress\AI\Features\Loader;
use WordPress\AI\Features\Registry;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class - Main
 *
 * @internal This class should not be used outside the plugin and there is no guarantee of backwards compatibility.
 *
 * @since 0.8.0
 */
final class Main {
	/**
	 * Instance of the class.
	 * @since 0.8.0
	 *
	 *
	 * @var ?static
	 */
	private static $instance;

	/**
	 * Gets the (singleton) instance of the Main class.
	 *
	 * @since 0.8.0
	 */
	public static function get_instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Setup the plugin.
	 */
	private function setup(): void {
		// Load the plugin classes.
		if (did_action('plugins_loaded')) {
			$this->load();
		} else {
			add_action( 'plugins_loaded', array( $this, 'load' ) );
		}

		// Removed activation and deactivation hooks.
	}

	/**
	 * Load the plugin classes.
	 *
	 * @since 0.8.0
	 *
	 * @internal Used in the plugins_loaded action.
	 */
	public function load(): void {
		// Check plugin requirements before continuing.
		if ( ! ( new Requirements() )->are_requirements_met() ) {
			return;
		}

		// Include globals
		require_once WPAI_PLUGIN_DIR . 'includes/helpers.php';

		// Defer feature initialization to the 'init' action.
		add_action( 'init', array( $this, 'initialize_features' ), 15 );

		// Register provider data globally so it is available to any plugin script.
		add_action( 'init', array( $this, 'register_provider_data' ), 20 );

		// Register the default ability category.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_category' ) );
	}

	/**
	 * Initializes plugin features.
	 *
	 * @since 0.8.0
	 */
	public function initialize_features(): void {
		try {
			// Experiments are hooked into our Loader, so we need to register them first.
			( new Experiments() )->init();

			// The one true registry of all features.
			$registry = new Registry();

			// Initializes all the features.
			( new Loader( $registry ) )->init();

			// Removed unnecessary abilities and settings registration
		} catch ( \Throwable $e ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
				/* translators: %s: Error message. */
					esc_html__( 'AI Plugin initialization failed: %s', 'ai' ),
					esc_html( $e->getMessage() )
				),
				'0.8.0'
			);
		}
	}



	/**
	 * Registers provider availability data for frontend scripts.
	 *
	 * @since 1.0.0
	 */
	public function register_provider_data(): void {
		Asset_Loader::add_global_data( 'ProviderData', get_provider_availability_data() );
	}

	/**
	 * Register a generic catch-all category that all Abilities we register can use.
	 *
	 * This can be re-evaluated in the future if we need/want more specific categories.
	 *
	 * @internal Used in the wp_abilities_api_categories_init action.
	 *
	 * @since 0.8.0
	 */
	public function register_ability_category(): void {
		wp_register_ability_category(
			WPAI_DEFAULT_ABILITY_CATEGORY,
			array(
				'label'       => __( 'AI', 'ai' ),
				'description' => __( 'Various AI features and experiments.', 'ai' ),
			),
		);
	}

	/**
	 * Prevent the class from being cloned.
	 *
	 * @since 0.8.0
	 */
	public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s: Class name.
				esc_html__( 'The %s class should not be cloned.', 'ai' ),
				esc_html( self::class ),
			),
			'0.8.0'
		);
	}

	/**
	 * Prevent the class from being deserialized.
	 *
	 * @since 0.8.0
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s: Class name.
				esc_html__( 'De-serializing instances of %s is not allowed.', 'ai' ),
				esc_html( self::class ),
			),
			'0.8.0'
		);
	}
}
