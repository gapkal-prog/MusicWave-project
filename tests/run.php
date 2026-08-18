<?php
/**
 * Dependency-free domain smoke tests.
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['mw_test_meta']          = array();
$GLOBALS['mw_test_options']       = array();
$GLOBALS['mw_test_types']         = array(
	1  => 'mw_release',
	10 => 'product',
	11 => 'product',
);
$GLOBALS['mw_test_release_types'] = array(
	1 => array( 'album' ),
	2 => array( 'track' ),
	3 => array( 'podcast_episode' ),
);
$GLOBALS['mw_test_users']         = array( 7 => (object) array( 'user_email' => 'buyer@example.test' ) );
$GLOBALS['mw_test_purchases']     = array();
$GLOBALS['mw_test_actions']       = array();
$GLOBALS['mw_test_terms']         = array();
$GLOBALS['mw_test_term_meta']     = array();
$GLOBALS['mw_test_object_terms']  = array();
$GLOBALS['mw_test_user_meta']     = array();

final class WP_Post {
	/** @var int */
	public $ID;

	/** @var string */
	public $post_type;

	public function __construct( int $post_id, string $post_type ) {
		$this->ID        = $post_id;
		$this->post_type = $post_type;
	}
}

final class WP_Term {
	/** @var int */
	public $term_id;

	/** @var string */
	public $taxonomy;

	/** @var string */
	public $name;

	/** @var string */
	public $slug;

	public function __construct( int $term_id, string $taxonomy, string $name, string $slug ) {
		$this->term_id  = $term_id;
		$this->taxonomy = $taxonomy;
		$this->name     = $name;
		$this->slug     = $slug;
	}
}

final class WP_Error {
	/** @var string */
	private $message;

	public function __construct( string $code = '', string $message = '' ) {
		unset( $code );
		$this->message = $message;
	}

	public function get_error_message(): string {
		return $this->message;
	}
}

final class WP_Query {
	/** @var bool */
	public $is_search = false;

	/** @var bool */
	public $mw_test_is_main = true;

	/** @var array<int, string> */
	public $mw_test_archive_types = array();

	public function is_main_query(): bool {
		return $this->mw_test_is_main;
	}

	public function is_search(): bool {
		return $this->is_search;
	}

	/** @param mixed $post_type */
	public function is_post_type_archive( $post_type = '' ): bool {
		return in_array( (string) $post_type, $this->mw_test_archive_types, true );
	}
}

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function plugin_dir_path( string $file ): string {
	return dirname( $file ) . DIRECTORY_SEPARATOR;
}

function plugin_dir_url( string $file ): string {
	unset( $file );
	return 'https://example.test/wp-content/plugins/music-wave-core/';
}

function register_activation_hook( string $file, callable $callback ): void {
	unset( $file, $callback );
}

function register_deactivation_hook( string $file, callable $callback ): void {
	unset( $file, $callback );
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	unset( $callback, $priority, $accepted_args );
	$GLOBALS['mw_test_actions'][] = $hook;
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	add_action( $hook, $callback, $priority, $accepted_args );
}

function add_shortcode( string $tag, callable $callback ): void {
	unset( $tag, $callback );
}

function apply_filters( string $hook, $value ) {
	unset( $hook );
	return $value;
}

function do_action( string $hook, ...$arguments ): void {
	unset( $arguments );
	$GLOBALS['mw_test_actions'][] = $hook;
}

function is_admin(): bool {
	return false;
}

function __( string $text, string $domain = '' ): string {
	unset( $domain );
	return $text;
}

function sanitize_text_field( string $value ): string {
	return trim( strip_tags( $value ) );
}

function wp_unslash( string $value ): string {
	return stripslashes( $value );
}

function sanitize_key( string $value ): string {
	return trim( strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ) );
}

function sanitize_file_name( string $value ): string {
	return trim( preg_replace( '/[^A-Za-z0-9._-]/', '-', $value ) );
}

function sanitize_title( string $value ): string {
	$value = strtolower( trim( $value ) );
	return trim( preg_replace( '/[^a-z0-9\p{L}]+/u', '-', $value ), '-' );
}

function remove_accents( string $value ): string {
	return $value;
}

function wp_strip_all_tags( string $value ): string {
	return trim( strip_tags( $value ) );
}

function wp_kses_post( string $value ): string {
	return strip_tags( $value, '<p><br><strong><em><a><ul><ol><li>' );
}

function wp_trim_words( string $value, int $count, string $more = '…' ): string {
	$words = preg_split( '/\s+/', trim( $value ) );
	if ( ! is_array( $words ) || count( $words ) <= $count ) {
		return $value;
	}
	return implode( ' ', array_slice( $words, 0, $count ) ) . $more;
}

function absint( $value ): int {
	return abs( (int) $value );
}

function esc_url_raw( string $url, array $protocols = array() ): string {
	$scheme = (string) parse_url( $url, PHP_URL_SCHEME );
	return filter_var( $url, FILTER_VALIDATE_URL ) && ( empty( $protocols ) || in_array( $scheme, $protocols, true ) ) ? $url : '';
}

function wp_parse_url( string $url, int $component = -1 ) {
	return parse_url( $url, $component );
}

function get_post_type( int $post_id ): string {
	return isset( $GLOBALS['mw_test_types'][ $post_id ] ) ? $GLOBALS['mw_test_types'][ $post_id ] : '';
}

/** @return array<int, string> */
function wp_get_post_terms( int $post_id, string $taxonomy, array $arguments = array() ): array {
	unset( $taxonomy, $arguments );
	return isset( $GLOBALS['mw_test_release_types'][ $post_id ] ) ? $GLOBALS['mw_test_release_types'][ $post_id ] : array();
}

function taxonomy_exists( string $taxonomy ): bool {
	return in_array( $taxonomy, array( 'mw_artist', 'mw_genre', 'mw_label', 'mw_mood', 'mw_release_type' ), true );
}

function get_term( int $term_id, string $taxonomy = '' ) {
	$term = isset( $GLOBALS['mw_test_terms'][ $term_id ] ) ? $GLOBALS['mw_test_terms'][ $term_id ] : null;
	return $term instanceof WP_Term && ( '' === $taxonomy || $term->taxonomy === $taxonomy ) ? $term : null;
}

function get_term_by( string $field, string $value, string $taxonomy ) {
	foreach ( $GLOBALS['mw_test_terms'] as $term ) {
		if ( $term instanceof WP_Term && $term->taxonomy === $taxonomy && isset( $term->{$field} ) && (string) $term->{$field} === $value ) {
			return $term;
		}
	}
	return false;
}

function get_terms( array $arguments = array() ) {
	$taxonomy = isset( $arguments['taxonomy'] ) ? (string) $arguments['taxonomy'] : '';
	$include  = isset( $arguments['include'] ) && is_array( $arguments['include'] ) ? array_map( 'absint', $arguments['include'] ) : array();
	$terms    = array_values(
		array_filter(
			$GLOBALS['mw_test_terms'],
			static function ( $term ) use ( $taxonomy, $include ): bool {
				return $term instanceof WP_Term
					&& ( '' === $taxonomy || $term->taxonomy === $taxonomy )
					&& ( empty( $include ) || in_array( $term->term_id, $include, true ) );
			}
		)
	);
	if ( isset( $arguments['meta_key'], $arguments['meta_value'] ) ) {
		$terms = array_values(
			array_filter(
				$terms,
				static function ( WP_Term $term ) use ( $arguments ): bool {
					return get_term_meta( $term->term_id, (string) $arguments['meta_key'], true ) === $arguments['meta_value'];
				}
			)
		);
	}
	if ( isset( $arguments['number'] ) ) {
		$terms = array_slice( $terms, 0, (int) $arguments['number'] );
	}
	if ( isset( $arguments['fields'] ) && 'names' === $arguments['fields'] ) {
		return array_map(
			static function ( WP_Term $term ): string {
				return $term->name;
			},
			$terms
		);
	}
	return $terms;
}

function wp_insert_term( string $name, string $taxonomy, array $arguments = array() ) {
	$term_id                              = empty( $GLOBALS['mw_test_terms'] ) ? 100 : max( array_keys( $GLOBALS['mw_test_terms'] ) ) + 1;
	$slug                                 = isset( $arguments['slug'] ) ? (string) $arguments['slug'] : sanitize_title( $name );
	$GLOBALS['mw_test_terms'][ $term_id ] = new WP_Term( $term_id, $taxonomy, $name, $slug );
	return array(
		'term_id'          => $term_id,
		'term_taxonomy_id' => $term_id,
	);
}

function wp_set_object_terms( int $post_id, array $term_ids, string $taxonomy, bool $append = false ) {
	unset( $append );
	$GLOBALS['mw_test_object_terms'][ $post_id ][ $taxonomy ] = array_values( array_map( 'absint', $term_ids ) );
	return $GLOBALS['mw_test_object_terms'][ $post_id ][ $taxonomy ];
}

function get_term_meta( int $term_id, string $key, bool $single = false ) {
	unset( $single );
	return isset( $GLOBALS['mw_test_term_meta'][ $term_id ][ $key ] ) ? $GLOBALS['mw_test_term_meta'][ $term_id ][ $key ] : '';
}

function update_term_meta( int $term_id, string $key, $value ) {
	$GLOBALS['mw_test_term_meta'][ $term_id ][ $key ] = $value;
	return true;
}

function register_term_meta( string $taxonomy, string $key, array $arguments ): bool {
	unset( $taxonomy, $key, $arguments );
	return true;
}

function get_post_meta( int $post_id, string $key, bool $single = false ) {
	unset( $single );
	return isset( $GLOBALS['mw_test_meta'][ $post_id ][ $key ] ) ? $GLOBALS['mw_test_meta'][ $post_id ][ $key ] : '';
}

function update_post_meta( int $post_id, string $key, $value ) {
	$GLOBALS['mw_test_meta'][ $post_id ][ $key ] = $value;
	return true;
}

function delete_post_meta( int $post_id, string $key ): bool {
	unset( $GLOBALS['mw_test_meta'][ $post_id ][ $key ] );
	return true;
}

function metadata_exists( string $meta_type, int $object_id, string $meta_key ): bool {
	unset( $meta_type );
	return array_key_exists( $meta_key, isset( $GLOBALS['mw_test_meta'][ $object_id ] ) ? $GLOBALS['mw_test_meta'][ $object_id ] : array() );
}

function get_option( string $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['mw_test_options'] ) ? $GLOBALS['mw_test_options'][ $key ] : $default;
}

function add_option( string $key, $value, string $deprecated = '', bool $autoload = true ): bool {
	unset( $deprecated, $autoload );
	if ( array_key_exists( $key, $GLOBALS['mw_test_options'] ) ) {
		return false;
	}
	$GLOBALS['mw_test_options'][ $key ] = $value;
	return true;
}

function delete_option( string $key ): bool {
	unset( $GLOBALS['mw_test_options'][ $key ] );
	return true;
}

function get_userdata( int $user_id ) {
	return isset( $GLOBALS['mw_test_users'][ $user_id ] ) ? $GLOBALS['mw_test_users'][ $user_id ] : false;
}

function get_user_meta( int $user_id, string $key, bool $single = false ) {
	unset( $single );
	return isset( $GLOBALS['mw_test_user_meta'][ $user_id ][ $key ] ) ? $GLOBALS['mw_test_user_meta'][ $user_id ][ $key ] : '';
}

function update_user_meta( int $user_id, string $key, $value ) {
	$GLOBALS['mw_test_user_meta'][ $user_id ][ $key ] = $value;
	return true;
}

function delete_user_meta( int $user_id, string $key ): bool {
	unset( $GLOBALS['mw_test_user_meta'][ $user_id ][ $key ] );
	return true;
}

function wc_customer_bought_product( string $email, int $user_id, int $product_id ): bool {
	unset( $email );
	$key = $user_id . ':' . $product_id;
	return ! empty( $GLOBALS['mw_test_purchases'][ $key ] );
}

require dirname( __DIR__ ) . '/music-wave-core/src/Support/Autoloader.php';
ManaCore\MusicWave\Core\Support\Autoloader::register();

final class TestReleaseRepository implements ManaCore\MusicWave\Core\Contracts\ReleaseRepository {
	/** @var array<int, int> */
	public $products = array();

	public function get( int $release_id, string $key ) {
		unset( $release_id, $key );
		return null;
	}

	public function update( int $release_id, string $key, $value ): bool {
		unset( $release_id, $key, $value );
		return true;
	}

	public function delete( int $release_id, string $key ): bool {
		unset( $release_id, $key );
		return true;
	}

	public function product_ids( int $release_id ): array {
		unset( $release_id );
		return $this->products;
	}

	public function collection_ids( int $release_id ): array {
		unset( $release_id );
		return array();
	}

	public function collection_items( int $collection_id ): array {
		unset( $collection_id );
		return array();
	}

	public function replace_collection_items( int $collection_id, array $items ): bool {
		unset( $collection_id, $items );
		return true;
	}
}

final class TestPolicyRepository implements ManaCore\MusicWave\Core\Contracts\ReleaseRepository {
	/** @var array<string, mixed> */
	public $values = array(
		'mw_access_mode'       => 'public',
		'mw_membership_levels' => array(),
	);

	public function get( int $release_id, string $key ) {
		unset( $release_id );
		return isset( $this->values[ $key ] ) ? $this->values[ $key ] : null;
	}

	public function update( int $release_id, string $key, $value ): bool {
		unset( $release_id );
		$this->values[ $key ] = $value;
		return true;
	}

	public function delete( int $release_id, string $key ): bool {
		unset( $release_id, $key );
		return true;
	}

	/** @return array<int, int> */
	public function product_ids( int $release_id ): array {
		unset( $release_id );
		return isset( $this->values['mw_product_ids'] ) ? $this->values['mw_product_ids'] : array( 10 );
	}

	/** @return array<int, int> */
	public function collection_ids( int $release_id ): array {
		unset( $release_id );
		return array();
	}

	/** @return array<int, array<string, int|string|null>> */
	public function collection_items( int $collection_id ): array {
		unset( $collection_id );
		return isset( $this->values['mw_collection_items'] ) && is_array( $this->values['mw_collection_items'] ) ? $this->values['mw_collection_items'] : array();
	}

	public function replace_collection_items( int $collection_id, array $items ): bool {
		$this->values['mw_collection_items'] = $items;
		unset( $collection_id );
		return true;
	}
}

final class TestMembershipProvider implements ManaCore\MusicWave\Core\Access\MembershipProvider {
	/** @var bool */
	public $granted = false;

	/** @param array<int, string> $levels */
	public function has_access( int $user_id, array $levels ): bool {
		return $this->granted && $user_id > 0 && ! empty( $levels );
	}
}

final class TestMigration implements ManaCore\MusicWave\Core\Contracts\Migration {
	/** @var string */
	private $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	public function version(): string {
		return $this->version;
	}

	public function up(): void {}
}

function mw_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
		fwrite( STDERR, 'Actual: ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}
}

$schema = new ManaCore\MusicWave\Core\Schema\ReleaseMetaSchema();
mw_assert_same( 24, count( $schema->all() ), 'Schema must expose every canonical release and metadata lookup field.' );
mw_assert_same( false, $schema->get( 'mw_product_ids' )->show_in_rest(), 'Product mappings must remain private.' );
mw_assert_same( 'Discovery', $schema->get( 'mw_album' )->sanitize( ' Discovery ' ), 'Album names must be safely normalized.' );
mw_assert_same( 2026, $schema->get( 'mw_release_year' )->sanitize( '2026' ), 'Release years must be stored as valid integers.' );
mw_assert_same( 0, $schema->get( 'mw_release_year' )->sanitize( '26' ), 'Malformed release years must be rejected.' );
mw_assert_same( '2026-08-06', $schema->get( 'mw_release_date' )->sanitize( '2026-08-06' ), 'Valid ISO dates must survive.' );
mw_assert_same( '', $schema->get( 'mw_release_date' )->sanitize( '2026-02-30' ), 'Invalid calendar dates must be rejected.' );
mw_assert_same( 'USRC17607839', $schema->get( 'mw_isrc' )->sanitize( 'US-RC1-76-07839' ), 'ISRC values must be normalized to their canonical form.' );
mw_assert_same( '', $schema->get( 'mw_isrc' )->sanitize( 'invalid' ), 'Malformed ISRC values must be rejected.' );
mw_assert_same( 0, $schema->get( 'mw_bpm' )->sanitize( 999 ), 'Out-of-range BPM must fail safely.' );
mw_assert_same( 12, $schema->get( 'mw_track_number' )->sanitize( '12' ), 'Track numbers must be normalized as integers.' );
mw_assert_same( array( 10, 11 ), $schema->get( 'mw_product_ids' )->sanitize( array( 10, '11', 10, 0 ) ), 'Product IDs must be normalized.' );
mw_assert_same( 'restricted', $schema->get( 'mw_access_mode' )->sanitize( 'unknown' ), 'Unknown access modes must fail closed.' );
mw_assert_same( 'restricted', $schema->get( 'mw_access_mode' )->sanitize( array( 'purchase' ) ), 'Malformed scalar access input must fail closed.' );
mw_assert_same( 30, $schema->get( 'mw_preview_duration' )->sanitize( '3' ), 'Preview limits below the safe range must use the default.' );
$year_only_result = ManaCore\MusicWave\Core\Metadata\MetadataResult::from_array(
	array(
		'title'        => 'Year-only release',
		'artist'       => 'Example Artist',
		'release_date' => '2024',
		'duration'     => 95,
	)
);
mw_assert_same( '2024', $year_only_result->year, 'A provider year must remain available for the dedicated year field.' );
mw_assert_same( '', $year_only_result->release_date, 'A provider year must not be converted into a fabricated January 1 date.' );
mw_assert_same( '', $year_only_result->date_for_meta(), 'Year-only results must not populate the full release date field.' );
mw_assert_same( 95, $year_only_result->duration, 'Normalized provider durations must remain in seconds.' );
$secure_cover_result = ManaCore\MusicWave\Core\Metadata\MetadataResult::from_array(
	array(
		'title'     => 'Secure cover',
		'artist'    => 'Example Artist',
		'cover_url' => 'http://coverartarchive.org/release/example/front-1200',
	)
);
mw_assert_same( 'https://coverartarchive.org/release/example/front-1200', $secure_cover_result->cover_url, 'Cover URLs must be upgraded to HTTPS for secure editor previews and imports.' );
$structured_result = ManaCore\MusicWave\Core\Metadata\MetadataResult::from_array(
	array(
		'title'       => 'Structured metadata',
		'artist'      => '',
		'description' => '<p>Useful factual description.</p><script>unsafe()</script>',
		'artists'     => array(
			array(
				'name'        => 'Artist One',
				'external_id' => 'artist-1',
			),
			array(
				'name'        => 'Artist One',
				'external_id' => 'duplicate',
			),
			array(
				'name'        => 'Artist Two',
				'external_id' => 'artist-2',
			),
		),
		'genres'      => array( 'Rock', 'Electronic' ),
		'moods'       => array( 'Energetic', 'Dreamy' ),
		'tags'        => array( 'seen live' ),
		'labels'      => array(
			array(
				'name'        => 'Example Label',
				'external_id' => 'label-1',
			),
		),
	)
);
mw_assert_same( 'Artist One, Artist Two', $structured_result->artist, 'Structured artists must populate the legacy display field without duplicates.' );
mw_assert_same( 2, count( $structured_result->genres ), 'Structured genre identities must be retained for taxonomy mapping.' );
mw_assert_same( 2, count( $structured_result->moods ), 'Structured mood identities must be retained for taxonomy mapping.' );
mw_assert_same( 1, count( $structured_result->tags ), 'Raw provider tags must remain separate from genres and moods.' );
mw_assert_same( false, false !== strpos( $structured_result->description, '<script>' ), 'Imported descriptions must remove unsafe markup.' );
$GLOBALS['mw_test_terms'][100]                                    = new WP_Term( 100, 'mw_genre', 'Rock', 'rock' );
$GLOBALS['mw_test_terms'][101]                                    = new WP_Term( 101, 'mw_artist', 'هنرمند نمونه', 'sample-artist-fa' );
$GLOBALS['mw_test_term_meta'][101]['_mw_metadata_musicbrainz_id'] = 'artist-mbid-1';
$taxonomy_mapper = new ManaCore\MusicWave\Core\Metadata\MetadataTaxonomyMapper();
$taxonomy_mapper->remember_previous_identity( 100, 'mw_genre' );
$GLOBALS['mw_test_terms'][100]->name = 'راک';
$GLOBALS['mw_test_terms'][100]->slug = 'rock-fa';
mw_assert_same( array( 'Rock', 'rock' ), get_term_meta( 100, '_mw_metadata_aliases', true ), 'Renaming a term must retain its previous visible identity automatically.' );
mw_assert_same(
	array( 100 ),
	$taxonomy_mapper->assign(
		1,
		'mw_genre',
		array(
			array(
				'name'        => 'Rock',
				'external_id' => 'genre-id-1',
			),
		),
		'musicbrainz'
	),
	'A translated genre must be matched through its stable metadata alias instead of creating a duplicate.'
);
mw_assert_same(
	array( 101 ),
	$taxonomy_mapper->assign(
		1,
		'mw_artist',
		array(
			array(
				'name'        => 'Completely Different Display Name',
				'external_id' => 'artist-mbid-1',
			),
		),
		'musicbrainz'
	),
	'A translated artist must be matched through its stable provider identity.'
);
$new_genre_ids = $taxonomy_mapper->assign(
	1,
	'mw_genre',
	array(
		array(
			'name'        => 'Synthwave',
			'external_id' => '',
		),
	),
	'musicbrainz'
);
mw_assert_same( 1, count( $new_genre_ids ), 'A confirmed provider genre must be created when it does not exist.' );
mw_assert_same( 'Synthwave', get_term( $new_genre_ids[0], 'mw_genre' )->name, 'The newly created genre must preserve the provider name.' );
$GLOBALS['mw_test_terms'][103]                             = new WP_Term( 103, 'mw_mood', 'آرام', 'calm-fa' );
$GLOBALS['mw_test_term_meta'][103]['_mw_metadata_aliases'] = array( 'Calm' );
mw_assert_same(
	array( 103 ),
	$taxonomy_mapper->resolve( 'mw_mood', array( array( 'name' => 'Calm' ) ), 'musicbrainz', false ),
	'An existing translated mood must be selected through its metadata alias.'
);
$term_count_before_raw_tag = count( $GLOBALS['mw_test_terms'] );
mw_assert_same(
	array(),
	$taxonomy_mapper->resolve( 'mw_mood', array( array( 'name' => 'seen live' ) ), 'musicbrainz', false ),
	'An unclassified raw provider tag must not create a mood.'
);
mw_assert_same( $term_count_before_raw_tag, count( $GLOBALS['mw_test_terms'] ), 'Resolve-only raw tags must not pollute taxonomies.' );
$query_with_types = ManaCore\MusicWave\Core\Metadata\MetadataQuery::from_strings( '', 'Example', '', '', '', array( 'podcast_episode', 'invalid' ) );
mw_assert_same( array( 'podcast_episode' ), $query_with_types->release_types, 'Release-type context must accept only canonical MusicWave types.' );
mw_assert_same( true, $query_with_types->is_podcast(), 'Podcast release types must be detectable by metadata providers.' );
$GLOBALS['mw_test_options'][ ManaCore\MusicWave\Core\Support\Settings::OPTION ] = array(
	'default_access_mode' => 'restricted',
	'archive_per_page'    => 24,
	'purchase_message'    => 'Existing purchase message',
);
$settings_update = ManaCore\MusicWave\Core\Support\Settings::sanitize(
	array(
		'archive_per_page' => 36,
	)
);
mw_assert_same( 'restricted', $settings_update['default_access_mode'], 'Saving one settings tab must preserve values from other tabs.' );
mw_assert_same( 36, $settings_update['archive_per_page'], 'Settings updates must sanitize and apply the submitted value.' );
mw_assert_same( 'Existing purchase message', $settings_update['purchase_message'], 'Partial settings submissions must preserve customer-facing messages.' );
$GLOBALS['mw_test_options'][ ManaCore\MusicWave\Core\Support\Settings::OPTION ] = array();

$GLOBALS['mw_test_types'][4] = 'mw_release';
$GLOBALS['mw_test_options'][ ManaCore\MusicWave\Core\Support\Settings::OPTION ] = array(
	'default_access_mode'      => 'restricted',
	'default_preview_duration' => 45,
);
$default_repository = new ManaCore\MusicWave\Core\Infrastructure\WordPressReleaseRepository( $schema );
$release_defaults   = new ManaCore\MusicWave\Core\Catalog\ReleaseDefaults( $default_repository );
$release_defaults->apply( 4, new WP_Post( 4, 'mw_release' ), false );
mw_assert_same( 'restricted', get_post_meta( 4, 'mw_access_mode', true ), 'New releases must persist the configured access default.' );
mw_assert_same( 45, get_post_meta( 4, 'mw_preview_duration', true ), 'New releases must persist the configured preview default.' );
$GLOBALS['mw_test_options'][ ManaCore\MusicWave\Core\Support\Settings::OPTION ] = array(
	'default_access_mode'      => 'public',
	'default_preview_duration' => 30,
);
$release_defaults->apply( 4, new WP_Post( 4, 'mw_release' ), true );
mw_assert_same( 'restricted', get_post_meta( 4, 'mw_access_mode', true ), 'Changing defaults must not rewrite an existing release.' );
$GLOBALS['mw_test_options'][ ManaCore\MusicWave\Core\Support\Settings::OPTION ] = array();
mw_assert_same(
	array(
		array(
			'key'      => 'mp3-320',
			'label'    => 'MP3 320 kbps',
			'asset_id' => 'local:downloads/track-320.mp3',
		),
	),
	$schema->get( 'mw_download_assets' )->sanitize(
		array(
			array(
				'key'      => 'mp3-320',
				'label'    => 'MP3 320 kbps',
				'asset_id' => 'local:downloads/track-320.mp3',
			),
			array(
				'key'      => 'mp3-320',
				'label'    => 'Duplicate',
				'asset_id' => 'local:downloads/duplicate.mp3',
			),
			array(
				'key'      => 'public',
				'label'    => 'Public URL',
				'asset_id' => 'https://example.test/file.mp3',
			),
		)
	),
	'Download quality variants must reject duplicates and public URLs.'
);
$quality_assets = array();
for ( $quality_index = 1; $quality_index <= 13; $quality_index++ ) {
	$quality_assets[] = array(
		'key'       => 'mp3-' . $quality_index,
		'label'     => 'MP3 ' . $quality_index,
		'asset_id'  => 'local:downloads/track-' . $quality_index . '.mp3',
		'file_name' => 'track ' . $quality_index . '.mp3',
		'format'    => 'MP3',
		'bitrate'   => '320',
		'duration'  => '225',
		'file_size' => '123456',
	);
}
$sanitized_quality_assets = $schema->get( 'mw_download_assets' )->sanitize( $quality_assets );
mw_assert_same( 13, count( $sanitized_quality_assets ), 'Download qualities must not have an arbitrary editor limit.' );
mw_assert_same(
	array(
		'key'       => 'mp3-1',
		'label'     => 'MP3 1',
		'asset_id'  => 'local:downloads/track-1.mp3',
		'format'    => 'mp3',
		'file_name' => 'track-1.mp3',
		'bitrate'   => 320,
		'duration'  => 225,
		'file_size' => 123456,
	),
	$sanitized_quality_assets[0],
	'Download quality metadata must be normalized and remain private.'
);
mw_assert_same(
	array(
		array(
			'key'        => 'song-a-mp3',
			'label'      => 'MP3 320 kbps',
			'asset_id'   => 'local:downloads/song-a.mp3',
			'file_key'   => 'song-a',
			'file_label' => 'Song A',
		),
		array(
			'key'      => 'incomplete-file-group',
			'label'    => 'Broken group',
			'asset_id' => 'local:downloads/broken.mp3',
		),
	),
	$schema->get( 'mw_download_assets' )->sanitize(
		array(
			array(
				'key'        => 'song-a-mp3',
				'label'      => 'MP3 320 kbps',
				'asset_id'   => 'local:downloads/song-a.mp3',
				'file_key'   => 'song-a',
				'file_label' => 'Song A',
			),
			array(
				'key'      => 'incomplete-file-group',
				'label'    => 'Broken group',
				'asset_id' => 'local:downloads/broken.mp3',
				'file_key' => 'broken',
			),
		)
	),
	'Each downloadable file must retain a valid title and stable group key without breaking legacy variants.'
);
mw_assert_same(
	array(
		array(
			'release_id' => 3,
			'position'   => 4,
			'disc'       => null,
			'role'       => 'track',
		),
	),
	$schema->get( 'mw_collection_items' )->sanitize(
		array(
			array(
				'release_id' => '3',
				'position'   => '4',
				'disc'       => '0',
				'role'       => 'track',
			),
			array(
				'release_id' => 'bad',
				'position'   => 2,
				'role'       => 'unknown',
			),
		)
	),
	'Collection relation values must be strictly normalized.'
);

mw_assert_same( 'latest', ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery::normalize_sort( 'not-a-sort' ), 'Unknown archive sorting must fall back safely.' );
mw_assert_same( 'title_desc', ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery::normalize_sort( 'TITLE_DESC' ), 'Archive sorting must normalize allow-listed values.' );
mw_assert_same(
	array(
		'orderby' => 'title',
		'order'   => 'ASC',
	),
	ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery::sort_arguments( 'title_asc' ),
	'Title sorting must use native WordPress ordering.'
);
mw_assert_same(
	array(
		'orderby' => 'date',
		'order'   => 'DESC',
	),
	ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery::sort_arguments( 'unexpected' ),
	'Invalid sorting arguments must use the default ordering.'
);

$mw_catalog_search                         = new WP_Query();
$mw_catalog_search->is_search              = true;
$mw_catalog_search->mw_test_archive_types  = array( 'mw_release' );
( new ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery() )->scope_archive_search( $mw_catalog_search );
mw_assert_same( false, $mw_catalog_search->is_search, 'Catalog search phrases must stay inside the release archive view.' );

$mw_global_search             = new WP_Query();
$mw_global_search->is_search  = true;
( new ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery() )->scope_archive_search( $mw_global_search );
mw_assert_same( true, $mw_global_search->is_search, 'Global searches must keep the standard search behavior.' );

$mw_secondary_search                         = new WP_Query();
$mw_secondary_search->is_search              = true;
$mw_secondary_search->mw_test_is_main        = false;
$mw_secondary_search->mw_test_archive_types  = array( 'mw_release' );
( new ManaCore\MusicWave\Core\Catalog\ReleaseArchiveQuery() )->scope_archive_search( $mw_secondary_search );
mw_assert_same( true, $mw_secondary_search->is_search, 'Secondary queries must never be adjusted by the catalog scoping.' );

$GLOBALS['mw_test_types'][2] = 'mw_release';
$GLOBALS['mw_test_types'][3] = 'mw_release';
$relation_repository         = new ManaCore\MusicWave\Core\Infrastructure\WordPressReleaseRepository( $schema );
mw_assert_same(
	true,
	$relation_repository->replace_collection_items(
		1,
		array(
			array(
				'release_id' => 2,
				'position'   => 1,
				'role'       => 'track',
			),
		)
	),
	'Collection relations must be persisted.'
);
mw_assert_same( array( 1 ), $relation_repository->collection_ids( 2 ), 'Collection reverse index must be maintained.' );
mw_assert_same( true, $relation_repository->remove_collection_item( 1, 2 ), 'Collection children must be removable.' );
mw_assert_same( array(), $relation_repository->collection_ids( 2 ), 'Removing a child must clear its reverse index.' );
$duplicate_rejected = false;
try {
	$relation_repository->replace_collection_items(
		1,
		array(
			array(
				'release_id' => 2,
				'position'   => 1,
				'role'       => 'track',
			),
			array(
				'release_id' => 2,
				'position'   => 2,
				'role'       => 'track',
			),
		)
	);
} catch ( InvalidArgumentException $exception ) {
	$duplicate_rejected = true;
}
mw_assert_same( true, $duplicate_rejected, 'Duplicate collection children must be rejected.' );

$token_service   = new ManaCore\MusicWave\Core\Downloads\DownloadTokenService( 'test-download-secret' );
$download_token  = $token_service->issue( 1, 7, 60, 'test-nonce', 'mp3-320' );
$download_claims = $token_service->verify( $download_token, 'test-nonce' );
mw_assert_same( 1, null === $download_claims ? 0 : $download_claims->release_id(), 'Signed download tokens must retain the release binding.' );
mw_assert_same( 'mp3-320', null === $download_claims ? '' : $download_claims->asset_key(), 'Signed download tokens must retain the selected quality.' );
mw_assert_same( null, $token_service->verify( $download_token . 'tampered', 'test-nonce' ), 'Modified download tokens must be rejected.' );
mw_assert_same( null, $token_service->verify( $download_token, 'wrong-nonce' ), 'Download tokens must be nonce-bound.' );
$replays = new ManaCore\MusicWave\Core\Downloads\TransientReplayStore();
mw_assert_same( true, $replays->consume( 'one-time-token', time() + 60 ), 'The first use of a download token must be accepted.' );
mw_assert_same( false, $replays->consume( 'one-time-token', time() + 60 ), 'Replay use of a download token must be rejected.' );

$runner  = new ManaCore\MusicWave\Core\Migrations\MigrationRunner( array( new TestMigration( '0.3.0' ), new TestMigration( '0.2.0' ) ) );
$pending = $runner->pending( '0.2.0' );
mw_assert_same( 1, count( $pending ), 'Only newer migrations should be pending.' );
mw_assert_same( '0.3.0', $pending[0]->version(), 'Migrations must be version sorted.' );
mw_assert_same( '0.8.0', ManaCore\MusicWave\Core\Migrations\MigrationRunner::LATEST_VERSION, 'Health checks must compare against the schema version rather than the plugin release version.' );

$mapper = new ManaCore\MusicWave\Core\Commerce\ProductMapper();
$mapper->sync_reverse_index( 1, array(), array( 10, 11 ) );
mw_assert_same( array( 1 ), get_post_meta( 10, '_mw_release_ids', true ), 'Product reverse index must be created.' );
$GLOBALS['mw_test_meta'][1]['mw_product_ids'] = array( 10, 11 );
$mapper->handle_deleted_post( 11 );
mw_assert_same( array( 10 ), get_post_meta( 1, 'mw_product_ids', true ), 'Deleting a product must clean release mappings.' );

$repository                           = new TestReleaseRepository();
$repository->products                 = array( 10 );
$checker                              = new ManaCore\MusicWave\Core\Commerce\PurchaseChecker( $repository );
$GLOBALS['mw_test_purchases']['7:10'] = true;
mw_assert_same( true, $checker->user_owns_release( 7, 1 ), 'A paid mapped product must grant ownership.' );
$GLOBALS['mw_test_purchases']['7:10'] = false;
mw_assert_same( false, $checker->user_owns_release( 7, 1 ), 'A refunded product must not grant ownership.' );
mw_assert_same( false, $checker->user_owns_release( 0, 1 ), 'Anonymous users must not gain purchase ownership.' );

$policy_repository = new TestPolicyRepository();
$membership        = new TestMembershipProvider();
$engine            = new ManaCore\MusicWave\Core\Access\AccessPolicyEngine( $policy_repository, $checker, $membership );
$anonymous         = new ManaCore\MusicWave\Core\Access\AccessSubject();
$customer          = new ManaCore\MusicWave\Core\Access\AccessSubject( 7 );
$administrator     = new ManaCore\MusicWave\Core\Access\AccessSubject( 1, array( 'administrator' ), array( 'manage_options' ) );
mw_assert_same( true, $engine->decide( 1, $anonymous )->is_allowed(), 'Public releases must allow anonymous visitors.' );
$policy_repository->values['mw_access_mode'] = 'restricted';
mw_assert_same( false, $engine->decide( 1, $anonymous )->is_allowed(), 'Restricted releases must deny anonymous visitors.' );
$policy_repository->values['mw_access_mode'] = 'purchase';
mw_assert_same( false, $engine->decide( 1, $customer )->is_allowed(), 'Unpurchased releases must deny customers.' );
$GLOBALS['mw_test_purchases']['7:10'] = true;
mw_assert_same( true, $engine->decide( 1, $customer )->is_allowed(), 'Purchased releases must allow customers.' );
$GLOBALS['mw_test_purchases']['7:10']              = false;
$policy_repository->values['mw_access_mode']       = 'membership';
$policy_repository->values['mw_membership_levels'] = array( 'gold' );
$membership->granted                               = true;
mw_assert_same( true, $engine->decide( 1, $customer )->is_allowed(), 'Membership provider grants membership releases.' );
$membership->granted                         = false;
$policy_repository->values['mw_access_mode'] = 'unknown';
mw_assert_same( false, $engine->decide( 1, $customer )->is_allowed(), 'Unknown access modes must deny by default.' );
mw_assert_same( true, $engine->decide( 1, $administrator )->is_allowed(), 'Administrator override must be explicit and auditable.' );

$library_repository = new ManaCore\MusicWave\Core\Library\LibraryRepository();
mw_assert_same( true, $library_repository->add( 7, 'release', 2 ), 'Valid releases must be addable to the personal library.' );
mw_assert_same( false, $library_repository->add( 7, 'release', 2 ), 'Duplicate personal library items must be rejected.' );
mw_assert_same( false, $library_repository->add( 7, 'release', 10 ), 'Non-release posts must not enter the personal library.' );
mw_assert_same( false, $library_repository->add( 7, 'unknown', 2 ), 'Unknown personal library item types must be rejected.' );
mw_assert_same( true, $library_repository->add( 7, 'artist', 101 ), 'Artist terms must be addable to the personal library.' );
mw_assert_same( false, $library_repository->add( 7, 'artist', 999 ), 'Unknown artists must not enter the personal library.' );
mw_assert_same( array( 2 ), $library_repository->ids( 7, 'release' ), 'Stored library release IDs must remain queryable by type.' );
mw_assert_same( 2, $library_repository->count( 7 ), 'Library counts must include every stored item type.' );
mw_assert_same( true, $library_repository->remove( 7, 'release', 2 ), 'Stored library items must be removable.' );
mw_assert_same( false, $library_repository->has( 7, 'release', 2 ), 'Removed library items must no longer report as saved.' );

require dirname( __DIR__ ) . '/music-wave-core/music-wave-core.php';
mw_assert_same( true, in_array( 'music_wave_core_loaded', $GLOBALS['mw_test_actions'], true ), 'Plugin composition root must boot successfully.' );

require __DIR__ . '/template-integrity.php';

echo 'MusicWave domain smoke tests passed.' . PHP_EOL;
