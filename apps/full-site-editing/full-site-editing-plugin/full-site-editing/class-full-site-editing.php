<?php
/**
 * Full site editing file.
 *
 * @package A8C\FSE
 */

namespace A8C\FSE;

/**
 * Class Full_Site_Editing
 */
class Full_Site_Editing {
	/**
	 * Class instance.
	 *
	 * @var \A8C\FSE\Full_Site_Editing
	 */
	private static $instance = null;

	/**
	 * Custom post types.
	 *
	 * @var array
	 */
	private $template_post_types = [ 'wp_template' ];

	/**
	 * Current theme slug.
	 *
	 * @var string
	 */
	private $theme_slug = '';

	/**
	 * Instance of WP_Template_Inserter class.
	 *
	 * @var WP_Template_Inserter
	 */
	public $wp_template_inserter;

	/**
	 * Whether the plugin is actively loaded.
	 * This means that hooks have been added to the appropriate places.
	 *
	 * @var boolean
	 */
	private $is_loaded = false;


	/**
	 * Full_Site_Editing constructor.
	 */
	private function __construct() {
		require_once __DIR__ . '/blocks/navigation-menu/index.php';
		require_once __DIR__ . '/blocks/post-content/index.php';
		require_once __DIR__ . '/blocks/site-description/index.php';
		require_once __DIR__ . '/blocks/site-title/index.php';
		require_once __DIR__ . '/blocks/template/index.php';
		require_once __DIR__ . '/templates/class-rest-templates-controller.php';
		require_once __DIR__ . '/templates/class-wp-template.php';
		require_once __DIR__ . '/templates/class-wp-template-inserter.php';
		require_once __DIR__ . '/serialize-block-fallback.php';
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		// put this late so it fires after `after_theme_switch`
		add_action( 'init', [ $this, 'maybe_load_or_unload' ] );
		add_action( 'after_switch_theme', [ $this, 'insert_default_data' ], 11 );
		add_action( 'admin_init', [ $this, 'add_admin_hooks' ] );
		add_action( 'switch_blog', [ $this, 'maybe_load_or_unload' ] );
		add_action( 'restapi_theme_init', [ $this, 'maybe_load_or_unload'] );
	}

	/**
	 * Adds hooks to wp-admin screens
	 */
	public function add_admin_hooks() {
		if ( ! self::is_active() ) {
			return;
		}
		add_filter( 'admin_body_class', [ $this, 'toggle_editor_post_title_visibility' ] );
		add_filter( 'post_row_actions', [ $this, 'remove_trash_row_action_for_template_post_types' ], 10, 2 );
		add_filter( 'bulk_actions-edit-wp_template', [ $this, 'remove_trash_bulk_action_for_template_post_type' ] );
		add_filter( 'wp_template_type_row_actions', [ $this, 'remove_delete_row_action_for_template_taxonomy' ], 10, 2 );
		add_filter( 'bulk_actions-edit-wp_template_type', [ $this, 'remove_delete_bulk_action_for_template_taxonomy' ] );
	}

	/**
	 * Loads or unloads plugin functionality as needed
	 */
	public function maybe_load_or_unload() {
		if ( self::is_active() ) {
			$this->load();
		} else {
			$this->unload();
		}
	}

	/**
	 * Loads plugin functionality
	 */
	public function load() {
		// already loaded
		if ( $this->is_loaded ) {
			return;
		}

		$this->register_template_post_types();
		$this->register_blocks();

		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_script_and_style' ], 100 );
		add_action( 'the_post', [ $this, 'merge_template_and_post' ] );
		add_filter( 'wp_insert_post_data', [ $this, 'remove_template_components' ], 10, 2 );
		add_filter( 'block_editor_settings', [ $this, 'set_block_template' ] );
		add_filter( 'body_class', array( $this, 'add_fse_body_class' ) );

		add_action( 'wp_trash_post', [ $this, 'restrict_template_deletion' ] );
		add_action( 'before_delete_post', [ $this, 'restrict_template_deletion' ] );
		add_action( 'pre_delete_term', [ $this, 'restrict_template_taxonomy_deletion' ], 10, 2 );
		add_action( 'transition_post_status', [ $this, 'restrict_template_drafting' ], 10, 3 );

		$this->is_loaded = true;
	}

	/**
	 * Unloads plugin functionality. Needed in Multisite environments where
	 * `switch_to_blog` gets called.
	 */
	public function unload() {
		if ( ! $this->is_loaded ) {
			return;
		}

		$this->unregister_template_post_types();
		$this->unregister_blocks();

		remove_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_script_and_style' ], 100 );
		remove_action( 'the_post', [ $this, 'merge_template_and_post' ] );
		remove_filter( 'wp_insert_post_data', [ $this, 'remove_template_components' ], 10, 2 );
		remove_filter( 'admin_body_class', [ $this, 'toggle_editor_post_title_visibility' ] );
		remove_filter( 'block_editor_settings', [ $this, 'set_block_template' ] );
		remove_filter( 'body_class', array( $this, 'remove_fse_body_class' ) );

		remove_action( 'wp_trash_post', [ $this, 'restrict_template_deletion' ] );
		remove_action( 'before_delete_post', [ $this, 'restrict_template_deletion' ] );
		remove_action( 'pre_delete_term', [ $this, 'restrict_template_taxonomy_deletion' ], 10, 2 );
		remove_action( 'transition_post_status', [ $this, 'restrict_template_drafting' ], 10, 3 );

		$this->wp_template_inserter = null;
		$this->is_loaded = false;
	}

	/**
	 * Checks if the plugin is in an active state, which generally means that
	 * the theme has declared support for `full-site-editing`.
	 *
	 * @return boolean
	 */
	public static function is_active() {
		/**
	 * Can be used to disable Full Site Editing functionality.
	 *
	 * @since 0.2
	 *
	 * @param bool true if Full Site Editing should be disabled, false otherwise.
	 */
		if ( true === apply_filters( 'a8c_disable_full_site_editing', false ) ) {
			return false;
		}
		return current_theme_supports( 'full-site-editing' );
		return (bool) get_option( 'current_theme_supports_fse', false );
	}

	/**
	 * Creates instance.
	 *
	 * @return \A8C\FSE\Full_Site_Editing
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Determines whether provided theme supports FSE.
	 *
	 * @deprecated being replaced soon by an is_active static method - don't add new usages
	 * @param string $theme_slug Theme slug to check support for.
	 *
	 * @return bool True if passed theme supports FSE, false otherwise.
	 */
	// phpcs:disable
	public function is_supported_theme( $theme_slug = null ) {
		// phpcs:enable
		// now in reality is_current_theme_supported.
		return self::is_active();
	}

	/**
	 * Fetches an instance of `WP_Template_Inserter` as needed
	 *
	 * @return \A8C\FSE\WP_Template_Inserter instance
	 */
	public function get_inserter() {
		if ( ! $this->wp_template_inserter ) {
			$theme_slug = $this->normalize_theme_slug( get_stylesheet() );
			$this->wp_template_inserter = new WP_Template_Inserter( $theme_slug );
		}
		return $this->wp_template_inserter;
	}

	/**
	 * Inserts template data for the theme we are currently switching to.
	 *
	 * This insertion will only happen if theme supports FSE.
	 * It is hooked into after_switch_theme action.
	 */
	public function insert_default_data() {
		// Bail if current theme doesn't support FSE.
		if ( ! $this->is_supported_theme() ) {
			return;
		}

		if ( ! $this->get_inserter()->is_template_data_inserted() ) {
			$this->get_inserter()->insert_default_template_data();
		}

		if ( ! $this->get_inserter()->is_pages_data_inserted() ) {
			$this->get_inserter()->insert_default_pages();
		}
	}

	/**
	 * Returns normalized theme slug for the current theme.
	 *
	 * Normalize WP.com theme slugs that differ from those that we'll get on self hosted sites.
	 * For example, we will get 'modern-business-wpcom' when retrieving theme slug on self hosted sites,
	 * but due to WP.com setup, on Simple sites we'll get 'pub/modern-business' for the theme.
	 *
	 * @param string $theme_slug Theme slug to check support for.
	 *
	 * @return string Normalized theme slug.
	 */
	public function normalize_theme_slug( $theme_slug ) {
		if ( 'pub/' === substr( $theme_slug, 0, 4 ) ) {
			$theme_slug = substr( $theme_slug, 4 );
		}

		if ( '-wpcom' === substr( $theme_slug, -6, 6 ) ) {
			$theme_slug = substr( $theme_slug, 0, -6 );
		}

		return $theme_slug;
	}

	/**
	 * Register post types.
	 */
	public function register_template_post_types() {
		$this->get_inserter()->register_template_post_types();
	}

	/**
	 * Unregister post types.
	 */
	public function unregister_template_post_types() {
		$this->get_inserter()->unregister_template_post_types();
	}

	/**
	 * Auth callback.
	 *
	 * @return mixed
	 */
	public function meta_template_id_auth_callback() {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_script_and_style() {
		$script_dependencies = json_decode(
			file_get_contents(
				plugin_dir_path( __FILE__ ) . 'dist/full-site-editing.deps.json'
			),
			true
		);
		wp_enqueue_script(
			'a8c-full-site-editing-script',
			plugins_url( 'dist/full-site-editing.js', __FILE__ ),
			is_array( $script_dependencies ) ? $script_dependencies : array(),
			filemtime( plugin_dir_path( __FILE__ ) . 'dist/full-site-editing.js' ),
			true
		);

		wp_localize_script(
			'a8c-full-site-editing-script',
			'fullSiteEditing',
			array(
				'editorPostType'      => get_current_screen()->post_type,
				'closeButtonLabel'    => $this->get_close_button_label(),
				'closeButtonUrl'      => esc_url( $this->get_close_button_url() ),
				'editTemplateBaseUrl' => esc_url( $this->get_edit_template_base_url() ),
			)
		);

		$style_file = is_rtl()
			? 'full-site-editing.rtl.css'
			: 'full-site-editing.css';
		wp_enqueue_style(
			'a8c-full-site-editing-style',
			plugins_url( 'dist/' . $style_file, __FILE__ ),
			'wp-edit-post',
			filemtime( plugin_dir_path( __FILE__ ) . 'dist/' . $style_file )
		);
	}

	/**
	 * Register blocks.
	 */
	public function register_blocks() {
		register_block_type(
			'a8c/navigation-menu',
			array(
				'attributes'      => [
					'className' => [
						'default' => '',
						'type'    => 'string',
					],
				],
				'render_callback' => __NAMESPACE__ . '\render_navigation_menu_block',
			)
		);

		register_block_type(
			'a8c/post-content',
			array(
				'render_callback' => __NAMESPACE__ . '\render_post_content_block',
			)
		);

		register_block_type(
			'a8c/site-description',
			array(
				'render_callback' => __NAMESPACE__ . '\render_site_description_block',
			)
		);

		register_block_type(
			'a8c/template',
			array(
				'render_callback' => __NAMESPACE__ . '\render_template_block',
			)
		);

		register_block_type(
			'a8c/site-title',
			array(
				'render_callback' => __NAMESPACE__ . '\render_site_title_block',
			)
		);
	}

	/**
	 * Unregister blocks.
	 */
	public function unregister_blocks() {
		unregister_block_type( 'a8c/navigation-menu' );
		unregister_block_type( 'a8c/post-content'	);
		unregister_block_type( 'a8c/site-description'	);
		unregister_block_type( 'a8c/template'	);
		unregister_block_type( 'a8c/site-title'	);
	}

	/**
	 * Returns the parent post ID if sent as query param when editing a Template from a
	 * Post/Page or a Template.
	 *
	 * @return null|string The parent post ID, or null if not set.
	 */
	public function get_parent_post_id() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_GET['fse_parent_post'] ) ) {
			return null;
		}

		$parent_post_id = absint( $_GET['fse_parent_post'] );
		// phpcs:enable WordPress.Security.NonceVerification

		if ( empty( $parent_post_id ) ) {
			return null;
		}

		return $parent_post_id;
	}

	/**
	 * Returns the label for the Gutenberg close button.
	 *
	 * When we edit a Template from a Post/Page or a Template, we want to replace the close
	 * icon with a "Back to" button, to clarify that it will take us back to the previous editing
	 * view, and not the Template CPT list.
	 *
	 * @return null|string Override label string if it should be inserted, or null otherwise.
	 */
	public function get_close_button_label() {
		$parent_post_id = $this->get_parent_post_id();

		if ( ! $parent_post_id ) {
			return null;
		}

		$parent_post_type        = get_post_type( $parent_post_id );
		$parent_post_type_object = get_post_type_object( $parent_post_type );

		/* translators: %s: "Back to Post", "Back to Page", "Back to Template", etc. */
		return sprintf( __( 'Back to %s', 'full-site-editing' ), $parent_post_type_object->labels->singular_name );
	}

	/**
	 * Returns the URL for the Gutenberg close button.
	 *
	 * In some cases we want to override the default value which would take us to post listing
	 * for a given post type. For example, when navigating back from Header, we want to show the
	 * parent page editing view, and not the Template CPT list.
	 *
	 * @return null|string Override URL string if it should be inserted, or null otherwise.
	 */
	public function get_close_button_url() {
		$parent_post_id = $this->get_parent_post_id();

		if ( ! $parent_post_id ) {
			return null;
		}

		$close_button_url = get_edit_post_link( $parent_post_id );

		/**
		 * Filter the Gutenberg's close button URL when editing Template CPTs.
		 *
		 * @since 0.1
		 *
		 * @param string Current close button URL.
		 */
		return apply_filters( 'a8c_fse_close_button_link', $close_button_url );
	}

	/**
	 * Returns the base URL for the Edit Template button. The URL does not contain neither
	 * the post ID nor the template ID. Those query arguments should be provided by
	 * the Template on the Block.
	 *
	 * @return string edit link without post ID
	 */
	public function get_edit_template_base_url() {
		$edit_post_link = remove_query_arg( 'post', get_edit_post_link( 0, 'edit' ) );

		/**
		 * Filter the Gutenberg's edit template button base URL
		 * when editing pages or posts.
		 *
		 * @since 0.2
		 *
		 * @param string Current edit button URL.
		 */
		return apply_filters( 'a8c_fse_edit_template_base_url', $edit_post_link );
	}

	/** This will merge the post content with the post template, modifiying the $post parameter.
	 *
	 * @param \WP_Post $post Post instance.
	 */
	public function merge_template_and_post( $post ) {
		// Bail if not a REST API Request and not in the editor.
		if ( ! $this->should_merge_template_and_post( $post ) ) {
			return;
		}

		$template         = new WP_Template();
		$template_content = $template->get_page_template_content();

		// Bail if the template has no post content block.
		if ( ! has_block( 'a8c/post-content', $template_content ) ) {
			return;
		}

		$post->post_content = preg_replace( '@(<!-- wp:a8c/post-content)(.*?)(/-->)@', "$1$2-->$post->post_content<!-- /wp:a8c/post-content -->", $template_content );
	}

	/**
	 * Detects if we are in a context where the template and post should be merged.
	 *
	 * Conditions:
	 * 1. Current theme supports it
	 * 2. AND in a REST API request (either flavour)
	 * 3. OR on a block editor screen (inlined requests using `rest_preload_api_request` )
	 * 4. AND editing a post_type that supports full site editing
	 *
	 * @param \WP_Post $post object for the check.
	 * @return bool
	 */
	private function should_merge_template_and_post( $post ) {
		if ( ! self::is_active() ) {
			return false;
		}
		$is_rest_api_wpcom      = ( defined( 'REST_API_REQUEST' ) && REST_API_REQUEST );
		$is_rest_api_core       = ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		$is_block_editor_screen = ( function_exists( 'get_current_screen' ) && get_current_screen() && get_current_screen()->is_block_editor() );

		if ( ! ( $is_block_editor_screen || $is_rest_api_core || $is_rest_api_wpcom ) ) {
			return false;
		}
		return $this->is_full_site_page( $post );
	}

	/**
	 * This will extract the inner blocks of the post content and
	 * serialize them back to HTML for saving.
	 *
	 * @param array $data    An array of slashed post data.
	 * @param array $postarr An array of sanitized, but otherwise unmodified post data.
	 * @return array
	 */
	public function remove_template_components( $data, $postarr ) {
		// Bail if the post type is one of the template post types.
		if ( in_array( $postarr['post_type'], $this->template_post_types, true ) ) {
			return $data;
		}

		$post_content = wp_unslash( $data['post_content'] );

		// Bail if post content has no blocks.
		if ( ! has_blocks( $post_content ) ) {
			return $data;
		}

		$post_content_blocks = parse_blocks( $post_content );
		$post_content_key    = array_search( 'a8c/post-content', array_column( $post_content_blocks, 'blockName' ), true );

		// Bail if no post content block found.
		if ( ! $post_content_key ) {
			return $data;
		}

		$data['post_content'] = wp_slash( serialize_blocks( $post_content_blocks[ $post_content_key ]['innerBlocks'] ) );
		return $data;
	}

	/**
	 * Return an extra class that will be assigned to the body element if a full site page is being edited.
	 *
	 * That class hides the default post title of the editor and displays a new post title rendered by the post content
	 * block in order to have it just before the content of the post.
	 *
	 * @param string $classes Space-separated list of CSS classes.
	 * @return string
	 */
	public function toggle_editor_post_title_visibility( $classes ) {
		if ( get_current_screen()->is_block_editor() && $this->is_full_site_page() ) {
			$classes .= ' show-post-title-before-content ';
		}
		return $classes;
	}

	/**
	 * Sets the block template to be loaded by the editor when creating a new full site page.
	 *
	 * @param array $editor_settings Default editor settings.
	 * @return array Editor settings with the updated template setting.
	 */
	public function set_block_template( $editor_settings ) {
		if ( $this->is_full_site_page() ) {
			$fse_template    = new WP_Template();
			$template_blocks = $fse_template->get_template_blocks();

			$template = array();
			foreach ( $template_blocks as $block ) {
				$template[] = $this->fse_map_block_to_editor_template_setting( $block );
			}
			$editor_settings['template']     = $template;
			$editor_settings['templateLock'] = 'all';
		}
		return $editor_settings;
	}

	/**
	 * Determine if the current edited post is a full site page.
	 * So far we only support static pages.
	 *
	 * @param object $post optional post object, if not passed in then current post is checked.
	 * @return boolean
	 */
	public function is_full_site_page( $post = null ) {
		$post_type = get_post_type( $post );
		return 'page' === $post_type || ( 'revision' === $post_type && 'page' === get_post_type( $post->post_parent ) );
	}

	/**
	 * Determines whether given post belongs to FSE template CPTs.
	 *
	 * @param WP_Post $post Check if this post belongs to templates.
	 *
	 * @return boolean
	 */
	public function is_template_post_type( $post ) {
		return in_array( $post->post_type, $this->template_post_types, true );
	}

	/**
	 * Add fse-enabled class to body so we can target css only if plugin enabled.
	 *
	 * @param array $classes classes to be applied to body.
	 * @return array classes to be applied to body.
	 */
	public function add_fse_body_class( $classes ) {
		$classes[] = 'fse-enabled';
		return $classes;
	}

	/**
	 * Returns an array with the expected format of the block template setting syntax.
	 *
	 * @see https://github.com/WordPress/gutenberg/blob/1414cf0ad1ec3d0f3e86a40815513c15938bb522/docs/designers-developers/developers/block-api/block-templates.md
	 *
	 * @param array $block Block to convert.
	 * @return array
	 */
	private function fse_map_block_to_editor_template_setting( $block ) {
		$block_name   = $block['blockName'];
		$attrs        = $block['attrs'];
		$inner_blocks = $block['innerBlocks'];

		$inner_blocks_template = array();
		foreach ( $inner_blocks as $inner_block ) {
			$inner_blocks[] = fse_map_block_to_editor_template_setting( $inner_block );
		}
		return array( $block_name, $attrs, $inner_blocks_template );
	}

	/**
	 * Removes the Trash action from the quick actions on the Templates list
	 *
	 * @param array   $actions Array of row action links.
	 * @param WP_Post $post The post object.
	 * @return array
	 */
	public function remove_trash_row_action_for_template_post_types( $actions, $post ) {
		if ( $this->is_template_post_type( $post ) ) {
			unset( $actions['trash'] );
		}
		return $actions;
	}

	/**
	 * Removes the Trash bulk action from the Template List page.
	 *
	 * @param array $bulk_actions Array of bulk actions.
	 * @return array;
	 */
	public function remove_trash_bulk_action_for_template_post_type( $bulk_actions ) {
		unset( $bulk_actions['trash'] );
		return $bulk_actions;
	}

	/**
	 * Prevents posts for the template post types to be deleted.
	 *
	 * @param integer $post_id The post id.
	 */
	public function restrict_template_deletion( $post_id ) {
		if ( $this->is_template_post_type( get_post( $post_id ) ) ) {
			wp_die( esc_html__( 'Templates cannot be deleted.' ) );
		}
	}

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	/**
	 * Prevents draftinig of template post types.
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Old post status.
	 * @param object $post Post object for which the status change is attempted.
	 */
	public function restrict_template_drafting( $new_status, $old_status, $post ) {
		if ( 'draft' === $new_status && $this->is_template_post_type( $post ) ) {
			wp_die( esc_html__( 'Templates cannot be moved to drafts.' ) );
		}
	}
	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed

	/**
	 * Removes the Delete action from the quick actions for the template taxonomy.
	 *
	 * @param array   $actions Array of row action links.
	 * @param WP_Term $term The Term object.
	 * @return array
	 */
	public function remove_delete_row_action_for_template_taxonomy( $actions, $term ) {
		if ( 'wp_template_type' === $term->taxonomy ) {
			unset( $actions['delete'] );
		}
		return $actions;
	}

	/**
	 * Removes the Delete bulk action from the Template Taxonomy list.
	 *
	 * @param array $bulk_actions Array of bulk actions.
	 * @return array
	 */
	public function remove_delete_bulk_action_for_template_taxonomy( $bulk_actions ) {
		unset( $bulk_actions['delete'] );
		return $bulk_actions;
	}

	/**
	 * Prevents template types to be deleted.
	 *
	 * @param integer $term The Term Id.
	 * @param string  $taxonomy Taxonomy name.
	 */
	public function restrict_template_taxonomy_deletion( $term, $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		if ( 'wp_template_type' === $taxonomy ) {
			wp_die( esc_html__( 'Template Types cannon be deleted.' ) );
		}
	}
}
