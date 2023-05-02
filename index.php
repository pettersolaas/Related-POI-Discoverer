<?php
// Prevent caching of page
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Turn on all error reporting 
error_reporting(E_ALL);
?>


<!DOCTYPE html>
<html>
	<head>
		<title>Related POI Discoverer</title>
		<meta charset="UTF-8">
		<style type="text/css">
		h2 {
			font: bold 30px arial, sans-serif;
			color: #333;
			text-shadow: 2px 2px 2px #CCCCCC;
			text-align: center;
			margin: 0;
			}
			
		h3 {
			font: bold 22px arial, sans-serif;
			color: #333;
			text-shadow: 2px 2px 2px #CCCCCC;
			text-align: center;
			margin: 0;
			}
		
		p {
			font: 15px Arial, Helvetica, Sans-serif;
			color: #333;
			margin: 7px;
		}
		
		#geosearch_text {
			font: bold 20px arial, sans-serif;
			color: #333;
			text-shadow: 2px 2px 2px #CCCCCC;
			text-align: center;
			margin: 0;
			display: block;
		}
		
		#geosearch_text:hover {
			color: #55c;
			text-decoration: underline;
		}
		
		a {
			text-decoration: none;
		}

		.center {
		    margin: 10px auto 10px auto;
		    width: 400px;
		    background-color: #eee;
		    padding: 10px;
		    border: 1px solid #000;
		    text-align: center;
		}
		
		#dbpedia_search_field {
			font: bold 18px arial, sans-serif;
			width: 280px;
			height: 35px;
			vertical-align: middle;
			margin: 10px 0 5px 0;
		}
		
		#submit_button {
			font: bold 18px arial, sans-serif;
			height: 41px;
			width: 80px;
			vertical-align: middle;
			margin: 10px 0 5px 0;
		}
		</style>

	</head>
	
	<body>
		<div class="center">
			<h2>Related POI Discoverer</h2>
			<p>The Related POI Discoverer lets you discover Points Of Interest (POIs) that are related to one another. This is calculated based on resource properties and properties extracted from abstracts. This was made as a tool to investigate the research questions in my masters thesis at UiB, titled "Discovery of related geospatial points of interest using semantic web technologies".</p>
		</div>
		
		<div class="center">
			<a href="geosearch.php">
				<img src="images/geosearch.png" />
				<span id="geosearch_text">Find POIs near your location</span>
			</a>
		</div>
		
		<div class="center">
			<img src="images/dbpedia2.png" />
			<form action="textsearch.php" method="get">
			    <input type="text" name="query" placeholder="Search for POIs in DBpedia" id="dbpedia_search_field"/>
			    <button type="submit" id="submit_button">Search</button>
			</form>
		</div>
	</body>
</html>