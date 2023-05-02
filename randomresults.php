<?php
/*
 * This file will call a query using a DBpedia resource and list all the returned POIs in a random order.
 * The number of matching properties and matching percentage will be hidden.
 * Each result can then be evaluated and later compared to the number of matching properties and matching percentage.
*/

// Prevent caching of page
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Turn on all error reporting 
error_reporting(E_ALL);

require_once("functions.php");

// Get resource data from URI
$resource = $_GET["resource"];
$resource_lat = $_GET["lat"];
$resource_long = $_GET["long"];
$search_radius = $_GET["radius"];

$resource_abstract = get_abstract($resource);

// Get result set from query function and duplicate it
$results = get_related_pois($resource, $resource_lat, $resource_long, $search_radius);
$results2 = clone($results);

// Counter for number of results returned
$number_of_results = 0;

// Find min/max number of matching properties to calculate percentages
$min_properties = 9999;
$max_properties = 0;

foreach($results2 as $row) {	
    
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
	
	/*
	echo "\r\n\r\n";
	echo "[" . $_GET["resource"] . "]";
	echo "   ";
	echo "[" . $row["subject"] . "]";
	*/
	
	if(($current_resource == dbpedia_encode($_GET["resource"])) || ($current_resource == $_GET["resource"])){
		$max_properties2 = $row["numProperties"];
	}

	$number_of_results++;
}

// Make a new array that we can put our result set in
$sorted_results = array();

// Loop through the result set
foreach($results as $row) {
	// Get the adjusted percent value
	$adjusted_percentage = calculate_adjusted_percentage($row["numProperties"], 0, $max_properties2);
	
	
	 // Adjusts the percentage down to 100 if it is above (due to a paradox where matches can have more matching properties than the resource)
	if($adjusted_percentage > 100){
		$adjusted_percentage = 100;
	}
	
	
	// Put each result as an array in the array we created, making a multidimensional associative array
	$sorted_results[] = array("label" => $row["label"], "num_properties" => $row["numProperties"], "percentile_match" => $adjusted_percentage, "abstract" => $row["abstract"], "subject" => $row["subject"]);
}

// Shuffle the array to put our results in a random order 
shuffle($sorted_results);
?>


<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<style type="text/css">
		body, table, th, td {
			font: 12px Verdana, Arial, Georgia, serif;
		} 
		table {
		    border-collapse: collapse;
		    width: 700px;
		}
		
		table, th, td {
		    border: 1px solid black;
		}

		th, td {
			padding: 2px;
		}
		
		th {
			font-weight: bold;
		}

		#infowindow a, #infowindow a:visited {
			position: relative;
			display: block;
			width: 50px;
			text-decoration: none;
			color: #000;
			text-align: center;
		}
		
		#infowindow a span {
			display: none;
			color: #000;
		}
		
		#infowindow a:hover span {
			display: block;
			position: absolute;
			
			width: 700px;
			padding: 10px;
			background: #ddd;
			border: 1px solid #000;
			z-index: 1;
			top: 5px;
			left: 55px;
			text-align: left;
		}
		
		input {
			width: 30px;
			text-align: center;
		}
		
		td.properties {
		    text-align: center;
		    width: 100px;
		    color: #fff;
		}
		
		input[type=button] {
			margin: 30px 0px 200px 200px;
			padding:15px;
			width: 200px;
		}
		
		span.metadata_header {
			font-weight: bold;
		}
		
		td.info {
			width: 53px;
		}
		
		td.properties_data {
			color: #fff;
		}
		
		</style>
		
		<script>
		function export_numbers(){
			for (var i = 0; i < 100; ++i) {
				document.getElementsByName("rating_div")[i].innerHTML = document.getElementsByName("rating_textbox")[i].value;
				document.getElementsByName("rating_textbox")[i].style.display = 'none';
			}
		}
		
		</script>
	</head>
	
	<body>
	
		<div></div>
		<div></div>
		<div></div>
		
		<br />
		
		<div id="infowindow">
	
			<table>
				<tr>
					<td colspan="5">
						<span class="metadata_header">DBpedia resource:&nbsp;</span><a href="http://dbpedia.org/page/<?php echo $resource ?>" title="<?php echo $resource_abstract; ?>">http://dbpedia.org/page/<?php echo $resource ?></a>
					</td>
				</tr>
				<tr>
					<td colspan="5">
						<span class="metadata_header">URI:&nbsp;</span><a href="http://<?php echo $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]; ?>">http://<?php echo $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]; ?></a>
					</td>
				</tr>
				<tr>
					<td colspan="5"><span class="metadata_header">Search distance:&nbsp;</span><?php echo $_GET["radius"]; ?> km</td>
				</tr>
				<tr>
					<td colspan="5"><span class="metadata_header">Results:&nbsp;</span><?php echo $number_of_results . " (max " . $max_search_results . ")"; ?></td>
				</tr>
				<tr>
					<th>Title</th>
					<th>Rating</th>
					<th name="info_th">Info</th>
					<th>Matching %</th>
					<th>Matching #</th>
				</tr>
		
				<?php
				foreach($sorted_results as $poi) {
				?>
				
				<tr>
					<td class="label"><?php echo $poi["label"]; ?></td>
					<td class="properties">
						<input type="text" name="rating_textbox">
						<span name="rating_div"></span>
					</td>
					<td class="info"><a href="<?php echo dbpedia_encode($poi["subject"]); ?>">Hover<span><?php echo $poi["abstract"]; ?></span></a></td>
					<td class="properties"><?php echo round($poi["percentile_match"]); ?></td>
					<td class="properties"><?php echo $poi["num_properties"]; ?></td>
				</tr>
			
				<?php
				}
				?>	
				
				<tr>
					<td colspan="5" class="properties_data"><span class="metadata_header">Resource properties: </span><?php echo $prefixed_resource_properties; if(!$use_resource_properties){ echo "Disabled"; } ?></td>
				</tr>
				<tr>
					<td colspan="5" class="properties_data"><span class="metadata_header">Abstract keywords: </span><?php echo $prefixed_relevant_keywords; if(!$use_abstract_properties){ echo "Disabled"; } ?></td>
				</tr>
				<tr>
					<td colspan="5" class="properties_data"><span class="metadata_header">Multiword keywords: </span><?php echo $prefixed_multiword_keywords; if(!$use_multiword_abstract_properties){ echo "Disabled"; } ?></td>
				</tr>
				<tr>
					<td colspan="5" class="properties_data"><span class="metadata_header">Intersecting properties: </span><?php echo $prefixed_intersecting_properties; if(!$use_intersecting_properties){ echo "Disabled"; } ?></td>
				</tr>
				
			</table>
		</div>
	
		<input type="button" onClick="export_numbers()" value="Export rating values">

	</body>
</html>