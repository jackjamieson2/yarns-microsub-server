
<?php

Class Yarns_Microsub_Admin {

    /**
     *
     * Display options menu
     *
     */
    public static function admin_menu()
    {
        // If the IndieWeb Plugin is installed use its menu.
        if ( class_exists( 'IndieWeb_Plugin' ) ) {
            add_submenu_page(
                'indieweb',
                __( 'Yarns Microsub Server', 'yarns-microsub-server' ), // page title
                __( 'Yarns Microsub Server', 'yarns-microsub-server' ), // menu title
                'manage_options', // access capability
                'yarns_microsub_options',
                array( 'Yarns_Microsub_Admin', 'yarns_settings_html' )
            );
        } else {
            add_options_page( '', __( 'Yarns Microsub Server', 'yarns-microsub-server' ), 'manage_options', 'yarns_microsub_options', array( 'Yarns_Microsub_Admin', 'yarns_settings_html' ) );
        }

        add_submenu_page(
            'settings.php',
            'Yarns Microsub Server settings',
            'Yarns Microsub Server ',
            'manage_options',
            'yarns_settings',
            'yarns_settings_html',
            plugin_dir_url(__FILE__) . 'images/icon_wporg.png',
            20
        );
    }




    public static function yarns_settings_html()
    {
        // check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <div id="yarns-admin-area">

                <div >
                    <h1>Yarns Microsub Server</h1>
                    <p> explanation here </p>


                    <h1> Manage subscriptions </h1>

                    <div class="yarns-channels">
                        <?php echo static::yarns_list_channels();?>
                    </div>
                    <div class="yarns-feeds"></div>
                    <div class="feed-options"></div>

                </div>


            </div><!--#yarns-admin-area-->

        </div>
        <?php
    }

    private static function yarns_list_channels(){
        $channels = Yarns_Microsub_Channels::get()['channels'];

        $html = "";

        foreach ($channels as $channel){
            $name = $channel['name'];
            $uid = $channel['uid'];
            $html .= "<div class='yarns-channel' data-uid='{$uid}'>{$name}</div>";
        }

        return $html;
    }
    private static function yarns_list_feeds($uid){}
    private static function yarns_feed_options(){}





}


