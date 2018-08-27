<?php

function __hey_scheduler_deactivate_do()
{
    global $wpdb; /** @var $wpdb wpdb */
    $PREFIX = $wpdb->base_prefix;

    $sql = [
        "DROP TABLE IF EXISTS `{$PREFIX}hey_scheduler`;"
    ];

    foreach($sql as $q) {
        $wpdb->query($q);
    }
}