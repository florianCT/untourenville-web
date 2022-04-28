<?php

/**
 * Deployer recipes to push Bedrock database from local development
 * machine to a server and vice versa.
 *
 * Assumes that Bedrock runs locally:
 *
 * Will always create a DB backup on the target machine.
 *
 * Requires these Deployer variables to be set:
 *   local_root: Absolute path to website root on local host machine
 */

namespace Deployer;

require(__DIR__ . '/lib/functions.php');

desc('Pulls DB from server and installs it locally, after having made a backup of local DB');
task('pull:db', function () use ($getLocalEnv, $getRemoteEnv, $urlToDomain) {

    $option = null;
    if (input()->hasOption('db')) {
        $option = input()->getOption('db');
    }

    $exclude="--exclude_tables=wp_users";
    $isFullBackup = false;
    if($option == 'full') {
        $isFullBackup = askConfirmation('Are you sure to download your online users ? Users from local machine will be removed forever.', false);
    }

    if( $isFullBackup ) {
        writeln('<comment>Full download with users</comment>');
        $exclude="";
    }

    $envDist = run("cat {{current_path}}/.env");
    $remoteUrl = substr($envDist, strpos($envDist, "DOMAIN_CURRENT_SITE=") + strlen("DOMAIN_CURRENT_SITE=")+1);
    $remoteHttp = substr($remoteUrl, 0, strpos($remoteUrl, "'") );

    $remoteProtocol = substr($envDist, strpos($envDist, "PROTOCOL=") + strlen("PROTOCOL=")+1);
    $protocol = substr($remoteProtocol, 0, strpos($remoteProtocol, "'") );

    $php = host(get('hostname'))->get('php');
    $stage = host(get('hostname'))->get('stage');
    $hostSsh =  host(get('hostname'))->getRealHostname();
    $user = host(get('hostname'))->getUser();
    $remoteSshPort = host(get('hostname'))->getPort();
    // Load local .env file and get local WP URL
    if (!$localUrl = $getLocalEnv()) {
        return;
    }

    // Load remote .env file and get remote WP URL
    if (!$remoteUrl = "{$protocol}://{$remoteHttp}") {
        return;
    }

    // Also get domain without protocol and trailing slash
    $localDomain = $urlToDomain($localUrl);
    $remoteDomain = $urlToDomain($remoteUrl);

    // Export db
    $exportFilename = '_db_export_' . date('Y-m-d_H-i-s') . '.sql';
    $exportAbsFile  = get('deploy_path') . '/' . $exportFilename;
    writeln("<comment>Exporting server DB to {$exportAbsFile}</comment>");
    run("cd {{current_path}} && {$php} wp-cli.phar db export {$exportAbsFile} {$exclude} --allow-root");

    // Download db export
    $downloadedExport = get('local_root') . '/' . $exportFilename;
    writeln("<comment>Downloading DB export to {$downloadedExport}</comment>");
    //download($exportAbsFile, $downloadedExport);
    runLocally("scp -P{$remoteSshPort} {$user}@{$hostSsh}:{$exportAbsFile}  \"{$downloadedExport}\"");

    // Cleanup exports on server
    writeln("<comment>Cleaning up {$exportAbsFile} on server</comment>");
    run("rm {$exportAbsFile}");

    // Create backup of local DB
    $backupFilename = '_db_backup_local_replaced_by' . $stage . '_' . date('Y-m-d_H-i-s') . '.sql';
    $backupAbsFile  = get('local_root') . '/localDbBackup/' . $backupFilename;
    writeln("<comment>Making backup of DB on local machine to {$backupAbsFile}</comment>");
    runLocally("cd {{local_root}} && php wp-cli.phar db export {$backupAbsFile}");

    // Create users table from local DB
    if( !$isFullBackup ) {
        $usersFilename = '_db_users_' . date('Y-m-d_H-i-s') . '.sql';
        $usersAbsFile  = get('local_root') . '/' . $usersFilename;
        writeln("<comment>Making backup of DB on local machine to {$usersAbsFile}</comment>");
        runLocally("cd {{local_root}} && php wp-cli.phar db export {$usersFilename} --tables=wp_users ");
    }

    // Empty local DB
    writeln("<comment>Reset local DB</comment>");
    runLocally("cd {{local_root}} && php wp-cli.phar db reset");

    // Import export file
    writeln("<comment>Importing {$downloadedExport}</comment>");
    runLocally("cd {{local_root}} && php wp-cli.phar db import {$exportFilename}");

    // Import users file
    if( !$isFullBackup ) {
        writeln("<comment>Importing {$usersFilename}</comment>");
        runLocally("cd {{local_root}} && php wp-cli.phar db import {$usersFilename}");
    }

    // Update URL in DB
    // In a multisite environment, the DOMAIN_CURRENT_SITE in the .env file uses the new remote domain.
    // In the DB however, this new remote domain doesn't exist yet before search-replace. So we have
    // to specify the old (remote) domain as --url parameter.
    writeln("<comment>Updating the URLs in the DB</comment>");
    runLocally("cd {{local_root}} && php wp-cli.phar search-replace '{$remoteUrl}' '{$localUrl}' --skip-themes --url='{$remoteDomain}' --network");
    // Also replace domain (multisite WP also uses domains without protocol in DB)
    runLocally("cd {{local_root}} && php wp-cli.phar search-replace '{$remoteDomain}' '{$localDomain}' --skip-themes --url='{$remoteDomain}' --network");

    // Cleanup exports on local machine
    writeln("<comment>Cleaning up {$downloadedExport} on local machine</comment>");
    runLocally("rm {$downloadedExport}");

    // Cleanup users on local machine
    if( !$isFullBackup ) {
        writeln("<comment>Cleaning up {$usersFilename} on local machine</comment>");
        runLocally("rm {$usersFilename}");
    }
});

desc('Pushes DB from local machine to server and installs it, after having made a backup of server DB');
task('push:db', function () use ($getLocalEnv, $getRemoteEnv, $urlToDomain) {

    $option = null;
    if (input()->hasOption('db')) {
        $option = input()->getOption('db');
    }

    $exclude="--exclude_tables=wp_users";
    $isFullBackup = false;
    if($option == 'full') {
        $isFullBackup = askConfirmation('Are you sure to upload your local users ? Users from server will be removed forever.', false);
    }

    if( $isFullBackup ) {
        writeln('<comment>Full upload with users</comment>');
        $exclude="";
    }

    $envDist = run("cat {{current_path}}/.env");
    $remoteUrl = substr($envDist, strpos($envDist, "DOMAIN_CURRENT_SITE=") + strlen("DOMAIN_CURRENT_SITE=")+1);
    $remoteHttp = substr($remoteUrl, 0, strpos($remoteUrl, "'") );

    $remoteProtocol = substr($envDist, strpos($envDist, "PROTOCOL=") + strlen("PROTOCOL=")+1);
    $protocol = substr($remoteProtocol, 0, strpos($remoteProtocol, "'") );

    $php = host(get('hostname'))->get('php');
    $stage = host(get('hostname'))->get('stage');
    $hostSsh =  host(get('hostname'))->getRealHostname();
    $user = host(get('hostname'))->getUser();
    $remoteSshPort = host(get('hostname'))->getPort();
    // Load local .env file and get local WP URL
    if (!$localUrl = $getLocalEnv()) {
        return;
    }

    // Load remote .env file and get remote WP URL
    if (!$remoteUrl = "{$protocol}://{$remoteHttp}") {
        return;
    }

    // Also get domain without protocol and trailing slash
    $localDomain = $urlToDomain($localUrl);
    $remoteDomain = $urlToDomain($remoteUrl);

    // Export db
    $exportFilename = '_db_export_' . date('Y-m-d_H-i-s') . '.sql';
    $exportAbsFile  = get('local_root') . '/' . $exportFilename;
    writeln("<comment>Exporting to {$exportAbsFile}</comment>");
    runLocally("cd {{local_root}} && php wp-cli.phar db export {$exportFilename} {$exclude}");

    // Upload export to server
    $uploadedExport = get('current_path') . '/' . $exportFilename;
    writeln("<comment>Uploading export to {$uploadedExport} on server</comment>");
    runLocally("scp -P{$remoteSshPort} \"{$exportAbsFile}\" {$user}@{$hostSsh}:{$uploadedExport}");

    // Cleanup local export
    writeln("<comment>Cleaning up {$exportAbsFile} on local machine</comment>");
    runLocally("rm {$exportAbsFile}");

    // Create backup of server DB
    $backupFilename = '_db_backup_' . $stage . '_replaced_by_local_' . date('Y-m-d_H-i-s') . '.sql';
    $backupAbsFolder  = get('deploy_path') . '/db_backup/';
    $backupAbsFile  = $backupAbsFolder . $backupFilename;
    writeln("<comment>Making backup of DB on server to {$backupAbsFile}</comment>");
    run("cd {{current_path}} && mkdir -p {$backupAbsFolder} && {$php} wp-cli.phar db export {$backupAbsFile} --allow-root");

    //Create users export from server
    if( !$isFullBackup ) {
        $usersFilename = '_db_users_' . date('Y-m-d_H-i-s') . '.sql';
        $usersAbsFile  = get('deploy_path') . '/' . $usersFilename;
        writeln("<comment>Making backup of Users on server to {$usersAbsFile}</comment>");
        run("cd {{current_path}} && {$php} wp-cli.phar db export {$usersAbsFile} --tables=wp_users --allow-root");
    }

    // Empty server DB
    writeln("<comment>Reset server DB</comment>");
    run("cd {{current_path}} && {$php} wp-cli.phar db reset --allow-root");

    // Import export file from local
    writeln("<comment>Importing {$uploadedExport}</comment>");
    run("cd {{current_path}} && {$php} wp-cli.phar db import {$uploadedExport} --allow-root");

    // Import users file
    if( !$isFullBackup ) {
        writeln("<comment>Importing users : {$usersAbsFile}</comment>");
        run("cd {{current_path}} && {$php} wp-cli.phar db import {$usersAbsFile} --allow-root");
    }

    // Update URL in DB
    // In a multisite environment, the DOMAIN_CURRENT_SITE in the .env file uses the new remote domain.
    // In the DB however, this new remote domain doesn't exist yet before search-replace. So we have
    // to specify the old (local) domain as --url parameter.
    writeln("<comment>Updating the URLs in the DB</comment>");
    writeln("cd {{current_path}} && {$php} wp-cli.phar search-replace \"{$localUrl}\" \"{$remoteUrl}\" --skip-themes --url='{$localDomain}' --network --allow-root");
    run("cd {{current_path}} && {$php} wp-cli.phar search-replace \"{$localUrl}\" \"{$remoteUrl}\" --skip-themes --url='{$localDomain}' --network --allow-root");
    // Also replace domain (multisite WP also uses domains without protocol in DB)
    writeln("cd {{current_path}} && {$php} wp-cli.phar search-replace \"{$localDomain}\" \"{$remoteDomain}\" --skip-themes --url='{$localDomain}' --network --allow-root");
    run("cd {{current_path}} && {$php} wp-cli.phar search-replace \"{$localDomain}\" \"{$remoteDomain}\" --skip-themes --url='{$localDomain}' --network --allow-root");

    // Cleanup uploaded file
    writeln("<comment>Cleaning up {$uploadedExport} from server</comment>");
    run("rm {$uploadedExport}");
    if( !$isFullBackup ) {
        writeln("<comment>Cleaning up {$usersAbsFile} from server</comment>");
        run("rm {$usersAbsFile}");
    }
});
