<?php
/**
 * Builds the plugin's object graph.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades;

use ElPuas\CCCBR\OrbitasPropiedades\Auth\Permissions;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\CoordinatesMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\LocationMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\MediaMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\PeopleMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\PropertyMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Repository\PropertyRepository;
use ElPuas\CCCBR\OrbitasPropiedades\Repository\RelationsRepository;
use ElPuas\CCCBR\OrbitasPropiedades\Rest\PropertiesController;
use ElPuas\CCCBR\OrbitasPropiedades\Rest\PropertySchema;
use ElPuas\CCCBR\OrbitasPropiedades\Schema\FieldMap;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IdListParser;
use ElPuas\CCCBR\OrbitasPropiedades\Support\LocationResolver;
use ElPuas\CCCBR\OrbitasPropiedades\Support\NumberParser;
use ElPuas\CCCBR\OrbitasPropiedades\Support\OptionNormalizer;

/**
 * The composition root: the one place that knows how the services fit together.
 *
 * It is called **lazily**, from inside the `rest_api_init` callback, so a page view, an admin screen
 * or a cron run never constructs the fourteen objects an API request needs. It is also the only
 * place that reads WordPress's global `$wpdb`, which it passes in by constructor so no other class
 * of this plugin touches a global.
 *
 * `tools/bootstrap.php` calls the same method, so the CLI tools exercise exactly the graph an HTTP
 * request uses — there is no second, drifting copy of the wiring.
 */
final class Factory {

	/**
	 * Whether to verify that an attachment's file exists, or null to decide from the environment.
	 *
	 * @var bool|null
	 */
	private ?bool $check_media_files;

	/**
	 * Constructor.
	 *
	 * @param bool|null $check_media_files Pass `false` to switch the media file check off
	 *                                     regardless of the environment — the CLI tools do, because
	 *                                     the `wpcli` container does not share the web container's
	 *                                     environment type. `null` decides from the environment.
	 */
	public function __construct( ?bool $check_media_files = null ) {
		$this->check_media_files = $check_media_files;
	}

	/**
	 * Builds the properties endpoint with all of its dependencies.
	 *
	 * @return PropertiesController
	 */
	public function controller(): PropertiesController {
		global $wpdb;

		$id_parser = new IdListParser();

		return new PropertiesController(
			new Permissions(),
			new PropertySchema(),
			new PropertyRepository( $wpdb ),
			new PropertyMapper(
				new FieldMap( $wpdb ),
				new OptionNormalizer(),
				new NumberParser(),
				new LocationMapper( new LocationResolver() ),
				new CoordinatesMapper(),
				new MediaMapper( $id_parser, $this->should_check_media_files() ),
				new PeopleMapper( new RelationsRepository( $wpdb ), $id_parser )
			)
		);
	}

	/**
	 * Whether this environment can answer "does the attachment's file exist?".
	 *
	 * Checking is only meaningful where the uploads directory is the real one. On a local replica
	 * every uploads request is redirected to production, so the check would report every file as
	 * missing; reporting nothing is the honest answer there. See `docs/OPEN-QUESTIONS.md` Q3.
	 *
	 * An explicit constructor value wins over the environment, because the environment type is not
	 * reliable everywhere: the `wpcli` container reports `production` while an HTTP request on the
	 * same site reports `local` (`CLAUDE.md` §3.3).
	 *
	 * @return bool
	 */
	private function should_check_media_files(): bool {
		if ( null !== $this->check_media_files ) {
			return $this->check_media_files;
		}

		return 'local' !== wp_get_environment_type();
	}
}
