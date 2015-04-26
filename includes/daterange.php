<?php
if(!defined('WPINC')) {
	exit;
}

// Class for database access via wordpress functions
class EL_Daterange {
	private static $instance;
	public $date_formats;
	public $daterange_formats;

	public static function &get_instance() {
		// Create class instance if required
		if(!isset( self::$instance)) {
			self::$instance = new  self();
		}
		// Return class instance
		return self::$instance;
	}

	private function __construct() {
		$this->init_formats();
		add_action('admin_init', array(&$this, 'load_formats_helptexts'), 2);
	}

	public function init_formats() {
		$this->date_formats = array(
			'year'         => array('regex' => '^((19[7-9]\d)|(2\d{3}))$',
			                        'start' => '%v%-01-01',
			                        'end'   => '%v%-12-31'),
			'month'        => array('regex' => '^((19[7-9]\d)|(2\d{3}))-(0[1-9]|1[012])$',
			                        'start' => '%v%-01',
			                        'end'   => '%v%-31'),
			'day'          => array('regex' => '^((19[7-9]\d)|(2\d{3}))-(0[1-9]|1[012])-(0[1-9]|[12]\d|3[01])$',
			                        'start' => '%v%',
			                        'end'   => '%v%'),
		);
		$this->daterange_formats = array(
			'date_range'   => array('regex' => '.+~.+'),
			'all'          => array('regex' => '^all$',
			                        'start' => '1970-01-01',
			                        'end'   => '2999-12-31'),
			'upcoming'     => array('regex' => '^upcoming$',
			                        'start' => '--func--date("Y-m-d", current_time("timestamp"));',
			                        'end'   => '2999-12-31'),
			'past'         => array('regex' => '^past$',
			                        'start' => '1970-01-01',
			                        'end'   => '--func--date("Y-m-d", current_time("timestamp")-86400);'),  // previous day (86400 seconds = 1*24*60*60 = 1 day
		);
	}

	public function load_formats_helptexts() {
		require_once(EL_PATH.'includes/daterange_helptexts.php');
		foreach($date_formats_helptexts as $name => $values) {
			$this->date_formats[$name] += $values;
		}
		unset($date_formats_helptexts);
		foreach($daterange_formats_helptexts as $name => $values) {
			$this->daterange_formats[$name] += $values;
		}
		unset($daterange_formats_helptexts);
	}

	public function check_date_format($element) {
		foreach($this->date_formats as $date_type) {
			if(preg_match('@'.$date_type['regex'].'@', $element)) {
				return $this->get_date_range($element, $date_type);
			}
		}
		return null;
	}

	public function check_daterange_format($element) {
		foreach($this->daterange_formats as $key => $daterange_type) {
			if(preg_match('@'.$daterange_type['regex'].'@', $element)) {
				//check for date_range which requires special handling
				if('date_range' == $key) {
					$sep_pos = strpos($element, "~");
					$startrange = $this->check_date_format(substr($element, 0, $sep_pos));
					$endrange = $this->check_date_format(substr($element, $sep_pos+1));
					return array($startrange[0], $endrange[1]);
				}
				return $this->get_date_range($element, $daterange_type);
			}
		}
		return null;
	}

	public function get_date_range($element, &$range_type) {
		// range start
		if(substr($range_type['start'], 0, 8) == '--func--') {
			eval('$range[0] = '.substr($range_type['start'], 8));
		}
		else {
			$range[0] = str_replace('%v%', $element, $range_type['start']);
		}
		// range end
		if(substr($range_type['end'], 0, 8) == '--func--') {
			eval('$range[1] = '.substr($range_type['end'], 8));
		}
		else {
			$range[1] = str_replace('%v%', $element, $range_type['end']);
		}
		return $range;
	}
}

/* create date_create_from_format (DateTime::createFromFormat) alternative for PHP 5.2
 *
 * This function is only a small implementation of this function with reduced functionality to handle sql dates (format: 2014-01-31)
 */
if(!function_exists("date_create_from_format")) {
	function date_create_from_format($dformat, $dvalue) {
		$d = new DateTime($dvalue);
		return $d;
	}
}
?>
