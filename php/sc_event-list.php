<?php
require_once( EL_PATH.'php/db.php' );

// This class handles the shortcode [event-list]
class sc_event_list {
	private static $instance;
	private $db;
	private $options;
	private $atts;
	private $num_sc_loaded;

	public static function &get_instance() {
		// Create class instance if required
		if( !isset( self::$instance ) ) {
			self::$instance = new sc_event_list();
		}
		// Return class instance
		return self::$instance;
	}

	private function __construct() {
		$this->db = el_db::get_instance();
		//$this->options = &el_options::get_instance();

		// All available attributes
		$this->atts = array(

			'initial_date'  => array( 'val'     => 'upcoming<br />year e.g. "2012"',
			                          'std_val' => 'upcoming',
			                          'desc'    => 'This attribute specifies which events are initially shown. The standard is to show the upcoming events.<br />
			                                        Specify a year e.g. "2012" to change this behavior.' ),

			'num_events'    => array( 'val'     => 'number',
			                          'std_val' => '0',
			                          'desc'    => 'This attribute specifies how many events should be displayed if upcoming events is selected.<br />
			                                        0 is the standard value which means that all events will be displayed.' ),

			'show_nav'      => array( 'val'     => '0..false<br />1..true',
			                          'std_val' => '1',
			                          'desc'    => 'This attribute specifies if the calendar navigation should be displayed.'),

			'show_details'  => array( 'val'     => '0..false<br />1..true',
			                          'std_val' => '1',
			                          'desc'    => 'This attribute specifies if the details are displayed in the event list.'),

			'show_location' => array( 'val'     => '0..false<br />1..true',
			                          'std_val' => '1',
			                          'desc'    => 'This attribute specifies if the location is displayed in the event list.'),

			'link_to_event' => array( 'val'     => '0..false<br />1..true',
			                          'std_val' => '1',
			                          'desc'    => 'This attribute specifies if a link to the single event should be added onto the event name in the event list.')
			// Internal attributes:
			//   'sc_id'
			//   'ytd'
		);

		$this->num_sc_loaded = 0;
	}

	public function get_atts() {
		return $this->atts;
	}

	// main function to show the rendered HTML output
	public function show_html( $atts ) {
		// change number of shortcodes
		$this->num_sc_loaded++;
		// check shortcode attributes
		$std_values = array();
		foreach( $this->atts as $aname => $attribute ) {
			$std_values[$aname] = $attribute['std_val'];
		}
		$a = shortcode_atts( $std_values, $atts );
		// add internal attributes
		$a['sc_id'] = $this->num_sc_loaded;
		$a['event_id'] = isset( $_GET['event_id_'.$a['sc_id']] ) ? (integer)$_GET['event_id_'.$a['sc_id']] : null;
		$a['ytd'] = $this->get_ytd( $a );

		if( is_numeric( $a['event_id'] ) ) {
			// show events details if event_id is set
			$out = $this->html_event_details( $a );
		}
		else {
			// show full event list
			$out = $this->html_events( $a );
		}
		return $out;
	}

	private function html_event_details( &$a ) {
		$event = $this->db->get_event( $a['event_id'] );
		$out = $this->html_calendar_nav( $a );
		$out .= '
			<h2>Event Information:</h2>
			<ul class="event-list">';
		$out .= $this->html_event( $event, $a );
		$out .= '</ul>';
		return $out;
	}

	private function html_events( &$a ) {
		// specify to show all events if not upcoming is selected
		if( is_numeric( $a['ytd'] ) ) {
			$a['num_events'] = 0;
		}
		$events = $this->db->get_events( $a['ytd'], $a['num_events'] );
		$out = '';
		// TODO: add rss feed
		//		if ($mfgigcal_settings['rss']) {
		//			(get_option('permalink_structure')) ? $feed_link = "/feed/events" : $feed_link = "/?feed=events";
		//			$out .= "<a href=\"$feed_link\" class=\"rss-link\">RSS</a>";
		//		}

		// generate output
		if( 0 != $a['show_nav'] ) {
			$out .= $this->html_calendar_nav( $a );
		}
		// TODO: Setting missing
		if( empty( $events ) /*&& $mfgigcal_settings['no-events'] == "text"*/ ) {
			$out .= "<p>" . 'no event' /*$mfgigcal_settings['message'] */. "</p>";
		}
		/*		else if (empty($events)) {
		 $this_year = date("Y");
		// show the current year
		$sql = "SELECT * FROM $mfgigcal_table WHERE (end_date >= '$this_year-01-01' AND start_date <= '$this_year-12-31') ORDER BY start_date ASC";
		$events = $wpdb->get_results($sql);
		if (empty($events)) {
		$out .= "<p>" . $mfgigcal_settings['message'] . "</p>";
		return $out;
		}
		}
		*/
		else {
			// set html code
			$out .= '
				<ul class="event-list">';
			$single_day_only = $this->is_single_day_only( $events );
			foreach ($events as $event) {
				$out .= $this->html_event( $event, $a, $this->get_url( $a['sc_id'] ), $single_day_only );
			}
			$out .= '</ul>';
		}
		return $out;
	}

	private function html_event( &$event, &$a, $url=null, $single_day_only=false ) {
		$out = '
			 	<li class="event">';
		$out .= $this->html_fulldate( $event->start_date, $event->end_date, $single_day_only );
		$out .= '
					<div class="event-info';
		if( $single_day_only ) {
			$out .= ' single-day';
		}
		else {
			$out .= ' multi-day';
		}
		$out .= '"><h3>';
		if( null !== $url && 0 != $a['link_to_event'] ) {
			$out .= '<a href="'.$url.'event_id_'.$a['sc_id'].'='.$event->id.'">'.$event->title.'</a>';
		}
		else {
			$out .= $event->title;
		}
		$out .= '</h3>';
		if( $event->time != '' ) {
			$out .= '<span class="event-time">'.mysql2date( get_option( 'time_format' ), $event->time ).'</span>';
		}
		if( null === $a || 0 != $a['show_location'] ) {
			$out .= '<span class="event-location">'.$event->location.'</span>';
		}
		if( null === $a || 0 != $a['show_details'] ) {
			$out .= '<div class="event-details">'.$event->details.'</div>';
		}
		$out .= '</div>
				</li>';
		return $out;
	}

	private function html_fulldate( $start_date, $end_date, $single_day_only=false ) {
		$out = '
					';
		if( $start_date === $end_date ) {
			// one day event
			$out .= '<div class="event-date">';
			if( $single_day_only ) {
				$out .= '<div class="start-date">';
			}
			else {
				$out .= '<div class="end-date">';
			}
			$out .= $this->html_date( $start_date );
			$out .= '</div>';
		}
		else {
			// multi day event
			$out .= '<div class="event-date multi-date">';
			$out .= '<div class="start-date">';
			$out .= $this->html_date( $start_date );
			$out .= '</div>';
			$out .= '<div class="end-date">';
			$out .= $this->html_date( $end_date );
			$out .= '</div>';
		}
		$out .= '</div>';
		return $out;
	}

	private function html_date( $date ) {
		$out = '<div class="event-weekday">'.mysql2date( 'D', $date ).'</div>';
		$out .= '<div class="event-day">'.mysql2date( 'd', $date ).'</div>';
		$out .= '<div class="event-month">'.mysql2date( 'M', $date ).'</div>';
		$out .= '<div class="event-year">'.mysql2date( 'Y', $date ).'</div>';
		return $out;
	}

	private function html_calendar_nav( &$a ) {
		$first_year = $this->db->get_event_date( 'first' );
		$last_year = $this->db->get_event_date( 'last' );

		$url = $this->get_url( $a['sc_id'] );
		$out = '<div class="subsubsub">';
		if( is_numeric( $a['ytd'] ) || is_numeric( $a['event_id'] ) ) {
			$out .= '<a href="'.$url.'">Upcoming</a>';
		}
		else {
			$out .= '<strong>Upcoming</strong>';
		}
		for( $year=$last_year; $year>=$first_year; $year-- ) {
			$out .= ' | ';
			if( $year == $a['ytd'] ) {
				$out .= '<strong>'.$year.'</strong>';
			}
			else {
				$out .= '<a href="'.$url.'ytd_'.$a['sc_id'].'='.$year.'">'.$year.'</a>';
			}
		}
		$out .= '</div><br />';
		return $out;
	}

	private function get_ytd( &$a ) {
		if( isset( $_GET['ytd_'.$a['sc_id']] ) && is_numeric( $_GET['ytd_'.$a['sc_id']] ) ) {
			$ytd = (int)$_GET['ytd_'.$a['sc_id']];
		}
		elseif( isset( $a['initial_date'] ) && is_numeric( $a['initial_date'] ) ) {
			$ytd = (int)$a['initial_date'];
		}
		else {
			$ytd = 'upcoming';
		}
		return $ytd;
	}

	private function get_url( $sc_id ) {
		if( get_option( 'permalink_structure' ) ) {
			$url = get_permalink().'?';
		}
		else {
			$url ='?';
			foreach( $_GET as  $k => $v ) {
				if( 'ytd_'.$sc_id !== $k && 'event_id_'.$sc_id !== $k ) {
					$url .= $k.'='.$v.'&amp;';
				}
			}
		}
		return $url;
	}

	private function is_single_day_only( &$events ) {
		foreach( $events as $event ) {
			if( $event->start_date !== $event->end_date ) {
				return false;
			}
		}
		return true;
	}
}
?>
