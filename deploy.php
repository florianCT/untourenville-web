<?php

namespace Deployer;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

require 'vendor/deployer/deployer/recipe/common.php';
require 'vendor/florianmoser/bedrock-deployer/recipe/bedrock_env.php';
// require 'vendor/florianmoser/bedrock-deployer/recipe/bedrock_misc.php';
require 'vendor/florianmoser/bedrock-deployer/recipe/common.php';
require 'custom_deploy/ct_bedrock_db.php';
require 'custom_deploy/ct_plugins.php';
require 'custom_deploy/ct_files.php';
require 'custom_deploy/ct_vendors.php';

//CT : hack for raspberry and composer 2.0
set('composer_options', get('composer_options').' --ignore-platform-reqs');

// Configuration

// Common Deployer config
set( 'repository', 'git@github.com:florianCT/preime.git' );
set( 'shared_dirs', [
	'web/app/uploads'
] );

// Bedrock DB and Sage config
set( 'local_root', dirname( __FILE__ ) );

// File transfer config
set( 'sync_dirs', [
	dirname( __FILE__ ) . '/web/app/uploads/' => '{{deploy_path}}/shared/web/app/uploads/',
] );

// Hosts
// CT update : error with multiplexing on windows
set('ssh_multiplexing', false);
// CT update : tty doesn't work on windows
set('git_tty', false); 

option('db', null, InputOption::VALUE_OPTIONAL, 'Migrate all db (--db full) or skip users (default).');

set( 'default_stage', 'staging' );

// ****** STAGING ENV ******

// ****** PROD ENV ******

host( 'untourenville.fr')
	->port(22)
	->stage( 'production' )
	->hostname('ssh.cluster028.hosting.ovh.net')
 	->user( 'cheftos' )
	->identityFile('~/.ssh/id_rsa')
	->set( 'php', '/usr/local/php7.2/bin/php' )
 	->set( 'deploy_path', '~/public_utev' );


// Tasks

// Deployment flow
desc( 'Deploy your project' );
task( 'deploy', [
	'deploy:prepare',
	'deploy:lock',
	'deploy:release',
	'deploy:update_code',
	'deploy:shared',
	'deploy:writable',
	'bedrock:vendors',
	//'wordpress:activatePlugins',
	'bedrock:env',
	'deploy:clear_paths',
	'deploy:symlink',
	'bedrock:permalink',
	'deploy:unlock',
	'cleanup',
	'success',
] );

desc( 'Pulls DB from server and installs it locally, after having made a backup of local DB' );
task( 'pulldb', [
	'pull:db'
] );

desc( 'Pushes DB from local machine to server and installs it, after having made a backup of server DB' );
task( 'pushdb', [
	'push:db'
] );

desc( 'Pushes media files from local machine to server, after having made a backup of server media files' );
task( 'pushfiles', [
	'push:files',
	'files:url_change_upload'
] );

desc( 'Pull media files from server to local machine, after having made a backup of local media files' );
task( 'pullfiles', [
	'pull:files',
	'files:url_change_download'
] );

desc( 'Activating All Wordpress plugins' );
task( 'activatePluginsServer', [
	'wordpress:activatePlugins'
] );

desc( 'Activating All Wordpress plugins Locally' );
task( 'plugins', [
	'wordpress:activatePluginsLocally'
] );

desc( 'Start local server' );
task( 'local', function() {
	runLocally("php -S 127.0.0.1:8000 -t web", ['tty' => true]);
});

// [Optional] if deploy fails automatically unlock.
after( 'deploy:failed', 'deploy:unlock' );