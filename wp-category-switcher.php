<?php
/*
Plugin Name: WP Category Switcher
Plugin URI: http://www.meow.fr/wp-category-switcher
Description: Re-order categories by switching theirs ID's
Version: 0.1.0
Author: Jordy Meow
Author URI: http://www.meow.fr

Dual licensed under the MIT and GPL licenses:
http://www.opensource.org/licenses/mit-license.php
http://www.gnu.org/licenses/gpl.html

Originally developed for two of my websites: 
- Totoro Times (http://www.totorotimes.com) 
- Haikyo (http://www.haikyo.org)
*/

add_action( 'admin_menu', 'wcsr_admin_menu' );

require( 'jordy_meow_footer.php' );

function wcsr_admin_menu() {
	load_plugin_textdomain( 'wp-category-switcher', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	add_management_page( 'Category Switcher', 'Category Switcher', 'manage_options', 'wp-category-switcher', 'wcsr_screen' );
}

// Switch the categories A and B
function wcsr_switch( $a, $b ) {
	global $wpdb;
	$tmp = $wpdb->get_var( "SELECT MAX(term_id) as t FROM $wpdb->terms" ) + 1;

	// Thanks Roman for your post! :) (http://www.roman-kaeppeler.de/wordpress/change-category-id)

	// Update wp_terms
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->terms SET term_id = %d WHERE term_id = %d", $tmp, $a ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->terms SET term_id = %d WHERE term_id = %d", $a, $b ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->terms SET term_id = %d WHERE term_id = %d", $b, $tmp ) );

	// Update wp_term_taxonomy -> term_id
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_taxonomy SET term_id = %d WHERE term_id = %d", $tmp, $a ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_taxonomy SET term_id = %d WHERE term_id = %d", $a, $b ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_taxonomy SET term_id = %d WHERE term_id = %d", $b, $tmp ) );

	// Update wp_term_taxonomy -> term_taxonomy_id
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_taxonomy SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d", $tmp, $a ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_taxonomy SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d", $a, $b ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_taxonomy SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d", $b, $tmp ) );

	// Update wp_term_relationships -> term_taxonomy_id
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_relationships SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d", $tmp, $a ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_relationships SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d", $a, $b ) );
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_relationships SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d", $b, $tmp ) );

	if ( function_exists('icl_object_id') ) {
		// Update wp_icl_translations -> term_taxonomy_id
		$tmp = $wpdb->get_var( "SELECT MAX(element_id) as t FROM wp_icl_translations" ) + 1;
		$wpdb->query( $wpdb->prepare( "UPDATE wp_icl_translations SET element_id = %d WHERE element_type = 'tax_category' AND element_id = %d", $tmp, $a ) );
		$wpdb->query( $wpdb->prepare( "UPDATE wp_icl_translations SET element_id = %d WHERE element_type = 'tax_category' AND element_id = %d", $a, $b ) );
		$wpdb->query( $wpdb->prepare( "UPDATE wp_icl_translations SET element_id = %d WHERE element_type = 'tax_category' AND element_id = %d", $b, $tmp ) );
	}
}

function wcsr_check_notices() {
	if ( isset( $_GET['category_a'] ) && isset( $_GET['category_b'] ) ) {

		$a = $_GET['category_a'];
		$b = $_GET['category_b'];

		if ( $a == $b ) {
			echo '<div class="error"><p>' . __( 'The categories are both the same. Mmm. Please be careful!', 'wp-category-switcher' ) . '</p></div>';
			return;
		}

		wcsr_switch( $a, $b );
		return;

	}
}

function wcsr_screen() {
	?>
	<div class='wrap'>
		<?php jordy_meow_donation(); ?>
		<div id="icon-tools" class="icon32"><br></div>
		<h2>WP Category Switcher</h2>

		<p><?php _e( "This plugin switches category 2 by 2. Doing that, you can change the way WordPress picks the default category. This is particularly useful when a post belongs to 2 or more categories. This plugin modifies directly the ID's, which means you can uninstall it when you are done.", 'wp-category-switcher' ); ?>
		<b style='color: red;'>
		<?php _e( "Please backup your database before switching.", 'wp-category-switcher' ); ?>
		</b></p>

		<?php if ( function_exists('icl_object_id') ): ?>
			<div class="error"><p>
				<?php _e( 'The plugin has not been properly tested with WPML yet. You can <b>try</b>, but proper backup are more than mandatory!', 'wp-category-switcher' ); ?>
			</p></div>
		<?php endif; ?>

		<?php wcsr_check_notices(); ?>
		<h3><?php _e( 'Categories (ordered by id\'s)', 'wp-category-switcher' ); ?></h3>
		<ul>
		<?php 
			global $wpdb;
			$categories = $wpdb->get_results("
				SELECT x.term_id as term_id, name as name
				FROM wp_terms t, wp_term_taxonomy x
				WHERE t.term_id = x.term_id
				AND x.taxonomy =  'category'
				ORDER BY t.term_id
			", OBJECT );
			$order = 1;
			foreach ( $categories as $category ) {
				echo "<li>" . $order++ . ": <b>" . $category->name . "</b> (ID=" . $category->term_id . ")</li>";
			}
		?>
		</ul>

		<form action='tools.php' method="get">
			<?php _e( 'Switch:', 'wp-category-switcher' ); ?>
			<select name='category_a'>
			<?php 
				foreach ( $categories as $category ) {
					echo "<option value='" . $category->term_id . "'><b>" . $category->name . "</b></option>";
				}
			?>	
			</select>
			<input type='hidden' name='page' value='wp-category-switcher'>
			<select name='category_b'>
			<?php 
				foreach ( $categories as $category ) {
					echo "<option value='" . $category->term_id . "'>" . $category->name . "</option>";
				}
			?>	
			</select>
			<input type='submit' value="OK" class='button-primary' />
		</form>
	</div>
	<?php
	jordy_meow_footer();
}

