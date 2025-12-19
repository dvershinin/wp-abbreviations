<?php
/*
Plugin Name: Abbreviations
Description: Wrap abbreviations for search engine optimization and support other applications
Version: 1.5
Author: Danila Vershinin
Author URI: https://github.com/dvershinin
Regex101: https://regex101.com/r/aWv95u/1
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

add_filter('the_content', function($content) {
    $abbreviations = get_option('abbreviations', []);
    foreach ($abbreviations as $abbr) {
        $lang = ($abbr[2] == '') ? '' : 'lang="' . esc_attr( $abbr[2] ) . '"';
        $content = preg_replace(
            '/((?!<abbr.*>)(?:^|\s+|>))' . $abbr[0] . '((?:[.,>]|$|\s+)(?!<\/abbr>))/s',
            '$1<abbr class="nocode" title="' . esc_attr( $abbr[1] ) . '" ' . $lang . '>' . esc_html( $abbr[0] ) . '</abbr>$2',
            $content, 1);
    }
    return $content;
});

add_action('admin_menu', function() {
    add_options_page('Abbreviations', 'Abbreviations', 'manage_options', 'abbreviations',
        function() {
            // check user capabilities
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            // show error/update messages
            settings_errors( 'abbreviations' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form method="post" action="<?php echo admin_url('options.php'); ?>">
                <?php settings_fields('abbreviations'); ?>
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
                    $abbreviations = get_option( 'abbreviations', [] );
                    $i = 0;
                    foreach ($abbreviations as $abbr) { ?>
                        <tr>
                            <td>
                                <input required="required"  type="text" name="abbreviations[<?php echo esc_html($i); ?>][]" value="<?php echo esc_html($abbr[0]); ?>" placeholder="Put short text here..." class="large-text" />
                            </td>
                            <td>
                                <input required="required" type="text" name="abbreviations[<?php echo esc_html($i); ?>][]" value="<?php echo esc_html($abbr[1]); ?>" placeholder="Put long text here..." class="large-text" />
                            </td>
                            <td>
                                <input type="text" name="abbreviations[<?php echo esc_html($i); ?>][]" value="<?php echo esc_html($abbr[2]); ?>" placeholder="Specify language code, e.g.: en for English" class="large-text" />
                            </td>
                        </tr>
                        <?php
                        $i = $i + 1;
                    } ?>
                    <tr>
                        <td>
                            <input id="new_abbr_short" type="text" name="abbreviations[<?php echo esc_html($i); ?>][]" placeholder="Put short text here..." class="large-text"/>
                        </td>
                        <td>
                            <input id="new_abbr_long" type="text" name="abbreviations[<?php echo esc_html($i); ?>][]" placeholder="Put long text here..." class="large-text"/>
                        </td>
                        <td>
                            <input id="new_abbr_lang" type="text" name="abbreviations[<?php echo esc_html($i); ?>][]" placeholder="Specify language code, e.g.: en for English" class="large-text"/>
                        </td>
                    </tr>
                </table>
                <?php submit_button(null, 'primary', 'save_abbreviations_action'); ?>
            </form>
        </div>
        <?php
    });
});

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), function($actions) {
    $links = array(
        '<a href="' . admin_url('options-general.php?page=abbreviations') . '">' . __('Settings', 'abbreviations') . '</a>',
    );
    $actions = array_merge( $actions, $links );
    return $actions;
} );

add_action( 'admin_init', function() {
    $args = array(
        'type' => 'array',
        'sanitize_callback' => function($value) {
            $res = [];
            $all_abbrs = [];
            foreach ($value as $item) {
                $abbr = sanitize_text_field($item[0]);
                $desc = sanitize_text_field($item[1]);
                $lang = sanitize_text_field($item[2]);
                if ($abbr && $desc && !in_array($abbr, $all_abbrs)) {
                    $res[] = [$abbr, $desc, $lang];
                    $all_abbrs[] = $abbr;
                }
            }
            return $res;
        },
        'default' => [],
    );

    register_setting( 'abbreviations', 'abbreviations', $args );

    // Register a new section in the "abbreviations" page.
    add_settings_section(
        'abbreviations',
        '',
        function() {
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
});
