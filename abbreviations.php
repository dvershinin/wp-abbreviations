<?php
/**
 * Abbreviations Plugin
 *
 * @package     Abbreviations
 * @author      Danila Vershinin
 * @license     GPLv2
 *
 * @wordpress-plugin
 * Plugin Name: Abbreviations
 * Description: Wrap abbreviations for search engine optimization and support other applications
 * Version: 1.6.1
 * Author: Danila Vershinin
 * Author URI: https://github.com/dvershinin
 * Requires at least: 4.6
 * Requires PHP: 7.0
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Cache key for storing abbreviations.
define( 'ABBREVIATIONS_CACHE_KEY', 'abbreviations_processed' );
define( 'ABBREVIATIONS_CACHE_GROUP', 'abbreviations' );

/**
 * Get abbreviations from cache or database.
 *
 * Uses WordPress object cache for optimal performance.
 *
 * @return array Array of abbreviations with their expansions.
 */
function abbreviations_get_cached() {
	$abbreviations = wp_cache_get( ABBREVIATIONS_CACHE_KEY, ABBREVIATIONS_CACHE_GROUP );
	if ( false === $abbreviations ) {
		$abbreviations = get_option( 'abbreviations', array() );
		wp_cache_set( ABBREVIATIONS_CACHE_KEY, $abbreviations, ABBREVIATIONS_CACHE_GROUP );
	}
	return $abbreviations;
}

/**
 * Invalidate the abbreviations cache when settings are updated.
 *
 * @param mixed $old_value The old option value (unused, required by hook signature).
 * @param mixed $value     The new option value (unused, required by hook signature).
 */
function abbreviations_invalidate_cache( $old_value, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	wp_cache_delete( ABBREVIATIONS_CACHE_KEY, ABBREVIATIONS_CACHE_GROUP );
}
add_action( 'update_option_abbreviations', 'abbreviations_invalidate_cache', 10, 2 );

/**
 * Process content and wrap abbreviations in <abbr> tags.
 *
 * Uses a single combined regex pattern to match ALL abbreviations in one pass,
 * providing O(1) performance regardless of the number of abbreviations defined.
 *
 * @param string $content The post content.
 * @return string Modified content with abbreviations wrapped.
 */
function abbreviations_process_content( $content ) {
	// Early bail-out conditions for performance.
	if ( empty( $content ) ) {
		return $content;
	}

	$abbreviations = abbreviations_get_cached();
	if ( empty( $abbreviations ) ) {
		return $content;
	}

	// Build a lookup map: abbreviation => [title, lang].
	$abbr_map = array();
	foreach ( $abbreviations as $abbr ) {
		$abbr_map[ $abbr[0] ] = array(
			'title' => $abbr[1],
			'lang'  => isset( $abbr[2] ) ? $abbr[2] : '',
		);
	}

	// Sort abbreviations by length (longest first) to match longer terms first.
	$abbr_keys = array_keys( $abbr_map );
	usort(
		$abbr_keys,
		function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}
	);

	// Build a single regex pattern with alternation for all abbreviations.
	// Using word boundaries and negative lookbehind/lookahead to avoid existing <abbr> tags.
	$escaped_abbrs = array_map( 'preg_quote', $abbr_keys );
	$pattern       = '/((?:^|[\s>]))(' . implode( '|', $escaped_abbrs ) . ')((?:[\s><.,:;!?]|$))/s';

	// Track which abbreviations have been replaced (first occurrence only).
	$replaced = array();

	$content = preg_replace_callback(
		$pattern,
		function ( $matches ) use ( $abbr_map, &$replaced ) {
			$prefix = $matches[1];
			$abbr   = $matches[2];
			$suffix = $matches[3];

			// Skip if already inside an abbr tag (check for closing tag after).
			// Skip if this abbreviation was already replaced.
			if ( isset( $replaced[ $abbr ] ) ) {
				return $matches[0];
			}

			// Mark as replaced.
			$replaced[ $abbr ] = true;

			$title = esc_attr( $abbr_map[ $abbr ]['title'] );
			$lang  = $abbr_map[ $abbr ]['lang'];

			$lang_attr = '';
			if ( '' !== $lang ) {
				$lang_attr = ' lang="' . esc_attr( $lang ) . '"';
			}

			return $prefix . '<abbr class="nocode" title="' . $title . '"' . $lang_attr . '>' . esc_html( $abbr ) . '</abbr>' . $suffix;
		},
		$content
	);

	return $content;
}
add_filter( 'the_content', 'abbreviations_process_content' );

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Abbreviations',
			'Abbreviations',
			'manage_options',
			'abbreviations',
			function () {
				// check user capabilities
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				// show error/update messages
				settings_errors( 'abbreviations' );
				?>
		<div class="wrap">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
					<?php settings_fields( 'abbreviations' ); ?>
				<p>
					An abbreviation like <abbr title="For Your Information">FYI</abbr> is a shortened form of a word
					or phrase, by any method.
					This plugin adds <code>abbr</code>eviation HTML tag which creates meaningful abbreviation expansion
					and provides useful information to the browsers, translation systems, and search-engines.<br />
					The optional global attribute can be specified in case it differs from the language of the entire
					page.
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="new_abbr_short">Abbreviation <small>(required)</small></label></th>
						<th><label for="new_abbr_long">Description <small>(required)</small></th>
						<th title="Specify a language code for the content f.i. nl, de or en: ">
							<label for="new_abbr_lang">Language code <small>(optional)</small></label>
						</th>
					</tr>
						<?php
						// Get the value of the setting we've registered with register_setting()
						$abbreviations = get_option( 'abbreviations', array() );
						$i             = 0;
						foreach ( $abbreviations as $abbr ) {
							?>
						<tr>
							<td>
									<input required="required"  type="text" name="abbreviations[<?php echo esc_html( $i ); ?>][]" value="<?php echo esc_html( $abbr[0] ); ?>" placeholder="Put short text here..." class="large-text" />
							</td>
							<td>
									<input required="required" type="text" name="abbreviations[<?php echo esc_html( $i ); ?>][]" value="<?php echo esc_html( $abbr[1] ); ?>" placeholder="Put long text here..." class="large-text" />
							</td>
							<td>
									<input type="text" name="abbreviations[<?php echo esc_html( $i ); ?>][]" value="<?php echo esc_html( $abbr[2] ); ?>" placeholder="Specify language code, e.g.: en for English" class="large-text" />
							</td>
						</tr>
							<?php
							++$i;
						}
						?>
					<tr>
						<td>
								<input id="new_abbr_short" type="text" name="abbreviations[<?php echo esc_html( $i ); ?>][]" placeholder="Put short text here..." class="large-text"/>
						</td>
						<td>
								<input id="new_abbr_long" type="text" name="abbreviations[<?php echo esc_html( $i ); ?>][]" placeholder="Put long text here..." class="large-text"/>
						</td>
						<td>
								<input id="new_abbr_lang" type="text" name="abbreviations[<?php echo esc_html( $i ); ?>][]" placeholder="Specify language code, e.g.: en for English" class="large-text"/>
						</td>
					</tr>
				</table>
					<?php submit_button( null, 'primary', 'save_abbreviations_action' ); ?>
			</form>
		</div>
				<?php
			}
		);
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $actions ) {
		$links   = array(
			'<a href="' . admin_url( 'options-general.php?page=abbreviations' ) . '">' . __( 'Settings', 'abbreviations' ) . '</a>',
		);
		$actions = array_merge( $actions, $links );
		return $actions;
	}
);

add_action(
	'admin_init',
	function () {
		$args = array(
			'type'              => 'array',
			'sanitize_callback' => function ( $value ) {
				$res = array();
				$all_abbrs = array();
				foreach ( $value as $item ) {
					$abbr = sanitize_text_field( $item[0] );
					$desc = sanitize_text_field( $item[1] );
					$lang = sanitize_text_field( $item[2] );
					if ( $abbr && $desc && ! in_array( $abbr, $all_abbrs, true ) ) {
						$res[] = array( $abbr, $desc, $lang );
						$all_abbrs[] = $abbr;
					}
				}
				return $res;
			},
			'default'           => array(),
		);

		register_setting( 'abbreviations', 'abbreviations', $args );

		// Register a new section in the "abbreviations" page.
		add_settings_section(
			'abbreviations',
			'',
			function () {
				return 'Sexy';
			},
			'abbreviations'
		);

		add_settings_field(
			'abbreviations_field', // As of WP 4.6 this value is used only internally.
			null,
			// output function is none, we rely on form data due to 3 column headers impossible to print via WP API
			null,
			'abbreviations',
			'abbreviations'
		);
	}
);
