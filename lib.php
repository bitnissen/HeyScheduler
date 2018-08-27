<?php

function __hey_scheduler_add($action, $payload = null, $priority = 3, $run_at = null, $seconds_between_tries = '60,60,300,900')
{
    if (!$seconds_between_tries) $seconds_between_tries = '60'; // run only once

    if (!is_null($payload) && !is_array($payload)) return new WP_Error('payload_error', 'The payload must either be null or an array.');

    global $wpdb;
    /** @var $wpdb wpdb */

    $success = $wpdb->insert(
        $wpdb->base_prefix . "hey_scheduler",
        [
            'action' => $action,
            'payload' => serialize($payload),
            'blog_id' => get_current_blog_id(),
            'priority' => $priority,
            'next_run_at' => date('c', $run_at ?: time()),
            'seconds_between_tries' => $seconds_between_tries,
            'created_at' => date('c')
        ],
        [
            '%s', '%s', '%d', '%d', '%s', '%s', '%s'
        ]
    );

    if (!$success) {
        return new WP_Error('db_error', 'The database query for saving this task failed.');
    }

    return $wpdb->insert_id;
}

function __hey_scheduler_run()
{
    set_time_limit(60);

    // when did we start
    $start = time();

    // make sure we're not running two schedulers on top of each other
    $last_run_at = get_option('hey_scheduler_is_running');
    if ($last_run_at && $last_run_at + 55 > time()) return;
    update_option('hey_scheduler_is_running', time());

    // fetch one task at a time and run it - don't spend more than 55 seconds per run
    $tasks = [];
    while ($start + 55 > time() && __hey_has_next_task()) {
        $admin_url = parse_url(get_admin_url());

        $args = [
            'body' => [
                'security_token' => get_option('hey_scheduler_ajax_token')
            ],
            'timeout' => 55,
            'sslverify' => false,
            'headers' => [
                'Host' => $admin_url['host']
            ]
        ];

        if (isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER']) {
            $args['headers'] = [
                'Authorization' => 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ":" . $_SERVER['PHP_AUTH_PW'])
            ];
        }

        $res = wp_remote_post($x='http://localhost'.$admin_url['path'].'admin-ajax.php?action=hey_scheduler_run_batch', $args);
        if (is_wp_error($res)) {
            continue;
        }

        $tasks[] = $res['body'];
    }

    // no more tasks, so scheduler may run again, if anyone pleases
    delete_option('hey_scheduler_is_running');

    die();
}

/**
 * Keep running new tasks for 15 seconds before returning.
 */
function __hey_scheduler_run_do()
{
    set_time_limit(45);

    $security_token = $_REQUEST['security_token'];
    if (get_option('hey_scheduler_ajax_token') != $security_token) die(json_encode(['success' => false, 'message' => 'invalid_security_token']));

    // when did we start
    $start = time();

    // fetch one task at a time and run it - don't spend more than 55 seconds per run
    $tasks = [];
    while ($start + 15 > time() && ($task = __hey_get_and_iterate_next_task())) {
        set_time_limit(50);
        __hey_scheduler_run_single_task($task);
    }

    die();
}

/**
 * Fetches the next task from the database and iterate the run counter etc.
 *
 * @return bool|object
 */
function __hey_get_and_iterate_next_task()
{
    global $wpdb;
    $task = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT `id`, `blog_id`, `attempts`, `seconds_between_tries`, `action`, `payload` " .
            "FROM {$wpdb->base_prefix}hey_scheduler " .
            "WHERE " .
            "`next_run_at` IS NOT NULL AND `next_run_at` < %s " .
            "ORDER BY `attempts` ASC, `priority` ASC, `id` ASC " .
            "LIMIT 1"
            , date('c'))
    );

    // no more tasks, at least for now
    if (!$task) return false;

    // increment the attempts counter and set the next_run_at to whatever the seconds_between_tries dictates
    $attempts = $task->attempts ?: 0;
    $seconds_between_tries = explode(",", $task->seconds_between_tries);

    $next_run_in = isset($seconds_between_tries[$attempts]) ? (int)$seconds_between_tries[$attempts] : false;

    // no credit left in the machine - fail the task
    if ($next_run_in === false) {
        $wpdb->update($wpdb->base_prefix . 'hey_scheduler', [
            'next_run_at' => null
        ], [
            'id' => $task->id
        ]);
    } else {
        // update the task to prepare it for a future run, if this run fails
        $wpdb->update($wpdb->base_prefix . 'hey_scheduler', [
            'next_run_at' => date('c', time() + $next_run_in),
            'attempts' => $attempts + 1
        ], [
                'id' => $task->id
            ]
        );
    }

    // return the task information
    return $task;
}

function __hey_has_next_task()
{
    global $wpdb;
    /** @var $wpdb wpdb */
    $task = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id " .
            "FROM {$wpdb->base_prefix}hey_scheduler " .
            "WHERE " .
            "`next_run_at` < %s " .
            "ORDER BY `attempts` ASC, `priority` ASC, `id` ASC " .
            "LIMIT 1"
            , date('c'))
    );
    return (boolean)$task;
}

/**
 * Run a single task
 */
function __hey_scheduler_run_single_task($task)
{
    set_time_limit(-1);

    // right blog?
    if (get_current_blog_id() != $task->blog_id) {
        switch_to_blog($task->blog_id);
    }

    // do the action!
    do_action_ref_array($task->action, unserialize($task->payload));

    // if we're still alive, the task must have completed successfully - remove from the queue
    global $wpdb;
    /** @var $wpdb wpdb */
    $wpdb->delete($wpdb->base_prefix . 'hey_scheduler', [
        'id' => $task->id
    ]);
}