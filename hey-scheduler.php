<?php
/*
Plugin Name: Hey! Scheduler
Plugin URI:
Description: Alternative to wp_schedule_single_event with proper logging, retrying and support for large amounts of tasks.
Version:     1.0
Author:      Bitnissen
Author URI:  http://bitnissen.com
License:     MIT
*/

register_activation_hook(__FILE__, '__hey_scheduler_activate');
function __hey_scheduler_activate()
{
    require_once(__DIR__."/setup/install.php");
    __hey_scheduler_install();
}

register_deactivation_hook(__FILE__, '__hey_scheduler_deactivate');
function __hey_scheduler_deactivate()
{
    require_once(__DIR__."/setup/deactivate.php");
    __hey_scheduler_deactivate_do();
}

/**
 * Create a scheduled task. Returns the database ID of the task if success and a WP_Error on failure.
 *
 * The last argument (seconds_between_tries), tells how many times the task will be retries, before we consider the run
 * a failure, as well as how long a task may run, before we re-schedule it.
 * The default is 60,300,900,3600,60 which means that:
 * - If the first run fails, 60 seconds will pass before it is re-scheduled.
 * - If the second run fails, 5 minutes (300s) before re-scheduling.
 * - If the third run fails, 15 minutes (900s) before re-scheduling.
 * - If the fourth run fails, an hour (3600s).
 * - If the previous job and last run takes more than 60 seconds, the job is failed.
 *
 * @param string $action Name of action to be called.
 * @param array $payload Array of arguments to pass the action.
 * @param int $priority Priority of task. Default is 10. Lower numbers means faster execution.
 * @param int $run_at When to run the task. Unix timestamp. Default is now.
 * @param string $seconds_between_tries Comma-separated list of seconds between retries. Default is 60,60,300,900.
 */
function hey_scheduler_add($action, $payload=[], $priority=10, $run_at=null, $seconds_between_tries='60,60,300,900')
{
    require_once(__DIR__."/lib.php");
    __hey_scheduler_add($action, $payload, $priority, $run_at, $seconds_between_tries);
}

add_action('wp_ajax_nopriv_hey_scheduler_run', function() {
    require_once(__DIR__."/lib.php");
    __hey_scheduler_run();
});

add_action('wp_ajax_nopriv_hey_scheduler_run_batch', function() {
    require_once(__DIR__."/lib.php");
    __hey_scheduler_run_do();
});