<?php
/**
 * Plugin Name:       ACJ MongoDB SYNC
 * Plugin URI:        https://github.com/iamarunchaitanyajami/acj-mongodb-sync
 * Description:       ACJ MongoDB SYNC is a plugin that help you sync data from WordPress to Mongo Db.
 * Requires WP:       6.0 ( Minimal )
 * Requires PHP:      8.0
 * Version:           1.1.0
 * Author:            Arun Chaitanya Jami
 * Text Domain:       acjpd-mongodb-sync
 * Domain Path:       /language/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package           acjpd-mongodb-sync
 * @sub-package       WordPress
 */

namespace Acjpd\Mongodb;

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
define( 'ACJPD_MONGODB_PLUGIN_VERSION', '1.1.0' );
define( 'ACJPD_MONGODB_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACJPD_MONGODB_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'ACJPD_MONGODB_PREFIX', 'acjpd_mongodb_' );
define( 'ACJPD_MONGODB_ENABLE_CRON', false );

/**
 * Composer Autoload file.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	include __DIR__ . '/vendor/autoload.php';
}

if ( ! function_exists( 'alch_get_option' ) ) {
	include __DIR__ . '/vendor/alchemyoptions/alchemyoptions/alchemy-options.php';
}

add_filter( 'alch_acjpd-mongodb-sync-options-page_icon', __NAMESPACE__ . '\\acjpd_mongodb_change_my_options_page_icon' );
add_filter( 'alch_options', __NAMESPACE__ . '\\acjpd_mongodb_add_custom_options' );
add_filter( 'alch_options_pages', __NAMESPACE__ . '\\acjpd_mongodb_add_custom_options_pages' );

add_action(
	'alch_output_before_options_tabs',
	function () {
		if ( ! extension_loaded( 'mongodb' ) ) {
			echo wp_kses_post( '<div class="notice error"><p>Please Install <b>MongoDb</b> PHP EXTENSION for this plugin to work. Please Read instruction how to install in READ.MD file of this plugin.</p></div>' );
		}
	}
);

add_action(
	'alch_output_no_options',
	function () {
		$parse_down = new \Parsedown();
		$contents   = file_get_contents( __DIR__ . '/readme.md' ); //phpcs:ignore

		return $parse_down->text( $contents );
	}
);

$uri = alch_get_option( 'acjpd-mongodb-connection-uri', '' );
if ( empty( $uri ) ) {
	return;
}

if ( ! extension_loaded( 'mongodb' ) ) {
	return;
}

use Acjpd\Mongodb\Cron\Post;
use Acjpd\Mongodb\Cron\Term;
use Acjpd\Mongodb\Cron\User;
use MongoDB\Client as MongoDbClient;
use MongoDB\Database;

global $acjpd_mongodb;

$acjpd_mongodb = new MongoDbClient( $uri );

/**
 * Get mongodb client.
 *
 * @param string $db_name Database name.
 *
 * @return Database|null
 */
function acjpd_mongodb_get_client( string $db_name ): \MongoDB\Database|null {
	if ( empty( $db_name ) ) {
		return null;
	}

	global $acjpd_mongodb;

	return $acjpd_mongodb->selectDatabase( $db_name );
}

/**
 *  Load classes.
 */
( new PostTypeSync() )->init();
( new TermSync() )->init();
( new OptionSync() )->init();
( new UserSync() )->init();

/**
 * Change Icon.
 *
 * @return string
 */
function acjpd_mongodb_change_my_options_page_icon(): string {
	return 'dashicons-database';
}

/**
 * Add options pages to the plugin.
 *
 * @param array $pages Existing pages.
 *
 * @return array
 */
function acjpd_mongodb_add_custom_options_pages( array $pages ): array {
	$my_pages = array(
		array(
			'id'   => 'acjpd-mongodb-sync-options-page',
			'name' => __( 'MONGO DB', 'acjpd-mongodb-sync' ),
			'tabs' => array(
				array(
					'id'   => 'mongodb-setting',
					'name' => __( 'Mongo DB Settings', 'acjpd-mongodb-sync' ),
				),
				array(
					'id'   => 'wp-settings',
					'name' => __( 'Wordpress Settings', 'acjpd-mongodb-sync' ),
				),
				array(
					'id'   => 'read-me',
					'name' => __( 'Read Me', 'acjpd-mongodb-sync' ),
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
function acjpd_mongodb_add_custom_options( array $options ): array {

	$choices   = array();
	$choices[] = array(
		'value' => 'trash',
		'label' => 'Trash',
	);
	$choices[] = array(
		'value' => 'inherit',
		'label' => 'Inherit',
	);
	$statuses  = get_post_statuses();
	foreach ( $statuses as $status => $label ) {
		$choices[] = array(
			'value' => $status,
			'label' => $label,
		);
	}
	$choices = apply_filters( 'acjpd_mongodb_sync_post_statuses', $choices );

	$post_types         = get_post_types( array(), 'objects' );
	$post_types_choices = array();
	foreach ( $post_types as $post_type ) {
		$post_types_choices[] = array(
			'value' => $post_type->name,
			'label' => $post_type->label,
		);
	}

	$post_types_choices = apply_filters( 'acjpd_mongodb_sync_post_types', $post_types_choices );

	$taxonomies    = get_taxonomies( array(), 'objects' );
	$taxonomy_list = array();
	foreach ( $taxonomies as $taxonomy_slug => $taxonomy_name ) {
		if ( ! $taxonomy_name instanceof \WP_Taxonomy ) {
			continue;
		}

		$taxonomy_list[] = array(
			'value' => $taxonomy_slug,
			'label' => $taxonomy_name->label,
		);
	}

	$taxonomy_list = apply_filters( 'acjpd_mongodb_sync_taxonomies', $taxonomy_list );

	$my_options = array(
		array(
			'id'    => 'acjpd-mongodb-connection-uri',
			'title' => __( 'MONGO DB CONNECT URI', 'acjpd-mongodb-sync' ),
			'type'  => 'text',
			'place' => array(
				'page' => 'acjpd-mongodb-sync-options-page',
				'tab'  => 'mongodb-setting',
			),
		),
		array(
			'title'   => __( 'Sync Data by Post Type', 'acjpd-mongodb-sync' ),
			'id'      => 'acjpd-sync-object-types',
			'desc'    => __( 'Option that will only allow posts to sync based on enabled post type flag.', 'acjpd-mongodb-sync' ),
			'place'   => array(
				'page' => 'acjpd-mongodb-sync-options-page',
				'tab'  => 'wp-settings',
			),
			'type'    => 'checkbox',
			'choices' => $post_types_choices,
		),
		array(
			'title'   => __( 'Sync Data by Post status', 'acjpd-mongodb-sync' ),
			'id'      => 'acjpd-sync-object-status',
			'desc'    => __( 'Option that will only allow posts to sync based on enabled status flag.', 'acjpd-mongodb-sync' ),
			'place'   => array(
				'page' => 'acjpd-mongodb-sync-options-page',
				'tab'  => 'wp-settings',
			),
			'type'    => 'checkbox',
			'choices' => $choices,
		),
		array(
			'title'   => __( 'Sync Data by Taxonomy', 'acjpd-mongodb-sync' ),
			'id'      => 'acjpd-sync-taxonomy-types',
			'desc'    => __( 'Option that will only allow terms to sync based on enabled taxonomies flag.', 'acjpd-mongodb-sync' ),
			'place'   => array(
				'page' => 'acjpd-mongodb-sync-options-page',
				'tab'  => 'wp-settings',
			),
			'type'    => 'checkbox',
			'choices' => $taxonomy_list,
		),
		array(
			'title'   => __( 'Enable Cron', 'acjpd-mongodb-sync' ),
			'id'      => 'acjpd-cron-sync-enable',
			'desc'    => __( 'This Option allows us to enable or disable cron.', 'acjpd-mongodb-sync' ),
			'place'   => array(
				'page' => 'acjpd-mongodb-sync-options-page',
				'tab'  => 'wp-settings',
			),
			'type'    => 'checkbox',
			'choices' => array(
				array(
					'value' => 1,
					'label' => __( 'Enable', 'acjpd-mongodb-sync' ),
				),
			),
		),
	);

	return array_merge( $options, $my_options );
}

/**
 * Import CLI.
 */
( new Post() )->init();
( new Term() )->init();
( new User() )->init();
