<?php

/**
 * Plugin Name: PostPeek
 * Description: PostPeek: Immediate Post Insights, a convenient plugin that adds a direct link to search console stats for each post.
 * Version: 1.1.3
 * Tested up to: 6.3
 * Author: Jlil
 * Author URI: https://jlil.net
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('POSTPEEK__FILE__', __FILE__);

require_once 'includes/PostPeek_AdminNotices.php';


// Define the main plugin class.
class PostPeek_SearchConsoleLink
{

    public function __construct()
    {
        // Add the plugin's hooks and filters.
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Add filters for "post", custom post types, and "page" post types.
        add_filter('post_row_actions', array($this, 'add_search_console_link'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_search_console_link'), 10, 2);
        // Add Admin Menu
        add_action('admin_menu', array($this, 'postpeek_options_page'));
        add_action('admin_init', array($this, 'postpeek_settings_init'));

        // Hook the function into the 'admin_bar_menu' action
        add_action('admin_bar_menu', array($this, 'add_admin_bar_search_console_link'), 999);
    }


    public function add_admin_bar_search_console_link($wp_admin_bar)
    {
        // Check if the user has the necessary capabilities.
        if (!$this->user_has_capability()) {
            return;
        }

        // Fetch plugin options
        $options = get_option('postpeek_options', array());

        // Determine account type
        $site_type = isset($options['site_type']) ? $options['site_type'] : 'url_prefix';

        // Create the resource identifier based on the account type
        $home_url = home_url(); // Get the full URL
        $parsed_url = parse_url($home_url); // Parse the URL
        $host = $parsed_url['host']; // Extract the host component

        // When using [domain_property], must extract only the host, [example.com], not [https://example.com]
        $resource_id = ($site_type === 'domain_property') ? "sc-domain:$host" : home_url();

        // Get default_date_period from options, or set it to 7 days if not set
        $default_date_period = isset($options['default_date_period']) ? $options['default_date_period'] : 'num_of_days=7';

        // Define the link based on whether the user is in the admin area or the frontend
        // Determine the URL to point to based on the current page context
        $home_url = esc_url($home_url);
        $home_url_with_slash = trailingslashit($home_url);

        if (is_admin()) {
            $page_url = '&page=!' . $home_url_with_slash;
        } elseif (is_home() || is_front_page() || is_search()) {
            $page_url = '&page=!' . $home_url_with_slash;
        } elseif (is_single() || is_page()) {
            $page_url = '&page=!' . esc_url(get_permalink());
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            $page_url = '&page=!' . esc_url(get_term_link($term));
        } else {
            $page_url = '&page=!' . $home_url_with_slash; // Fallback to home URL
        }

        // Create the complete URL
        $search_console_url = "https://search.google.com/search-console/performance/search-analytics?resource_id=" . $resource_id . $page_url . "&" . esc_attr($default_date_period);

        // Add the Search Console link to the admin bar
        $args = array(
            'id' => 'search_console',
            'title' => 'Search Console',
            'href' => $search_console_url,
            'meta' => array(
                'target' => '_blank',
                'class' => 'google_link'
            )
        );
        $wp_admin_bar->add_node($args);
    }






    public function postpeek_settings_init()
    {
        // register a new setting for "postpeek" page
        register_setting('postpeek_options', 'postpeek_options');

        // register a new section in the "postpeek" page
        add_settings_section(
            'postpeek_section',
            'Settings',
            array($this, 'postpeek_section_callback'),
            'postpeek'
        );

        add_settings_field(
            'postpeek_site_type',
            'Search Console Site Type',
            array($this, 'postpeek_site_type_render'),
            'postpeek',
            'postpeek_section'
        );

        add_settings_field(
            'postpeek_allowed_roles',
            'Allowed User Roles',
            array($this, 'postpeek_allowed_roles_render'),
            'postpeek',
            'postpeek_section'
        );

        add_settings_field(
            'postpeek_default_date_period',
            'Default Date Period',
            array($this, 'postpeek_default_date_period_render'),
            'postpeek',
            'postpeek_section'
        );



        // Add other fields here...
    }

    function postpeek_section_callback()
    {
        // This can go inside your function that displays the settings page.
        update_option('postpeek_settings_visited', true);
    }


    public function postpeek_allowed_roles_render()
    {
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        $editable_roles = apply_filters('editable_roles', $all_roles);

        $options = get_option('postpeek_options', array());
        $selected_roles = isset($options['allowed_roles']) ? (array) $options['allowed_roles'] : array();

        foreach ($editable_roles as $role => $details) {
            // check if the 'edit_posts' capability exists for the role.
            // only roles with the capability to edit posts are shown in the options
            if (array_key_exists('edit_posts', $details['capabilities']) && $details['capabilities']['edit_posts']) {
                $name = translate_user_role($details['name']);
?>
                <input type="checkbox" name="postpeek_options[allowed_roles][]" value="<?php echo esc_attr($role); ?>" <?php checked(in_array($role, $selected_roles)); ?> />
                <?php echo esc_html($name); ?><br />
        <?php
            }
        }
    }


    public function DISABLED_postpeek_allowed_roles_render()
    {
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        $editable_roles = apply_filters('editable_roles', $all_roles);

        $options = get_option('postpeek_options', array());
        $selected_roles = isset($options['allowed_roles']) ? (array) $options['allowed_roles'] : array();

        ?>
        <select multiple name="postpeek_options[allowed_roles][]">
            <?php
            foreach ($editable_roles as $role => $details) {
                // check if the 'edit_posts' capability exists for the role.
                // only roles with the capability to edit posts are shown in the options
                if (array_key_exists('edit_posts', $details['capabilities']) && $details['capabilities']['edit_posts']) {
                    $name = translate_user_role($details['name']);
                    echo "<option value='" . esc_attr($role) . "' " . selected(in_array($role, $selected_roles), true, false) . ">" . esc_html($name) . "</option>";
                }
            }
            ?>
        </select>
    <?php
    }
    public function postpeek_site_type_render()
    {
        $options = get_option('postpeek_options', array());

        $site_type = isset($options['site_type']) ? $options['site_type'] : 'default_value_here';

    ?>
        <select name="postpeek_options[site_type]">
            <option value="url_prefix" <?php selected($site_type, 'url_prefix'); ?>>URL Prefix</option>
            <option value="domain_property" <?php selected($site_type, 'domain_property'); ?>>Domain Property</option>
        </select>
        <a href="https://support.google.com/webmasters/answer/34592" target="_blank" title="Read more about URL Prefix vs Domain Property">
            <span class="dashicons dashicons-editor-help"></span>
        </a>
    <?php
    }




    public function postpeek_options_page()
    {
        // add_menu_page(
        add_options_page(
            'PostPeek Settings',
            'PostPeek',
            'manage_options',
            'postpeek',
            array($this, 'postpeek_options_page_html')
        );
    }

    public function postpeek_options_page_html()
    {
        // check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
    ?>
        <div class="wrap">
            <h1><?= esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                // output security fields for the registered setting "postpeek_options"
                settings_fields('postpeek_options');
                // output setting sections and their fields
                do_settings_sections('postpeek');
                // output save settings button
                submit_button('Save Settings');
                ?>
            </form>
        </div>
    <?php
    }


    public function postpeek_default_date_period_render()
    {
        $options = get_option('postpeek_options', array());
        $selected_period = isset($options['default_date_period']) ? $options['default_date_period'] : 'num_of_days=7';

    ?>
        <select name="postpeek_options[default_date_period]">
            <option value="num_of_days=1" <?php selected($selected_period, 'num_of_days=1'); ?>>Last 24 hours</option>
            <option value="num_of_days=7" <?php selected($selected_period, 'num_of_days=7'); ?>>Last 7 days</option>
            <option value="num_of_days=28" <?php selected($selected_period, 'num_of_days=28'); ?>>Last 28 days</option>
            <option value="num_of_months=3" <?php selected($selected_period, 'num_of_months=3'); ?>>Last 3 months</option>
            <option value="num_of_months=6" <?php selected($selected_period, 'num_of_months=6'); ?>>Last 6 months</option>
            <option value="num_of_months=12" <?php selected($selected_period, 'num_of_months=12'); ?>>Last 12 months</option>
            <option value="num_of_months=16" <?php selected($selected_period, 'num_of_months=16'); ?>>Last 16 months</option>
        </select>
<?php
    }




    // Add the Search Console link to the actions.

    public function add_search_console_link($actions, $post)
    {
        // Check if the user has the necessary capabilities.
        if (!$this->user_has_capability()) {
            return $actions;
        }

        // Fetch plugin options
        $options = get_option('postpeek_options', array());

        // Determine account type
        $site_type = isset($options['site_type']) ? $options['site_type'] : 'url_prefix';

        // Create the resource identifier based on the account type
        $home_url = home_url(); // Get the full URL
        $parsed_url = parse_url($home_url); // Parse the URL
        $host = $parsed_url['host']; // Extract the host component

        // when using [domain_property], must extract only the host, [example.com], not [https://example.com]
        $resource_id = ($site_type === 'domain_property') ? "sc-domain:$host" : home_url();

        // Get default_date_period from options, or set it to 7 days if not set
        $default_date_period = isset($options['default_date_period']) ? $options['default_date_period'] : 'num_of_days=7';

        // Add the Search Console link to the actions array.
        // [/&page=!] strict to this url only
        // [/&page=*] any page contains this url

        $actions['search_console'] = '<a target="_blank" href="https://search.google.com/search-console/performance/search-analytics?resource_id=' . $resource_id . '&page=!' . esc_url(get_permalink($post)) . '&' . esc_attr($default_date_period) . '" class="google_link">' . __('Search Console', 'postpeek') . '</a>';

        return $actions;
    }

    // Check if the current user has the necessary capabilities.

    private function user_has_capability()
    {
        // If the user is an administrator, allow access by default.
        if (current_user_can('administrator')) {
            return true;
        }

        $options = get_option('postpeek_options', array());
        $allowed_roles = isset($options['allowed_roles']) ? $options['allowed_roles'] : array();

        $user = wp_get_current_user();

        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles)) {
                return true;
            }
        }

        return false;
    }
}

// Instantiate the plugin class.
$post_peek_search_console_link = new PostPeek_SearchConsoleLink();
