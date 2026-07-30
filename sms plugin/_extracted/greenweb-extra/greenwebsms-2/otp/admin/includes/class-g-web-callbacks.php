<?php

if ( ! defined( _greenweb_xd("\xe0\xf0\x90\x84\xa4\xa2\xef") ) ) {
	exit;
}

class G_Web_Callbacks {

	public function __construct(){

	}

	public function simplify_args( $args ){

		if( !isset( $args[_greenweb_xd("\xc8\xd6")] ) || ( !isset( $args[_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84")] )  && $args[_greenweb_xd("\xd5\xcb\xb3\xb1")] === _greenweb_xd("\xd2\xd7\xb7\xa0\x8c\x98\xc0") )  ){
			return;
		}

		$data = array();

		$value = get_option($args[_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84")]);

		if( is_array( $value ) ){
			$value = isset( $value[$args[_greenweb_xd("\xc8\xd6")]] ) ? $value[$args[_greenweb_xd("\xc8\xd6")]] : ( isset( $args[_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3")] ) ? $args[_greenweb_xd("\xc5\xd7\xa5\xb5\x90\x9a\xd3")] : null );
		}

		if( isset( $args[_greenweb_xd("\xc4\xca\xb7\xa6\x84")] ) ){

			if( isset( $args[_greenweb_xd("\xc4\xca\xb7\xa6\x84")][_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xd4")] ) ){
				$data[_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xd4")] = $args[_greenweb_xd("\xc4\xca\xb7\xa6\x84")][_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xd4")];
			}

		}

		$description = isset( $args[_greenweb_xd("\xc5\xd7\xb0\xb7")] ) ? esc_attr( $args[_greenweb_xd("\xc5\xd7\xb0\xb7")] ) : null;

		$data = array_merge($data,
			array(
				_greenweb_xd("\xc8\xd6") 			=> $args[_greenweb_xd("\xce\xc2\xb7\xbd\x8a\x98\xf8\xd6\xa8\xbd\x84")].'['.$args[_greenweb_xd("\xc8\xd6")].']',
				_greenweb_xd("\xd7\xd3\xaf\xa1\x80") 		=> $value,
				_greenweb_xd("\xc5\xd7\xb0\xb7\x97\x9f\xd7\xcc\xa0\xbf\x8f") 	=> $description,
			)
		);

		return $data;

	}

	public function section($args){
		extract( $args );
		?>
		<span class="section-title"><?php echo esc_attr($title); ?></span>
		<?php
	}

	public function checkbox( $args ){
		extract( $this->simplify_args( $args ) );
		?>
		<input type="hidden" name="<?php echo $id; ?>" value="no">
		<input type="checkbox" class="g-input-checkbox" id="<?php echo $id; ?>" name="<?php echo $id; ?>" value="yes" <?php checked($value, "yes"); ?> />
		<?php
		$this->description($description);
	}

	public function color( $args ){
		extract( $this->simplify_args( $args ) );
		?>
		<input type="text" class="color-field g-input-text" id="<?php echo $id; ?>" name="<?php echo $id; ?>" value="<?php echo $value; ?>" />
		<?php
		$this->description($description);
	}

	public function text( $args ){
		extract( $this->simplify_args( $args ) );
		?>
		<input type="text" class="g-input-text" id="<?php echo $id; ?>" name="<?php echo $id; ?>" value="<?php echo $value; ?>" />
		<?php
		$this->description($description);
	}

	public function textarea( $args ){
		extract( $this->simplify_args( $args ) );
		?>
		<textarea rows="4" cols="50" class="g-input-text" id="<?php echo $id; ?>" name="<?php echo $id; ?>"><?php echo $value; ?></textarea>
		<?php
		$this->description($description);
	}

	public function number( $args ){
		extract( $this->simplify_args( $args ) );
		?>
		<input type="number" class="g-input-number" id="<?php echo $id; ?>" name="<?php echo $id; ?>" value="<?php echo $value; ?>" />
		<?php
		$this->description($description);
	}

	public function upload( $args ){
		extract( $this->simplify_args( $args ) );
		?>
		<a class="button-primary g-upload-icon">Select</a>
		<input type="hidden" id="<?php echo $id; ?>" name="<?php echo $id; ?>" class="g-upload-url" value="<?php echo $value; ?>">
		<a class="button g-remove-media">Remove</a>
		<span class="g-upload-title"></span>
		<p class="description">Supported format: JPEG,PNG </p>
		<?php
	}

	public function select( $args ){
		extract( $this->simplify_args( $args ) );
		?>
		<select name="<?php echo $id; ?>">
			<?php foreach ($options as $option_value => $option_label ): ?>
				<option value="<?php echo $option_value; ?>" <?php selected( $value, $option_value ); ?> > <?php echo $option_label; ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		$this->description($description);
	}

	public function description($description){
		if( !isset( $description ) ) return;
		?>
		<p class="description"><?php echo $description; ?></p>
		<?php
	}
}

return new G_Web_Callbacks();

?>
