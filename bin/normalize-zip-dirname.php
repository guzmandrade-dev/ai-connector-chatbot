<?php
/**
 * Normalizes the root folder inside a distribution archive to the plugin slug.
 *
 * `wp dist-archive` names the archive's root folder after the local checkout
 * directory, which does not always match the plugin slug. WordPress derives
 * a plugin's slug from its folder name, so a mismatch makes Plugin Check
 * report text domain errors after the archive is installed.
 *
 * Usage: php bin/normalize-zip-dirname.php <archive.zip> <slug>
 *
 * @package Just_Another_Generic_Chatbot
 */

// phpcs:ignoreFile -- Standalone CLI build tool; unescaped CLI output is intentional.

if ( 3 !== $argc ) {
	fwrite( STDERR, "Usage: php bin/normalize-zip-dirname.php <archive.zip> <slug>\n" );
	exit( 1 );
}

$archive = $argv[1];
$slug    = $argv[2];

if ( ! is_file( $archive ) ) {
	fwrite( STDERR, "Archive not found: {$archive}\n" );
	exit( 1 );
}

$zip = new ZipArchive();

if ( true !== $zip->open( $archive ) ) {
	fwrite( STDERR, "Could not open archive: {$archive}\n" );
	exit( 2 );
}

$num_entries = $zip->count();
$prefix      = null;

// Determine the current root folder from the first directory entry.
for ( $i = 0; $i < $num_entries; $i++ ) {
	$name = $zip->getNameIndex( $i );

	if ( null === $prefix && str_contains( $name, '/' ) ) {
		$prefix = strstr( $name, '/', true ) . '/';
		break;
	}
}

if ( null === $prefix ) {
	fwrite( STDERR, "Could not determine the archive's root folder.\n" );
	$zip->close();
	exit( 2 );
}

if ( $prefix === $slug . '/' ) {
	fwrite( STDOUT, "Archive root folder already matches the slug.\n" );
	$zip->close();
	exit( 0 );
}

$failures = 0;

for ( $i = 0; $i < $num_entries; $i++ ) {
	$name = $zip->getNameIndex( $i );

	if ( 0 !== strpos( $name, $prefix ) ) {
		continue;
	}

	if ( ! $zip->renameIndex( $i, $slug . '/' . substr( $name, strlen( $prefix ) ) ) ) {
		fwrite( STDERR, "Could not rename entry: {$name}\n" );
		$failures++;
	}
}

$zip->close();

if ( 0 !== $failures ) {
	exit( 3 );
}

fwrite( STDOUT, "Renamed archive root folder from {$prefix} to {$slug}/.\n" );