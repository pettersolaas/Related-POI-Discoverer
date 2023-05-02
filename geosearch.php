<?php
/*
 * This file will use HTML5 Geolocation to try and determine the users location.
 * Alternately the user can specify his location to simulate a location-aware application.
 * The file will call a query for POIs around the users position and show the returned POIs on a map.
 * The POIs are shown as markers and have infowindows which provide information about the POI itself
 * The user can then continue to explore the area/database by using the returned POIs as a resource for new queries. 
*/

// Execution timer
$time_start = microtime(true);

// Prevent caching of page
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Turn on all error reporting 
error_reporting(E_ALL);


require_once("functions.php");

// Get lat and long from URI
$user_lat = isset($_GET['lat']) ? $_GET['lat'] : "x";
$user_long = isset($_GET['long']) ? $_GET['long'] : "x";


// Get or set search distance and make sure it's within range. Default is 5
if(isset($_GET['radius'])) {
	if($_GET['radius'] <= 0) {
		$search_distance = 1;
	} elseif ($_GET['radius'] >=50 ) {
		$search_distance = 50;
	} else {
		$search_distance = $_GET['radius'];
	}
} else {
	$search_distance = 5;
}

?>


<!DOCTYPE html>
<html>
	<head>
		<?php
			// Look for invalid lat/long values which will trigger Geolocation functions 
			if(!is_numeric($user_lat) || !($user_lat <= 90) || !($user_lat >= -90) || !is_numeric($user_long) || !($user_long >= -180) || !($user_long <= 180)){
		?>
			<script>
			// Check if Geolocation is working and call functions 
			function getLocation() {
			    if (navigator.geolocation) {
			        navigator.geolocation.getCurrentPosition(showPosition, showError);
			    } else { 
			    	errorMessage= "Geolocation is not supported by your browser.";
			    	getCoordinatesManually(errorMessage);
			    }
			}
			
			// Refresh page with the geolocation position data in the URI
			function showPosition(position) {
				document.location.href = "geosearch.php?lat=" + position.coords.latitude + "&long=" + position.coords.longitude;
			}
			
			// Error handling if position couldn't be determined
			function showError(error) {
			    switch(error.code) {
			        case error.PERMISSION_DENIED:
			        	errorMessage = "Access to your location was denied.";
			        	getCoordinatesManually(errorMessage);
			            break;
			        case error.POSITION_UNAVAILABLE:
			            errorMessage = "Location information is unavailable.";
			        	getCoordinatesManually(errorMessage);
			            break;
			        case error.TIMEOUT:
			        	errorMessage = "The request to get your location timed out.";
			        	getCoordinatesManually(errorMessage);
			            break;
			        case error.UNKNOWN_ERROR:
			        	errorMessage = "An unknown error occurred when getting your location.";
			        	getCoordinatesManually(errorMessage);
			            break;
			    }
			}

			// Print error message and let user enter manual location (to simulate Geolocation functionality)
			function getCoordinatesManually(){
				var lat = parseFloat(prompt(errorMessage + "\r\n\r\nPlease enter your latitude (between -90 and 90)", 60.3894));
				var lon = parseFloat(prompt("Please enter your longitude (between -180 and 180)", 5.3300));
			
				if ((lat < 90) && (lat > -90) && (lon < 180) && (lon > -180)) {
					//alert("Got data manually\r\n\r\n" + "Lat: " + lat + "\r\nLon: " + lon + "\r\n\r\nWill now refresh!");
					document.location.href = "geosearch.php?lat=" + lat + "&long=" + lon;
				} else {
					alert("Woops! Some of the coordinates are wrong.\r\n\r\nPlease enter a latitude (north-south) between 90 and -90\r\nand\r\na longitude (east-west) between -180 and 180");
					getCoordinatesManually();
				}
			}

			// Start Geolocation when page loads
			window.onload = function() {
				  getLocation();
				}
			</script>
			
			<?php
			// Lat/long coordinates are okay; print map and its contents 
			} else {
			?>

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
				center: {lat: <?php echo $user_lat; ?>, lng: <?php echo $user_long; ?>},
				mapTypeId:google.maps.MapTypeId.ROADMAP
			};
	
			var map = new google.maps.Map(document.getElementById("map-canvas"),mapProp);
			var marker = new Array();
			var infowindow = new Array();
			var lat_long_array = new Array()
			var zoom_level = new google.maps.LatLngBounds();

			// The resource marker (where the user is located, added manually with id # 0)
	        marker[0] = new google.maps.Marker({
	            position: {lat: <?php echo $user_lat; ?>, lng: <?php echo $user_long; ?>},
	            title: 'You are here',
	            map: map,
	            icon: "markers/user2.png",
	            zIndex: 999
			});
     
			// The resource infowindow, also id # 0
		   	infowindow[0] = new google.maps.InfoWindow({
		    content: '<div class="poi_label">You are here&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>'+
		     		'<div>Find POIs within '+
		     		'<a href="geosearch.php?lat=<?php echo $user_lat; ?>&long=<?php echo $user_long; ?>&radius=1">1 km</a> | '+
		     		'<a href="geosearch.php?lat=<?php echo $user_lat; ?>&long=<?php echo $user_long; ?>&radius=5">5 km</a> | '+
		     		'<a href="geosearch.php?lat=<?php echo $user_lat; ?>&long=<?php echo $user_long; ?>&radius=10">10 km</a> | '+
		     		'<a href="geosearch.php?lat=<?php echo $user_lat; ?>&long=<?php echo $user_long; ?>&radius=20">20 km</a> | '+
		     		'<a href="geosearch.php?lat=<?php echo $user_lat; ?>&long=<?php echo $user_long; ?>&radius=50">50 km</a> | '+
		     		'</div>',
		       });

		   	// Function to open this infowindow
		    google.maps.event.addListener(marker[0], 'click', function() {

				// Loop through infowindow array and close all when a new one is opened
			    for(var i=0, length=infowindow.length; i < length; i++){
			    	infowindow[i].close()
			    }
	
			    // Open the clicked infowindow
			    infowindow[0].open(map,this);
		    });

			// Add resource coordinates to array 
		    lat_long_array.push(new google.maps.LatLng (<?php echo $user_lat; ?>,<?php echo $user_long; ?>));
		
			<?php
			// Get result set from query function
			$result = get_nearby_pois($user_lat, $user_long, $search_distance);
			
			// Counter for the results (We already know resource marker and added it with id # 0)
			$i = 1;
		
			//var_dump($result);
			
			// Loop through the results
			foreach($result as $row) {			    
				
				// Strip the subject of it's URI
			    $current_resource = str_replace("http://dbpedia.org/resource/", "", $row["subject"]);
			    
			    //Removes newlines from abstract (they mess with JS or Google maps)
			    $current_abstract = preg_replace('~[\r\n]+~', '<br />', $row["abstract"]);
				
				
			?>
					// Map marker with data
		            marker[<?php echo $i; ?>] = new google.maps.Marker({
		                    position: {lat: <?php echo $row["lat"]; ?>, lng: <?php echo $row["long"]; ?>},
		                    title: '<?php echo addslashes($current_resource); ?>',
		                    map: map,
		                    icon: "markers/green.png"
		             });
		             
					// Infowindow with content
		           	infowindow[<?php echo $i; ?>] = new google.maps.InfoWindow({
		            content: '<div class="poi_label"><?php echo addslashes($row["label"]); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>'+
		             		'<?php 
		             		if($print_abstract){
		             		    echo "<div class=\"infowindow_text\">" . addslashes($current_abstract) . "</div>";
		             		}
		             		
		             		if($print_dbpedia_link){
		             			echo "<div class=\"infowindow_text\"><a href=\"https://dbpedia.org/page/" . addslashes($current_resource) . "\">https://dbpedia.org/page/" . addslashes($current_resource) . "</a></div>";
                                //echo "<div class=\"infowindow_text\"><a href=\"http://www.dbpedia.org/page/" . addslashes($current_resource) . "\">http://www.dbpedia.org/page/" . addslashes($current_resource) . "</a></div>";
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
					new google.maps.LatLng(<?php echo $min_lat2 ?>, <?php echo $min_long2 ?>),
					new google.maps.LatLng(<?php echo $min_lat2 ?>, <?php echo $max_long2 ?>),
					new google.maps.LatLng(<?php echo $max_lat2 ?>, <?php echo $max_long2 ?>),
					new google.maps.LatLng(<?php echo $max_lat2 ?>, <?php echo $min_long2 ?>),
					new google.maps.LatLng(<?php echo $min_lat2 ?>, <?php echo $min_long2 ?>)
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
		
		<?php
		}
		?>
	</head>
	<body>
		<div id="map-canvas"></div>
		<?php

		// Execution timer
		$time_end = microtime(true);
		$time = $time_end - $time_start;
		
		//echo "Execution time: " . round($time, 3) . " seconds\n";
		
		?>
	</body>
</html>