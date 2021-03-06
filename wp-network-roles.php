<?php
/**
 * Plugin initialization file
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: WP Network Roles
 * Plugin URI:  https://github.com/felixarntz/wp-network-roles
 * Description: Implements network-wide user roles in WordPress.
 * Version:     1.0.0-beta.1
 * Author:      Felix Arntz
 * Author URI:  https://leaves-and-love.net
 * License:     GNU General Public License v2 (or later)
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-network-roles
 * Domain Path: /languages/
 * Network:     true
 * Tags:        network roles, network, multisite, multinetwork
 */

/**
 * Loads the plugin textdomain.
 *
 * @since 1.0.0
 */
function nr_load_textdomain() {
	load_plugin_textdomain( 'wp-network-roles', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

/**
 * Initializes the plugin.
 *
 * Loads the required files.
 *
 * @since 1.0.0
 */
function nr_init() {
	require_once NR_PATH . 'wp-network-roles/wp-includes/class-wp-network-role.php';
	require_once NR_PATH . 'wp-network-roles/wp-includes/class-wp-network-roles.php';
	require_once NR_PATH . 'wp-network-roles/wp-includes/class-wpnr-user-with-network-roles.php';
	require_once NR_PATH . 'wp-network-roles/wp-includes/capabilities.php';
	require_once NR_PATH . 'wp-network-roles/wp-includes/user.php';
	require_once NR_PATH . 'wp-network-roles/wp-includes/sync-network-relationships.php';
	require_once NR_PATH . 'wp-network-roles/wp-includes/sync-super-admins.php';
	require_once NR_PATH . 'wp-network-roles/wp-includes/setup-migration.php';

	if ( is_admin() ) {
		require_once NR_PATH . 'wp-network-roles/wp-admin/includes/wp-ms-users-list-table-tweaks.php';
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once NR_PATH . 'wp-network-roles/wp-includes/class-wp-cli-network-capabilities-command.php';
		require_once NR_PATH . 'wp-network-roles/wp-includes/class-wp-cli-network-role-command.php';

		WP_CLI::add_command( 'network-cap', 'WP_CLI_Network_Capabilities_Command' );
		WP_CLI::add_command( 'network-role', 'WP_CLI_Network_Role_Command' );
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( is_plugin_active( 'wp-multi-network/wpmn-loader.php' ) ) {
		require_once NR_PATH . 'wp-network-roles/multi-network-compat.php';
	}
}

/**
 * Shows an admin notice if the WordPress version installed is not supported.
 *
 * @since 1.0.0
 */
function nr_requirements_notice() {
	$plugin_file = plugin_basename( __FILE__ );

	if ( ! current_user_can( 'deactivate_plugin', $plugin_file ) ) {
		return;
	}

	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php
			printf(
				/* translators: %s: URL to deactivate plugin */
				__( 'Please note: WP Network Roles requires WordPress 4.9 or higher. <a href="%s">Deactivate plugin</a>.', 'wp-network-roles' ),
				wp_nonce_url(
					add_query_arg(
						array(
							'action'        => 'deactivate',
							'plugin'        => $plugin_file,
							'plugin_status' => 'all',
						),
						network_admin_url( 'plugins.php' )
					),
					'deactivate-plugin_' . $plugin_file
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Ensures that this plugin gets activated in every new network by filtering the `active_sitewide_plugins` option.
 *
 * @since 1.0.0
 *
 * @param array $network_options All network options for the new network.
 * @return array Modified network options including the plugin.
 */
function nr_activate_on_new_network( $network_options ) {
	$plugin_file = plugin_basename( __FILE__ );

	if ( ! isset( $network_options['active_sitewide_plugins'][ $plugin_file ] ) ) {
		$network_options['active_sitewide_plugins'][ $plugin_file ] = time();
	}

	return $network_options;
}

/**
 * Ensures that this plugin gets activated on a request to update the network-active plugins.
 *
 * @since 1.0.0
 *
 * @param array $plugins Associative array of `$plugin_basename => $time` pairs.
 * @return array Modified array.
 */
function nr_activate_on_update_request( $plugins ) {
	$network_options = nr_activate_on_new_network( array(
		'active_sitewide_plugins' => $plugins,
	) );

	remove_filter( 'pre_update_site_option_active_sitewide_plugins', 'nr_activate_on_update_request' );

	return $network_options['active_sitewide_plugins'];
}

/**
 * Adds the hook to ensure that this plugin gets activated in every network created by WP Multi Network.
 *
 * @since 1.0.0
 */
function nr_activate_on_new_wpmn_network_add_hook() {
	add_filter( 'pre_update_site_option_active_sitewide_plugins', 'nr_activate_on_update_request' );
}

/**
 * Hooks in plugin initialization functionality.
 *
 * @since 1.0.0
 */
function nr_add_hooks() {
	$file          = wp_normalize_path( __FILE__ );
	$mu_plugin_dir = wp_normalize_path( WPMU_PLUGIN_DIR );
	$is_mu_plugin  = (bool) preg_match( '#^' . preg_quote( $mu_plugin_dir, '#' ) . '/#', $file );
	$plugin_hook   = $is_mu_plugin ? 'muplugins_loaded' : 'plugins_loaded';

	add_action( $plugin_hook, 'nr_load_textdomain', 1 );

	if ( version_compare( $GLOBALS['wp_version'], '4.9', '<' ) ) {
		add_action( 'admin_notices', 'nr_requirements_notice' );
		add_action( 'network_admin_notices', 'nr_requirements_notice' );
		return;
	}

	define( 'NR_PATH', plugin_dir_path( __FILE__ ) );
	define( 'NR_URL', plugin_dir_url( __FILE__ ) );

	add_action( $plugin_hook, 'nr_init' );

	if ( ! $is_mu_plugin ) {
		add_filter( 'populate_network_meta', 'nr_activate_on_new_network', 10, 1 );
		add_action( 'add_network', 'nr_activate_on_new_wpmn_network_add_hook', 10, 0 );
	}
}

nr_add_hooks();
