<?php
//
//	创建验证码函数
//
//	
function create_captcha($config = array()) {
	
	// Check for GD library
	if( !function_exists('gd_info') ) {
		throw new Exception('Required GD library is missing');
	}
	
	$bg_path = ABSPATH . 'content/captcha/';
	$font_path = ABSPATH . 'content/fonts/';

	// Default values defaults
	$defaults = array(
		'code' => '',
		'min_length' => 5,
		'max_length' => 5,
		'backgrounds' => array(
			$bg_path . 'white-carbon.png',
		),
		'fonts' => array(
			$font_path . 'JollyLodger-Regular.ttf',
			$font_path . 'FreckleFace-Regular.ttf'
		),
		'characters' => 'ABCDEFGHJKLMNPRSTUVWXYZabcdefghjkmnprstuvwxyz23456789',
		'min_font_size' => 28,
		'max_font_size' => 28,
		'color' => '#666',
		'angle_min' => 0,
		'angle_max' => 10,
		'bg_enable' => false,
		'img_width' => 80,
		'img_height' =>30,
		'shadow' => true,
		'shadow_color' => '#fff',
		'shadow_offset_x' => -1,
		'shadow_offset_y' => 1
	);
	
	// Overwrite defaults with custom config values
	if( is_array($config) ) {
		foreach( $config as $key => $value ) $defaults[$key] = $value;
	}
	
	// Restrict certain values
	if( $defaults['min_length'] < 1 ) $defaults['min_length'] = 1;
	if( $defaults['angle_min'] < 0 ) $defaults['angle_min'] = 0;
	if( $defaults['angle_max'] > 10 ) $defaults['angle_max'] = 10;
	if( $defaults['angle_max'] < $defaults['angle_min'] ) $defaults['angle_max'] = $defaults['angle_min'];
	if( $defaults['min_font_size'] < 10 ) $defaults['min_font_size'] = 10;
	if( $defaults['max_font_size'] < $defaults['min_font_size'] ) $defaults['max_font_size'] = $defaults['min_font_size'];
	
	// Use milliseconds instead of seconds
	srand(microtime() * 100);
	
	// Generate CAPTCHA code if not set by user
	if( empty($defaults['code']) ) {
		$defaults['code'] = '';
		$length = rand($defaults['min_length'], $defaults['max_length']);
		while( strlen($defaults['code']) < $length ) {
			$defaults['code'] .= substr($defaults['characters'], rand() % (strlen($defaults['characters'])), 1);
		}
	}
	
	// Generate HTML for image src
	$image_src = substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT'])) . '?_CAPTCHA&amp;t=' . urlencode(microtime());
	$image_src = '/' . ltrim(preg_replace('/\\\\/', '/', $image_src), '/');
	
	$_SESSION['_CAPTCHA']['config'] = serialize($defaults);
	
	return array(
		'code' => $defaults['code'],
		'image_src' => $image_src
	);
	
}


if( !function_exists('hex2rgb') ) {
	function hex2rgb($hex_str, $return_string = false, $separator = ',') {
		$hex_str = preg_replace("/[^0-9A-Fa-f]/", '', $hex_str); // Gets a proper hex string
		$rgb_array = array();
		if( strlen($hex_str) == 6 ) {
			$color_val = hexdec($hex_str);
			$rgb_array['r'] = 0xFF & ($color_val >> 0x10);
			$rgb_array['g'] = 0xFF & ($color_val >> 0x8);
			$rgb_array['b'] = 0xFF & $color_val;
		} elseif( strlen($hex_str) == 3 ) {
			$rgb_array['r'] = hexdec(str_repeat(substr($hex_str, 0, 1), 2));
			$rgb_array['g'] = hexdec(str_repeat(substr($hex_str, 1, 1), 2));
			$rgb_array['b'] = hexdec(str_repeat(substr($hex_str, 2, 1), 2));
		} else {
			return false;
		}
		return $return_string ? implode($separator, $rgb_array) : $rgb_array;
	}
}

// Draw the image
if( isset($_GET['_CAPTCHA']) ) {
	
	session_start();
	
	$defaults = unserialize($_SESSION['_CAPTCHA']['config']);
	if( !$defaults ) exit();
	
	unset($_SESSION['_CAPTCHA']);
	
	// Use milliseconds instead of seconds
	srand(microtime() * 100);
	
	if($defaults['bg_enable']){
		// Pick random background, get info, and start captcha
		$background = $defaults['backgrounds'][rand(0, count($defaults['backgrounds']) -1)];
		list($bg_width, $bg_height, $bg_type, $bg_attr) = getimagesize($background);
		
		$captcha = imagecreatefrompng($background);
	}else{

		$captcha = imagecreatetruecolor($defaults['img_width'], $defaults['img_height']);
		$white = imagecolorallocate($captcha, 255, 255, 255);
		imagefill($captcha, 0, 0, $white);
	}
	
	
	$color = hex2rgb($defaults['color']);
	$color = imagecolorallocate($captcha, $color['r'], $color['g'], $color['b']);
	
	// Determine text angle
	$angle = rand( $defaults['angle_min'], $defaults['angle_max'] ) * (rand(0, 1) == 1 ? -1 : 1);
	
	// Select font randomly
	$font = $defaults['fonts'][rand(0, count($defaults['fonts']) - 1)];
	
	// Verify font file exists
	if( !file_exists($font) ) throw new Exception('Font file not found: ' . $font);
	
	//Set the font size.
	$font_size = rand($defaults['min_font_size'], $defaults['max_font_size']);
	$text_box_size = imagettfbbox($font_size, $angle, $font, $defaults['code']);
	
	// Determine text position
	$box_width = abs($text_box_size[6] - $text_box_size[2]);
	$box_height = abs($text_box_size[5] - $text_box_size[1]);
	$text_pos_x_min = 0;
	if($defaults['bg_enable']){
		$text_pos_x_max = ($bg_width) - ($box_width);
	}else{
		$text_pos_x_max = ($defaults['img_width']) - ($box_width);
	}
	$text_pos_x = rand($text_pos_x_min, $text_pos_x_max);			
	$text_pos_y_min = $box_height;
	if($defaults['bg_enable']){
		$text_pos_y_max = ($bg_height) - ($box_height / 2);
	}else{
		$text_pos_y_max = ($defaults['img_height']) - ($box_height / 2);
	}
	$text_pos_y = rand($text_pos_y_min, $text_pos_y_max);
	
	// Draw shadow
	if( $defaults['shadow'] ){
		$shadow_color = hex2rgb($defaults['shadow_color']);
	 	$shadow_color = imagecolorallocate($captcha, $shadow_color['r'], $shadow_color['g'], $shadow_color['b']);
		imagettftext($captcha, $font_size, $angle, $text_pos_x + $defaults['shadow_offset_x'], $text_pos_y + $defaults['shadow_offset_y'], $shadow_color, $font, $defaults['code']);	
	}
	
	// Draw text
	imagettftext($captcha, $font_size, $angle, $text_pos_x, $text_pos_y, $color, $font, $defaults['code']);	
	
	// Output image
	header("Content-type: image/png");
	imagepng($captcha);

	// Clear image
	imagedestroy($captcha);
}
