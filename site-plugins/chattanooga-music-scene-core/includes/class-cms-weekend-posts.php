<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CMS_Weekend_Posts {
	const CRON_HOOK       = 'cms_weekend_publish';
	const OPTION_SETTINGS = 'cms_weekend_post_settings';
	const META_WEEK_KEY   = '_cms_weekend_key';
	const META_GENERATED  = '_cms_weekend_generated';
	const NONCE_ACTION    = 'cms_weekend_action';
	const NONCE_NAME      = 'cms_weekend_nonce';

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_weekly_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_publish' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_cms_weekend_action', array( $this, 'handle_admin_action' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'update_option_' . self::OPTION_SETTINGS, array( $this, 'settings_updated' ), 10, 2 );
	}

	public static function activate() {
		self::instance()->synchronize_schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function add_weekly_schedule( $schedules ) {
		$schedules['cms_weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly (Chattanooga Music Scene)', 'chattanooga-music-scene-core' ),
		);

		return $schedules;
	}

	public function register_admin_page() {
		add_management_page(
			__( 'Weekend Posts', 'chattanooga-music-scene-core' ),
			__( 'Weekend Posts', 'chattanooga-music-scene-core' ),
			'publish_posts',
			'cms-weekend-posts',
			array( $this, 'render_admin_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'cms_weekend_posts',
			self::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->default_settings(),
			)
		);
	}

	private function default_settings() {
		return array(
			'enabled'      => 0,
			'publish_time' => '',
			'post_author'  => 0,
			'introduction' => 'Chattanooga’s stages come alive every weekend. Explore the shows happening across the city, then open any event for its complete details.',
			'closing'      => 'Plans can change. Open the individual event page for the latest details before heading out.',
		);
	}

	private function get_settings() {
		return wp_parse_args( get_option( self::OPTION_SETTINGS, array() ), $this->default_settings() );
	}

	public function sanitize_settings( $input ) {
		$old      = $this->get_settings();
		$settings = $this->default_settings();

		$settings['enabled']      = empty( $input['enabled'] ) ? 0 : 1;
		$settings['publish_time'] = isset( $input['publish_time'] ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $input['publish_time'] )
			? $input['publish_time']
			: '';
		$settings['post_author']  = isset( $input['post_author'] ) ? absint( $input['post_author'] ) : 0;
		$settings['introduction'] = isset( $input['introduction'] ) ? sanitize_textarea_field( $input['introduction'] ) : $old['introduction'];
		$settings['closing']      = isset( $input['closing'] ) ? sanitize_textarea_field( $input['closing'] ) : $old['closing'];

		if ( $settings['enabled'] && ( ! $settings['publish_time'] || ! $settings['post_author'] ) ) {
			$settings['enabled'] = 0;
			add_settings_error(
				self::OPTION_SETTINGS,
				'cms_weekend_missing_schedule_value',
				__( 'Automatic publishing remains disabled until both a Thursday time and post author are selected.', 'chattanooga-music-scene-core' ),
				'error'
			);
		}

		return $settings;
	}

	public function settings_updated() {
		$this->synchronize_schedule();
	}

	private function synchronize_schedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );

		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) || empty( $settings['publish_time'] ) || empty( $settings['post_author'] ) ) {
			return;
		}

		$next_run = $this->next_thursday_timestamp( $settings['publish_time'] );
		if ( $next_run ) {
			wp_schedule_event( $next_run, 'cms_weekly', self::CRON_HOOK );
		}
	}

	private function next_thursday_timestamp( $time ) {
		if ( ! preg_match( '/^(\d{2}):(\d{2})$/', $time, $matches ) ) {
			return 0;
		}

		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		$run      = $now->modify( 'thursday this week' )->setTime( (int) $matches[1], (int) $matches[2] );

		if ( $run <= $now ) {
			$run = $run->modify( '+1 week' );
		}

		return $run->getTimestamp();
	}

	private function weekend_window( $reference = null ) {
		$timezone = wp_timezone();
		$now      = $reference instanceof DateTimeImmutable ? $reference : new DateTimeImmutable( 'now', $timezone );
		$friday   = $now->modify( 'friday this week' )->setTime( 0, 0, 0 );

		if ( $now > $friday->modify( 'sunday this week' )->setTime( 23, 59, 59 ) ) {
			$friday = $friday->modify( '+1 week' );
		}

		return array(
			'start' => $friday,
			'end'   => $friday->modify( '+2 days' )->setTime( 23, 59, 59 ),
			'key'   => $friday->format( 'Y-m-d' ),
		);
	}

	private function get_events( array $window ) {
		if ( ! class_exists( 'EM_Events' ) ) {
			return new WP_Error( 'cms_weekend_missing_events_manager', __( 'Events Manager is not active.', 'chattanooga-music-scene-core' ) );
		}

		$events = EM_Events::get(
			array(
				'scope'      => $window['start']->format( 'Y-m-d' ) . ',' . $window['end']->format( 'Y-m-d' ),
				'status'     => 1,
				'orderby'    => 'event_start_date,event_start_time,event_name',
				'order'      => 'ASC',
				'limit'      => 0,
				'pagination' => false,
			)
		);

		if ( ! is_array( $events ) ) {
			return new WP_Error( 'cms_weekend_event_query_failed', __( 'Events Manager did not return an event list.', 'chattanooga-music-scene-core' ) );
		}

		$unique = array();
		foreach ( $events as $event ) {
			$key = ! empty( $event->event_id ) ? 'event-' . absint( $event->event_id ) : 'post-' . absint( $event->post_id );
			$unique[ $key ] = $event;
		}

		return array_values( $unique );
	}

	private function event_value( $event, $placeholder, $fallback = '' ) {
		if ( is_object( $event ) && is_callable( array( $event, 'output' ) ) ) {
			$value = trim( wp_strip_all_tags( (string) $event->output( $placeholder ) ) );
			if ( '' !== $value && $placeholder !== $value ) {
				return $value;
			}
		}

		return $fallback;
	}

	private function event_url( $event ) {
		$post_id = isset( $event->post_id ) ? absint( $event->post_id ) : 0;
		$url     = $post_id ? get_permalink( $post_id ) : '';

		if ( ! $url && is_callable( array( $event, 'output' ) ) ) {
			$url = $event->output( '#_EVENTURL' );
		}

		return esc_url_raw( $url );
	}

	private function event_start( $event ) {
		$date = ! empty( $event->event_start_date ) ? $event->event_start_date : '';
		$time = ! empty( $event->event_start_time ) ? $event->event_start_time : '00:00:00';

		try {
			return new DateTimeImmutable( trim( $date . ' ' . $time ), wp_timezone() );
		} catch ( Exception $exception ) {
			return null;
		}
	}

	private function build_post_content( array $events, array $window ) {
		$settings = $this->get_settings();
		$groups   = array();

		foreach ( $events as $event ) {
			$start = $this->event_start( $event );
			if ( ! $start ) {
				continue;
			}
			$groups[ $start->format( 'Y-m-d' ) ][] = $event;
		}

		ob_start();
		?>
		<div class="cms-weekend-guide" data-weekend-start="<?php echo esc_attr( $window['key'] ); ?>">
			<p class="cms-weekend-introduction"><?php echo esc_html( $settings['introduction'] ); ?></p>
			<?php foreach ( $groups as $date => $day_events ) : ?>
				<section class="cms-weekend-day">
					<h2><?php echo esc_html( wp_date( 'l, F j', strtotime( $date . ' 12:00:00' ), wp_timezone() ) ); ?></h2>
					<div class="cms-weekend-events">
						<?php foreach ( $day_events as $event ) : ?>
							<?php
							$start    = $this->event_start( $event );
							$title    = ! empty( $event->event_name ) ? $event->event_name : $this->event_value( $event, '#_EVENTNAME', __( 'Untitled event', 'chattanooga-music-scene-core' ) );
							$url      = $this->event_url( $event );
							$location = $this->event_value( $event, '#_LOCATIONNAME' );
							$post_id  = isset( $event->post_id ) ? absint( $event->post_id ) : 0;
							$image    = $post_id ? get_the_post_thumbnail_url( $post_id, 'medium_large' ) : '';
							?>
							<article class="cms-weekend-event">
								<?php if ( $image ) : ?>
									<a class="cms-weekend-event-image" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
										<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy">
									</a>
								<?php endif; ?>
								<div class="cms-weekend-event-copy">
									<p class="cms-weekend-event-time"><?php echo esc_html( $start->format( 'g:i a' ) ); ?></p>
									<h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
									<?php if ( $location ) : ?>
										<p class="cms-weekend-event-location"><?php echo esc_html( $location ); ?></p>
									<?php endif; ?>
									<a class="cms-weekend-event-details" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open event details', 'chattanooga-music-scene-core' ); ?></a>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
			<p class="cms-weekend-closing"><?php echo esc_html( $settings['closing'] ); ?></p>
			<p class="cms-weekend-calendar-link"><a href="<?php echo esc_url( home_url( '/events/' ) ); ?>"><?php esc_html_e( 'Browse the complete Chattanooga music calendar', 'chattanooga-music-scene-core' ); ?></a></p>
		</div>
		<?php

		return trim( ob_get_clean() );
	}

	private function post_title( array $window ) {
		$start = $window['start'];
		$end   = $window['end'];
		$range = $start->format( 'F j' ) . ( $start->format( 'F' ) === $end->format( 'F' ) ? '–' . $end->format( 'j' ) : '–' . $end->format( 'F j' ) );

		return sprintf( __( 'Chattanooga Music This Weekend: %s', 'chattanooga-music-scene-core' ), $range );
	}

	private function find_existing_post( $week_key ) {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'draft', 'pending', 'future', 'publish' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_WEEK_KEY,
				'meta_value'     => $week_key,
			)
		);

		return $posts ? absint( $posts[0] ) : 0;
	}

	public function generate( $status = 'draft' ) {
		if ( ! current_user_can( 'publish_posts' ) && ! wp_doing_cron() ) {
			return new WP_Error( 'cms_weekend_forbidden', __( 'You do not have permission to generate weekend posts.', 'chattanooga-music-scene-core' ) );
		}

		$status = 'publish' === $status ? 'publish' : 'draft';
		$window = $this->weekend_window();
		$events = $this->get_events( $window );

		if ( is_wp_error( $events ) ) {
			return $events;
		}

		if ( empty( $events ) ) {
			return new WP_Error( 'cms_weekend_no_events', __( 'No published events were found for the coming weekend. Nothing was created.', 'chattanooga-music-scene-core' ) );
		}

		$post_id = $this->find_existing_post( $window['key'] );
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			return new WP_Error( 'cms_weekend_already_published', __( 'This weekend’s guide is already published and was not overwritten.', 'chattanooga-music-scene-core' ) );
		}

		$settings = $this->get_settings();
		$post_data = array(
			'ID'           => $post_id,
			'post_type'    => 'post',
			'post_status'  => $status,
			'post_title'   => $this->post_title( $window ),
			'post_name'    => 'chattanooga-music-this-weekend-' . $window['key'],
			'post_content' => $this->build_post_content( $events, $window ),
			'post_excerpt' => sprintf(
				__( 'Live music happening across Chattanooga from %1$s through %2$s. Open the weekend guide and choose your stage.', 'chattanooga-music-scene-core' ),
				$window['start']->format( 'F j' ),
				$window['end']->format( 'F j' )
			),
			'meta_input'   => array(
				self::META_WEEK_KEY  => $window['key'],
				self::META_GENERATED => current_time( 'mysql' ),
			),
		);

		if ( ! empty( $settings['post_author'] ) && get_user_by( 'id', $settings['post_author'] ) ) {
			$post_data['post_author'] = $settings['post_author'];
		}

		$result = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'post_id'     => $result,
			'event_count' => count( $events ),
			'status'      => $status,
			'week_key'    => $window['key'],
		);
	}

	public function run_scheduled_publish() {
		$settings = $this->get_settings();
		if ( empty( $settings['enabled'] ) || empty( $settings['publish_time'] ) || empty( $settings['post_author'] ) ) {
			return;
		}

		$result = $this->generate( 'publish' );
		update_option(
			'cms_weekend_last_run',
			array(
				'time'   => current_time( 'mysql' ),
				'result' => is_wp_error( $result ) ? $result->get_error_message() : $result,
				'ok'     => ! is_wp_error( $result ),
			),
			false
		);
	}

	public function handle_admin_action() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'chattanooga-music-scene-core' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$requested = isset( $_POST['cms_action'] ) ? sanitize_key( wp_unslash( $_POST['cms_action'] ) ) : '';
		$status    = 'publish' === $requested ? 'publish' : 'draft';
		$result    = $this->generate( $status );
		$args      = array( 'page' => 'cms-weekend-posts' );

		if ( is_wp_error( $result ) ) {
			$args['cms_error'] = rawurlencode( $result->get_error_message() );
		} else {
			$args['cms_created'] = absint( $result['post_id'] );
			$args['cms_count']   = absint( $result['event_count'] );
			$args['cms_status']  = sanitize_key( $result['status'] );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}

	public function enqueue_styles() {
		if ( ! is_singular( 'post' ) || ! get_post_meta( get_queried_object_id(), self::META_WEEK_KEY, true ) ) {
			return;
		}

		wp_enqueue_style( 'cms-weekend-guide', CMS_CORE_URL . 'assets/weekend-guide.css', array(), CMS_CORE_VERSION );
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return;
		}

		$settings = $this->get_settings();
		$window   = $this->weekend_window();
		$events   = $this->get_events( $window );
		$next_run = wp_next_scheduled( self::CRON_HOOK );
		$last_run = get_option( 'cms_weekend_last_run', array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Chattanooga Weekend Posts', 'chattanooga-music-scene-core' ); ?></h1>
			<?php settings_errors( self::OPTION_SETTINGS ); ?>
			<?php if ( isset( $_GET['cms_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['cms_error'] ) ) ) ); ?></p></div>
			<?php elseif ( isset( $_GET['cms_created'], $_GET['cms_count'] ) ) : ?>
				<div class="notice notice-success"><p>
					<?php
					echo esc_html(
						sprintf(
							__( 'The %1$s weekend guide was generated with %2$d events.', 'chattanooga-music-scene-core' ),
							isset( $_GET['cms_status'] ) ? sanitize_key( wp_unslash( $_GET['cms_status'] ) ) : 'draft',
							absint( $_GET['cms_count'] )
						)
					);
					?>
					<a href="<?php echo esc_url( get_edit_post_link( absint( $_GET['cms_created'] ) ) ); ?>"><?php esc_html_e( 'Open the post', 'chattanooga-music-scene-core' ); ?></a>
				</p></div>
			<?php endif; ?>

			<div class="card" style="max-width: 860px">
				<h2><?php echo esc_html( $this->post_title( $window ) ); ?></h2>
				<p>
					<?php
					if ( is_wp_error( $events ) ) {
						echo esc_html( $events->get_error_message() );
					} else {
						echo esc_html( sprintf( _n( '%d published event found.', '%d published events found.', count( $events ), 'chattanooga-music-scene-core' ), count( $events ) ) );
					}
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:.5rem">
					<input type="hidden" name="action" value="cms_weekend_action">
					<input type="hidden" name="cms_action" value="draft">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<?php submit_button( __( 'Generate or Update Draft', 'chattanooga-music-scene-core' ), 'primary', 'submit', false, empty( $events ) || is_wp_error( $events ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
					<input type="hidden" name="action" value="cms_weekend_action">
					<input type="hidden" name="cms_action" value="publish">
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
					<?php submit_button( __( 'Publish Now', 'chattanooga-music-scene-core' ), 'secondary', 'submit', false, empty( $events ) || is_wp_error( $events ) ? array( 'disabled' => 'disabled' ) : array() ); ?>
				</form>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'cms_weekend_posts' ); ?>
				<h2><?php esc_html_e( 'Automatic Thursday Publishing', 'chattanooga-music-scene-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Automatic publishing', 'chattanooga-music-scene-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Publish a Friday–Sunday guide every Thursday', 'chattanooga-music-scene-core' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="cms-weekend-time"><?php esc_html_e( 'Thursday time', 'chattanooga-music-scene-core' ); ?></label></th>
						<td><input id="cms-weekend-time" type="time" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[publish_time]" value="<?php echo esc_attr( $settings['publish_time'] ); ?>"> <p class="description"><?php echo esc_html( wp_timezone_string() ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="cms-weekend-author"><?php esc_html_e( 'Post author', 'chattanooga-music-scene-core' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_users(
								array(
									'id'                 => 'cms-weekend-author',
									'name'               => self::OPTION_SETTINGS . '[post_author]',
									'selected'           => absint( $settings['post_author'] ),
									'show_option_none'   => __( 'Select an author', 'chattanooga-music-scene-core' ),
									'option_none_value'  => 0,
									'who'                => 'authors',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Choose the WordPress author whose Jetpack Social connection should publish the scheduled post.', 'chattanooga-music-scene-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cms-weekend-introduction"><?php esc_html_e( 'Introduction', 'chattanooga-music-scene-core' ); ?></label></th>
						<td><textarea id="cms-weekend-introduction" class="large-text" rows="3" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[introduction]"><?php echo esc_textarea( $settings['introduction'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="cms-weekend-closing"><?php esc_html_e( 'Closing note', 'chattanooga-music-scene-core' ); ?></label></th>
						<td><textarea id="cms-weekend-closing" class="large-text" rows="3" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[closing]"><?php echo esc_textarea( $settings['closing'] ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Weekend Settings', 'chattanooga-music-scene-core' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Scheduler status', 'chattanooga-music-scene-core' ); ?></h2>
			<p><?php echo $next_run ? esc_html( wp_date( 'l, F j, Y \a\t g:i a T', $next_run, wp_timezone() ) ) : esc_html__( 'Automatic publishing is disabled.', 'chattanooga-music-scene-core' ); ?></p>
			<?php if ( ! empty( $last_run['time'] ) ) : ?>
				<p><?php echo esc_html( sprintf( __( 'Last run: %1$s — %2$s', 'chattanooga-music-scene-core' ), $last_run['time'], ! empty( $last_run['ok'] ) ? __( 'successful', 'chattanooga-music-scene-core' ) : (string) $last_run['result'] ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
