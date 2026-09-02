<?php
/**
 * Normalizes the root folder inside a distribution archive to the plugin slug.
 *
 * `wp dist-archive` names the archive's root folder after the local checkout
 * directory, which does not always match the plugin slug. WordPress derives
 * a plugin's slug from its folder name, so a mismatch makes Plugin Check
 * report text domain errors after the archive is installed.
 *
 * This wp-cli version also appends to an existing archive instead of
 * replacing it, so trees from earlier builds can accumulate. When more
 * than one root folder is found, only the most recently added tree is
 * kept, because dist-archive appends fresh content after existing
 * entries.
 *
 * Usage: php bin/normalize-zip-dirname.php <archive.zip> <slug>
 *
 * @package Guzmandrade_AI_Chatbot
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

if ( 0 === $num_entries ) {
	fwrite( STDERR, "Archive is empty: {$archive}\n" );
	$zip->close();
	exit( 2 );
}

// Track the highest entry index per root folder.
$roots = array();

for ( $i = 0; $i < $num_entries; $i++ ) {
	$name  = $zip->getNameIndex( $i );
	$slash = strpos( $name, '/' );
	$root  = ( false === $slash ) ? '' : substr( $name, 0, $slash );

	if ( ! isset( $roots[ $root ] ) || $roots[ $root ] < $i ) {
		$roots[ $root ] = $i;
	}
}

// The most recently added tree has the highest entry index.
$latest = array_search( max( $roots ), $roots, true );

if ( '' === $latest ) {
	fwrite( STDERR, "Could not determine the archive's root folder.\n" );
	$zip->close();
	exit( 2 );
}

if ( 1 === count( $roots ) && $slug === $latest ) {
	fwrite( STDOUT, "Archive root folder already matches the slug.\n" );
	$zip->close();
	exit( 0 );
}

// Rebuild the archive keeping only the latest tree, renamed to the slug.
$tmp_name = $archive . '.tmp';
$tmp      = new ZipArchive();

if ( true !== $tmp->open( $tmp_name, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not create temporary archive: {$tmp_name}\n" );
	$zip->close();
	exit( 3 );
}

$kept      = 0;
$discarded = 0;
$renamed   = 0;

for ( $i = 0; $i < $num_entries; $i++ ) {
	$name  = $zip->getNameIndex( $i );
	$slash = strpos( $name, '/' );
	$root  = ( false === $slash ) ? '' : substr( $name, 0, $slash );

	if ( $latest !== $root ) {
		$discarded++;
		continue;
	}

	$new_name = ( $slug === $root ) ? $name : $slug . '/' . substr( $name, $slash + 1 );

	if ( '/' === substr( $new_name, -1 ) ) {
		$tmp->addEmptyDir( rtrim( $new_name, '/' ) );
	} else {
		$tmp->addFromString( $new_name, $zip->getFromIndex( $i ) );
	}

	if ( $new_name !== $name ) {
		$renamed++;
	}

	$kept++;
}

$zip->close();
$tmp->close();

if ( ! unlink( $archive ) || ! rename( $tmp_name, $archive ) ) {
	fwrite( STDERR, "Could not replace archive: {$archive}\n" );
	exit( 3 );
}

fwrite( STDOUT, "Archive root folder is now {$slug}/ — kept {$kept} entries, renamed {$renamed}, discarded {$discarded} stale entries.\n" );