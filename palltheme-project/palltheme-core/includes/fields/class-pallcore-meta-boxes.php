<?php
/**
 * Generic meta box renderer + saver driven by pallcore_field_schema().
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta boxes.
 */
final class Pallcore_Meta_Boxes {

	/**
	 * Hook in.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Register boxes for each post type in the schema.
	 */
	public static function register(): void {
		foreach ( pallcore_field_schema() as $post_type => $boxes ) {
			foreach ( $boxes as $id => [ $title, $context, $fields ] ) {
				add_meta_box( $id, $title, array( __CLASS__, 'render' ), $post_type, $context, 'high', array( 'fields' => $fields ) );
			}
		}
	}

	/**
	 * Admin assets on edit screens of supported types only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! array_key_exists( $screen->post_type, pallcore_field_schema() ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'pallcore-admin', PALLCORE_URL . 'assets/css/admin.css', array(), PALLCORE_VERSION );
		wp_enqueue_script( 'pallcore-admin', PALLCORE_URL . 'assets/js/admin.js', array(), PALLCORE_VERSION, true );
		wp_localize_script(
			'pallcore-admin',
			'pallcoreAdmin',
			array(
				'chooseImage'  => __( 'Choose image', 'palltheme-core' ),
				'chooseImages' => __( 'Choose images', 'palltheme-core' ),
				'use'          => __( 'Use', 'palltheme-core' ),
			)
		);
	}

	/**
	 * Render a box.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $box  Box args.
	 */
	public static function render( WP_Post $post, array $box ): void {
		wp_nonce_field( 'pallcore_save_' . $box['id'], 'pallcore_nonce_' . $box['id'] );
		echo '<div class="pallcore-fields">';
		foreach ( $box['args']['fields'] as $key => $field ) {
			$value = get_post_meta( $post->ID, '_pall_' . $key, true );
			if ( '' === $value && isset( $field['default'] ) && 'auto-draft' === $post->post_status ) {
				$value = $field['default'];
			}
			self::field( $key, $field, $value );
		}
		echo '</div>';
	}

	/**
	 * Render one field.
	 *
	 * @param string $key   Key.
	 * @param array  $field Definition.
	 * @param mixed  $value Value.
	 */
	private static function field( string $key, array $field, $value ): void {
		$name = 'pall[' . $key . ']';
		$id   = 'pall-' . $key;
		$type = $field['type'];

		echo '<div class="pallcore-field pallcore-field--' . esc_attr( $type ) . '">';
		if ( 'checkbox' !== $type ) {
			echo '<label class="pallcore-label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>';
		}

		switch ( $type ) {
			case 'textarea':
			case 'html':
				printf( '<textarea id="%1$s" name="%2$s" rows="%4$d" class="widefat">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ), 'html' === $type ? 6 : 3 );
				break;

			case 'lines':
				printf( '<textarea id="%1$s" name="%2$s" rows="5" class="widefat">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( implode( "\n", (array) ( $value ?: array() ) ) ) );
				break;

			case 'checkbox':
				printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ), esc_html( $field['label'] ) );
				break;

			case 'select':
			case 'icon':
				$choices = 'icon' === $type ? array( '' => __( '— Choose —', 'palltheme-core' ) ) + pallcore_icon_choices() : $field['choices'];
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( 'icon' === $type ? ' data-pallcore-icon' : '' ) . '>';
				foreach ( $choices as $k => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( (string) $k ), selected( (string) $value, (string) $k, false ), esc_html( $label ) );
				}
				echo '</select>';
				if ( 'icon' === $type ) {
					echo '<span class="pallcore-icon-preview" data-pallcore-icon-preview>' . ( $value ? wp_kses( pallcore_icon( (string) $value ), pallcore_svg_kses() ) : '' ) . '</span>';
					echo '<template id="pallcore-icon-templates">';
					foreach ( array_keys( pallcore_icon_library() ) as $icon ) {
						echo '<span data-icon="' . esc_attr( $icon ) . '">' . wp_kses( pallcore_icon( $icon ), pallcore_svg_kses() ) . '</span>';
					}
					echo '</template>';
				}
				break;

			case 'image':
				$img = $value ? wp_get_attachment_image( (int) $value, 'thumbnail' ) : '';
				printf(
					'<div class="pallcore-media" data-pallcore-media="single"><input type="hidden" id="%1$s" name="%2$s" value="%3$s"><div class="pallcore-media__preview">%4$s</div><button type="button" class="button" data-pallcore-media-pick>%5$s</button> <button type="button" class="button-link-delete" data-pallcore-media-clear>%6$s</button></div>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					$img, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
					esc_html__( 'Select image', 'palltheme-core' ),
					esc_html__( 'Remove', 'palltheme-core' )
				);
				break;

			case 'gallery':
				$ids  = array_filter( array_map( 'absint', (array) ( $value ?: array() ) ) );
				$imgs = '';
				foreach ( $ids as $img_id ) {
					$imgs .= wp_get_attachment_image( $img_id, 'thumbnail' );
				}
				printf(
					'<div class="pallcore-media" data-pallcore-media="multiple"><input type="hidden" id="%1$s" name="%2$s" value="%3$s"><div class="pallcore-media__preview">%4$s</div><button type="button" class="button" data-pallcore-media-pick>%5$s</button> <button type="button" class="button-link-delete" data-pallcore-media-clear>%6$s</button></div>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( implode( ',', $ids ) ),
					$imgs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_html__( 'Select images', 'palltheme-core' ),
					esc_html__( 'Clear', 'palltheme-core' )
				);
				break;

			case 'pairs':
				$rows = is_array( $value ) && $value ? $value : array( array( '', '' ) );
				$cols = $field['cols'] ?? array( '', '' );
				echo '<div class="pallcore-pairs" data-pallcore-pairs data-name="' . esc_attr( $name ) . '">';
				echo '<div class="pallcore-pairs__head"><span>' . esc_html( $cols[0] ) . '</span><span>' . esc_html( $cols[1] ) . '</span><span></span></div>';
				echo '<div class="pallcore-pairs__rows">';
				foreach ( $rows as $i => $row ) {
					self::pair_row( $name, (int) $i, (string) ( $row[0] ?? '' ), (string) ( $row[1] ?? '' ) );
				}
				echo '</div><template>';
				self::pair_row( $name, '__i__', '', '' );
				echo '</template><button type="button" class="button" data-pallcore-pairs-add>' . esc_html__( '+ Add row', 'palltheme-core' ) . '</button></div>';
				break;

			case 'posts':
			case 'products':
				$selected  = array_map( 'absint', (array) ( $value ?: array() ) );
				$post_type = 'products' === $type ? 'product' : $field['post_type'];
				if ( ! post_type_exists( $post_type ) ) {
					echo '<p class="description">' . esc_html__( 'Not available (required plugin inactive).', 'palltheme-core' ) . '</p>';
					break;
				}
				$options = get_posts(
					array(
						'post_type'      => $post_type,
						'posts_per_page' => 300,
						'post_status'    => 'publish',
						'orderby'        => 'title',
						'order'          => 'ASC',
						'fields'         => 'ids',
						'post__not_in'   => array( get_the_ID() ),
					)
				);
				echo '<input type="search" class="pallcore-filter" placeholder="' . esc_attr__( 'Filter list…', 'palltheme-core' ) . '" data-pallcore-filter="' . esc_attr( $id ) . '">';
				echo '<select multiple size="6" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]" class="widefat">';
				foreach ( $options as $opt ) {
					printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $opt, selected( in_array( (int) $opt, $selected, true ), true, false ), esc_html( get_the_title( $opt ) ) );
				}
				echo '</select><p class="description">' . esc_html__( 'Hold Ctrl (Cmd on Mac) to select several.', 'palltheme-core' ) . '</p>';
				break;

			default:
				$input_type = in_array( $type, array( 'url', 'email', 'number' ), true ) ? $type : 'text';
				printf( '<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="widefat">', esc_attr( $input_type ), esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
		}

		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Repeater row.
	 *
	 * @param string     $name Base name.
	 * @param int|string $i    Index.
	 * @param string     $a    First value.
	 * @param string     $b    Second value.
	 */
	private static function pair_row( string $name, $i, string $a, string $b ): void {
		printf(
			'<div class="pallcore-pairs__row"><input type="text" name="%1$s[%2$s][0]" value="%3$s"><textarea name="%1$s[%2$s][1]" rows="1">%4$s</textarea><button type="button" class="button-link-delete" data-pallcore-pairs-remove aria-label="%5$s">✕</button></div>',
			esc_attr( $name ),
			esc_attr( (string) $i ),
			esc_attr( $a ),
			esc_textarea( $b ),
			esc_attr__( 'Remove row', 'palltheme-core' )
		);
	}

	/**
	 * Sanitize a value by field type.
	 *
	 * @param array $field Definition.
	 * @param mixed $raw   Raw value (already unslashed).
	 * @return mixed
	 */
	public static function sanitize( array $field, $raw ) {
		switch ( $field['type'] ) {
			case 'textarea':
				return sanitize_textarea_field( (string) $raw );
			case 'html':
				return wp_kses_post( (string) $raw );
			case 'url':
				return esc_url_raw( (string) $raw );
			case 'email':
				return sanitize_email( (string) $raw );
			case 'number':
				return is_numeric( $raw ) ? (string) ( 0 + $raw ) : '';
			case 'checkbox':
				return $raw ? '1' : '';
			case 'select':
				return array_key_exists( (string) $raw, $field['choices'] ) ? (string) $raw : '';
			case 'icon':
				return array_key_exists( (string) $raw, pallcore_icon_library() ) ? (string) $raw : '';
			case 'image':
				return absint( $raw ) ?: '';
			case 'gallery':
				return array_values( array_filter( array_map( 'absint', explode( ',', (string) $raw ) ) ) );
			case 'lines':
				return array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', (string) $raw ) ) ) );
			case 'pairs':
				$rows = array();
				foreach ( (array) $raw as $row ) {
					$a = sanitize_text_field( (string) ( $row[0] ?? '' ) );
					$b = sanitize_textarea_field( (string) ( $row[1] ?? '' ) );
					if ( '' !== $a || '' !== $b ) {
						$rows[] = array( $a, $b );
					}
				}
				return $rows;
			case 'posts':
			case 'products':
				return array_values( array_filter( array_map( 'absint', (array) $raw ) ) );
			default:
				return sanitize_text_field( (string) $raw );
		}
	}

	/**
	 * Save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$schema = pallcore_field_schema();
		if ( empty( $schema[ $post->post_type ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$input = isset( $_POST['pall'] ) && is_array( $_POST['pall'] ) ? wp_unslash( $_POST['pall'] ) : array();

		foreach ( $schema[ $post->post_type ] as $box_id => [ , , $fields ] ) {
			$nonce = isset( $_POST[ 'pallcore_nonce_' . $box_id ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'pallcore_nonce_' . $box_id ] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'pallcore_save_' . $box_id ) ) {
				continue;
			}
			foreach ( $fields as $key => $field ) {
				$value = self::sanitize( $field, $input[ $key ] ?? '' );
				if ( '' === $value || array() === $value ) {
					delete_post_meta( $post_id, '_pall_' . $key );
				} else {
					update_post_meta( $post_id, '_pall_' . $key, $value );
				}
			}
		}
	}
}
Pallcore_Meta_Boxes::init();
