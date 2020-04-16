<?php
/**
 * Generate readme.txt from GitHub's README.md
 *
 * @author fumikito
 * @version 2.0.0
 * @see https://github.com/fumikito/wp-readme
 */

/**
 * Find readme and return file path.
 *
 * @param string $target_dir
 *
 * @return string
 */
function wp_readme_find( $target_dir = '.' ) {
	$target_dir = rtrim( $target_dir, DIRECTORY_SEPARATOR );
	if ( is_dir( $target_dir ) ) {
		foreach ( scandir( $target_dir ) as $file ) {
			if ( in_array( $file, [ 'readme.md', 'README.md' ] ) ) {
				return $target_dir . DIRECTORY_SEPARATOR . $file;
			}
		}
	}

	return '';
}

/**
 * @param $target_file
 *
 * @return bool
 * @throws Exception
 */
function wp_readme_replace( $target_file ) {
	if ( ! file_exists( $target_file ) ) {
		throw new Exception( 'File not found.', 404 );
	}
	// Check if file is wriable.
	$target_dir = dirname( realpath( $target_file ) );
	if ( ! is_writable( $target_dir ) ) {
		throw new Exception( 'Target directory is not writable.', 403 );
	}
	$new_file = dirname( $target_file ) . DIRECTORY_SEPARATOR . strtolower( preg_replace( '/\.md$/u', '.txt', basename( $target_file ) ) );
	if ( file_exists( $new_file ) && ! is_writable( $new_file ) ) {
		throw new Exception( 'readme.txt already exists and is not writable.', 403 );
	}
	$string = file_get_contents( $target_file );
	$string = wp_readme_convert_string( $string );
	// Save file.
	if ( ! @file_put_contents( $new_file, $string ) ) {
		throw new Exception( 'Failed to save readme.txt' );
	}

	return true;
}

/**
 * Convert readme string.
 *
 * @param string $string
 *
 * @return string
 */
function wp_readme_convert_string( $string ) {
	// Control visibility.
	$string = wp_readme_visibility( $string );
	// Replace headers.
	$string = preg_replace_callback( '/^(#+)\s+(.*)/mu', function ( $match ) {
		$length = strlen( $match[ 1 ] );
		$sep    = '';
		for ( $i = 1, $l = 3 - ( $length - 1 ); $i <= $l; $i ++ ) {
			$sep .= '=';
		}

		return "{$sep} {$match[2]} {$sep}";
	}, $string );

	// Format code.
	$string = preg_replace( '/```([^\n`]*?)\n(.*?)\n```/us', '<pre>$2</pre>', $string );

	// Fix line breaks before =
	$string = preg_replace( '/(=+[^\r\n=]+)(\r\n)( =+)', '$1$3', $string );

	return $string;
}

/**
 * Convert visibility.
 *
 * @param $string
 *
 * @return string
 */
function wp_readme_visibility( $string ) {
	// Remove github comment
	$string = preg_replace( '#<!-- only:github/ -->(.*?)<!-- /only:github -->#us', '', $string );
	// Display WordPress comment.
	$string = preg_replace_callback( '#<!-- only:wp>(.*?)</only:wp -->#us', function( $matches ) {
		return trim( $matches[1] );
	}, $string );
	// Handle env variable.
	if ( $var = getenv( 'WP_README_ENV' ) ) {
		$string = preg_replace_callback( sprintf( '#<!-- only:%1$s>(.*?)</only:%1$s -->#us', $var ), function( $matches ) {
			return trim( $matches[1] );
		}, $string );
		$string = preg_replace_callback( '#<!-- not:([^/]+)/ -->(.*?)<!-- /not:[^ ]+ -->#us', function( $matches ) use ( $var ) {
			if ( $var === $matches[1] ) {
				return '';
			} else {
				return trim( $matches[2] );
			}
		}, $string );
	}
	return $string;
}

// This file is executed as main routine.
if ( ! debug_backtrace() ) {
	$file = wp_readme_find( getenv( 'WP_README_DIR' ) ?: '.' );
	try {
		// File not found.
		if ( ! $file ) {
			throw new Exception( 'No README.md in current directory.' );
		}
		echo 'readme.md found...' . PHP_EOL;
		if ( wp_readme_replace( $file ) ) {
			echo 'readme.txt generated successfully!' . PHP_EOL;
		}
	} catch ( Exception $e ) {
		echo '[ERROR]' . $e->getMessage() . PHP_EOL;
		exit( 1 );
	}
}
