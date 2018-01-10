<?php
/**
 * Scripts
 *
 * @package     EDD\ProductTaxes\Settings
 * @since       1.0.0
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Product tax settings
 *
 * Outputs the option to mark whether a product is exclusive of tax
 *
 * @since 1.0.0
 * @param int $post_id Download (Post) ID
 * @return void
 */
function edd_product_taxes_download_enable_custom_rates( $post_id = 0 ) {
	if( ! current_user_can( 'manage_shop_settings' ) || ! edd_use_taxes() ) {
		return;
	}

	$custom_rates = edd_download_custom_tax_rates_enabled( $post_id );
?>
	<p><strong><?php _e( 'Product Taxes:', 'edd-product-taxes' ); ?></strong></p>
	<label for="_edd_download_custom_tax_rates">
		<?php echo EDD()->html->checkbox( array(
			'name'    => '_edd_download_custom_tax_rates',
			'current' => $custom_rates
		) ); ?>
		<?php _e( 'Check this to apply custom tax rates', 'edd-product-taxes' ); ?>
	</label>
<?php
}
add_action( 'edd_meta_box_settings_fields', 'edd_product_taxes_download_enable_custom_rates', 25 );


/**
 * Add our fields above to the $fields save array
 *
 * @since 1.0.0
 * @return array $fields Array of fields.
 */
function edd_product_taxes_settings_metabox_fields_save( $fields ) {
  $fields[] = '_edd_download_custom_tax_rates';

  return $fields;
}
add_filter( 'edd_metabox_fields_save', 'edd_product_taxes_settings_metabox_fields_save', 10, 1 );


/**
 * Registers the subsection for EDD Settings
 *
 * @since       1.0.0
 * @param       array $sections The sections
 * @return      array Sections with commission fees added
 */
function edd_product_taxes_settings_section_extensions( $sections ) {
	$sections['product_taxes'] = __( 'Product Taxes', 'edd-product-taxes' );
	return $sections;
}
add_filter( 'edd_settings_sections_extensions', 'edd_product_taxes_settings_section_extensions' );


/**
 * Registers the new Commission Fees options in Extensions
 *
 * @since       1.0.0
 * @param       $settings array the existing plugin settings
 * @return      array The new EDD settings array with commissions added
 */
function edd_product_tax_settings_extensions( $settings ) {

	$product_tax_settings = array(
		array(
			'id'            => 'product_taxes_header',
			'name'          => '<strong>' . __( 'Product Tax Settings', 'edd-product-taxes' ) . '</strong>',
			'desc'          => '',
			'type'          => 'header',
			'size'          => 'regular',
		),
		array(
      'id'            => 'enable_product_taxes',
      'name'          => __( 'Enable Taxes', 'edd-product-taxes' ),
      'desc'          => __( 'Check this to enable product taxes on purchases.', 'edd-product-taxes' ),
      'type'          => 'checkbox',
      'tooltip_title' => __( 'Enabling Product Taxes', 'edd-product-taxes' ),
      'tooltip_desc'  => __( 'With product taxes enabled, Easy Digital Downloads will use the rules below to charge tax to customers. Taxes must be enabled in Easy Digital Downloads settings. Downloads must be marked to use custom rates.', 'edd-product-taxes' ),
		),
    array(
      'id'            => 'product_tax_rates',
      'name'          => '<strong>' . __( 'Tax Rates', 'edd-product-taxes' ) . '</strong>',
      'desc'          => __( 'Add tax rates for specific regions. Enter a percentage, such as 6.5 for 6.5%.', 'edd-product-taxes' ),
      'type'          => 'product_tax_rates',
    ),
    array(
      'id'            => 'product_tax_rate',
      'name'          => __( 'Fallback Tax Rate', 'edd-product-taxes' ),
      'desc'          => __( 'Customers not in a specific rate will be charged this tax rate. Enter a percentage, such as 6.5 for 6.5%.', 'edd-product-taxes' ),
      'type'          => 'text',
      'size'          => 'small',
      'tooltip_title' => __( 'Fallback Tax Rate', 'edd-product-taxes' ),
      'tooltip_desc'  => __( 'If the customer\'s address fails to meet the above tax rules, you can define a `default` tax rate to be applied to all other customers. Enter a percentage, such as 6.5 for 6.5%.', 'edd-product-taxes' ),
    ),
		array(
			'id' 						=> 'product_tax_categories',
			'name' 					=> __( 'Categories', 'edd-product-taxes' ),
			'desc' 					=> __( 'Select the categories that contain physical goods or services requiring custom tax rates.', 'edd-product-taxes' ),
			'type' 					=> 'multicheck',
			'options' 			=> edd_product_taxes_get_terms()
		),
		array(
			'id'            => 'enable_fee_taxes',
			'name'          => __( 'Apply Rates To Fees', 'edd-product-taxes' ),
			'desc'          => __( 'Check this to apply product tax rates to fees.', 'edd-product-taxes' ),
			'type'          => 'checkbox',
			'tooltip_title' => __( 'Fee Taxes', 'edd-product-taxes' ),
			'tooltip_desc'  => __( 'By default, Easy Digital Downloads will apply the default tax rates to fees such as domestic or international shipping. By checking this option, any associated fees will use the tax rates declared above.', 'edd-product-taxes' ),
		),
    array(
      'id'            => 'disable_integrations',
      'name'          => __( 'Disable Integrations', 'edd-product-taxes' ),
      'desc'          => __( 'Check this to disable automatic integration with Simple Shipping and Downloads As Services. By default If a download is marked as either, product tax rates will apply.', 'edd-product-taxes' ),
      'type'          => 'checkbox',
    ),
	);

	$product_tax_settings = apply_filters( 'edd_product_tax_settings', $product_tax_settings );

	if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
		$product_tax_settings = array( 'product_taxes' => $product_tax_settings );
	}

	return array_merge( $settings, $product_tax_settings );
}
add_filter( 'edd_settings_extensions', 'edd_product_tax_settings_extensions' );


/**
 * Registers the Commission settings (if active)
 *
 * @since       1.0.0
 * @param       $settings array the existing plugin settings
 * @return      array The new EDD settings array with commissions added
 */
if ( edd_product_taxes_commissions_active() ) :
function edd_product_tax_settings_commissions( $settings ) {

	$commissions_settings = array(
		array(
			'id'            => 'mitigate_tax_liability',
			'name'          => __( 'Mitigate Tax Liability', 'edd-product-taxes' ),
			'desc'          => __( 'Check this to pass the item tax amount onto the commission recipient.', 'edd-product-taxes' ),
			'type'          => 'checkbox',
			'tooltip_title' => __( 'Mitigating Tax Liabilities', 'edd-product-taxes' ),
			'tooltip_desc'  => __( 'By default, the store usually retains the entire or partial taxable amount on orders when doing a commission split. By checking this option, the full item tax amount will be passed to the vendor and subsumed within the commission amount. This is useful if you are operating a service marketplace and wish to offset tax liabilities. This only applies to Product Tax items.', 'edd-product-taxes' ),
		),
	);

	return array_merge( $settings, $commissions_settings );
}
add_filter( 'edd_product_tax_settings', 'edd_product_tax_settings_commissions', 10, 1 );
endif;


/**
 * Product Tax Rates Callback
 *
 * Renders product tax rates table
 *
 * @since 1.0.0
 * @param array $args Arguments passed by the setting
 * @return void
 */
function edd_product_tax_rates_callback( $args ) {

	$rates = edd_get_product_tax_rates();

	$class = edd_sanitize_html_class( $args['field_class'] );

	ob_start(); ?>
	<p><?php echo $args['desc']; ?></p>
	<table id="edd_tax_rates" class="wp-list-table widefat fixed posts <?php echo $class; ?>">
		<thead>
			<tr>
				<th scope="col" class="edd_tax_country"><?php _e( 'Country', 'edd-product-taxes' ); ?></th>
				<th scope="col" class="edd_tax_state"><?php _e( 'State / Province', 'edd-product-taxes' ); ?></th>
				<th scope="col" class="edd_tax_global"><?php _e( 'Country Wide', 'edd-product-taxes' ); ?></th>
				<th scope="col" class="edd_tax_rate"><?php _e( 'Rate', 'edd-product-taxes' ); ?><span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="<?php _e( '<strong>Regional tax rates: </strong>When a customer enters an address on checkout that matches the specified region for this tax rate, the cart tax will adjust automatically. Enter a percentage, such as 6.5 for 6.5%.' ); ?>"></span></th>
				<th scope="col"><?php _e( 'Remove', 'edd-product-taxes' ); ?></th>
			</tr>
		</thead>
		<?php if( ! empty( $rates ) ) : ?>
			<?php foreach( $rates as $key => $rate ) : ?>
			<tr>
				<td class="edd_tax_country">
					<?php
					echo EDD()->html->select( array(
						'options'          => edd_get_country_list(),
						'name'             => 'product_tax_rates[' . edd_sanitize_key( $key ) . '][country]',
						'selected'         => $rate['country'],
						'show_option_all'  => false,
						'show_option_none' => false,
						'class'            => 'edd-tax-country',
						'chosen'           => false,
						'placeholder'      => __( 'Choose a country', 'edd-product-taxes' )
					) );
					?>
				</td>
				<td class="edd_tax_state">
					<?php
					$states = edd_get_shop_states( $rate['country'] );
					if( ! empty( $states ) ) {
						echo EDD()->html->select( array(
							'options'          => $states,
							'name'             => 'product_tax_rates[' . edd_sanitize_key( $key ) . '][state]',
							'selected'         => $rate['state'],
							'show_option_all'  => false,
							'show_option_none' => false,
							'chosen'           => false,
							'placeholder'      => __( 'Choose a state', 'edd-product-taxes' )
						) );
					} else {
						echo EDD()->html->text( array(
							'name'  => 'product_tax_rates[' . edd_sanitize_key( $key ) . '][state]', $rate['state'],
							'value' => ! empty( $rate['state'] ) ? $rate['state'] : '',
						) );
					}
					?>
				</td>
				<td class="edd_tax_global">
					<input type="checkbox" name="product_tax_rates[<?php echo edd_sanitize_key( $key ); ?>][global]" id="product_tax_rates[<?php echo edd_sanitize_key( $key ); ?>][global]" value="1"<?php checked( true, ! empty( $rate['global'] ) ); ?>/>
					<label for="product_tax_rates[<?php echo edd_sanitize_key( $key ); ?>][global]"><?php _e( 'Apply to whole country', 'edd-product-taxes' ); ?></label>
				</td>
				<td class="edd_tax_rate"><input type="number" class="small-text" step="0.0001" min="0.0" max="99" name="product_tax_rates[<?php echo edd_sanitize_key( $key ); ?>][rate]" value="<?php echo esc_html( $rate['rate'] ); ?>"/></td>
				<td><span class="edd_remove_tax_rate button-secondary"><?php _e( 'Remove Rate', 'edd-product-taxes' ); ?></span></td>
			</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr>
				<td class="edd_tax_country">
					<?php
					echo EDD()->html->select( array(
						'options'          => edd_get_country_list(),
						'name'             => 'product_tax_rates[0][country]',
						'selected'         => '',
						'show_option_all'  => false,
						'show_option_none' => false,
						'class'            => 'edd-tax-country',
						'chosen'           => false,
						'placeholder'      => __( 'Choose a country', 'edd-product-taxes' )
					) ); ?>
				</td>
				<td class="edd_tax_state">
					<?php echo EDD()->html->text( array(
						'name' => 'product_tax_rates[0][state]'
					) ); ?>
				</td>
				<td class="edd_tax_global">
					<input type="checkbox" name="product_tax_rates[0][global]" value="1"/>
					<label for="product_tax_rates[0][global]"><?php _e( 'Apply to whole country', 'edd-product-taxes' ); ?></label>
				</td>
				<td class="edd_tax_rate"><input type="number" class="small-text" step="0.0001" min="0.0" name="product_tax_rates[0][rate]" value=""/></td>
				<td><span class="edd_remove_tax_rate button-secondary"><?php _e( 'Remove Rate', 'edd-product-taxes' ); ?></span></td>
			</tr>
		<?php endif; ?>
	</table>
	<p>
		<span class="button-secondary" id="edd_add_tax_rate"><?php _e( 'Add Tax Rate', 'edd-product-taxes' ); ?></span>
	</p>
	<?php
	echo ob_get_clean();
}


/**
 * Product Taxes Settings Sanitization
 *
 * Adds a settings error (for the updated message)
 * This also saves the tax rates table
 *
 * @since 1.6
 * @param array $input The value inputted in the field
 * @return string $input Sanitized value
 */
function edd_settings_sanitize_product_taxes( $input ) {

	if( ! current_user_can( 'manage_shop_settings' ) ) {
		return $input;
	}

	if( ! isset( $_POST['product_tax_rates'] ) ) {
		return $input;
	}

	$new_rates = ! empty( $_POST['product_tax_rates'] ) ? array_values( $_POST['product_tax_rates'] ) : array();

	update_option( 'edd_product_tax_rates', $new_rates );

	return $input;
}
add_filter( 'edd_settings_extensions_sanitize', 'edd_settings_sanitize_product_taxes' );
