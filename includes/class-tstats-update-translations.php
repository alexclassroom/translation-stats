<?php
/**
 * Class file for the Translation Stats Update Translations.
 *
 * @package Translation Stats
 *
 * @since 0.9.5.2
 */

use Gettext\Translations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TStats_Update_Translations' ) ) {

	/**
	 * Class TStats_Update_Translations.
	 */
	class TStats_Update_Translations {


		/**
		 * Globals.
		 *
		 * @var object
		 */
		protected $tstats_globals;

		/**
		 * Translations API.
		 *
		 * @var object
		 */
		protected $translations_api;

		/**
		 * Gettext.
		 *
		 * @var object
		 */
		protected $gettext;


		/**
		 * Constructor.
		 */
		public function __construct() {

			// Instantiate Translation Stats Globals.
			$this->tstats_globals = new TStats_Globals();

			// Instantiate Translation Stats Translations API.
			$this->translations_api = new TStats_Translations_API();

			// Instantiate Translation Stats Gettext.
			$this->gettext = new TStats_Gettext();

		}


		/**
		 * Update project translation.
		 * Download .po file, extract with Gettext to generate .po and .json files.
		 *
		 * @since 0.9.5
		 *
		 * @param string $destination   Local destination of the language file. ( e.g: local/site/wp-content/languages/ ).
		 * @param array  $project       Project array.
		 * @param string $wp_locale     WP Locale ( e.g.: 'pt_PT' ).
		 *
		 * @return array|WP_Error       Array on success, WP_Error on failure.
		 */
		public function tstats_update_translation( $destination, $project, $wp_locale ) {

			// Set array of log entries.
			$result['log'] = array();

			// Get Translation Stats Locale data.
			$locale = $this->translations_api->tstats_locale( $wp_locale );

			// Download file from WordPress.org translation table.
			$download = $this->tstats_download_translations( $project, $locale );
			array_push( $result['log'], $download['log'] );
			$result['data'] = $download['data'];
			if ( is_wp_error( $result['data'] ) ) {
				return $result;
			}

			// Generate .po from WordPress.org response.
			$generate_po = $this->tstats_generate_po( $destination, $project, $locale, $download['data'] );
			array_push( $result['log'], $generate_po['log'] );
			$result['data'] = $generate_po['data'];
			if ( is_wp_error( $result['data'] ) ) {
				return $result;
			}

			// Extract translations from file.
			$translations = $this->tstats_extract_translations( $destination, $project, $locale );
			array_push( $result['log'], $translations['log'] );
			$result['data'] = $translations['data'];
			if ( is_wp_error( $result['data'] ) ) {
				return $result;
			}

			// Generate .mo file from extracted translations.
			$generate_mo = $this->tstats_generate_mo( $destination, $project, $locale, $translations['data'] );
			array_push( $result['log'], $generate_mo['log'] );
			$result['data'] = $generate_mo['data'];
			if ( is_wp_error( $result['data'] ) ) {
				return $result;
			}

			// Generate .json files from extracted translations.
			$generate_jsons = $this->gettext->tstats_make_json( $destination, $project, $locale, $translations['data'], false );
			$result['log']  = array_merge( $result['log'], $generate_jsons['log'] );
			$result['data'] = $generate_jsons['data'];
			if ( is_wp_error( $result['data'] ) ) {
				return $result;
			}

			array_push( $result['log'], esc_html__( 'Translation updated successfully.', 'translation-stats' ) );

			return $result;

		}


		/**
		 * Download file from WordPress.org translation table.
		 *
		 * @since 0.9.5
		 *
		 * @param array $project    Project array.
		 * @param array $locale     Locale array.
		 *
		 * @return array|WP_Error   Array on success, WP_Error on failure.
		 */
		public function tstats_download_translations( $project, $locale ) {

			// Set translation data path.
			$source = $this->translations_api->tstats_translation_path( $project, $locale );

			// Report message.
			$result['log'] = sprintf(
				/* translators: %s: URL. */
				esc_html__( 'Downloading translation from %s…', 'translation-stats' ),
				'<code>' . esc_html( $source ) . '</code>'
			);

			// Get the translation data.
			$response = wp_remote_get( $source );

			if ( ! is_array( $response ) || 'application/octet-stream' !== $response['headers']['content-type'] ) {

				// Report message.
				$result['data'] = new WP_Error(
					'download-translation',
					sprintf(
						'%s %s',
						esc_html__( 'Download failed.', 'translation-stats' ),
						esc_html__( 'A valid URL was not provided.', 'translation-stats' )
					)
				);
				return $result;

			}

			$result['data'] = $response;

			return $result;

		}


		/**
		 * Generate .po from WordPress.org response.
		 *
		 * @since 0.9.5
		 *
		 * @param string $destination   Local destination of the language file. ( e.g: local/site/wp-content/languages/ ).
		 * @param array  $project       Project array.
		 * @param array  $locale        Locale array.
		 * @param array  $response      HTTP response.
		 *
		 * @return array|WP_Error       Array on success, WP_Error on failure.
		 */
		public function tstats_generate_po( $destination, $project, $locale, $response ) {

			// Set the file naming convention. ( e.g.: {domain}-{locale}.po ).
			$domain    = $project['domain'] ? $project['domain'] . '-' : '';
			$file_name = $domain . $locale['wp_locale'] . '.po';

			// Report message.
			$result['log'] = sprintf(
				/* translators: %s: File name. */
				esc_html__( 'Saving file %s…', 'translation-stats' ),
				'<code>' . esc_html( $file_name ) . '</code>'
			);

			// Generate .po file.
			$success = file_put_contents( $destination . $file_name, $response['body'] ); // phpcs:ignore

			if ( ! $success ) {

				// Report message.
				$result['data'] = new WP_Error(
					'generate-po',
					esc_html__( 'Could not create file.', 'translation-stats' )
				);
				return $result;

			}

			$result['data'] = true;

			return $result;

		}


		/**
		 * Extract translations from file.
		 *
		 * @since 0.9.5
		 *
		 * @param string $destination   Local destination of the language file. ( e.g: local/site/wp-content/languages/ ).
		 * @param array  $project       Project array.
		 * @param array  $locale        Locale array.
		 *
		 * @return array|WP_Error       Array on success, WP_Error on failure.
		 */
		public function tstats_extract_translations( $destination, $project, $locale ) {

			// Set the file naming convention. ( e.g.: {domain}-{locale}.po ).
			$domain    = $project['domain'] ? $project['domain'] . '-' : '';
			$file_name = $domain . $locale['wp_locale'] . '.po';

			// Report message.
			$result['log'] = sprintf(
				/* translators: %s: File name. */
				esc_html__( 'Extracting translations from file %s…', 'translation-stats' ),
				'<code>' . esc_html( $file_name ) . '</code>'
			);

			$translations = Gettext\Translations::fromPoFile( $destination . $file_name );

			if ( ! is_object( $translations ) ) {

				// Report message.
				$result['data'] = new WP_Error(
					'extract-translations',
					esc_html__( 'Could not extract translations from file.', 'translation-stats' )
				);

				return $result;

			}

			$result['data'] = $translations;

			return $result;

		}


		/**
		 * Generate .mo file from extracted translations.
		 *
		 * @since 0.9.5
		 *
		 * @param string $destination    Local destination of the language file. ( e.g: local/site/wp-content/languages/ ).
		 * @param array  $project        Project array.
		 * @param array  $locale         Locale array.
		 * @param object $translations   Extracted translations to export.
		 *
		 * @return array|WP_Error        Array on success, WP_Error on failure.
		 */
		public function tstats_generate_mo( $destination, $project, $locale, $translations ) {

			// Set the file naming convention. ( e.g.: {domain}-{locale}.po ).
			$domain    = $project['domain'] ? $project['domain'] . '-' : '';
			$file_name = $domain . $locale['wp_locale'] . '.mo';

			// Report message.
			$result['log'] = sprintf(
				/* translators: %s: File name. */
				esc_html__( 'Saving file %s…', 'translation-stats' ),
				'<code>' . $file_name . '</code>'
			);

			// Generate .mo file.
			$generate = $translations->toMoFile( $destination . $file_name );

			if ( ! $generate ) {

				// Report message.
				$result['data'] = new WP_Error(
					'generate-mo',
					esc_html__( 'Could not create file.', 'translation-stats' )
				);
				return $result;

			}

			$result['data'] = true;

			return $result;

		}

	}

}