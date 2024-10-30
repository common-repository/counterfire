<?php
/*
Plugin Name: Counterfire
Plugin URI: https://counterfire.info
Description: Counterfire Statistics
Author: sxss
Version: 1.2
*/

/*
 *	TODO
 *	check if new counter_id is valid and available
 *	function to show the graph (shortcode, to use in dashboard and in widgets)
 */

define( 'COUNTERFIRE_STATS', 'counterfire_stats' );
define( 'COUNTERFIRE_DATE_FORMAT', 'YmdHi' );
define( 'COUNTERFIRE_CACHE', 1 );

function cf_update_id( $id ) {

	// TODO: api check if valid id after local check

	// TODO check valide id
	if( strlen( $id ) == 15 && preg_match('/[a-z0-9]{15}?/', $id) )
		update_option( "counterfire_id", $id );

	// if empty, delete option
	elseif( empty( $id ) )
		delete_option( "counterfire_id" );

	else {
		echo "<p>Bitte geben Sie eine gültige ID ein.</p>";
	}
}

function cf_update_include( $checkbox ) {
	// if checkbox is checked (not unchecked)
	if( !empty( $checkbox ) )
		update_option( "counterfire_include_counter", true );
	else
		update_option( "counterfire_include_counter", false );
}

function cf_update_stats() {

	// if last update isn't older then 5 minutes
	if( get_option("counterfire_last_update") < ( date( COUNTERFIRE_DATE_FORMAT ) - COUNTERFIRE_CACHE ) && 1==2 )
		return false;

	// Counterfire ID
	$counterfire_id = get_option("counterfire_id", false );

	// Check for ID
	if( false == $counterfire_id ) {
		return false;
	}

	// API URL
	$api_request = "https://counterfire.info/api.php?id=" . $counterfire_id . "&format=json";

	// Get stats from server (JSON)
	$json = file_get_contents( $api_request );

	// JSON to OBJECT
	$obj = json_decode( $json );

	// check if request was successfull
	if( $obj->status != "success" )
		return false;

	$i = 0;

	// Empty stats notice
	if( 0 == count($obj->statistic) ) {
		echo "Fehler: Statistik konnte nicht aktualisiert werden.";
		return false;
	}

	foreach( $obj->statistic as $s ) {

		if( $i != 0 )
			$break = ',';

		$stats["labels"] .= $break . '"' . substr( $s->date, 0, 6 ) . '"';
		$stats["pageviews"] .= $break . $s->pageviews;
		$stats["visitors"] .= $break . $s->visitors;

		$i++;
	}

	// Serialize Statistic Arrays
	$stats = serialize( $stats );

	// Save stats and timestamp
	update_option( "counterfire_stats", $stats );
	update_option( "counterfire_last_update", date( COUNTERFIRE_DATE_FORMAT ) );

	return true;
}

function cf_stats() {
	// Update stats if cache to old
	$update = cf_update_stats();
	// Get updated stats
	$stats = get_option( "counterfire_stats" );
	// Return as array
	$stats = unserialize( $stats );
	// If invalid data
	if( 3 != sizeof( $stats ) ) {
		echo "<p>Fehler beim Abfragen der Statistik.</p>";
		return false;
	}
	// Return stats
	return $stats;
}

function cf_dashboard_widget() {

	// Update counter ID
	if( $_POST["counterfire-update"] == "update" ) {

		// Update Counterfire ID
		cf_update_id( $_POST["counterfire_id"] );

		// Include counter
		cf_update_include( $_POST["counterfire_include_counter"] );

		// Delete cached stats
		delete_option( "counterfire_stats" );
	}

	// Get counter id
	$counter_id = get_option( "counterfire_id", false );

	// Notice: Insert Counterfire ID
	if( false == $counter_id ) {
		echo '<p style="text-align: center; font-size: 200%; color: #C0C0C0;">Zuerst: Counterfire ID eintragen</p>';
	}

	else {

		// Get cached stats
		$stats = cf_stats();

		?>

		<style>#counterfire-settings { display: none; }</style>

		<?php if( false != $stats ) { ?>

			<div class="wrap" style="margin: 0px;">

			<canvas id="counterfire-stats" style="height: 200px !important;"></canvas>

			<script>
					var randomScalingFactor = function(){ return Math.round(Math.random()*100)};
					var lineChartData = {
						labels : [<?php echo $stats["labels"]; ?>],
						datasets : [
							{
								label: "Besucher",
								fillColor : "rgba(255,153,0,0.2)",
								strokeColor : "rgba(255,153,0,1)",
								pointColor : "rgba(255,153,0,1)",
								pointStrokeColor : "#fff",
								pointHighlightFill : "#fff",
								pointHighlightStroke : "rgba(151,187,205,1)",
								data : [<?php echo $stats["visitors"]; ?>]
							},
							{
								label: "Seitenaufrufe",
								fillColor : "rgba(255,204,128,0.2)",
								strokeColor : "rgba(255,204,128,1)",
								pointColor : "rgba(255,204,128,1)",
								pointStrokeColor : "#fff",
								pointHighlightFill : "#fff",
								pointHighlightStroke : "rgba(220,220,220,1)",
								data : [<?php echo $stats["pageviews"]; ?>]
							},
						]

					}

					window.onload = function(){
						var ctx = document.getElementById("counterfire-stats").getContext("2d");
						window.myLine = new Chart(ctx).Line(lineChartData, {
							responsive: true,
							tooltipFontFamily: '"Open Sans", sans-serif',
		    				tooltipFontSize: 12,
		    				tooltipTitleFontSize: 12,
		    				multiTooltipTemplate: "<%= value %><%if (label){%> <%=datasetLabel%><%}%>",
		    				scaleIntegersOnly: true,
		    				scaleShowGridLines : true,
		    				datasetFill : true,
							showScale: true
						});
					}


				</script>

			<br><br>

			<div style="float: right; text-transform: uppercase; font-size: 80%;">
				<span style="color: rgba(255,153,0,1);">Besucher</span> -
				<span style="color: rgba(255,204,128,1);">Seitenaufrufe</span>
			</div>

		<?php } ?>

		<a class="button button-primary" target="_blank" href="https://counterfire.info/statistik.php?id=<?php echo $counter_id; ?>">Statistik öffnen</a>
		<a class="button opencfsettings" href="#">Einstellungen</a>

	<?php } ?>

	<div style="margin-top: 25px; padding-top: 20px;border-top: 1px solid #E1E1E1;" id="counterfire-settings">

	<?php
		// Only display settings if user has the nessessary rights
		if( current_user_can( "manage_options" ) ) {
	?>

		<form name="update-counterfire" action="" method="POST">

			<input type="hidden" name="counterfire-update" value="update">

			<p><input type="text" name="counterfire_id" value="<?php echo $counter_id; ?>" placeholder="Counterfire ID"> </p>

			<?php if( get_option("counterfire_include_counter") ) { ?>

				<p><input type="checkbox" name="counterfire_include_counter" checked> Besucherz&auml;hler einbauen</p>

			<?php } else { ?>

				<p><input type="checkbox" name="counterfire_include_counter"> Besucherz&auml;hler einbauen</p>

			<?php } ?>

			<input class="button" type="submit" value="Einstellungen speichern">

		</form>

		<?php } else { ?>

			<p>Sie haben nicht die nötigen Rechte um Änderungen vorzunehmen</p>

		<?php } ?>
		<p style="color: #C0C0C0; font-size: 90%;">Hinweis: Wenn Sie den Besucherzähler automatisch einbinden lassen, binden Sie ihn bitte <u>nicht</u> zus&auml;tzlich in Widgets o.&auml;. ein!</p>

	</div>

	<?php

	echo '</div>';

	?>

		<script>

			jQuery( 'a.opencfsettings' ).click( function() {

				jQuery( '#counterfire-settings' ).toggle('slow');

			});

		</script>

	<?php
}

// Init dashboard widget
function cf_init_dashboard_widget()
{
	global $wp_meta_boxes;
	wp_add_dashboard_widget('counterfire_stats_widget', 'Counterfire Statistik', 'cf_dashboard_widget');
}

add_action( 'wp_dashboard_setup', 'cf_init_dashboard_widget' );

function cf_register_script() {
	wp_register_script( 'chartjs', plugin_dir_url( __FILE__ ). '/chartjs/Chart.js' );
	wp_enqueue_script( 'chartjs' );
}

add_action( 'admin_enqueue_scripts', 'cf_register_script' );

function cf_add_counter( ) {
	// Get counter id
	$counter_id = get_option( "counterfire_id", false );

	// If there is a counter and it should be displayed
	if( $counter_id != false && get_option( "counterfire_include_counter", false ) == true ) {

		echo '<script>var counterfire_id = "' . $counter_id . '";</script><script src="https://static.counterfire.info/track.js" type="text/javascript"></script><noscript><a style="position: fixed; bottom: 0; right: 0;padding: 20px; z-index: 9999;" target="_blank" title="Kostenloser Counter von Counterfire (https://counterfire.info)" href="https://counterfire.info/statistik.php?id=' . $counter_id . '"><img border="0" alt="Kostenloser Counter von Counterfire (https://counterfire.info)" src="https://counterfire.info/image.php?id=' . $counter_id . '"></a></noscript>';

	}
}

add_action( 'wp_footer', 'cf_add_counter' );
?>
