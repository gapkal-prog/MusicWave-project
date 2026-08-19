<?php
/**
 * Playlist presentation blocks.
 *
 * Both blocks render complete, usable HTML on the server: every control is a
 * form posted to `admin-post.php`, so creating, renaming, sharing, reordering,
 * and deleting playlists works with JavaScript disabled. Reordering uses
 * accessible move-up/move-down buttons rather than drag-and-drop only
 * (PROJECT_PLAN.md Stage 5 deliverable 4).
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Blocks;

use ManaCore\MusicWave\Core\Catalog\ReleasePostType;
use ManaCore\MusicWave\Core\Playlists\PlaylistFormHandler;
use ManaCore\MusicWave\Core\Playlists\PlaylistRepository;

final class PlaylistBlocks {
	/** @var PlaylistRepository */
	private $repository;

	/** @var PlaylistFormHandler */
	private $forms;

	/** @var object|null */
	private $render_context;

	public function __construct( PlaylistRepository $repository, PlaylistFormHandler $forms ) {
		$this->repository = $repository;
		$this->forms      = $forms;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		BlockSupport::register_dynamic(
			'music-wave/playlists',
			function ( $attributes ): string {
				return $this->render_manager( is_array( $attributes ) ? $attributes : array() );
			},
			array(
				'api_version' => 3,
				'supports'    => BlockSupport::appearance_tools(),
			)
		);
		BlockSupport::register_dynamic(
			'music-wave/add-to-playlist',
			function ( $attributes, $content, $block ): string {
				unset( $content );
				$previous             = $this->render_context;
				$this->render_context = is_object( $block ) ? $block : null;

				try {
					return $this->render_picker( is_array( $attributes ) ? $attributes : array() );
				} finally {
					$this->render_context = $previous;
				}
			},
			array(
				'api_version'  => 3,
				'uses_context' => array( 'postId', 'postType' ),
				'supports'     => BlockSupport::appearance_tools(),
			)
		);
	}

	/**
	 * Render the playlist manager.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_manager( array $attributes ): string {
		$user_id = get_current_user_id();
		$heading = $this->text( $attributes, 'heading', __( 'Your playlists', 'music-wave-core' ) );

		if ( $user_id < 1 ) {
			return '<section ' . BlockSupport::wrapper_attributes( 'mw-playlists mw-playlists--guest' ) . '>'
				. '<h2 class="mw-playlists__title">' . esc_html( $heading ) . '</h2>'
				. '<p>' . esc_html__( 'Sign in to build playlists from your catalog.', 'music-wave-core' ) . '</p>'
				. '<a class="wp-element-button" href="' . esc_url( wp_login_url( $this->current_url() ) ) . '">' . esc_html__( 'Sign in', 'music-wave-core' ) . '</a>'
				. '</section>';
		}

		$playlists = $this->repository->for_user( $user_id, PlaylistRepository::MAX_PLAYLISTS );
		$expanded  = $this->requested_playlist();

		$rows = array();
		foreach ( $playlists as $playlist ) {
			$rows[] = $this->playlist_markup( $playlist, $user_id, (int) $playlist['id'] === $expanded );
		}

		$body = array() === $rows
			? '<p class="mw-playlists__empty">' . esc_html__( 'You have no playlists yet. Create your first one below.', 'music-wave-core' ) . '</p>'
			: '<ul class="mw-playlists__list">' . implode( '', $rows ) . '</ul>';

		return '<section ' . BlockSupport::wrapper_attributes( 'mw-playlists' ) . '>'
			. '<h2 class="mw-playlists__title">' . esc_html( $heading ) . '</h2>'
			. $this->notice_markup()
			. $body
			. $this->create_form()
			. '</section>';
	}

	/**
	 * Render the add-to-playlist picker for one release.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function render_picker( array $attributes ): string {
		$release_id = $this->release_id( $attributes );
		if ( $release_id < 1 ) {
			return '';
		}

		$user_id = get_current_user_id();
		$label   = $this->text( $attributes, 'label', __( 'Add to playlist', 'music-wave-core' ) );
		if ( $user_id < 1 ) {
			return '<div ' . BlockSupport::wrapper_attributes( 'mw-playlist-picker mw-playlist-picker--guest' ) . '>'
				. '<a class="wp-element-button" href="' . esc_url( wp_login_url( $this->current_url() ) ) . '">' . esc_html( $label ) . '</a>'
				. '</div>';
		}

		$playlists = $this->repository->for_user( $user_id, PlaylistRepository::MAX_PLAYLISTS );
		$field_id  = 'mw-playlist-picker-' . $release_id;

		if ( array() === $playlists ) {
			// No playlists yet: create one and add the release in a single post.
			return '<div ' . BlockSupport::wrapper_attributes( 'mw-playlist-picker' ) . '>'
				. $this->notice_markup()
				. '<form class="mw-playlist-picker__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
				. $this->hidden_fields( 'create' )
				. '<label for="' . esc_attr( $field_id ) . '">' . esc_html__( 'New playlist name', 'music-wave-core' ) . '</label>'
				. '<input id="' . esc_attr( $field_id ) . '" type="text" name="mw_title" maxlength="' . esc_attr( (string) PlaylistRepository::MAX_TITLE ) . '" required>'
				. '<button type="submit">' . esc_html__( 'Create playlist', 'music-wave-core' ) . '</button>'
				. '</form></div>';
		}

		$options = '';
		foreach ( $playlists as $playlist ) {
			$options .= '<option value="' . esc_attr( (string) $playlist['id'] ) . '">' . esc_html( (string) $playlist['title'] ) . '</option>';
		}

		return '<div ' . BlockSupport::wrapper_attributes( 'mw-playlist-picker' ) . '>'
			. $this->notice_markup()
			. '<form class="mw-playlist-picker__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. $this->hidden_fields( 'add-item' )
			. '<input type="hidden" name="mw_release_id" value="' . esc_attr( (string) $release_id ) . '">'
			. '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>'
			. '<select id="' . esc_attr( $field_id ) . '" name="mw_playlist_id">' . $options . '</select>'
			. '<button type="submit">' . esc_html__( 'Add', 'music-wave-core' ) . '</button>'
			. '</form></div>';
	}

	/**
	 * One playlist row with its controls and, when expanded, its items.
	 *
	 * @param array<string, mixed> $playlist Normalized playlist.
	 */
	private function playlist_markup( array $playlist, int $user_id, bool $expanded ): string {
		$playlist_id = (int) $playlist['id'];
		$title       = (string) $playlist['title'];
		$visibility  = (string) $playlist['visibility'];
		$count       = isset( $playlist['count'] ) ? (int) $playlist['count'] : 0;
		$panel_id    = 'mw-playlist-panel-' . $playlist_id;

		$items_markup = '';
		if ( $expanded ) {
			$view         = $this->repository->view( $playlist_id, $user_id );
			$items_markup = null !== $view ? $this->items_markup( $playlist_id, $view ) : '';
		}

		$toggle = '<a class="mw-playlists__toggle" href="' . esc_url( $this->playlist_url( $expanded ? 0 : $playlist_id ) ) . '#' . esc_attr( $panel_id ) . '" aria-expanded="' . ( $expanded ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $panel_id ) . '">'
			. esc_html( $expanded ? __( 'Hide tracks', 'music-wave-core' ) : __( 'Show tracks', 'music-wave-core' ) )
			. '</a>';

		return '<li class="mw-playlists__item">'
			. '<div class="mw-playlists__row">'
			. '<h3 class="mw-playlists__name">' . esc_html( $title ) . '</h3>'
			. '<p class="mw-playlists__meta">'
			. '<span class="mw-playlists__badge">' . esc_html( $this->visibility_label( $visibility ) ) . '</span> '
			. '<span class="mw-playlists__count">' . esc_html(
				sprintf(
					/* translators: %d: number of releases in the playlist. */
					_n( '%d release', '%d releases', $count, 'music-wave-core' ),
					$count
				)
			) . '</span>'
			. '</p>'
			. $toggle
			. '</div>'
			. $this->settings_form( $playlist )
			. $this->share_markup( $playlist )
			. '<div class="mw-playlists__panel" id="' . esc_attr( $panel_id ) . '"' . ( $expanded ? '' : ' hidden' ) . '>' . $items_markup . '</div>'
			. '</li>';
	}

	/**
	 * Ordered items of one playlist with accessible reorder controls.
	 *
	 * @param array<string, mixed> $view Playlist view payload.
	 */
	private function items_markup( int $playlist_id, array $view ): string {
		$items = isset( $view['items'] ) && is_array( $view['items'] ) ? $view['items'] : array();
		if ( array() === $items ) {
			return '<p class="mw-playlists__empty">' . esc_html__( 'This playlist is empty. Use “Add to playlist” on any release.', 'music-wave-core' ) . '</p>';
		}

		$total = count( $items );
		$rows  = array();
		foreach ( $items as $index => $item ) {
			$release_id = (int) $item['release_id'];
			$title      = get_the_title( $release_id );
			$title      = '' !== $title ? $title : __( 'Untitled release', 'music-wave-core' );
			$permalink  = get_permalink( $release_id );

			$controls = '';
			if ( $index > 0 ) {
				$controls .= $this->item_button( $playlist_id, $release_id, 'move-up', __( 'Move up', 'music-wave-core' ), $title );
			}
			if ( $index < $total - 1 ) {
				$controls .= $this->item_button( $playlist_id, $release_id, 'move-down', __( 'Move down', 'music-wave-core' ), $title );
			}
			$controls .= $this->item_button( $playlist_id, $release_id, 'remove-item', __( 'Remove', 'music-wave-core' ), $title );

			$rows[] = '<li class="mw-playlists__track">'
				. '<span class="mw-playlists__position" aria-hidden="true">' . esc_html( (string) ( (int) $index + 1 ) ) . '</span>'
				. '<span class="mw-playlists__track-title">'
				. ( is_string( $permalink ) && '' !== $permalink ? '<a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) )
				. '</span>'
				. '<span class="mw-playlists__track-actions">' . $controls . '</span>'
				. '</li>';
		}

		return '<ol class="mw-playlists__tracks">' . implode( '', $rows ) . '</ol>';
	}

	/**
	 * One item-scoped submit button carrying an accessible name.
	 */
	private function item_button( int $playlist_id, int $release_id, string $operation, string $label, string $title ): string {
		$accessible = sprintf(
			/* translators: 1: action such as Move up, 2: release title. */
			_x( '%1$s: %2$s', 'playlist item action', 'music-wave-core' ),
			$label,
			$title
		);

		return '<form class="mw-playlists__action" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. $this->hidden_fields( $operation )
			. '<input type="hidden" name="mw_playlist_id" value="' . esc_attr( (string) $playlist_id ) . '">'
			. '<input type="hidden" name="mw_release_id" value="' . esc_attr( (string) $release_id ) . '">'
			. '<button type="submit"><span aria-hidden="true">' . esc_html( $label ) . '</span><span class="screen-reader-text">' . esc_html( $accessible ) . '</span></button>'
			. '</form>';
	}

	/**
	 * Rename/visibility/delete controls for one playlist.
	 *
	 * @param array<string, mixed> $playlist Normalized playlist.
	 */
	private function settings_form( array $playlist ): string {
		$playlist_id = (int) $playlist['id'];
		$title_id    = 'mw-playlist-title-' . $playlist_id;
		$vis_id      = 'mw-playlist-visibility-' . $playlist_id;
		$options     = '';
		foreach ( $this->repository->visibilities() as $value ) {
			$options .= '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $playlist['visibility'], $value, false ) . '>' . esc_html( $this->visibility_label( $value ) ) . '</option>';
		}

		return '<form class="mw-playlists__settings" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. $this->hidden_fields( 'update' )
			. '<input type="hidden" name="mw_playlist_id" value="' . esc_attr( (string) $playlist_id ) . '">'
			. '<label for="' . esc_attr( $title_id ) . '">' . esc_html__( 'Playlist name', 'music-wave-core' ) . '</label>'
			. '<input id="' . esc_attr( $title_id ) . '" type="text" name="mw_title" value="' . esc_attr( (string) $playlist['title'] ) . '" maxlength="' . esc_attr( (string) PlaylistRepository::MAX_TITLE ) . '">'
			. '<label for="' . esc_attr( $vis_id ) . '">' . esc_html__( 'Who can see it', 'music-wave-core' ) . '</label>'
			. '<select id="' . esc_attr( $vis_id ) . '" name="mw_visibility">' . $options . '</select>'
			. '<button type="submit">' . esc_html__( 'Save', 'music-wave-core' ) . '</button>'
			. '</form>'
			. '<form class="mw-playlists__delete" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. $this->hidden_fields( 'delete' )
			. '<input type="hidden" name="mw_playlist_id" value="' . esc_attr( (string) $playlist_id ) . '">'
			. '<button type="submit">'
			. '<span aria-hidden="true">' . esc_html__( 'Delete', 'music-wave-core' ) . '</span>'
			. '<span class="screen-reader-text">' . esc_html(
				sprintf(
					/* translators: %s: playlist name. */
					__( 'Delete playlist: %s', 'music-wave-core' ),
					(string) $playlist['title']
				)
			) . '</span>'
			. '</button>'
			. '</form>';
	}

	/**
	 * Share link for a shared playlist, shown to the owner only.
	 *
	 * @param array<string, mixed> $playlist Normalized playlist.
	 */
	private function share_markup( array $playlist ): string {
		$token = (string) $playlist['share_token'];
		if ( PlaylistRepository::VISIBILITY_PRIVATE === (string) $playlist['visibility'] || '' === $token ) {
			return '';
		}

		$url = add_query_arg(
			array(
				'mw-playlist' => (string) $playlist['id'],
				'mw-share'    => $token,
			),
			$this->current_url()
		);

		return '<p class="mw-playlists__share"><label>' . esc_html__( 'Share link', 'music-wave-core' )
			. '<input type="text" readonly value="' . esc_attr( $url ) . '" spellcheck="false"></label>'
			. '<span class="mw-playlists__share-hint">' . esc_html__( 'Anyone with this link can open the playlist. Switch the playlist back to private to revoke it.', 'music-wave-core' ) . '</span></p>';
	}

	/**
	 * Create-playlist form.
	 */
	private function create_form(): string {
		return '<form class="mw-playlists__create" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. $this->hidden_fields( 'create' )
			. '<label for="mw-playlist-new-title">' . esc_html__( 'New playlist name', 'music-wave-core' ) . '</label>'
			. '<input id="mw-playlist-new-title" type="text" name="mw_title" maxlength="' . esc_attr( (string) PlaylistRepository::MAX_TITLE ) . '" required>'
			. '<button type="submit">' . esc_html__( 'Create playlist', 'music-wave-core' ) . '</button>'
			. '</form>';
	}

	/**
	 * Shared hidden fields for every playlist form.
	 */
	private function hidden_fields( string $operation ): string {
		return wp_nonce_field( PlaylistFormHandler::NONCE, '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="' . esc_attr( PlaylistFormHandler::ACTION ) . '">'
			. '<input type="hidden" name="mw_operation" value="' . esc_attr( $operation ) . '">'
			. '<input type="hidden" name="mw_redirect" value="' . esc_attr( $this->current_url() ) . '">';
	}

	/**
	 * Post/redirect/get notice rendered in a polite live region.
	 */
	private function notice_markup(): string {
		// Read-only presentation of a redirect marker; no state changes here.
		$notice  = isset( $_GET[ PlaylistFormHandler::NOTICE_ARG ] ) && is_scalar( $_GET[ PlaylistFormHandler::NOTICE_ARG ] ) ? sanitize_key( wp_unslash( (string) $_GET[ PlaylistFormHandler::NOTICE_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = '' !== $notice ? $this->forms->notice_message( $notice ) : '';
		if ( '' === $message ) {
			return '';
		}

		$class = 'mw-playlists__notice' . ( $this->forms->notice_is_error( $notice ) ? ' mw-playlists__notice--error' : '' );

		return '<p class="' . esc_attr( $class ) . '" role="status" aria-live="polite">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Playlist expanded through the query string, when any.
	 */
	private function requested_playlist(): int {
		// Read-only presentation state from the query string.
		return isset( $_GET[ PlaylistFormHandler::CURRENT_ARG ] ) ? absint( wp_unslash( (string) $_GET[ PlaylistFormHandler::CURRENT_ARG ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Current URL with the expanded-playlist argument set or cleared.
	 */
	private function playlist_url( int $playlist_id ): string {
		$base = remove_query_arg( array( PlaylistFormHandler::CURRENT_ARG, PlaylistFormHandler::NOTICE_ARG ), $this->current_url() );

		return $playlist_id > 0 ? add_query_arg( array( PlaylistFormHandler::CURRENT_ARG => (string) $playlist_id ), $base ) : $base;
	}

	private function visibility_label( string $visibility ): string {
		if ( PlaylistRepository::VISIBILITY_PUBLIC === $visibility ) {
			return __( 'Public', 'music-wave-core' );
		}
		if ( PlaylistRepository::VISIBILITY_UNLISTED === $visibility ) {
			return __( 'Anyone with the link', 'music-wave-core' );
		}

		return __( 'Private', 'music-wave-core' );
	}

	/**
	 * Resolve the release the picker acts on.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function release_id( array $attributes ): int {
		$explicit = isset( $attributes['releaseId'] ) ? absint( $attributes['releaseId'] ) : 0;
		if ( $explicit > 0 ) {
			return $explicit;
		}

		$context = $this->render_context;
		if ( is_object( $context ) && isset( $context->context ) && is_array( $context->context ) ) {
			$post_type = isset( $context->context['postType'] ) ? (string) $context->context['postType'] : '';
			$post_id   = isset( $context->context['postId'] ) ? absint( $context->context['postId'] ) : 0;
			if ( ReleasePostType::KEY === $post_type && $post_id > 0 ) {
				return $post_id;
			}
		}

		$current = function_exists( 'get_the_ID' ) ? absint( get_the_ID() ) : 0;

		return ReleasePostType::KEY === get_post_type( $current ) ? $current : 0;
	}

	private function current_url(): string {
		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$permalink = get_permalink();
			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) && is_scalar( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '';

		return '' !== $request ? home_url( $request ) : home_url( '/' );
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function text( array $attributes, string $key, string $default_value ): string {
		$value = isset( $attributes[ $key ] ) && is_scalar( $attributes[ $key ] ) ? sanitize_text_field( (string) $attributes[ $key ] ) : '';

		return '' !== $value ? $value : $default_value;
	}
}
