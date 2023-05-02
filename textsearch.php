<?php
// Prevent caching of page
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Turn on all error reporting 
error_reporting(E_ALL);

require_once("functions.php");
?>


<!DOCTYPE html>
<html>
	<head>
		<title>Related POI Discoverer - Search DBpedia</title>
		<meta charset="UTF-8">
		<style type="text/css">
		.center {
		    margin: 10px auto 10px auto;
		    width: 400px;
		    background-color: #eee;
		    padding: 10px;
		    border: 1px solid #000;
		    text-align: center;
		}
		
		.left {
		text-align: left;
		}
		
		a {
			font: 18px arial, sans-serif;
		}
		
		a:hover {
			color: #77f;
		}
		
		h2 {
			font: bold 30px arial, sans-serif;
			color: #333;
			text-shadow: 2px 2px 2px #CCCCCC;
			margin: 0;
		}
		h3 {
			font: bold 22px arial, sans-serif;
			color: #333;
			text-shadow: 2px 2px 2px #CCCCCC;
			margin: 10px 0;
			}
		
		p {
			font: 15px Arial, Helvetica, Sans-serif;
			color: #333;
			margin: 7px;
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
		
		#dbpedia_image {
		margin: 10px 40px;
		}

        table, th, td {
            border: 1px solid black;
            border-collapse: collapse;
            padding: 5px;
        }
		</style>
	</head>

	<body>
		<div class="center">
		<img src="images/dbpedia2.png" />
	
		<h2>Search for POIs in DBpedia</h2>

		<form action="textsearch.php" method="get">
    		<input type="text" name="query" value="<?php echo $_GET["query"]; ?>" id="dbpedia_search_field" />
    		<button type="submit" id="submit_button">Search</button>
		</form>
		</div>

		<?php 
		
		// Checks if a query is passed 
		if(strlen($_GET["query"]) > 0) {


			echo "<div class=\"center\">";
			
				// Print search result heading
				echo "<h3>Search results:</h3>";
				
				// Format keywords for query
				$search_keywords = str_replace(" ", "' AND '", $_GET["query"]);
				
				// SELECT (sql:SAMPLE(?subject) AS ?subject) ?label ?lat ?long
				$query = "
				PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
				PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
				PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>
				PREFIX dcterms: <http://purl.org/dc/terms/>
				PREFIX : <http://dbpedia.org/resource/>
				PREFIX dbpprop: <http://dbpedia.org/property/>
				PREFIX dbpedia: <http://dbpedia.org/>
				PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
				PREFIX dbpedia-owl: <http://dbpedia.org/ontology/>
				SELECT distinct ?subject ?lat ?long ?label
				WHERE {
				?subject rdfs:label ?label .
				?subject geo:lat ?lat .
				?subject geo:long ?long .
				?label bif:contains \"'" . $search_keywords . "'\"
				}
				GROUP BY ?label ?lat ?long
				ORDER BY ?label";
	
				//echo $query;
				
				// Get the query results
				$result = sparql_get($dbpedia_server_uri, $query);
				
				//print_r($result);
				
				// Print query errors
				if(!$result) {
					echo sparql_errno() . ": " . sparql_error();
					exit;
				}
				

                if( !isset($result) )
                {
                    print "<p>Error: ".sparql_errno().": ".sparql_error()."</p>";
                }
                
                /* Debugging table
                print "<table>";
                print "<tr>";
                print "<th>#</th>";
                foreach( $result->fields() as $field )
                {
                    print "<th>$field</th>";
                }
                print "</tr>";
                $i = 0;
                foreach( $result as $row )
                {
                    $i++;
                    print "<tr>";
                    print "<td>" . $i . "</td>";
                    foreach( $result->fields() as $field )
                    {
                        print "<td>$row[$field]</td>";
                    }
                    print "</tr>";
                }
                print "</table>";
                */
                
				// Loop through the results
				foreach($result as $row)
				{
					// Strip the subject of it's URI
					$resource = str_replace("http://dbpedia.org/resource/", "", $row["subject"]);
					
					// Print search result line with link
					echo "<div class=\"left\"><a href=\"rpd.php?resource=" . $resource . "&lat=" . $row["lat"] . "&long=" . $row["long"] . "&radius=1\">" . $row["label"] . "</a>&nbsp;&nbsp;&nbsp;<a href=\"geosearch.php?lat=" . $row["lat"] . "&long=" . $row["long"] . "&radius=10\">[&oplus;]</a>&nbsp;&nbsp;&nbsp;<a href=\"randomresults.php?resource=" . $resource . "&lat=" . $row["lat"] . "&long=" . $row["long"] . "&radius=10\">[R]</a></div>";
					
				}
                
				
				// Print message if no results were found
				if(strlen($resource) < 1) {
					echo "No results were found";
				}
							
			echo "</div>";
			
		}
		?>

	</body>
</html>