<?php
/**
 * Plugin Name:       ACJ MANGODB CLONE
 * Plugin URI:        https://github.com/Arun Chaitanya Jami/acj-mangodb-clone
 * Requires WP:       6.0 ( Minimal )
 * Requires PHP:      8.0
 * Version:           1.0.0
 * Author:            Arun Chaitanya Jami
 * Text Domain:       acj-mangodb-clone
 * Domain Path:       /language/
 *
 * @package           acj-mangodb-clone
 * @sub-package       WordPress
 */

namespace Acj\Mangodb;

/**
 * If this file is called directly, abort.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'ACJ_MANGODB_PLUGIN_VERSION', '1.0.0' );
define( 'ACJ_MANGODB_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACJ_MANGODB_DIR_URL', plugin_dir_url( __FILE__ ) );

if ( ! extension_loaded( 'mongodb' ) ) {
	return;
}

/**
 * Composer Autoload file.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	include __DIR__ . '/vendor/autoload.php';
}

use MongoDB\Client as MangoDbClient;
use MongoDB\Database;

if ( ! function_exists( 'alch_get_option' ) ) {
	include __DIR__ . '/vendor/alchemyoptions/alchemyoptions/alchemy-options.php';
}

add_filter( 'alch_acj-mangodb-clone-options-page_icon', __NAMESPACE__ . '\\acj_mangodb_change_my_options_page_icon' );
add_filter( 'alch_options', __NAMESPACE__ . '\\acj_mangodb_add_custom_options' );
add_filter( 'alch_options_pages', __NAMESPACE__ . '\\acj_mangodb_add_custom_options_pages' );

$uri = alch_get_option( 'acj-mongodb-connection-uri', '' );
if ( empty( $uri ) ) {
	return;
}

global $acj_mangodb;
$acj_mangodb = new MangoDbClient( $uri );

/**
 * Get mangodb client.
 *
 * @param string $db_name Database name.
 *
 * @return Database|null
 */
function acj_mongodb_get_client( string $db_name ): \MongoDB\Database|null {
	if ( empty( $db_name ) ) {
		return null;
	}

	global $acj_mangodb;

	return $acj_mangodb->selectDatabase( $db_name );
}

/**
 *  Load classes.
 */
( new Acj_PostTypeSync() )->init();

/**
 * Change Icon.
 *
 * @return string
 */
function acj_mangodb_change_my_options_page_icon(): string {
	return 'dashicons-database';
}

/**
 * Add options pages to the plugin.
 *
 * @param array $pages Existing pages.
 *
 * @return array
 */
function acj_mangodb_add_custom_options_pages( array $pages ): array {
	$my_pages = array(
		array(
			'id'   => 'acj-mangodb-clone-options-page',
			'name' => __( 'MANGO DB CLONE', 'acj-mangodb-clone' ),
			'tabs' => array(
				array(
					'id'   => 'mangodb-setting',
					'name' => __( 'Mango DB Settings', 'acj-mangodb-clone' ),
				),
				array(
					'id'   => 'wp-settings',
					'name' => __( 'Wordpress Settings', 'acj-mangodb-clone' ),
				),
			),
		),
	);

	return array_merge( $pages, $my_pages );
}

/**
 * Add options to pages.
 *
 * @param array $options Existing options.
 *
 * @return array
 */
function acj_mangodb_add_custom_options( array $options ): array {

	$choices   = array();
	$choices[] = array(
		'value' => 'trash',
		'label' => 'Trash',
	);
	$statuses  = get_post_statuses();
	foreach ( $statuses as $status => $label ) {
		$choices[] = array(
			'value' => $status,
			'label' => $label,
		);
	}
	$choices = apply_filters( 'acj_mangodb_clone_post_statuses', $choices );



	$post_types         = get_post_types( array(), 'objects' );
	$post_types_choices = array();
	foreach ( $post_types as $post_type ) {
		$post_types_choices[] = array(
			'value' => $post_type->name,
			'label' => $post_type->label,
		);
	}

	$post_types_choices = apply_filters( 'acj_mangodb_clone_post_types_choices', $post_types_choices );

	$my_options = array(
		array(
			'id'    => 'acj-mongodb-connection-uri',
			'title' => __( 'MANGO DB CONNECT URI', 'acj-mangodb-clone' ),
			'type'  => 'text',
			'place' => array(
				'page' => 'acj-mangodb-clone-options-page',
				'tab'  => 'mangodb-setting',
			),
		),
		array(
			'title'   => __( 'Sync Data by Post Type', 'acj-mangodb-clone' ),
			'id'      => 'sync-object-types',
			'desc'    => 'Option that will only allow posts to clone based on enabled post type flag.',
			'place'   => array(
				'page' => 'acj-mangodb-clone-options-page',
				'tab'  => 'wp-settings',
			),
			'type'    => 'checkbox',
			'choices' => $post_types_choices,
		),
		array(
			'title'   => __( 'Sync Data by Post status', 'acj-mangodb-clone' ),
			'id'      => 'sync-object-status',
			'desc'    => 'Option that will only allow posts to clone based on enabled status flag.',
			'place'   => array(
				'page' => 'acj-mangodb-clone-options-page',
				'tab'  => 'wp-settings',
			),
			'type'    => 'checkbox',
			'choices' => $choices,
		),
	);

	return array_merge( $options, $my_options );
}
