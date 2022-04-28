<?php

/**
 * Provides Deployer tasks to upload and download files
 * from and to server. When not explicitly using the
 * xyz:files-no-bak, a backup of the current files is
 * created, before new files are transferred.
 *
 * Requires these Deployer variables to be set:
 *   sync_dirs: Array of paths, that will be simultaneously updated
 *              with $absoluteLocalPath => $absoluteRemotePath
 *              If a path has a trailing slash, only its content
 *              will be transferred, not the directory itself.
 */

namespace Deployer;

/*
 * Uploads all files (and directories) from local machine to
 * remote server. Overwrites existing files on server with
 * updated local files and uploads new files. Locally deleted
 * files are not deleted on server.
 */
desc( 'Upload sync directories from local to server' );
task( 'push:files-no-bak', function () {

    $hostSsh =  host(get('hostname'))->getRealHostname();
    $user = host(get('hostname'))->getUser();
    $remoteSshPort = host(get('hostname'))->getPort();

    foreach ( get( 'sync_dirs' ) as $localDir => $serverDir ) {
        //upload( $localDir, $serverDir );
        //writeln("<comment>scp -r -P{$remoteSshPort} \"{$localDir}\" {$user}@{$hostSsh}:{$serverDir}</comment>");
        runLocally("scp -r -P{$remoteSshPort} \"{$localDir}\" {$user}@{$hostSsh}:{$serverDir}..", ['timeout' => null]);
    };

} );

/*
 * Downloads all files (and directories) from remote server to
 * local machine. Overwrites existing files on local machine with
 * updated server files and downloads new files. Deleted files
 * on the server are not deleted on local machine.
 */
desc( 'Download sync directories from server to local' );
task( 'pull:files-no-bak', function () {

    $hostSsh =  host(get('hostname'))->getRealHostname();
    $user = host(get('hostname'))->getUser();
    $remoteSshPort = host(get('hostname'))->getPort();

    foreach ( get( 'sync_dirs' ) as $localDir => $serverDir ) {
        //download( $serverDir, $localDir );
        //writeln("<comment></comment>");
        runLocally("scp -r -P{$remoteSshPort} {$user}@{$hostSsh}:{$serverDir}  \"{$localDir}..\"", ['timeout' => null]);
    };

} );

desc( 'Create backup from sync directories on server' );
task( 'backup:remote_files', function () {

    foreach ( get( 'sync_dirs' ) as $localDir => $serverDir ) {
        $backupFilename = '_backup_' . date( 'Y-m-d_H-i-s' ) . '.zip';

        // Note: sync_dirs can have a trailing slash (which means, sync only the content of the specified directory)
        if ( substr( $serverDir, - 1 ) == '/' ) {
            // Add everything from synced directory to zip, but exclude previous backups
            run( "cd {$serverDir} && zip -r {$backupFilename} . -x \"_backup_*.zip\" -i \*" );
        } else {
            $backupDir = dirname( $serverDir );
            $dir       = basename( $serverDir );
            // Add everything from synced directory to zip, but exclude previous backups
            run( "cd {$backupDir} && zip -r {$backupFilename} {$dir} -x \"_backup_*.zip\" -i \*" );
        }
    };

} );

desc( 'Create backup from sync directories on local machine' );
task( 'backup:local_files', function () {

    foreach ( get( 'sync_dirs' ) as $localDir => $serverDir ) {
        $backupFilename = '_backup_' . date( 'Y-m-d_H-i-s' ) . '.zip';

        // Note: sync_dirs can have a trailing slash (which means, sync only the content of the specified directory)
        if ( substr( $localDir, - 1 ) == '/' ) {
            // Add everything from synced directory to zip, but exclude previous backups
            runLocally( "cd {$localDir} && zip -r {$backupFilename} . -x \"_backup_*.zip\" -i \*" );
        } else {
            $backupDir = dirname( $localDir );
            $dir       = basename( $localDir );
            // Add everything from synced directory to zip, but exclude previous backups
            runLocally( "cd {$backupDir} && zip -r {$backupFilename} {$dir} -x \"_backup_*.zip\" -i\*" );
        }
    };

} );

desc( 'Upload sync directories from local to server after making backup of remote files' );
task( 'push:files', [
    'backup:remote_files',
    'push:files-no-bak',
] );

desc( 'Download sync directories from server to local machine after making backup of local files' );
task( 'pull:files', [
    'backup:local_files',
    'pull:files-no-bak',
] );

desc( 'Change URL in generated files for server' );
task( 'files:url_change_upload', function () use ($getLocalEnv, $getRemoteEnv, $urlToDomain) {

    $php = host(get('hostname'))->get('php');
    $envDist = run("cat {{current_path}}/.env");
    $remoteUrl = substr($envDist, strpos($envDist, "DOMAIN_CURRENT_SITE=") + strlen("DOMAIN_CURRENT_SITE=")+1);
    $remoteHttp = substr($remoteUrl, 0, strpos($remoteUrl, "'") );

    $remoteProtocol = substr($envDist, strpos($envDist, "PROTOCOL=") + strlen("PROTOCOL=")+1);
    $protocol = substr($remoteProtocol, 0, strpos($remoteProtocol, "'") );
    
    $localHttp = $getLocalEnv();

    writeln("replace urls from: {$localHttp} to: {$protocol}://{$remoteHttp}");
    $escLocalHttp = str_replace ("/", "\/", preg_quote($localHttp));
    $escServerHttp = str_replace ("/", "\/", preg_quote("{$protocol}://{$remoteHttp}"));
    run("cd {{current_path}}/web/app/uploads && find . |xargs perl -pi -e 's/{$escLocalHttp}/{$escServerHttp}/g'");
});


desc( 'Change URL in generated files for local' );
task( 'files:url_change_download', function () use ($getLocalEnv, $getRemoteEnv, $urlToDomain) {

    $envDist = run("cat {{current_path}}/.env");
    $remoteUrl = substr($envDist, strpos($envDist, "DOMAIN_CURRENT_SITE=") + strlen("DOMAIN_CURRENT_SITE=")+1);
    $remoteHttp = substr($remoteUrl, 0, strpos($remoteUrl, "'") );

    $remoteProtocol = substr($envDist, strpos($envDist, "PROTOCOL=") + strlen("PROTOCOL=")+1);
    $protocol = substr($remoteProtocol, 0, strpos($remoteProtocol, "'") );
    
    $localHttp = $getLocalEnv();

    writeln("replace urls from: {$protocol}://{$remoteHttp} to: {$localHttp}");
    $escLocalHttp = str_replace ("/", "\/", preg_quote($localHttp));
    $escServerHttp = str_replace ("/", "\/", preg_quote("{$protocol}://{$remoteHttp}"));
    // runLocally("php wp-cli.phar search-replace {$protocol}://{$remoteHttp} {$localHttp}");
    runLocally("cd {{local_root}}/web/app/uploads && find . |xargs perl -pi -e 's/{$escServerHttp}/{$escLocalHttp}/g'");
});