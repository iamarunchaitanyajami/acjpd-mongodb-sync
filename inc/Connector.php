<?php
/**
 * Mongodb Db Collection Settings.
 *
 * @package    acjpd-mongodb-sync
 * @subpackage WordPress
 */

namespace Acjpd\Mongodb;

use MongoDB\Collection;
use function Acjpd\Mongodb\acjpd_mongodb_get_client as mongoDbClient;

/**
 * Class.
 */
class Connector {
	/**
	 * Is multisite enabled?.
	 *
	 * @var bool
	 */
	public bool $is_multi_blog = false;

	/**
	 * Exclude meta keys to sync.
	 *
	 * @var array|mixed|string[]|null
	 */
	public array $exclude_meta_keys = array(
		'acjpd_mongodb_sync_inserted_id',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->is_multi_blog     = is_multisite();
		$this->exclude_meta_keys = apply_filters( 'acjpd_mongodb_sync_excluded_meta_keys', $this->exclude_meta_keys );
	}

	/**
	 * Post db collection.
	 *
	 * @return Collection
	 */
	protected function get_post_collection(): Collection {
		global $wpdb;

		$db_name = apply_filters( 'acjpd_mongodb_sync_db_name', $wpdb->dbname );
		$mongodb = mongoDbClient( $db_name );

		return $mongodb->selectCollection( $wpdb->posts );
	}

	/**
	 * Post meta db collection.
	 *
	 * @return Collection
	 */
	protected function get_post_meta_collection(): Collection {
		global $wpdb;

		$db_name = apply_filters( 'acjpd_mongodb_sync_db_name', $wpdb->dbname );
		$mongodb = mongoDbClient( $db_name );

		return $mongodb->selectCollection( $wpdb->postmeta );
	}

	/**
	 * Term db collection.
	 *
	 * @return Collection
	 */
	protected function get_term_collection(): Collection {
		global $wpdb;

		$db_name = apply_filters( 'acjpd_mongodb_sync_db_name', $wpdb->dbname );
		$mongodb = mongoDbClient( $db_name );

		return $mongodb->selectCollection( $wpdb->terms );
	}

	/**
	 * Term meta db collection.
	 *
	 * @return Collection
	 */
	protected function get_term_meta_collection(): Collection {
		global $wpdb;

		$db_name = apply_filters( 'acjpd_mongodb_sync_db_name', $wpdb->dbname );
		$mongodb = mongoDbClient( $db_name );

		return $mongodb->selectCollection( $wpdb->termmeta );
	}

	/**
	 * Options db collection.
	 *
	 * @return Collection
	 */
	protected function get_option_collection(): Collection {
		global $wpdb;

		$db_name = apply_filters( 'acjpd_mongodb_sync_db_name', $wpdb->dbname );
		$mongodb = mongoDbClient( $db_name );

		return $mongodb->selectCollection( $wpdb->options );
	}

	/**
	 * User db collection.
	 *
	 * @return Collection
	 */
	protected function get_user_collection(): Collection {
		global $wpdb;

		$db_name = apply_filters( 'acjpd_mongodb_sync_db_name', $wpdb->dbname );
		$mongodb = mongoDbClient( $db_name );

		return $mongodb->selectCollection( $wpdb->users ); //phpcs:ignore
	}

	/**
	 * User meta db collection.
	 *
	 * @return Collection
	 */
	protected function get_user_meta_collection(): Collection {
		global $wpdb;

		$db_name = apply_filters( 'acjpd_mongodb_sync_db_name', $wpdb->dbname );
		$mongodb = mongoDbClient( $db_name );

		return $mongodb->selectCollection( $wpdb->usermeta );
	}

	/**
	 * Options db collection.
	 *
	 * @param string $table_name Table Name.
	 *
	 * @return Collection
	 */
	public function get_custom_table_collection( string $table_name ): Collection {
		global $wpdb;

		$db_name = apply_filters( 'acjpd_mongodb_sync_db_name', $wpdb->dbname );
		$mongodb = mongoDbClient( $db_name );

		return $mongodb->selectCollection( $table_name );
	}

	/**
	 * Site db collection.
	 *
	 * @return Collection
	 */
	public function get_site_collection(): Collection {
		global $wpdb;

		return $this->get_custom_table_collection( $wpdb->site );
	}

	/**
	 * Site meta db collection.
	 *
	 * @return Collection
	 */
	public function get_site_meta_collection(): Collection {
		global $wpdb;

		return $this->get_custom_table_collection( $wpdb->sitemeta );
	}

	/**
	 * Blog db collection.
	 *
	 * @return Collection
	 */
	public function get_blog_collection(): Collection {
		global $wpdb;

		return $this->get_custom_table_collection( $wpdb->blogs );
	}

	/**
	 * Blog meta db collection.
	 *
	 * @return Collection
	 */
	public function get_blog_meta_collection(): Collection {
		global $wpdb;

		return $this->get_custom_table_collection( $wpdb->blogmeta );
	}

	/**
	 * Get Mongodb settings.
	 *
	 * @param string $key          Options Key.
	 * @param mixed  $default_args Default value.
	 *
	 * @since v1.2.0
	 *
	 * @return mixed
	 */
	public function get_setting_options( string $key, mixed $default_args ): mixed {
		$option_key = sprintf( '_alchemy_options_%s', esc_html( $key ) );

		return $this->is_multi_blog ? alch_admin_get_saved_network_option( $option_key, $default_args ) : alch_get_option( $key, $default_args );
	}
}
