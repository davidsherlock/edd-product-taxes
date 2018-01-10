<?php
/**
 * Helper Functions
 *
 * @package     EDD\ProductTaxes\Functions
 * @since       1.0.0
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;


/**
 * Does the download have Shipping enabled?
 *
 * @since 1.0.0
 * @return bool
 */
function edd_download_has_shipping_enabled( $download_id = 0 ) {
	$ret = (bool) get_post_meta( $download_id, '_edd_enable_shipping', true );
	return apply_filters( 'edd_download_has_shipping_enabled', $ret, $download_id );
}


/**
 * Does the download have Download As Services enabled?
 *
 * @since 1.0.0
 * @return bool
 */
function edd_download_has_das_enabled( $download_id = 0 ) {
	$ret = (bool) get_post_meta( $download_id, '_edd_das_enabled', true );
	return apply_filters( 'edd_download_has_das_enabled', $ret, $download_id );
}


/**
 * Is "Downloads As Services" or "Simple Shipping" active?
 *
 * @since 1.0.0
 * @return bool
 */
function edd_product_taxes_integrations_active() {
	return ( class_exists( 'EDD_Downloads_As_Services' ) || class_exists( 'EDD_Simple_Shipping' ) ) ;
}


/**
 * Is "Commissions" active?
 *
 * @since 1.0.0
 * @return bool
 */
function edd_product_taxes_commissions_active() {
	return class_exists( 'EDDC' );
}

/**
 * Is service
 * @param  int  $item_id ID of download
 * @return boolean true if service, false otherwise
 * @return boolean
 */
function edd_download_is_service( $download_id = 0 ) {
	global $edd_options;

	// get array of service categories
	$service_categories = isset( $edd_options['edd_das_service_categories'] ) ? $edd_options['edd_das_service_categories'] : '';

	$term_ids = array();

	if ( $service_categories ) {
		foreach ( $service_categories as $term_id => $term_name ) {
			$term_ids[] = $term_id;
		}
	}

	$is_service = edd_download_has_das_enabled( $download_id );

	// check if download has meta key or has a service term assigned to it
	if ( $is_service || ( ! empty( $term_ids ) && has_term( $term_ids, 'download_category', $download_id ) ) ) {
		return true;
	}

	return false;
}


/**
 * Is service
 * @param  int  $item_id ID of download
 * @return boolean true if service, false otherwise
 * @return boolean
 */
function edd_download_has_product_taxes( $download_id = 0 ) {
	global $edd_options;

	// Get array of product tax categories
	$product_tax_categories = isset( $edd_options['product_tax_categories'] ) ? $edd_options['product_tax_categories'] : '';

	$term_ids = array();

	if ( $product_tax_categories ) {
		foreach ( $product_tax_categories as $term_id => $term_name ) {
			$term_ids[] = $term_id;
		}
	}

	$download_custom_tax_rates = edd_download_custom_tax_rates_enabled( $download_id );

	// check if download has meta key or has a service term assigned to it
	if ( $download_custom_tax_rates || ( ! empty( $term_ids ) && has_term( $term_ids, 'download_category', $download_id ) ) ) {
		return true;
	}

	return false;
}


/**
 * Get download categories (terms)
 *
 * @since 1.0.0
 * @return array
 */
function edd_product_taxes_get_terms() {
	$args = array(
		'hide_empty'		=> false,
		'hierarchical'	=> false
	);

	$terms = get_terms( 'download_category', apply_filters( 'edd_product_taxes_get_terms', $args ) );

	$terms_array = array();

	foreach ( $terms as $term ) {
		$term_id = $term->term_id;
		$term_name = $term->name;

		$terms_array[$term_id] = $term_name;
	}

	if ( $terms )
		return $terms_array;

	return false;
}
