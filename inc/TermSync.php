<?php
/**
 * Any Term Sync to Mongodb.
 *
 * @package acj-mongodb-sync
 * @subpackage WordPress
 */

namespace Acj\Mongodb;

/**
 * Class.
 */
class TermSync extends Connector {

	/**
	 * Terms to create/update.
	 *
	 * @var array
	 */
	public array $terms = array();

	/**
	 * Term meta to create/update.
	 *
	 * @var array
	 */
	public array $terms_delete = array();

	/**
	 * Term meta to create/update.
	 *
	 * @var array
	 */
	public array $term_meta = array();

	/**
	 * Term meta to delete.
	 *
	 * @var array
	 */
	public array $delete_term_meta = array();

	/**
	 * Class inti.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'shutdown', array( $this, 'process_sync' ) );

		/**
		 * Terms Sync.
		 */
		add_action( 'create_term', array( $this, 'sync_taxonomy' ), 10, 3 );
		add_action( 'edit_term', array( $this, 'sync_taxonomy' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'remove_taxonomy' ), 10, 3 );

		/**
		 * Term meta sync.
		 */
		add_action(
			'add_term_meta',
			function ( int $object_id, string $meta_key, string $_meta_value ): void {
				$this->sync_term_meta( 0, $object_id, $meta_key, $_meta_value );
			},
			10,
			4
		);
		add_action( 'update_term_meta', array( $this, 'sync_term_meta' ), 10, 4 );
		add_action( 'delete_term_meta', array( $this, 'remove_term_meta' ), 10, 3 );
	}

	/**
	 * Delete term meta.
	 *
	 * @param array  $meta_id Meta id.
	 * @param int    $term_id Term id.
	 * @param string $meta_key Meta Key.
	 *
	 * @return void
	 */
	public function remove_term_meta( array $meta_id, int $term_id, string $meta_key ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( in_array( $meta_key, $this->exclude_meta_keys, true ) ) {
			return;
		}

		$site_id                              = get_current_blog_id();
		$this->delete_term_meta[ $site_id ][] = array(
			'post_id'  => $term_id,
			'meta_key' => $meta_key,
		);
	}

	/**
	 * Sync Term Meta.
	 *
	 * @param int    $meta_id Meta id.
	 * @param int    $term_id Term I'd.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @return void
	 */
	public function sync_term_meta( int $meta_id, int $term_id, string $meta_key, mixed $meta_value ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( in_array( $meta_key, $this->exclude_meta_keys, true ) ) {
			return;
		}

		$site_id                       = get_current_blog_id();
		$this->term_meta[ $site_id ][] = array(
			'term_id'    => $term_id,
			'meta_key'   => $meta_key,
			'meta_value' => $meta_value, // phpcs:ignore
		);
	}

	/**
	 * Sync Taxonomy.
	 *
	 * @param int    $term_id Term I'd.
	 * @param int    $tt_id Terms table id.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return void
	 */
	public function sync_taxonomy( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$exclude_taxonomies = alch_get_option( 'sync-taxonomy-types', array() );
		if ( ! in_array( $taxonomy, $exclude_taxonomies, true ) ) {
			return;
		}

		$site_id = get_current_blog_id();

		// Get the term data.
		$term = get_term( $term_id, $taxonomy );

		$this->terms[ $site_id ][] = $term;

		/**
		 * Add flags.
		 */
		update_term_meta( $term_id, 'acj_mongodb_sync_is_term_sync', true );
		update_term_meta( $term_id, 'acj_mongodb_sync_term_last_sync', time() );
		update_term_meta( $term_id, 'acj_mongodb_sync_post_sync_site_id', $site_id );
	}

	/**
	 * Remove Term.
	 *
	 * @param int    $term_id Term ID.
	 * @param int    $tt_id TERM TAXONOMY ID.
	 * @param string $taxonomy Taxonomy.
	 *
	 * @return void
	 */
	public function remove_taxonomy( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$exclude_taxonomies = alch_get_option( 'sync-taxonomy-types', array() );
		if ( ! in_array( $taxonomy, $exclude_taxonomies, true ) ) {
			return;
		}

		$site_id                          = get_current_blog_id();
		$this->terms_delete[ $site_id ][] = $term_id;

		delete_term_meta( $term_id, 'acj_mongodb_sync_is_post_sync' );
		delete_term_meta( $term_id, 'acj_mongodb_sync_post_last_sync' );
		delete_term_meta( $term_id, 'acj_mongodb_sync_post_sync_site_id' );
	}

	/**
	 * Sync process to Mongo db.
	 *
	 * @return void
	 */
	public function process_sync(): void {

		/**
		 * Process Term queue.
		 */
		if ( ! empty( $this->terms ) ) {
			$term_collection = $this->get_term_collection();
			foreach ( $this->terms as $site_id => $wp_terms ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $wp_terms as $mdb_term ) {
					if ( ! $mdb_term instanceof \WP_Term ) {
						continue;
					}

					$term_update = $term_collection->updateOne(
						array( 'ID' => $mdb_term->term_id ),
						array( '$set' => $mdb_term ),
						array( 'upsert' => true )
					);

					$upserted_id = $term_update->getUpsertedId();
					if ( ! empty( $upserted_id ) ) {
						update_term_meta( $mdb_term->term_id, 'acj_mongodb_sync_inserted_id', $upserted_id );
					}
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Process delete term queue.
		 */
		if ( ! empty( $this->terms_delete ) ) {
			$term_delete_collection = $this->get_term_collection();
			foreach ( $this->terms_delete as $site_id => $delete_terms ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $delete_terms as $delete_term ) {
					$term_delete_collection->deleteOne( array( 'ID' => $delete_term ) );
				}
				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Process Meta queue.
		 */
		if ( ! empty( $this->term_meta ) ) {
			$post_meta_collection = $this->get_term_meta_collection();
			foreach ( $this->term_meta as $site_id => $terms_meta ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $terms_meta as $term_meta ) {
					$post_meta_collection->updateOne(
						array(
							'term_id'  => $term_meta['term_id'],
							'meta_key' => $term_meta['meta_key'],
						),
						array( '$set' => $term_meta ),
						array( 'upsert' => true )
					);
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Delete term meta.
		 */
		if ( ! empty( $this->delete_term_meta ) ) {
			$delete_term_meta_collection = $this->get_term_meta_collection();
			foreach ( $this->delete_term_meta as $site_id => $delete_terms_meta ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $delete_terms_meta as $delete_term_meta ) {
					$delete_term_meta_collection->deleteMany( $delete_term_meta );
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}
	}
}
