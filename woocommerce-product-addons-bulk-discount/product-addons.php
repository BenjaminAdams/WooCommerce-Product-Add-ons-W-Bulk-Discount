<?php
/*
Plugin Name: WooCommerce Product Add-ons /w Bulk Discount
Plugin URI: https://github.com/BenjaminAdams/WooCommerce-Product-Add-ons-W-Bulk-Discount
Description: WooCommerce Product Add-ons lets you add extra options to products which the user can select. Add-ons can be checkboxes, a select box, or custom input. Each option can optionally be given a price which is added to the cost of the product.  Also adds a bulk discount when you order one or more of that add-on
Version: 2.3.0
Author: Benjamin Adams
Author URI: https://github.com/BenjaminAdams
Requires at least: 3.1
Tested up to: 3.2

*/

/**
 * Required functions
 */
//if ( ! function_exists( 'woothemes_queue_update' ) )
	//require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
//woothemes_queue_update( plugin_basename( __FILE__ ), '147d0077e591e16db9d0d67daeb8c484', '18618' );

if ( is_woocommerce_active() ) {

	/**
	 * Localisation
	 */
	load_plugin_textdomain( 'wc_product_addons', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/**
	 * Init
	 */
	if ( ! class_exists( 'Product_Addon_Display' ) ) {

		if ( is_admin() ) {

			include_once( 'admin/class-product-addon-admin.php' );

			$GLOBALS['Product_Addon_Admin'] = new Product_Addon_Admin();

		}

		include_once( 'classes/class-product-addon-display.php' );

		$GLOBALS['Product_Addon_Display'] = new Product_Addon_Display();

		include_once( 'classes/class-product-addon-cart.php' );

		$GLOBALS['Product_Addon_Cart'] = new Product_Addon_Cart();
	}

	/**
	 * Gets addons assigned to a product by ID
	 *
	 * @param  int $post_id ID of the product to get addons for
	 * @return array array of addons
	 */
	function get_product_addons( $post_id, $prefix = false ) {
		if ( ! $post_id )
			return array();

		$addons            = array();
		$raw_addons        = array();
		$product_terms     = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
		
		$exclude			= get_post_meta( $post_id, '_product_addons_exclude_global', TRUE );

		// Product level addons
		$raw_addons[10][0] = array_filter( (array) get_post_meta( $post_id, '_product_addons', true ) );

		if ( !isset($exclude) || $exclude != '1' ) :
		
			// Global level addons (all products)
			$args = array(
				'posts_per_page'   => -1,
				'orderby'          => 'meta_value',
				'order'            => 'ASC',
				'meta_key'         => '_priority',
				'post_type'        => 'global_product_addon',
				'post_status'      => 'publish',
				'suppress_filters' => true,
				'meta_query' => array(
					array(
						'key'   => '_all_products',
						'value' => '1',
					)
				)
			);

			$global_addons = get_posts( $args );

			if ( $global_addons ) :
				foreach ( $global_addons as $global_addon ) {
					$priority = get_post_meta( $global_addon->ID, '_priority', true );
					$raw_addons[ $priority ][ $global_addon->ID ] = array_filter( (array) get_post_meta( $global_addon->ID, '_product_addons', true ) );
				}
			endif;

			// Global level addons (categories)
			if ( $product_terms ) :
				$args = array(
					'posts_per_page'   => -1,
					'orderby'          => 'meta_value',
					'order'            => 'ASC',
					'meta_key'         => '_priority',
					'post_type'        => 'global_product_addon',
					'post_status'      => 'publish',
					'suppress_filters' => true,
					'tax_query' => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'id',
							'terms'    => $product_terms,
							'include_children' => false
						)
					)
				);

				$global_addons = get_posts( $args );

				if ( $global_addons ) :
					foreach ( $global_addons as $global_addon ) {
						$priority = get_post_meta( $global_addon->ID, '_priority', true );
						$raw_addons[ $priority ][ $global_addon->ID ] = array_filter( (array) get_post_meta( $global_addon->ID, '_product_addons', true ) );
					}
				endif;
			endif;
			
		endif; // exclude from global addons

		ksort( $raw_addons );

		foreach ( $raw_addons as $addon_group ) {
			if ( $addon_group ) {
				foreach ( $addon_group as $addon )
					$addons = array_merge( $addons, $addon );
			}
		}

		// Generate field names with unqiue prefixes
		if ( ! $prefix )
			$prefix = apply_filters( 'product_addons_field_prefix', "{$post_id}-", $post_id );

		foreach ( $addons as $addon_key => $addon ) {
			$addons[ $addon_key ]['field-name'] = $prefix . $addon['name'];
		}

		return $addons;
	}

	/**
	 * Register post types for global addons
	 */
	function product_addons_post_types() {
		register_post_type( "global_product_addon",
			array(
				'public' 				=> false,
				'show_ui' 				=> false,
				'capability_type' 		=> 'product',
				'map_meta_cap'			=> true,
				'publicly_queryable' 	=> false,
				'exclude_from_search' 	=> true,
				'hierarchical' 			=> false,
				'rewrite' 				=> false,
				'query_var'				=> false,
				'supports' 				=> array( 'title' ),
				'show_in_nav_menus' 	=> false
			)
		);

		register_taxonomy_for_object_type( 'product_cat', 'global_product_addon' );
	}

	add_action( 'init', 'product_addons_post_types', 20 );
}