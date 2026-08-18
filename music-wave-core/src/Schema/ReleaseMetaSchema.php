<?php
/**
 * Canonical release metadata schema.
 *
 * @package ManaCore\MusicWave\Core
 */

declare(strict_types=1);

namespace ManaCore\MusicWave\Core\Schema;

final class ReleaseMetaSchema {
	/** @var array<string, MetaDefinition>|null */
	private $definitions;

	/** @return array<string, MetaDefinition> */
	public function all(): array {
		if ( null === $this->definitions ) {
			$this->definitions = array(
				'mw_catalog_number'    => new MetaDefinition(
					'mw_catalog_number',
					'string',
					'',
					true,
					array(
						'label' => __( 'Catalog number', 'music-wave-core' ),
						'type'  => 'text',
						'group' => 'release',
					)
				),
				'mw_album'             => new MetaDefinition(
					'mw_album',
					'string',
					'',
					true,
					array(
						'label' => __( 'Album', 'music-wave-core' ),
						'type'  => 'text',
						'group' => 'release',
					)
				),
				'mw_release_year'      => new MetaDefinition(
					'mw_release_year',
					'integer',
					0,
					true,
					array(
						'label' => __( 'Release year', 'music-wave-core' ),
						'type'  => 'number',
						'min'   => 1000,
						'max'   => 9999,
						'group' => 'release',
					)
				),
				'mw_release_date'      => new MetaDefinition(
					'mw_release_date',
					'string',
					'',
					true,
					array(
						'label' => __( 'Release date', 'music-wave-core' ),
						'type'  => 'date',
						'group' => 'release',
					)
				),
				'mw_isrc'              => new MetaDefinition(
					'mw_isrc',
					'string',
					'',
					true,
					array(
						'label' => __( 'ISRC', 'music-wave-core' ),
						'type'  => 'text',
						'group' => 'release',
					)
				),
				'mw_duration'          => new MetaDefinition(
					'mw_duration',
					'integer',
					0,
					true,
					array(
						'label' => __( 'Duration in seconds', 'music-wave-core' ),
						'type'  => 'number',
						'min'   => 0,
						'group' => 'media',
					)
				),
				'mw_preview_duration'  => new MetaDefinition(
					'mw_preview_duration',
					'integer',
					30,
					true,
					array(
						'label' => __( 'Preview duration in seconds', 'music-wave-core' ),
						'type'  => 'number',
						'min'   => 10,
						'max'   => 120,
						'group' => 'media',
					)
				),
				'mw_bpm'               => new MetaDefinition(
					'mw_bpm',
					'integer',
					0,
					true,
					array(
						'label' => __( 'BPM', 'music-wave-core' ),
						'type'  => 'number',
						'min'   => 20,
						'max'   => 300,
						'group' => 'media',
					)
				),
				'mw_musical_key'       => new MetaDefinition(
					'mw_musical_key',
					'string',
					'',
					true,
					array(
						'label' => __( 'Musical key', 'music-wave-core' ),
						'type'  => 'text',
						'group' => 'media',
					)
				),
				'mw_explicit'          => new MetaDefinition(
					'mw_explicit',
					'boolean',
					false,
					true,
					array(
						'label' => __( 'Contains explicit content', 'music-wave-core' ),
						'type'  => 'checkbox',
						'group' => 'release',
					)
				),
				'mw_preview_url'       => new MetaDefinition(
					'mw_preview_url',
					'string',
					'',
					true,
					array(
						'label' => __( 'HTTPS preview URL', 'music-wave-core' ),
						'type'  => 'url',
						'group' => 'media',
					)
				),
				'mw_track_number'      => new MetaDefinition(
					'mw_track_number',
					'integer',
					0,
					true,
					array(
						'label' => __( 'Track number', 'music-wave-core' ),
						'type'  => 'number',
						'min'   => 0,
						'group' => 'track',
					)
				),
				'mw_episode_number'    => new MetaDefinition(
					'mw_episode_number',
					'integer',
					0,
					true,
					array(
						'label' => __( 'Episode number', 'music-wave-core' ),
						'type'  => 'number',
						'min'   => 0,
						'group' => 'podcast',
					)
				),
				'mw_season_number'     => new MetaDefinition(
					'mw_season_number',
					'integer',
					0,
					true,
					array(
						'label' => __( 'Season number', 'music-wave-core' ),
						'type'  => 'number',
						'min'   => 0,
						'group' => 'podcast',
					)
				),
				'mw_credits'           => new MetaDefinition(
					'mw_credits',
					'array',
					array(),
					array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name' => array( 'type' => 'string' ),
									'role' => array( 'type' => 'string' ),
								),
							),
						),
					)
				),
				'mw_collection_items'  => new MetaDefinition(
					'mw_collection_items',
					'array',
					array(),
					array(
						'schema' => array(
							'type'    => 'array',
							'context' => array( 'view', 'edit' ),
							'items'   => array(
								'type'       => 'object',
								'required'   => array( 'release_id', 'position', 'role' ),
								'properties' => array(
									'release_id' => array(
										'type'    => 'integer',
										'minimum' => 1,
									),
									'position'   => array(
										'type'    => 'integer',
										'minimum' => 1,
									),
									'disc'       => array(
										'type'    => array( 'integer', 'null' ),
										'minimum' => 1,
									),
									'role'       => array(
										'type' => 'string',
										'enum' => array( 'track', 'episode' ),
									),
								),
							),
						),
					)
				),
				'mw_access_mode'       => new MetaDefinition(
					'mw_access_mode',
					'string',
					'public',
					true,
					array(
						'label'   => __( 'Access mode', 'music-wave-core' ),
						'type'    => 'select',
						'group'   => 'access',
						'options' => array(
							'public'                 => __( 'Public', 'music-wave-core' ),
							'purchase'               => __( 'Purchase required', 'music-wave-core' ),
							'membership'             => __( 'Membership required', 'music-wave-core' ),
							'purchase_or_membership' => __( 'Purchase or membership', 'music-wave-core' ),
							'restricted'             => __( 'Restricted / unavailable', 'music-wave-core' ),
						),
					)
				),
				'mw_product_ids'       => new MetaDefinition(
					'mw_product_ids',
					'array',
					array(),
					false,
					array(
						'label' => __( 'WooCommerce products', 'music-wave-core' ),
						'type'  => 'products',
						'group' => 'access',
					)
				),
				'mw_membership_levels' => new MetaDefinition(
					'mw_membership_levels',
					'array',
					array(),
					false,
					array(
						'label' => __( 'Membership level keys', 'music-wave-core' ),
						'type'  => 'key_list',
						'group' => 'access',
					)
				),
				'mw_external_id'       => new MetaDefinition( 'mw_external_id', 'string', '', false ),
				'mw_metadata_source'   => new MetaDefinition( 'mw_metadata_source', 'string', '', false ),
				'mw_metadata_provider' => new MetaDefinition( 'mw_metadata_provider', 'string', '', false ),
				'mw_download_asset_id' => new MetaDefinition( 'mw_download_asset_id', 'string', '', false ),
				'mw_download_assets'   => new MetaDefinition(
					'mw_download_assets',
					'array',
					array(),
					false,
					array(
						'label' => __( 'Protected files and download qualities', 'music-wave-core' ),
						'type'  => 'protected_assets',
						'group' => 'access',
					)
				),
			);
		}

		return $this->definitions;
	}

	public function get( string $key ): ?MetaDefinition {
		$definitions = $this->all();

		return isset( $definitions[ $key ] ) ? $definitions[ $key ] : null;
	}

	/** @return array<string, MetaDefinition> */
	public function admin_fields(): array {
		return array_filter(
			$this->all(),
			static function ( MetaDefinition $definition ): bool {
				return $definition->is_admin_field();
			}
		);
	}
}
