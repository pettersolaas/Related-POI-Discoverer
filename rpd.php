<?php
/*
 * This file will call a query using a DBpedia resource and show the returned POIs on a map.
 * POIs are shown as colored markers depending on the number of matching properties and their matching percentage.
 * The POIs also have infowindows which provide information about the POI itself and matching value
 * The user can then continue to explore the area/database by using the returned POIs as a resource for new queries.
 */

// Execution timer
//die("test3"); 
$time_start = microtime(true);

// Prevent caching of page
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Turn on all error reporting 
error_reporting(E_ALL);

require_once("functions.php");

// Check if a resource is specified. If not; use an example
if(!isset($_GET["resource"])){
	
	header('Location: rpd.php?resource=University_of_Bergen&lat=60.388086111111114&long=5.322872222222222&radius=0.5');
	exit;
		
} else {
	$resource = $_GET["resource"];
	$resource_lat = $_GET["lat"];
	$resource_long = $_GET["long"];
	$search_radius = $_GET["radius"];	
}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<style type="text/css">
		html, body, #map-canvas {
			height: 100%; width:100%; margin: 0; padding: 0;
		}
		.labels { color: black; background-color: #FF8075; font-family: Arial; font-size: 11px; font-weight: bold; text-align: center; width: 12px; }
		.poi_label {
			font-size: 14px;
			font-weight: bold;
		}
		.infowindow_text {
			margin: 5px 0 5px 0;
		}
		</style>
		<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key="></script> 
		<script type="text/javascript" src="v3_ll_grat.js"></script>
		<script type="text/javascript">

		// Super function for map and contents
		function initialize()
		{
			// Map properties
			var mapProp = {
				center: {lat: <?php echo $_GET["lat"]; ?>, lng: <?php echo $_GET["long"]; ?>},
				mapTypeId:google.maps.MapTypeId.ROADMAP
			};
	
			var map = new google.maps.Map(document.getElementById("map-canvas"),mapProp);
			var marker = new Array();
			var infowindow = new Array();
			var lat_long_array = new Array()
			var zoom_level = new google.maps.LatLngBounds();

			<?php
			// Get result set from query function and duplicate it
			$result = get_related_pois($resource, $resource_lat, $resource_long, $search_radius);
			$result2 = clone $result;

		
			if(!$result) {
				echo sparql_errno() . ": " . sparql_error();
				exit;
			}
			
			// Sets the max number of properties from results
			$max_properties2 = $result2[0]["numProperties"];

			// Find min/max number of matching properties to calculate percentages and select map marker color
			$min_properties = 9999;
			$max_properties = 0;



			
			foreach($result2 as $row) {	
				// Old style calculations
				/*
				if($min_properties > $row["numProperties"]){
					$min_properties = $row["numProperties"];
				}
			
				if($max_properties < $row["numProperties"]){
					$max_properties = $row["numProperties"];
				}
				*/
				// Sets max properties to equal the resource's number of properties (due to a paradox where matches can have more matching properties than the resource)
				$current_resource = str_replace("http://dbpedia.org/resource/", "", $row["subject"]);


				// echo "\ncurrent_resource: " . $current_resource;
				/*
				echo "[" . $row["subject"] . "]";
				echo "\r\n\r\n";
				echo "[" . $_GET["resource"] . "]";
				*/
				
				if(($current_resource == dbpedia_encode($_GET["resource"])) || ($current_resource == $_GET["resource"])){
					$max_properties2 = $row["numProperties"];
				}
				
			}

			//Debugging
			//echo "<!-- min_properties: " . $min_properties . "-->";
			//echo "<!-- max_properties: " . $max_properties . "-->";

			// Counter for the results
			$i = 0;
			
			// Loop through the results
			foreach($result as $row) {
			
				// Strip the subject of it's URI
				$current_resource = str_replace("http://dbpedia.org/resource/", "", $row["subject"]);
			
				// Calculates the color of the current marker based on a four-color scheme and an adjusted percentile weighing
				$adjusted_percentage = calculate_adjusted_percentage($row["numProperties"], 0, $max_properties2);
				
				/*
				// Adjusts the percentage to 100 if it is above (due to a paradox where matches can have more matching properties than the resource)
				if($adjusted_percentage > 100){
					$adjusted_percentage = 100;
				}
				*/
				
				if($adjusted_percentage >= 0 && $adjusted_percentage < 25){
					$marker_color = "red";
				} elseif($adjusted_percentage >= 25 && $adjusted_percentage < 50){
					$marker_color = "orange";
				} elseif($adjusted_percentage >= 50 && $adjusted_percentage < 75){
					$marker_color = "yellow";
				} elseif($adjusted_percentage >= 75){
					$marker_color = "green";
				}
				
				// Add special icon for the resource POI
				if($current_resource == dbpedia_encode($_GET["resource"])){
					$marker_color = "resource";
				}
				?>
				
				// Map marker with data
		        marker[<?php echo $i; ?>] = new google.maps.Marker({
	                position: {lat: <?php echo $row["lat"]; ?>, lng: <?php echo $row["long"]; ?>},
	                title: '<?php echo addslashes($current_resource); ?>',
	                map: map,
	                icon: "markers/<?php echo $marker_color; ?>.png",
	                zIndex: <?php echo 1000-$i; ?>
				});
		             
		
	           	infowindow[<?php echo $i; ?>] = new google.maps.InfoWindow({
	            	content: '<div class="poi_label"><?php echo addslashes($row["label"]); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>'+
             		'<?php
             		if($print_matching_statistics){
             			echo "<div class=\"infowindow_text\">" . $row["numProperties"] . " matching properties (" . round($adjusted_percentage) . "%)";
             		}
             		
             		if($print_abstract){
             			echo "<div class=\"infowindow_text\">" . addslashes(str_replace(array("\r", "\n"), '', $row["abstract"])) . "</div>";
             		}
             		
             		if($print_dbpedia_link){
             			echo "<div class=\"infowindow_text\"><a href=\"https://dbpedia.org/page/" . addslashes($current_resource) . "\">https://dbpedia.org/page/" . addslashes($current_resource) . "</a></div>";
             		}
             		
             		if($print_matching_properties_list){
						echo "<div class=\"infowindow_text\">Matching properties:</div>";
             			print_matching_properties($row["properties"]);
             		}
             		?>'+
             		'<div>Find POIs like this within '+
             		'<a href="rpd.php?resource=<?php echo addslashes($current_resource); ?>&lat=<?php echo $row["lat"]; ?>&long=<?php echo $row["long"]; ?>&radius=1">1 km</a> | '+
             		'<a href="rpd.php?resource=<?php echo addslashes($current_resource); ?>&lat=<?php echo $row["lat"]; ?>&long=<?php echo $row["long"]; ?>&radius=5">5 km</a> | '+
             		'<a href="rpd.php?resource=<?php echo addslashes($current_resource); ?>&lat=<?php echo $row["lat"]; ?>&long=<?php echo $row["long"]; ?>&radius=10">10 km</a> | '+
             		'<a href="rpd.php?resource=<?php echo addslashes($current_resource); ?>&lat=<?php echo $row["lat"]; ?>&long=<?php echo $row["long"]; ?>&radius=20">20 km</a> | '+
             		'<a href="rpd.php?resource=<?php echo addslashes($current_resource); ?>&lat=<?php echo $row["lat"]; ?>&long=<?php echo $row["long"]; ?>&radius=30">30 km</a> | '+
             		'<a href="rpd.php?resource=<?php echo addslashes($current_resource); ?>&lat=<?php echo $row["lat"]; ?>&long=<?php echo $row["long"]; ?>&radius=50">50 km</a> | '+
             		'<a href="randomresults.php?resource=<?php echo addslashes($current_resource); ?>&lat=<?php echo $row["lat"]; ?>&long=<?php echo $row["long"]; ?>&radius=10">[R]</a>'+
             		'</div>',
				});

	           	// Function to open infowindows
	            google.maps.event.addListener(marker[<?php echo $i; ?>], 'click', function() {

	            	// Loop through infowindow array and close all when a new one is opened
		            for(var i=0, length=infowindow.length; i < length; i++){
		            	infowindow[i].close()
		            }

	            	// Open the clicked infowindow
		            infowindow[<?php echo $i; ?>].open(map,this);
	            });
	
				// Add all coordinates to array 
				lat_long_array.push(new google.maps.LatLng (<?php echo $row["lat"]; ?>,<?php echo $row["long"]; ?>));
			
				<?php 
				$i++;
			}
			?>

			//  Go through lat/long-coordinates and adjust map zoom accordingly 
			for (var i = 0, LtLgLen = lat_long_array.length; i < LtLgLen; i++) {
			  zoom_level.extend (lat_long_array[i]);
			}
			map.fitBounds (zoom_level);
			
			// Draw grid on map (based on v3_ll_grat.js)
			var grid = new Graticule(map, true);
			
			// Specify coordinates for bounding box
			var grid_coordinates = [
				new google.maps.LatLng(<?php echo $min_lat ?>, <?php echo $min_long ?>),
				new google.maps.LatLng(<?php echo $min_lat ?>, <?php echo $max_long ?>),
				new google.maps.LatLng(<?php echo $max_lat ?>, <?php echo $max_long ?>),
				new google.maps.LatLng(<?php echo $max_lat ?>, <?php echo $min_long ?>),
				new google.maps.LatLng(<?php echo $min_lat ?>, <?php echo $min_long ?>)
			];
			
			var bounding_box = new google.maps.Polyline({
				path: grid_coordinates,
				geodesic: true,
				strokeColor: '#060',
				strokeOpacity: 1.0,
				strokeWeight: 2
			});
			bounding_box.setMap(map);
			
		}

		google.maps.event.addDomListener(window, 'load', initialize);
		</script>
	</head>
	
	<body>
		<div id="map-canvas"></div>
		<?php

		// Execution timer
		$time_end = microtime(true);
		$time = $time_end - $time_start;
		
		echo "Execution time: " . round($time, 3) . " seconds\n";
		
		?>
	</body>
</html>