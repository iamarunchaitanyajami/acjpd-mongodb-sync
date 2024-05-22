<?php
/**
 * Plugin Name:       ACJ MongoDB CLONE
 * Plugin URI:        https://github.com/iamarunchaitanyajami/acj-mongodb-clone
 * Requires WP:       6.0 ( Minimal )
 * Requires PHP:      8.0
 * Version:           1.0.2
 * Author:            Arun Chaitanya Jami
 * Text Domain:       acj-mongodb-clone
 * Domain Path:       /language/
 *
 * @package           acj-mongodb-clone
 * @sub-package       WordPress
 */

namespace Acj\Mongodb;

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
define( 'ACJ_MONGODB_PLUGIN_VERSION', '1.0.2' );
define( 'ACJ_MONGODB_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACJ_MONGODB_DIR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Composer Autoload file.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	include __DIR__ . '/vendor/autoload.php';
}

if ( ! function_exists( 'alch_get_option' ) ) {
	include __DIR__ . '/vendor/alchemyoptions/alchemyoptions/alchemy-options.php';
}

add_filter( 'alch_acj-mongodb-clone-options-page_icon', __NAMESPACE__ . '\\acj_mongodb_change_my_options_page_icon' );
add_filter( 'alch_options', __NAMESPACE__ . '\\acj_mongodb_add_custom_options' );
add_filter( 'alch_options_pages', __NAMESPACE__ . '\\acj_mongodb_add_custom_options_pages' );

add_action(
	'alch_output_before_options_tabs',
	function () {
		if ( ! extension_loaded( 'mongodb' ) ) {
			echo '<div class="notice error"><p>Please Install <b>MongoDb</b> PHP EXTENSION for this plugin to work. Please Read instruction how to install in READ.MD file of this plugin.</p></div>';
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

$uri = alch_get_option( 'acj-mongodb-connection-uri', '' );
if ( empty( $uri ) ) {
	return;
}

if ( ! extension_loaded( 'mongodb' ) ) {
	return;
}

use MongoDB\Client as MongoDbClient;
use MongoDB\Database;

global $acj_mongodb;
$acj_mongodb = new MongoDbClient( $uri );

/**
 * Get mongodb client.
 *
 * @param string $db_name Database name.
 *
 * @return Database|null
 */
function acj_mongodb_get_client( string $db_name ): \MongoDB\Database|null {
	if ( empty( $db_name ) ) {
		return null;
	}

	global $acj_mongodb;

	return $acj_mongodb->selectDatabase( $db_name );
}

/**
 *  Load classes.
 */
( new PostTypeSync() )->init();
( new TermSync() )->init();
( new OptionSync() )->init();

/**
 * Change Icon.
 *
 * @return string
 */
function acj_mongodb_change_my_options_page_icon(): string {
	return 'dashicons-database';
}

/**
 * Add options pages to the plugin.
 *
 * @param array $pages Existing pages.
 *
 * @return array
 */
function acj_mongodb_add_custom_options_pages( array $pages ): array {
	$my_pages = array(
		array(
			'id'   => 'acj-mongodb-clone-options-page',
			'name' => __( 'MONGO DB', 'acj-mongodb-clone' ),
			'tabs' => array(
				array(
					'id'   => 'mongodb-setting',
					'name' => __( 'Mongo DB Settings', 'acj-mongodb-clone' ),
				),
				array(
					'id'   => 'wp-settings',
					'name' => __( 'Wordpress Settings', 'acj-mongodb-clone' ),
				),
				array(
					'id'   => 'read-me',
					'name' => __( 'Read Me', 'acj-mongodb-clone' ),
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
function acj_mongodb_add_custom_options( array $options ): array {

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
	$choices = apply_filters( 'acj_mongodb_clone_post_statuses', $choices );

	$post_types         = get_post_types( array(), 'objects' );
	$post_types_choices = array();
	foreach ( $post_types as $post_type ) {
		$post_types_choices[] = array(
			'value' => $post_type->name,
			'label' => $post_type->label,
		);
	}

	$post_types_choices = apply_filters( 'acj_mongodb_clone_post_types', $post_types_choices );

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

	$taxonomy_list = apply_filters( 'acj_mongodb_clone_taxonomies', $taxonomy_list );

	$my_options = array(
		array(
			'id'    => 'acj-mongodb-connection-uri',
			'title' => __( 'MONGO DB CONNECT URI', 'acj-mongodb-clone' ),
			'type'  => 'text',
			'place' => array(
				'page' => 'acj-mongodb-clone-options-page',
				'tab'  => 'mongodb-setting',
			),
		),
		array(
			'title'   => __( 'Sync Data by Post Type', 'acj-mongodb-clone' ),
			'id'      => 'sync-object-types',
			'desc'    => 'Option that will only allow posts to clone based on enabled post type flag.',
			'place'   => array(
				'page' => 'acj-mongodb-clone-options-page',
				'tab'  => 'wp-settings',
			),
			'type'    => 'checkbox',
			'choices' => $post_types_choices,
		),
		array(
			'title'   => __( 'Sync Data by Post status', 'acj-mongodb-clone' ),
			'id'      => 'sync-object-status',
			'desc'    => 'Option that will only allow posts to clone based on enabled status flag.',
			'place'   => array(
				'page' => 'acj-mongodb-clone-options-page',
				'tab'  => 'wp-settings',
			),
			'type'    => 'checkbox',
			'choices' => $choices,
		),
		array(
			'title'   => __( 'Sync Data by Taxonomy', 'acj-mongodb-clone' ),
			'id'      => 'sync-taxonomy-types',
			'desc'    => 'Option that will only allow terms to clone based on enabled taxonomies flag.',
			'place'   => array(
				'page' => 'acj-mongodb-clone-options-page',
				'tab'  => 'wp-settings',
			),
			'type'    => 'checkbox',
			'choices' => $taxonomy_list,
		),
	);

	return array_merge( $options, $my_options );
}
