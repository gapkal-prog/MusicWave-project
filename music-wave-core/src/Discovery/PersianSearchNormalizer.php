<?php
/**
 * Script-aware normalization for Persian/Arabic search phrases.
 *
 * WordPress compares search terms byte-for-byte with `LIKE`, which silently
 * breaks Persian queries: keyboards, operating systems and copy/paste sources
 * disagree on Arabic vs Persian «ی/ي» and «ک/ك», on «ه/ة», on the
 * zero-width non-joiner versus a space («می‌خواهم» vs «می خواهم»), on
 * Persian/Arabic-Indic digits, and on optional diacritics (harakat) or
 * tatweel. A visitor typing «موسيقي» must still find «موسیقی».
 *
 * This class is pure (no WordPress calls) so it can be unit-tested and reused
 * by the main search query, the autocomplete route and any future indexer.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Discovery;

final class PersianSearchNormalizer {
	/** Zero-width non-joiner (U+200C) as it appears in stored content. */
	public const ZWNJ = "\u{200C}";

	/**
	 * Characters that are folded to a canonical Persian form.
	 *
	 * @var array<string, string>
	 */
	private const LETTER_MAP = array(
		"\u{064A}" => "\u{06CC}", // Arabic yeh → Farsi yeh.
		"\u{0649}" => "\u{06CC}", // Alef maksura → Farsi yeh.
		"\u{06D2}" => "\u{06CC}", // Yeh barree → Farsi yeh.
		"\u{0643}" => "\u{06A9}", // Arabic kaf → Keheh.
		"\u{0629}" => "\u{0647}", // Teh marbuta → Heh.
		"\u{06C0}" => "\u{0647}", // Heh with yeh above → Heh.
		"\u{0624}" => "\u{0648}", // Waw with hamza → Waw.
		"\u{0625}" => "\u{0627}", // Alef with hamza below → Alef.
		"\u{0623}" => "\u{0627}", // Alef with hamza above → Alef.
		"\u{0622}" => "\u{0627}", // Alef with madda → Alef (search tolerance for آ/ا).
		"\u{0626}" => "\u{06CC}", // Yeh with hamza → Farsi yeh.
	);

	/**
	 * Persian and Arabic-Indic digits mapped to ASCII.
	 *
	 * @var array<string, string>
	 */
	private const DIGIT_MAP = array(
		"\u{06F0}" => '0',
		"\u{06F1}" => '1',
		"\u{06F2}" => '2',
		"\u{06F3}" => '3',
		"\u{06F4}" => '4',
		"\u{06F5}" => '5',
		"\u{06F6}" => '6',
		"\u{06F7}" => '7',
		"\u{06F8}" => '8',
		"\u{06F9}" => '9',
		"\u{0660}" => '0',
		"\u{0661}" => '1',
		"\u{0662}" => '2',
		"\u{0663}" => '3',
		"\u{0664}" => '4',
		"\u{0665}" => '5',
		"\u{0666}" => '6',
		"\u{0667}" => '7',
		"\u{0668}" => '8',
		"\u{0669}" => '9',
	);

	/**
	 * Diacritics, tatweel and invisible formatting characters that never
	 * change the meaning of a search phrase.
	 */
	private const STRIP_PATTERN = '/[\x{064B}-\x{0652}\x{0653}-\x{0655}\x{0670}\x{0640}\x{200B}\x{200D}\x{200E}\x{200F}\x{FEFF}\x{202A}-\x{202E}]/u';

	/**
	 * Canonical form of a phrase: folded letters, ASCII digits, no diacritics,
	 * ZWNJ treated as a space, collapsed whitespace, lower-cased Latin.
	 */
	public static function normalize( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$value = self::fold( $value );
		$value = str_replace( self::ZWNJ, ' ', $value );
		$value = (string) preg_replace( '/\s+/u', ' ', $value );
		$value = trim( $value );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/**
	 * Fold letters and digits without touching spacing.
	 *
	 * Keeps the ZWNJ so callers can derive both the joined and the spaced
	 * spelling of a word.
	 */
	public static function fold( string $value ): string {
		$value = (string) preg_replace( self::STRIP_PATTERN, '', $value );
		$value = strtr( $value, self::LETTER_MAP );

		return strtr( $value, self::DIGIT_MAP );
	}

	/**
	 * Whether the phrase contains Arabic-script characters.
	 *
	 * Latin-only phrases keep the untouched WordPress behaviour so the
	 * expansion cost is paid only where it helps.
	 */
	public static function has_arabic_script( string $value ): bool {
		return 1 === preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $value );
	}

	/**
	 * Whether two phrases are the same once normalized.
	 */
	public static function equals( string $a, string $b ): bool {
		return self::normalize( $a ) === self::normalize( $b );
	}

	/**
	 * Whether the normalized needle occurs inside the normalized haystack.
	 */
	public static function contains( string $haystack, string $needle ): bool {
		$needle = self::normalize( $needle );
		if ( '' === $needle ) {
			return false;
		}

		// Byte-wise strpos() is exact for UTF-8 containment, so the mbstring
		// variant is only a preference (WordPress polyfills mb_substr, not mb_strpos).
		return function_exists( 'mb_strpos' )
			? false !== mb_strpos( self::normalize( $haystack ), $needle )
			: false !== strpos( self::normalize( $haystack ), $needle );
	}

	/**
	 * Every spelling a stored value may use for one search word.
	 *
	 * The database keeps the original bytes, so the query must try each
	 * plausible representation of the typed word: Persian and Arabic yeh/kaf,
	 * ZWNJ vs space vs joined, and Persian/Arabic digits. The result is
	 * bounded and deduplicated so the SQL stays small.
	 *
	 * @return array<int, string>
	 */
	public static function variants( string $word ): array {
		$word = trim( (string) preg_replace( '/\s+/u', ' ', self::fold( $word ) ) );
		if ( '' === $word ) {
			return array();
		}

		$spacings = array( $word );
		if ( false !== strpos( $word, self::ZWNJ ) ) {
			$spacings[] = str_replace( self::ZWNJ, ' ', $word );
			$spacings[] = str_replace( self::ZWNJ, '', $word );
		} elseif ( false !== strpos( $word, ' ' ) ) {
			$spacings[] = str_replace( ' ', self::ZWNJ, $word );
			$spacings[] = str_replace( ' ', '', $word );
		}

		$variants = array();
		foreach ( $spacings as $spelling ) {
			foreach ( self::script_forms( $spelling ) as $form ) {
				if ( ! in_array( $form, $variants, true ) ) {
					$variants[] = $form;
				}
			}
		}

		return $variants;
	}

	/**
	 * Split a phrase into search words, keeping quoted phrases intact.
	 *
	 * Mirrors the WordPress tokenizer closely enough for OR/AND expansion:
	 * ZWNJ stays inside a word so «می‌خواهم» is one token.
	 *
	 * @return array<int, string>
	 */
	public static function words( string $phrase ): array {
		$phrase = str_replace( array( "\r", "\n" ), ' ', $phrase );
		if ( ! preg_match_all( '/"([^"]+)"|[^\s",+]+/u', $phrase, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$words = array();
		foreach ( $matches as $match ) {
			$word = isset( $match[1] ) && '' !== $match[1] ? $match[1] : $match[0];
			$word = trim( $word, "\"' " );
			if ( '' === $word || in_array( $word, $words, true ) ) {
				continue;
			}
			$words[] = $word;
		}

		return $words;
	}

	/**
	 * Persian, Arabic and digit-script spellings of one already-folded word.
	 *
	 * @return array<int, string>
	 */
	private static function script_forms( string $word ): array {
		$persian = $word;
		$arabic  = strtr(
			$word,
			array(
				"\u{06CC}" => "\u{064A}",
				"\u{06A9}" => "\u{0643}",
			)
		);

		$forms = array( $persian );
		if ( $arabic !== $persian ) {
			$forms[] = $arabic;
		}

		if ( 1 === preg_match( '/[0-9]/', $word ) ) {
			$persian_digits = strtr( $word, array_flip( array_slice( self::DIGIT_MAP, 0, 10, true ) ) );
			$arabic_digits  = strtr( $word, array_flip( array_slice( self::DIGIT_MAP, 10, 10, true ) ) );
			foreach ( array( $persian_digits, $arabic_digits ) as $digit_form ) {
				if ( ! in_array( $digit_form, $forms, true ) ) {
					$forms[] = $digit_form;
				}
			}
		}

		return $forms;
	}
}
