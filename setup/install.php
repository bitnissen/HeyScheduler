<?php

function __hey_scheduler_install()
{
    global $wpdb; /** @var $wpdb wpdb */
    $PREFIX = $wpdb->base_prefix;

    $sql = [
        "DROP TABLE IF EXISTS `{$PREFIX}hey_scheduler`;",
        "CREATE TABLE `{$PREFIX}hey_scheduler` (".
            "`id` int(11) unsigned NOT NULL AUTO_INCREMENT,".
            "`blog_id` int(11) unsigned NOT NULL DEFAULT '1',".
            "`action` varchar(255) NOT NULL DEFAULT '',".
            "`payload` mediumblob,".
            "`attempts` int(1) NOT NULL DEFAULT '0',".
            "`seconds_between_tries` varchar(255) DEFAULT '60,60,300,900',".
            "`priority` int(3) NOT NULL DEFAULT '10',".
            "`next_run_at` timestamp NULL DEFAULT NULL,".
            "`created_at` timestamp NULL DEFAULT NULL,".
            "`updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,".
            "PRIMARY KEY (`id`),".
            "KEY `next_run_at` (`next_run_at`,`priority`)".
        ");"
    ];

    foreach($sql as $q) {
        $wpdb->query($q);
    }

    // generate security token for internal ajax requests
    update_option('hey_scheduler_ajax_token', wp_generate_password(32,false,false));
}