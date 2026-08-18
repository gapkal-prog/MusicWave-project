<?php
/**
 * Generate translation template files.
 *
 * Usage: php tools/generate-pot.php
 */

declare(strict_types=1);

$project_root = dirname( __DIR__ );
$components   = array(
	'musicwave'       => 'musicwave',
	'music-wave-core' => 'music-wave-core',
	'music-wave-vip'  => 'music-wave-vip',
);
foreach ( $components as $component => $domain ) {
	$source      = $project_root . DIRECTORY_SEPARATOR . $component;
	$languages   = $source . DIRECTORY_SEPARATOR . 'languages';
	$destination = $languages . DIRECTORY_SEPARATOR . $domain . '.pot';

	if ( ! is_dir( $languages ) && ! mkdir( $languages, 0775, true ) && ! is_dir( $languages ) ) {
		fwrite( STDERR, "Could not create language directory: {$languages}\n" );
		exit( 1 );
	}

	$entries = musicwave_pot_entries( $source, $domain );
	$content = musicwave_pot_header( $component, $domain );
	foreach ( $entries as $entry ) {
		$content .= musicwave_pot_entry( $entry );
	}
	if ( false === file_put_contents( $destination, $content ) ) {
		fwrite( STDERR, "Could not write POT file: {$destination}\n" );
		exit( 1 );
	}

	echo "Generated {$destination}\n";
}

/**
 * Extract supported WordPress translation calls from PHP and JavaScript.
 *
 * The generator intentionally supports the translation functions used by this
 * repository and avoids a build-time dependency for release packaging.
 *
 * @return array<string, array<string, mixed>>
 */
function musicwave_pot_entries( string $source, string $domain ): array {
	$entries = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	/** @var SplFileInfo $file */
	foreach ( $iterator as $file ) {
		$extension = strtolower( $file->getExtension() );
		if ( ! $file->isFile() || ! in_array( $extension, array( 'php', 'js' ), true ) ) {
			continue;
		}

		$path = $file->getPathname();
		$php  = file_get_contents( $path );
		if ( false === $php ) {
			continue;
		}

		$relative = str_replace( DIRECTORY_SEPARATOR, '/', ltrim( substr( $path, strlen( rtrim( $source, DIRECTORY_SEPARATOR ) ) ), DIRECTORY_SEPARATOR ) );
		foreach ( musicwave_pot_matches( $php, $domain ) as $match ) {
			$line = 1 + substr_count( substr( $php, 0, $match['offset'] ), "\n" );
			$key  = $match['context'] . "\004" . $match['singular'] . "\004" . $match['plural'];
			if ( ! isset( $entries[ $key ] ) ) {
				$entries[ $key ] = array(
					'context'  => $match['context'],
					'singular' => $match['singular'],
					'plural'   => $match['plural'],
					'refs'     => array(),
				);
			}
			$entries[ $key ]['refs'][] = $relative . ':' . $line;
		}
	}

	ksort( $entries, SORT_STRING );
	foreach ( $entries as &$entry ) {
		$entry['refs'] = array_values( array_unique( $entry['refs'] ) );
	}
	unset( $entry );

	return $entries;
}

/**
 * Find known translation function calls for one text domain.
 *
 * @return array<int, array<string, string|int>>
 */
function musicwave_pot_matches( string $php, string $domain ): array {
	$quoted = "(['\"])((?:\\\\.|(?!\\1).)*)\\1";
	$domain_pattern = preg_quote( $domain, '/' );
	$patterns = array(
		'/\b(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\s*\(\s*' . $quoted . '\s*,\s*([\'"])' . $domain_pattern . '\3\s*\)/s',
		'/\b(?:_x|esc_html_x|esc_attr_x)\s*\(\s*' . $quoted . '\s*,\s*' . $quoted . '\s*,\s*([\'"])' . $domain_pattern . '\5\s*\)/s',
		'/\b_n\s*\(\s*' . $quoted . '\s*,\s*' . $quoted . '\s*,.*?,\s*([\'"])' . $domain_pattern . '\5\s*\)/s',
	);
	$matches = array();

	foreach ( $patterns as $index => $pattern ) {
		$found = array();
		$count = preg_match_all( $pattern, $php, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		if ( false === $count || 0 === $count ) {
			continue;
		}
		foreach ( $found as $item ) {
			$matches[] = array(
				'context'  => 1 === $index ? stripcslashes( $item[4][0] ) : '',
				'singular' => stripcslashes( $item[2][0] ),
				'plural'   => 2 === $index ? stripcslashes( $item[4][0] ) : '',
				'offset'   => $item[0][1],
			);
		}
	}

	return $matches;
}

/**
 * Return a deterministic POT header.
 *
 * @return string
 */
function musicwave_pot_header( string $component, string $domain ): string {
	$headers = array(
		'Project-Id-Version: ' . $component,
		'Report-Msgid-Bugs-To: ',
		'POT-Creation-Date: ',
		'MIME-Version: 1.0',
		'Content-Type: text/plain; charset=UTF-8',
		'Content-Transfer-Encoding: 8bit',
		'X-Domain: ' . $domain,
	);
	$output = "msgid \"\"\nmsgstr \"\"\n";
	foreach ( $headers as $header ) {
		$output .= '"' . musicwave_pot_escape( $header ) . "\\n\"\n";
	}

	return $output . "\n";
}

/**
 * Render one POT entry.
 *
 * @param array<string, mixed> $entry Translation entry.
 * @return string
 */
function musicwave_pot_entry( array $entry ): string {
	$output = '#: ' . implode( ' ', $entry['refs'] ) . "\n";
	if ( false !== strpos( (string) $entry['singular'] . (string) $entry['plural'], '%' ) ) {
		$output .= "#, php-format\n";
	}
	if ( '' !== (string) $entry['context'] ) {
		$output .= 'msgctxt "' . musicwave_pot_escape( (string) $entry['context'] ) . '"' . "\n";
	}
	$output .= 'msgid "' . musicwave_pot_escape( (string) $entry['singular'] ) . '"' . "\n";
	if ( '' !== (string) $entry['plural'] ) {
		return $output . 'msgid_plural "' . musicwave_pot_escape( (string) $entry['plural'] ) . '"' . "\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
	}

	return $output . "msgstr \"\"\n\n";
}

/**
 * Escape a PO string.
 *
 * @return string
 */
function musicwave_pot_escape( string $value ): string {
	return str_replace( array( '\\', '"', "\r", "\n" ), array( '\\\\', '\\"', '', '\\n' ), $value );
}
