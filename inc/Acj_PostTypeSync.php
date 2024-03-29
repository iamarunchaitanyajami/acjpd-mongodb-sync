<?php
/**
 * Any post type Sync to Mangodb.
 *
 * @package acj-mangodb-clone
 * @subpackage WordPress
 */

namespace Acj\Mangodb;

use function Acj\Mangodb\acj_mongodb_get_client as mangoDbClient;

/**
 * Class.
 */
class Acj_PostTypeSync {

	/**
	 * Post to create/update.
	 *
	 * @var array
	 */
	public array $posts = array();

	/**
	 * Post to create/update.
	 *
	 * @var array
	 */
	public array $posts_meta = array();

	/**
	 * Post meta to delete.
	 *
	 * @var array
	 */
	public array $delete_posts_meta = array();

	/**
	 * Post to delete.
	 *
	 * @var array
	 */
	public array $posts_delete = array();

	/**
	 * Is multisite enabled?.
	 *
	 * @var bool
	 */
	public bool $is_multi_blog = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->is_multi_blog = is_multisite();
	}

	/**
	 * Class inti.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'save_post', array( $this, 'sync_post' ), 999, 2 );
		add_action( 'before_delete_post', array( $this, 'remove_post' ), 999, 2 );
		add_action( 'shutdown', array( $this, 'process_sync' ) );
		add_action( 'updated_postmeta', array( $this, 'sync_post_meta' ), 10, 4 );
		add_action( 'delete_post_meta', array( $this, 'remove_post_meta' ), 10, 3 );
	}

	/**
	 * Post db collection.
	 *
	 * @return \MongoDB\Collection
	 */
	private function get_post_collection(): \MongoDB\Collection {
		global $wpdb;

		$mongodb = mangoDbClient( $wpdb->dbname );

		return $mongodb->selectCollection( $wpdb->posts );
	}

	/**
	 * Post db collection.
	 *
	 * @return \MongoDB\Collection
	 */
	private function get_post_meta_collection(): \MongoDB\Collection {
		global $wpdb;

		$mongodb = mangoDbClient( $wpdb->dbname );

		return $mongodb->selectCollection( $wpdb->postmeta );
	}

	/**
	 * Sync Post to mangodb.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function sync_post( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $post->post_type ) && 'page' === $post->post_type ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$exclude_post_type = alch_get_option( 'sync-object-types', array() );
		if ( ! in_array( $post->post_type, $exclude_post_type, true ) ) {
			return;
		}

		$exclude_post_status = alch_get_option( 'sync-object-status', array() );
		if ( ! in_array( $post->post_status, $exclude_post_status, true ) ) {
			return;
		}

		$site_id = get_current_blog_id();

		$this->posts[ $site_id ][] = $post;

		/**
		 * Add flags.
		 */
		add_post_meta( $post_id, 'acj_mangodb_clone_is_post_sync', true );
	}

	/**
	 * Remove article from db.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post Post Object.
	 *
	 * @return void
	 */
	public function remove_post( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $post->post_type ) && 'page' === $post->post_type ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$exclude_post_type = alch_get_option( 'sync-object-types', array() );
		if ( ! in_array( $post->post_type, $exclude_post_type, true ) ) {
			return;
		}

		$exclude_post_status = alch_get_option( 'sync-object-status', array() );
		if ( ! in_array( $post->post_status, $exclude_post_status, true ) ) {
			return;
		}

		$site_id                          = get_current_blog_id();
		$this->posts_delete[ $site_id ][] = $post_id;

		delete_post_meta( $post_id, 'acj_mangodb_clone_is_post_sync' );
	}

	/**
	 * Sync Post meta.
	 *
	 * @param int    $meta_id Meta I`d.
	 * @param int    $post_id Post I`d.
	 * @param string $meta_key Meta Key.
	 * @param mixed  $meta_value Meta Value.
	 *
	 * @return void
	 */
	public function sync_post_meta( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ): void {
		if ( 'acj_mangodb_clone_is_post_sync' === $meta_key ) {
			return;
		}

		$site_id                        = get_current_blog_id();
		$this->posts_meta[ $site_id ][] = array(
			'post_id'    => $post_id,
			'meta_key'   => $meta_key,
			'meta_value' => $meta_value, // phpcs:ignore
		);
	}

	/**
	 * Remove Post meta.
	 *
	 * @param mixed  $meta_id Meta I`d.
	 * @param int    $post_id Post I`d.
	 * @param string $meta_key Meta Key.
	 *
	 * @return void
	 */
	public function remove_post_meta( mixed $meta_id, int $post_id, string $meta_key ): void {
		if ( 'acj_mangodb_clone_is_post_sync' === $meta_key ) {
			return;
		}

		$site_id                               = get_current_blog_id();
		$this->delete_posts_meta[ $site_id ][] = array(
			'post_id'  => $post_id,
			'meta_key' => $meta_key,
		);
	}


	/**
	 * Sync process to Mango db.
	 *
	 * @return void
	 */
	public function process_sync(): void {

		/**
		 * Process post queue.
		 */
		if ( ! empty( $this > $this->posts ) ) {
			$collection = $this->get_post_collection();
			foreach ( $this->posts as $site_id => $wp_posts ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $wp_posts as $mdb_post ) {
					$collection->updateOne(
						array( 'ID' => $mdb_post->ID ),
						array( '$set' => $mdb_post ),
						array( 'upsert' => true )
					);
				}
				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Process delete queue.
		 */
		if ( ! empty( $this->posts_delete ) ) {
			$post_delete_collection = $this->get_post_collection();
			foreach ( $this->posts_delete as $site_id => $delete_posts ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $delete_posts as $delete_post ) {
					$post_delete_collection->deleteOne( array( 'ID' => $delete_post ) );
				}
				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Process Meta queue.
		 */
		if ( ! empty( $this->posts_meta ) ) {
			$post_meta_collection = $this->get_post_meta_collection();
			foreach ( $this->posts_meta as $site_id => $posts_meta ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $posts_meta as $post_meta ) {
					$post_meta_collection->updateOne(
						array(
							'post_id'  => $post_meta['post_id'],
							'meta_key' => $post_meta['meta_key'],
						),
						array( '$set' => $post_meta ),
						array( 'upsert' => true )
					);
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Delete post meta.
		 */
		if ( ! empty( $this->delete_posts_meta ) ) {
			$delete_post_meta_collection = $this->get_post_meta_collection();
			foreach ( $this->delete_posts_meta as $site_id => $delete_posts_meta ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $delete_posts_meta as $delete_post_meta ) {
					$delete_post_meta_collection->deleteMany( $delete_post_meta );
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}
	}
}
