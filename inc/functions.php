<?php
/**
 * Custom Functions.
 *
 * @package acj-mongodb-clone
 * @subpackage WordPress
 */

namespace Acj\Mongodb;

use MongoDB\DeleteResult;
use MongoDB\UpdateResult;

/**
 * Push data to mongodb table using primary key.
 *
 * @param string $mongo_table Table Name.
 * @param string $primary_key Primary key.
 * @param int    $primary_value Primary Value.
 * @param mixed  $data Data to be inserted.
 *
 * @return UpdateResult
 */
function acj_mongodb_push_data( string $mongo_table, string $primary_key, int $primary_value, mixed $data ): \MongoDB\UpdateResult {
	$collection = ( new Connector() )->get_custom_table_collection( $mongo_table );

	return $collection->updateOne(
		array( $primary_key => $primary_value ),
		array( '$set' => $data ),
		array( 'upsert' => true )
	);
}

/**
 * Delete data from the table using primary key.
 *
 * @param string $mongo_table Table Name.
 * @param string $primary_key Primary key.
 * @param int    $primary_value Primary Value.
 *
 * @return DeleteResult
 */
function acj_mongodb_delete_data( string $mongo_table, string $primary_key, int $primary_value ): DeleteResult {
	$collection = ( new Connector() )->get_custom_table_collection( $mongo_table );

	return $collection->deleteOne( array( $primary_key => $primary_value ) );
}
