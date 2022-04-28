<?php

/**
 * Miscellaneous Bedrock tasks.
 */

namespace Deployer;

/*
 * Runs WP Cli activate all plugins on server
 */
desc( 'Activating All Wordpress plugins' );
task( 'wordpress:activatePlugins', function () {
    $php = host(get('hostname'))->get('php');
    writeln("<comment>Activating all plugins</comment>");
    run("cd {{current_path}} && {$php} wp-cli.phar plugin activate --all --allow-root");
});

/*
 * Runs WP Cli activate all plugins locally
 */
desc( 'Activating All Wordpress plugins Locally' );
task( 'wordpress:activatePluginsLocally', function () {
    writeln("<comment>Activating all plugins</comment>");
    runLocally("cd {{local_root}} && php wp-cli.phar plugin activate --all");
});