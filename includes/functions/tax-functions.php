<?php
/**
 * Tax Functions
 *
 * @package     EDD\ProductTaxes\Tax-Functions
 * @since       1.0.0
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;


/**
 * Checks if product taxes are enabled by using the option set from the EDD Settings.
 * The value returned can be filtered.
 *
 * @since 1.0.0
 * @return bool Whether or not taxes are enabled
 */
function edd_use_product_taxes() {
	$ret = edd_get_option( 'enable_product_taxes', false );
	return (bool) apply_filters( 'edd_use_product_taxes', $ret );
}


/**
 * Checks if "Apply tax rates to fees" is enabled by using the option set from the EDD Settings.
 * The value returned can be filtered.
 *
 * @since 1.0.0
 * @return bool Whether or not fee taxes are enabled
 */
function edd_apply_fee_tax_rates() {
	$ret = edd_get_option( 'enable_fee_taxes', false );
	return (bool) apply_filters( 'edd_apply_fee_tax_rates', $ret );
}


/**
 * Checks if product taxes are enabled by using the option set from the EDD Settings.
 * The value returned can be filtered.
 *
 * @since 1.0.0
 * @return bool Whether or not taxes are enabled
 */
function edd_use_mitigate_tax_liability() {
	$ret = edd_get_option( 'mitigate_tax_liability', false );
	return (bool) apply_filters( 'edd_use_mitigate_tax_liability', $ret );
}


/**
 * Are custom tax rates enabled on this download?
 *
 * @since 1.0.0
 * @return bool
 */
function edd_download_custom_tax_rates_enabled( $download_id = 0 ) {
	$ret = (bool) get_post_meta( $download_id, '_edd_download_custom_tax_rates', true );
	return apply_filters( 'edd_download_custom_tax_rates_enabled', $ret, $download_id );
}


/**
 * Checks if "Disable Integrations" is checked by using the option set from the EDD Settings.
 * The value returned can be filtered.
 *
 * @since 1.0.0
 * @return bool Whether or not disable integrations is enabled
 */
function edd_product_taxes_integrations_disabled() {
	$ret = edd_get_option( 'disable_integrations', false );
	return (bool) apply_filters( 'edd_product_taxes_integrations_disabled', $ret );
}


/**
 * Retrieve product tax rates
 *
 * @since 1.0.0
 * @return array Defined product tax rates
 */
function edd_get_product_tax_rates() {
	$rates = get_option( 'edd_product_tax_rates', array() );
	return apply_filters( 'edd_get_product_tax_rates', $rates );
}


/**
 * Get product taxation rate
 *
 * @since 1.0.0
 * @param bool $country
 * @param bool $state
 * @return mixed|void
 */
function edd_get_product_tax_rate( $country = false, $state = false ) {
	$rate = (float) edd_get_option( 'product_tax_rate', 0 );

	$user_address = edd_get_customer_address();

	if( empty( $country ) ) {
		if( ! empty( $_POST['billing_country'] ) ) {
			$country = $_POST['billing_country'];
		} elseif( is_user_logged_in() && ! empty( $user_address['country'] ) ) {
			$country = $user_address['country'];
		}
		$country = ! empty( $country ) ? $country : edd_get_shop_country();
	}

	if( empty( $state ) ) {
		if( ! empty( $_POST['state'] ) ) {
			$state = $_POST['state'];
		} elseif( ! empty( $_POST['card_state'] ) ) {
			$state = $_POST['card_state'];
		} elseif( is_user_logged_in() && ! empty( $user_address['state'] ) ) {
			$state = $user_address['state'];
		}
		$state = ! empty( $state ) ? $state : edd_get_shop_state();
	}

	if( ! empty( $country ) ) {
		$tax_rates   = edd_get_product_tax_rates();

		if( ! empty( $tax_rates ) ) {

			// Locate the tax rate for this country / state, if it exists
			foreach( $tax_rates as $key => $tax_rate ) {

				if( $country != $tax_rate['country'] )
					continue;

				if( ! empty( $tax_rate['global'] ) ) {
					if( ! empty( $tax_rate['rate'] ) ) {
						$rate = number_format( $tax_rate['rate'], 4 );
					}
				} else {

					if( empty( $tax_rate['state'] ) || strtolower( $state ) != strtolower( $tax_rate['state'] ) ) {
						continue;
					}

					$state_rate = $tax_rate['rate'];
					if( 0 !== $state_rate || ! empty( $state_rate ) ) {
						$rate = number_format( $state_rate, 4 );
					}
				}
			}
		}
	}

	// Convert to a number we can use
	$rate = $rate / 100;

	return apply_filters( 'edd_get_product_tax_rate', $rate, $country, $state );
}


/**
 * Retrieve a fully formatted tax rate
 *
 * @since 1.0.0
 * @param string $country The country to retrieve a rate for
 * @param string $state The state to retrieve a rate for
 * @return string Formatted rate
 */
function edd_get_formatted_product_tax_rate( $country = false, $state = false ) {
	$rate = edd_get_product_tax_rate( $country, $state );
	$rate = round( $rate * 100, 4 );
	$formatted = $rate .= '%';
	return apply_filters( 'edd_get_formatted_product_tax_rate', $formatted, $rate, $country, $state );
}


/**
 * Retrieve the product tax rate (unformatted)
 *
 * @since 1.0.0
 * @param string $country The country to retrieve a rate for
 * @param string $state The state to retrieve a rate for
 * @return float The product tax rate
 */
function edd_get_unformatted_product_tax_rate( $country = false, $state = false ) {
	$rate = edd_get_product_tax_rate( $country, $state );
	return apply_filters( 'edd_get_product_tax_rate', $rate, $country, $state );
}


/**
 * Calculate the product taxed amount
 *
 * @since 1.0.0
 * @param $amount float The original amount to calculate a tax cost
 * @param $country string The country to calculate tax for. Will use default if not passed
 * @param $state string The state to calculate tax for. Will use default if not passed
 * @return float $tax Taxed amount
 */
function edd_calculate_product_tax( $amount = 0, $country = false, $state = false ) {
	$rate = edd_get_product_tax_rate( $country, $state );
	$tax  = 0.00;

	if ( edd_use_taxes() && $amount > 0 ) {

		if ( edd_prices_include_tax() ) {
			$pre_tax = ( $amount / ( 1 + $rate ) );
			$tax     = $amount - $pre_tax;
		} else {
			$tax = $amount * $rate;
		}

	}

	return apply_filters( 'edd_calculate_product_tax', $tax, $rate, $country, $state );
}
