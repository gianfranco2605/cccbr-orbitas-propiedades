<?php
/**
 * Maps the people and relations attached to a property.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Mapper;

use ElPuas\CCCBR\OrbitasPropiedades\Repository\RelationsRepository;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IdListParser;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCode;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCollector;
use WP_Post;
use WP_User;

/**
 * Builds `creator`, `responsible`, `company`, `associate_id`, `marketplace_id` and `leads_count`.
 *
 * **The allow-list is the whole point of this class.** A person is reduced to exactly three
 * fields — id, display name, email — and `associate_id` to one deliberately allow-listed user meta
 * key. No user object is ever handed to the serializer, and user meta is never iterated, so no
 * national id, phone number, birth date, payment status, session token or Wasi credential can
 * reach the response even if someone adds one to the profile later.
 *
 * All lookups for a page are primed in bulk by {@see self::prime()}: two relation queries plus one
 * `cache_users()` call, instead of per-property queries.
 */
final class PeopleMapper {

	/**
	 * The only user meta key this plugin reads: the CCCBR associate number.
	 *
	 * Exposed as `associate_id` because the ticket requires the link to the associate, and it is an
	 * organisational identifier rather than contact or identity data. See Q4 in
	 * `docs/OPEN-QUESTIONS.md`.
	 */
	public const ASSOCIATE_META_KEY = 'numero-de-asociado';

	/**
	 * JetEngine field holding the responsible associate's user ID.
	 */
	private const RESPONSIBLE_META_KEY = 'corredor_asignado';

	/**
	 * Relation tables.
	 *
	 * @var RelationsRepository
	 */
	private RelationsRepository $relations;

	/**
	 * ID parser, reused for the responsible-associate field.
	 *
	 * @var IdListParser
	 */
	private IdListParser $ids;

	/**
	 * Company of each associate on the page: associate user ID => company user ID.
	 *
	 * @var array<int, int>
	 */
	private array $companies = array();

	/**
	 * Lead count per property on the page.
	 *
	 * @var array<int, int>
	 */
	private array $leads = array();

	/**
	 * Constructor.
	 *
	 * @param RelationsRepository $relations Relation tables.
	 * @param IdListParser        $ids       ID parser.
	 */
	public function __construct( RelationsRepository $relations, IdListParser $ids ) {
		$this->relations = $relations;
		$this->ids       = $ids;
	}

	/**
	 * Loads every person and relation a whole page needs.
	 *
	 * @param array $posts      Posts on the page.
	 * @param array $meta_by_id Raw meta of each property, keyed by property ID.
	 * @return void
	 *
	 * @phpstan-param list<WP_Post> $posts
	 * @phpstan-param array<int, array<string, mixed>> $meta_by_id
	 */
	public function prime( array $posts, array $meta_by_id ): void {
		$this->companies = array();
		$this->leads     = array();

		$post_ids = array();
		$user_ids = array();

		foreach ( $posts as $post ) {
			$post_ids[] = $post->ID;

			$author = (int) $post->post_author;

			if ( $author > 0 ) {
				$user_ids[ $author ] = true;
			}

			$responsible = $this->responsible_id( $meta_by_id[ $post->ID ] ?? array() );

			if ( null !== $responsible ) {
				$user_ids[ $responsible ] = true;
			}
		}

		$this->leads = $this->relations->lead_counts( $post_ids );

		if ( array() === $user_ids ) {
			return;
		}

		$this->companies = $this->relations->company_parents( array_keys( $user_ids ) );

		// The companies are users too, and they are only known after the relation query, so they
		// join the same single cache_users() call rather than triggering one lookup each.
		foreach ( $this->companies as $company_id ) {
			$user_ids[ $company_id ] = true;
		}

		cache_users( array_keys( $user_ids ) );
	}

	/**
	 * Maps the people of one property.
	 *
	 * @param WP_Post        $post   Property post.
	 * @param array          $meta   Raw meta.
	 * @param IssueCollector $issues Issue sink.
	 * @return array<string, mixed>
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	public function map( WP_Post $post, array $meta, IssueCollector $issues ): array {
		$author_id = (int) $post->post_author;
		$creator   = $this->person( $author_id, true );

		if ( null === $creator ) {
			$issues->add( IssueCode::CREATOR_MISSING, 'creator', null );
		}

		return array(
			'creator'        => $creator,
			'responsible'    => $this->map_responsible( $author_id, $meta, $issues ),
			'company'        => $this->map_company( $author_id ),
			'associate_id'   => $this->map_associate_id( $author_id, null !== $creator, $issues ),

			// No Marketplace or Orbitas identifier exists anywhere in the source; see
			// docs/contract.md §4.10. Nothing is inferred from the Wasi CRM fields.
			'marketplace_id' => null,

			'leads_count'    => $this->leads[ $post->ID ] ?? 0,
		);
	}

	/**
	 * Maps the responsible associate, when there is one and it differs from the creator.
	 *
	 * The field is empty on all five properties that carry it, so this returns `null` everywhere in
	 * the current data; the code exists because the field is the one the site means to use
	 * (JetEngine query 132 restricts it to the creator's sub-agents).
	 *
	 * @param int            $author_id Creator's user ID.
	 * @param array          $meta      Raw meta.
	 * @param IssueCollector $issues    Issue sink.
	 * @return array<string, mixed>|null
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function map_responsible( int $author_id, array $meta, IssueCollector $issues ): ?array {
		$responsible_id = $this->responsible_id( $meta );

		if ( null === $responsible_id || $responsible_id === $author_id ) {
			return null;
		}

		$person = $this->person( $responsible_id, true );

		if ( null === $person ) {
			$issues->add( IssueCode::RESPONSIBLE_MISSING_USER, 'responsible', (string) $responsible_id );
		}

		return $person;
	}

	/**
	 * Maps the creator's company through relation 131.
	 *
	 * Two of the relation's 14 children are users that no longer exist, so a resolved parent is
	 * still looked up rather than trusted.
	 *
	 * @param int $author_id Creator's user ID.
	 * @return array<string, mixed>|null
	 */
	private function map_company( int $author_id ): ?array {
		if ( ! isset( $this->companies[ $author_id ] ) ) {
			return null;
		}

		return $this->person( $this->companies[ $author_id ], false );
	}

	/**
	 * Maps the creator's CCCBR associate number.
	 *
	 * @param int            $author_id      Creator's user ID.
	 * @param bool           $creator_exists Whether the creator resolved to a real user.
	 * @param IssueCollector $issues         Issue sink.
	 * @return string|null
	 */
	private function map_associate_id( int $author_id, bool $creator_exists, IssueCollector $issues ): ?string {
		if ( ! $creator_exists ) {
			return null;
		}

		$value = get_user_meta( $author_id, self::ASSOCIATE_META_KEY, true );
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			$issues->add( IssueCode::ASSOCIATE_ID_MISSING, 'associate_id', null );

			return null;
		}

		return $value;
	}

	/**
	 * Builds a person object from the allow-list.
	 *
	 * @param int  $user_id       User ID.
	 * @param bool $include_email Whether this contract field carries an email address.
	 * @return array<string, mixed>|null Null when the user does not exist.
	 */
	private function person( int $user_id, bool $include_email ): ?array {
		if ( $user_id < 1 ) {
			return null;
		}

		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return null;
		}

		$person = array(
			'id'           => (int) $user->ID,
			'display_name' => (string) $user->display_name,
		);

		if ( $include_email ) {
			$person['email'] = (string) $user->user_email;
		}

		return $person;
	}

	/**
	 * Reads the responsible associate's user ID from the meta.
	 *
	 * @param array $meta Raw meta.
	 * @return int|null
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function responsible_id( array $meta ): ?int {
		if ( ! isset( $meta[ self::RESPONSIBLE_META_KEY ] ) || ! is_array( $meta[ self::RESPONSIBLE_META_KEY ] ) ) {
			return null;
		}

		$value = $meta[ self::RESPONSIBLE_META_KEY ][0] ?? null;
		$value = is_string( $value ) ? maybe_unserialize( $value ) : $value;

		return $this->ids->first_id( $value );
	}
}
