<?php

/**
 * Class to handle admin notices for the PostPeek plugin.
 */
class PostPeek_AdminNotices
{

    /**
     * Constructor
     */
    public function __construct()
    {
        register_activation_hook(POSTPEEK__FILE__, array($this, 'set_activation_time'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('admin_init', array($this, 'handle_rating_notice_actions'));
    }

    /**
     * Sets the plugin activation time.
     */
    public function set_activation_time()
    {
        add_option('postpeek_activated', current_time('mysql'));
    }

    /**
     * Displays admin notices.
     */
    public function display_admin_notices()
    {
        $this->show_activation_notice();
        $this->show_rating_notice();
    }

    /**
     * Shows the activation notice and beta version note.
     */
    private function show_activation_notice()
    {
        if (!get_option('postpeek_settings_visited')) {
            echo '<div class="notice notice-success is-dismissible">
                     <p><strong>PostPeek is activated.</strong> It\'s still in beta. Go to <a href="' . admin_url('options-general.php?page=postpeek') . '">Settings</a> to configure.</p>
                  </div>';
        }
    }

    /**
     * Shows the rating notice after 7 days of usage.
     */
    private function show_rating_notice()
    {
        $activation_time = get_option('postpeek_activated');
        $current_time = current_time('mysql');
        $datetime1 = new DateTime($activation_time);
        $datetime2 = new DateTime($current_time);
        $interval = $datetime1->diff($datetime2);
        $days = $interval->format('%a');

        if ($days >= 7) {
            echo '<div class="notice notice-info is-dismissible">
                     <p>Thank you for using PostPeek for more than 7 days. Please <a href="https://wordpress.org/support/plugin/postpeek/reviews/#postform" target="_blank">rate us</a>. <a href="?postpeek_rating_notice=ignore">Ignore</a> | <a href="?postpeek_rating_notice=remind">Remind later</a></p>
                  </div>';
        }
    }

    /**
     * Handles 'Ignore' and 'Remind later' actions for the rating notice.
     */
    public function handle_rating_notice_actions()
    {
        if (isset($_GET['postpeek_rating_notice'])) {
            if ($_GET['postpeek_rating_notice'] === 'ignore') {
                update_option('postpeek_rating_notice', 'ignored');
            } else if ($_GET['postpeek_rating_notice'] === 'remind') {
                update_option('postpeek_activated', current_time('mysql'));
            }
        }
    }
}

// Initialize the class
new PostPeek_AdminNotices();
