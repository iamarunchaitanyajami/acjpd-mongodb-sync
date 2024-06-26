<?php
/**
 * Any post type Sync to Mongodb.
 *
 * @package acjpd-mongodb-sync
 * @subpackage WordPress
 */

namespace Acjpd\Mongodb;

/**
 * Class.
 */
class PostTypeSync extends Connector {

	/**
	 * Post to create/update.
	 *
	 * @var array
	 */
	public array $posts = array();

	/**
	 * Post meta to create/update.
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
	 * Sync Post to mongodb.
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

		$exclude_post_type = alch_get_option( 'acjpd-sync-object-types', array() );
		if ( ! in_array( $post->post_type, $exclude_post_type, true ) ) {
			return;
		}

		$exclude_post_status = alch_get_option( 'acjpd-sync-object-status', array() );
		if ( ! in_array( $post->post_status, $exclude_post_status, true ) ) {
			return;
		}

		$site_id = get_current_blog_id();

		$this->posts[ $site_id ][] = $post;

		/**
		 * Add flags.
		 */
		update_post_meta( $post_id, 'acjpd_mongodb_sync_is_post_sync', true );
		update_post_meta( $post_id, 'acjpd_mongodb_sync_post_last_sync', time() );
		update_post_meta( $post_id, 'acjpd_mongodb_sync_post_sync_site_id', $site_id );
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

		$exclude_post_type = alch_get_option( 'acjpd-sync-object-types', array() );
		if ( ! in_array( $post->post_type, $exclude_post_type, true ) ) {
			return;
		}

		$exclude_post_status = alch_get_option( 'acjpd-sync-object-status', array() );
		if ( ! in_array( $post->post_status, $exclude_post_status, true ) ) {
			return;
		}

		$site_id                          = get_current_blog_id();
		$this->posts_delete[ $site_id ][] = $post_id;

		delete_post_meta( $post_id, 'acjpd_mongodb_sync_is_post_sync' );
		delete_post_meta( $post_id, 'acjpd_mongodb_sync_post_last_sync' );
		delete_post_meta( $post_id, 'acjpd_mongodb_sync_post_sync_site_id' );
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
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( in_array( $meta_key, $this->exclude_meta_keys, true ) ) {
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
		if ( in_array( $meta_key, $this->exclude_meta_keys, true ) ) {
			return;
		}

		$site_id                               = get_current_blog_id();
		$this->delete_posts_meta[ $site_id ][] = array(
			'post_id'  => $post_id,
			'meta_key' => $meta_key,
		);
	}


	/**
	 * Sync process to Mongo db.
	 *
	 * @return void
	 */
	public function process_sync(): void {

		/**
		 * Process post queue.
		 */
		if ( ! empty( $this->posts ) ) {
			$post_collection = $this->get_post_collection();
			foreach ( $this->posts as $site_id => $wp_posts ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $wp_posts as $mdb_post ) {
					$update = $post_collection->updateOne(
						array( 'ID' => $mdb_post->ID ),
						array( '$set' => $mdb_post ),
						array( 'upsert' => true )
					);

					$upserted_id = $update->getUpsertedId();
					if ( ! empty( $upserted_id ) ) {
						update_post_meta( $mdb_post->ID, 'acjpd_mongodb_sync_inserted_id', $upserted_id );
					}
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
