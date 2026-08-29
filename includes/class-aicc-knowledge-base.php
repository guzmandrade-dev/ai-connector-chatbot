<?php
/**
 * Knowledge Base module — registers the CPT and retrieves context for the chatbot.
 *
 * The post content is where you write the information/instructions the chatbot
 * should know. The post title becomes the heading in the AI context. For example,
 * create an article titled "Return Policy" with the policy text in the content body.
 *
 * @package AI_Connector_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the aicc_article custom post type and provides context retrieval.
 */
class AICC_Knowledge_Base {

	/** CPT slug. */
	const CPT_SLUG = 'aicc_article';

	/** @var AICC_Settings Settings instance. */
	private AICC_Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param AICC_Settings $settings Settings instance.
	 */
	public function __construct( AICC_Settings $settings ) {
		$this->settings = $settings;
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Registers the aicc_article custom post type.
	 */
	public function register_post_type(): void {
		register_post_type( self::CPT_SLUG, [
			'labels'       => [
				'name'               => __( 'Knowledge Base', 'ai-connector-chatbot' ),
				'singular_name'      => __( 'Knowledge Base Article', 'ai-connector-chatbot' ),
				'add_new'            => __( 'Add New Article', 'ai-connector-chatbot' ),
				'add_new_item'       => __( 'Add New Knowledge Base Article', 'ai-connector-chatbot' ),
				'edit_item'          => __( 'Edit Article', 'ai-connector-chatbot' ),
				'new_item'           => __( 'New Article', 'ai-connector-chatbot' ),
				'view_item'          => __( 'View Article', 'ai-connector-chatbot' ),
				'search_items'       => __( 'Search Knowledge Base', 'ai-connector-chatbot' ),
				'not_found'          => __( 'No articles found.', 'ai-connector-chatbot' ),
				'not_found_in_trash' => __( 'No articles in trash.', 'ai-connector-chatbot' ),
				'all_items'          => __( 'All Articles', 'ai-connector-chatbot' ),
				'menu_name'          => __( 'Knowledge Base', 'ai-connector-chatbot' ),
			],
			'public'        => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-book',
			'menu_position' => 25,
			'show_in_rest'  => true,
			'supports'      => [ 'title', 'editor', 'author', 'excerpt', 'custom-fields' ],
			'rewrite'       => [ 'slug' => 'knowledge-base' ],
		] );
	}

	/**
	 * Retrieves relevant context from the knowledge base for a given user message.
	 *
	 * Uses a simple keyword-matching approach: extracts significant words from the
	 * user's message and queries posts that contain them. For small knowledge bases,
	 * all entries are included.
	 *
	 * @param string $message The user's chat message.
	 * @return string Formatted context string for the system prompt.
	 */
	public function get_context( string $message ): string {
		$types       = (array) $this->settings->get( 'kb_post_types', [ 'aicc_article' ] );
		$max_length  = (int) $this->settings->get( 'max_context_length', 4000 );
		$context     = '';
		$used_length = 0;

		// Always include KB articles first.
		$articles = $this->query_relevant_posts( $message, [ 'aicc_article' ], 5 );
		if ( ! empty( $articles ) ) {
			$context .= "## Knowledge Base Articles\n\n";
			foreach ( $articles as $post ) {
				$entry = $this->format_post_as_context( $post );
				if ( $used_length + strlen( $entry ) > $max_length ) {
					break;
				}
				$context    .= $entry . "\n\n";
				$used_length += strlen( $entry );
			}
		}

		// Then include other post types (excluding aicc_article which was already done).
		$other_types = array_diff( $types, [ 'aicc_article' ] );
		if ( ! empty( $other_types ) ) {
			$posts = $this->query_relevant_posts( $message, $other_types, 5 );
			if ( ! empty( $posts ) ) {
				$context .= "## Site Content\n\n";
				foreach ( $posts as $post ) {
					$entry = $this->format_post_as_context( $post );
					if ( $used_length + strlen( $entry ) > $max_length ) {
						break;
					}
					$context    .= $entry . "\n\n";
					$used_length += strlen( $entry );
				}
			}
		}

		if ( empty( trim( $context ) ) ) {
			return '';
		}

		return "Use the following context from the knowledge base to answer the user's question. If the context does not contain relevant information, say you don't have that information.\n\n" . $context;
	}

	/**
	 * Queries posts relevant to the user message.
	 *
	 * Uses WP_Query with a keyword search (s parameter) as well as a fallback
	 * to recent posts when no keywords match.
	 *
	 * @param string   $message  User message.
	 * @param string[] $types    Post types to search.
	 * @param int      $limit    Max posts to return.
	 * @return WP_Post[] Array of post objects.
	 */
	private function query_relevant_posts( string $message, array $types, int $limit ): array {
		// For KB articles, if there are fewer than the limit, return all of them.
		$total = ( new WP_Query( [
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		] ) )->found_posts;

		if ( $total <= $limit ) {
			// Small KB — include everything.
			$query = new WP_Query( [
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );
			return $query->posts;
		}

		// Use keyword search for larger KBs.
		$keywords = $this->extract_keywords( $message );
		$search   = implode( ' ', $keywords );

		$query = new WP_Query( [
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			's'              => $search,
			'orderby'        => 'relevance',
		] );

		if ( empty( $query->posts ) ) {
			// Fallback to recent posts if keyword search returns nothing.
			$query = new WP_Query( [
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );
		}

		return $query->posts;
	}

	/**
	 * Extracts significant keywords from a user message for search.
	 *
	 * @param string $message User message.
	 * @return string[] Keywords.
	 */
	private function extract_keywords( string $message ): array {
		// Remove common stop words and punctuation.
		$stop_words = [ 'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'what', 'when', 'where', 'why', 'how', 'who', 'which', 'this', 'that', 'these', 'those', 'to', 'of', 'in', 'on', 'at', 'by', 'for', 'with', 'about', 'as', 'into', 'like', 'through', 'after', 'over', 'between', 'out', 'against', 'during', 'without', 'before', 'under', 'around', 'among', 'and', 'or', 'but', 'not', 'no', 'so', 'than', 'too', 'very', 'just', 'also', 'me', 'my', 'your', 'please', 'help', 'need', 'want', 'tell', 'give' ];

		$words    = preg_split( '/\s+/', strtolower( preg_replace( '/[^a-zA-Z0-9\s]/', ' ', $message ) ) );
		$keywords = array_filter( (array) $words, function ( $word ) use ( $stop_words ) {
			return ! empty( $word ) && strlen( $word ) > 2 && ! in_array( $word, $stop_words, true );
		} );

		return array_slice( array_unique( $keywords ), 0, 10 );
	}

	/**
	 * Formats a post as context text for the AI system prompt.
	 *
	 * The post title becomes the heading and the post content is included
	 * as the knowledge the AI should reference.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function format_post_as_context( WP_Post $post ): string {
		$content = wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) );
		// Truncate individual entries to keep total context manageable.
		$max_entry = 2000;
		if ( strlen( $content ) > $max_entry ) {
			$content = substr( $content, 0, $max_entry ) . '…';
		}

		$source = get_permalink( $post );
		return sprintf(
			"### %s\nSource: %s\n%s",
			$post->post_title,
			$source ?: '',
			$content
		);
	}
}