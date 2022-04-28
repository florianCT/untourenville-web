<?php

/**
 * Miscellaneous Bedrock tasks.
 */

namespace Deployer;

/*
 * Runs Composer install for Bedrock
 */
desc( 'Installing Bedrock vendors' );
task( 'bedrock:vendors', function () {
    $php = host(get('hostname'))->get('php');
    run( "cd {{release_path}} && {$php} composer.phar {{composer_options}}");
} );


/*
 * Set permalink struct
 */
desc( 'Set permalink' );
task( 'bedrock:permalink', function () {
    $php = host(get('hostname'))->get('php');
    writeln("<comment>Set permalink</comment>");
    run( "cd {{current_path}} && {$php} wp-cli.phar rewrite structure /%postname%");
} );