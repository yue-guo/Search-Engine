<?php

include 'SpellCorrector.php';
include 'simple_html_dom.php';

//make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html;charset=utf-8');
header("Access-Control-Allow-Origin: *"); 

$limit = 10;
//$query = isset($_REQUEST['q'])? $_REQUEST['q'] : false;
if(isset($_REQUEST['q'])){
	$query = $_REQUEST['q'];  //same
	$arrList = explode(' ',$_REQUEST['q']);
	foreach($arrList as $word){
		//echo $word;
		//echo "<br>";
		//correct each word
		$spellCorrectQuery = $spellCorrectQuery.SpellCorrector::correct($word).' ';
	}
	$spellCorrectQuery = trim($spellCorrectQuery);
}
else{
	$query = false;
}

$results = false;
$corrected = false; //symbol: corrected or not

if($query&&$spellCorrectQuery){  //+
	//The Apache Solr Client library should be on the include path
	//which is usially most easily accomplished by placing in the 
	//same directory as this script (. or current directory is a default
    //php include path entry in the php.ini)
    require_once('solr-php-client-master/Apache/Solr/Service.php');

    //create a new solr service instance - host, port, and corename
    //path(all defaults in this example)
    $solr = new Apache_Solr_Service('localhost',8983,'/solr/myexample/');

    //if magic quotes is enabled then stripslashs will be needed
    if(get_magic_quotes_gpc()==1){
    	//delete \
    	$query = stripslashes($query);
    	$spellCorrectQuery = stripslashes($spellCorrectQuery);      //+
    }

    //in production code you'll always want to use a try/catch for any
    //possible exceptions emitted by searching(i.e. connecttion
	//problems or a query parsing error)

	try{
		$additionalParameters = array(
			'sort' => 'pageRankFile desc'       //example
		);

		if($_GET['RankAlg'] == 'PageRank')
		{
			if(strtolower($query) == strtolower($spellCorrectQuery)){
				$corrected = false;
				$results = $solr->search($query, $start, $rows, $additionalParameters);
			}
			else{
				$corrected = true;
				//to check if the link for spell correction is clicked or not
				if($_GET['clicked']){
					$corrected = false;
					$results = $solr->search($query, $start, $rows, $additionalParameters);
				}
				else{
					$results = $solr->search($spellCorrectQuery, $start, $rows, $additionalParameters);
				}
			}
			
		}
		else if($_GET['RankAlg'] == 'Lucene')
		{
			if(strtolower($query) == strtolower($spellCorrectQuery)){
				//echo "equal";
				$corrected = false;
				$results = $solr->search($query,0,$limit);
			}
			else{
				//echo "not equal";
				$corrected = true;
				if($_GET['clicked']){
					$corrected = false;
					$results = $solr->search($query,0,$limit);
				}
				else{
					//echo "search for corrected";
					$results = $solr->search($spellCorrectQuery,0,$limit);
				}
			}
			//$results = $solr->search($query,0,$limit);
		}
		//$result = $solr->search($query,0,$limit);
	}
	catch(Exception $e){
		//in production you'd probably log or email this error to an admin
		//and then show a sepcial message to the user but for this example
		//we're going to show the full exception

		//similar to the exit(),and do the status die(status)
		die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
	}
}

?>

<html>
	<head>
		<style type="text/css">
			body{
				background-color: #F6F5F1;
			}
		</style>
		<title> PHP Solr Client Example </title>
		<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.4/themes/start/jquery-ui.css"> 
  		<script src="http://code.jquery.com/jquery-1.9.1.js"></script>  
		<script src="http://code.jquery.com/ui/1.10.4/jquery-ui.js"></script> 
	</head>
	<body>
		

		<center>
			<h1>Search Engine</h1>		
			<form accept-charset="utf-8" method = "get">
				<label for="q">Search:</label>
				<input id="q" name="q" type="text" size="35" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/>
				<input name="RankAlg" type="radio" value="Lucene" />Lucene
				<input name="RankAlg" type="radio" value="PageRank" />PageRank
				<input type="submit" />
				</div>			
		</center>
		<script type="text/javascript">
			$(function(){
				//parameter for JQuery UI
				//source: the source of the data, can be string, array, function
				//function(request,response) using request.term get the input
				//response([Array])for result (like JSOUP)
				//minLength: length of the word to minLength, active autocomplete
				$("#q").autocomplete({
					//source:data
					source: function(request,response){
						var prev = "";
						var curr = "";
						//do the word one by one, but show them together
						if(request.term.lastIndexOf(" ") != -1){  //have " "
							//before " ", and after " "
							prev = request.term.substr(0, request.term.lastIndexOf(" ")).toLowerCase();
							curr = request.term.substr(request.term.lastIndexOf(" ")+1).toLowerCase();
						}
						else{
							//no " "
							//single word or first word
							prev = "";
							curr = request.term.toLowerCase();
						}
						$.ajax({
					         type: "GET",
					         dataType: "json",
					         url: "response.php",
					         //url:"http://localhost:8983/solr/myexample/suggest?wt=json&q=".$_GET['query'],
					         /*data: {
					             query: curr
					         },*/
					         data:{
					          query: curr
					     	 },
							success: function(data){
								var suggestedList = Array();
								var jsonObject = data;
								//according to the response data pattern						
								for(var i=0; i<jsonObject.suggest.suggest[curr].numFound; i++){
									var suggestedItem = jsonObject.suggest.suggest[curr].suggestions[i].term;
									suggestedList[i] = prev + " " + suggestedItem;
								}
								response(suggestedList);
							},
							error: function(){
								alert("!!!");
							}
						});
					},
					minLength:1
				});

			});
  		</script>
<?php

//display results
if($results)
{
	$total = (int) $results->response->numFound;
	$start = min(1, $total);
	$end = min($limit, $total);

//spell correction
	if($corrected){
		//original query has been changed to the corrected one
		echo "&nbsp;&nbsp;The Results are about <a href='http://localhost/~YueGuo/572_hw5/solr.php?q=".$spellCorrectQuery."&RankAlg=".$_GET['RankAlg']."'>"
		.$spellCorrectQuery."</a>"; 
		echo "<br>";
		echo "&nbsp;&nbsp;Still Searching for <a href='http://localhost/~YueGuo/572_hw5/solr.php?q=".$query."&RankAlg=".$_GET['RankAlg']."&clicked=True"."'>".$query."</a>";
	}
	if($_GET['clicked']){
		//if user do not want to change, ask for the original one
		echo "Are you searching for: <a href='http://localhost/~YueGuo/572_hw5/solr.php?q=".$spellCorrectQuery."&RankAlg=".$_GET['RankAlg']."'>"
		.$spellCorrectQuery."</a>"; 
	}
	//echo $_GET['clicked'];
?>

	<div>&nbsp;&nbsp;Results<?php echo $start; ?> - <?php echo $end; ?> of <?php echo $total; ?>:</div>
	<ol>

<?php
	$file = fopen('Boston Global Map.csv','r');
	$result = array();
	while($line=fgetcsv($file))
	{
		$result[$line[0]] = $line[1];          //id：url for pageRank
	}
	
	//iterate result documents
	foreach($results->response->docs as $doc)
	{
		?>
		<li>
			<!--<table style="border:1px solid black; text-align:left">-->
<?php
	//iterate document fields/values
	foreach($doc as $field => $value)
	{
		if($field == 'title'){
			$title = htmlspecialchars($value,ENT_NOQUOTES,'utf-8');
		}
		else if($field == 'id'){
			$position = false;
			$id = htmlspecialchars($value,ENT_NOQUOTES,'utf-8');
			$arr = explode("/",$id);
			$url1 = $arr[count($arr)-1];         //file name **.html
			$url2 = $result[$url1];
			$htmlContent = file_get_html("BG/".$url1);
      		//echo $htmlContent->plaintext;
			if($corrected){
				//echo $spellCorrectQuery;
				$position = stripos($htmlContent->plaintext,$spellCorrectQuery);  //ignore the upercase & lowercase
				if($position){
					$snippet = substr($htmlContent->plaintext,$position,160);
					$highlightQuery = "<b>".$spellCorrectQuery."</b>";
					$snippet = "...".str_ireplace($spellCorrectQuery,$highlightQuery,$snippet)."...";
					for($i=0;$i<count($seperateQueryArr);$i++){
						$highlightQuery1 = "<b>".$seperateQueryArr[$i]."</b>";
						$snippet = str_ireplace($seperateQueryArr[$i],$highlightQuery1,$snippet);
					}

				}
				else{
					//there is no result for the full query-term, so now seperate the query
					$seperateQueryArr = explode(" ",$spellCorrectQuery);
					for($i=0; $i<count($seperateQueryArr); $i++){
						$position = stripos($htmlContent->plaintext, $seperateQueryArr[$i]);
						if($position){
							$snippet = substr($htmlContent->plaintext,$position,160);
							for($j=$i; $j<count($seperateQueryArr); $j++){
								$pos = stripos($snippet,$seperateQueryArr[$j]);
								if($pos){
									$highlightQuery = "<b>".$seperateQueryArr[$i]."</b>";
									$highlightQuery1 = "<b>".$seperateQueryArr[$j]."</b>";
									$snippet = str_ireplace($seperateQueryArr[$i], $highlightQuery, $snippet);
									$snippet = "...".str_ireplace($seperateQueryArr[$j],$highlightQuery1,$snippet)."...";
									break;
								}
								else{
									continue;
								}
							}
							//not hightlight the whole, only part
							$highlightQuery = "<b>".$seperateQueryArr[$i]."</b>";
							$snippet = "...".str_ireplace($seperateQueryArr[$i],$highlightQuery,$snippet);
							break;
						}
					}
				}
			}
			else{
				//not need to be corrected;
				//echo $query;
				$position = stripos($htmlContent->plaintext,$query);
				if($position){
					$snippet = substr($htmlContent->plaintext,$position,160);
					$seperateQueryArr = explode(" ", $query);
					$highlightQuery = "<b>".$query."</b>";
					$snippet = "...".str_ireplace($query,$highlightQuery,$snippet)."...";
					for($i=0;$i<count($seperateQueryArr);$i++){
						$highlightQuery1 = "<b>".$seperateQueryArr[$i]."</b>";
						$snippet = str_ireplace($seperateQueryArr[$i],$highlightQuery1,$snippet);
					}
				}
				else{
					$seperateQueryArr = explode(" ",$query);
					for($i=0; $i<count($seperateQueryArr); $i++){
						$position = stripos($htmlContent->plaintext, $seperateQueryArr[$i]);
						if($position){
							$snippet = substr($htmlContent->plaintext,$position,160);
							for($j=$i; $j<count($seperateQueryArr); $j++){
								$pos = stripos($snippet,$seperateQueryArr[$j]);
								if($pos){
									$highlightQuery = "<b>".$seperateQueryArr[$i]."</b>";
									$highlightQuery1 = "<b>".$seperateQueryArr[$j]."</b>";
									$snippet = str_ireplace($seperateQueryArr[$i], $highlightQuery, $snippet);
									$snippet = "...".str_ireplace($seperateQueryArr[$j],$highlightQuery1,$snippet)."...";
									break;
								}
								else{
									continue;
								}
							}
							$highlightQuery = "<b>".$seperateQueryArr[$i]."</b>";
							$snippet = "...".str_ireplace($seperateQueryArr[$i],$highlightQuery,$snippet)."...";
							break;
						}
					}
				}
			}			
		}
		else if($field == 'description'){
			$des = htmlspecialchars($value,ENT_NOQUOTES,'utf-8');
		}
		else if($field == 'og_url'){	
			$url = htmlspecialchars($value,ENT_NOQUOTES,'utf-8');	
		}
?>  

<?php
	}
?>
<b>TITLE:</b> &nbsp; <a href="<?php echo ($url == "")?$url2:$url?>" target="_blank"> <?php echo $title ?></a>
<br>
<b>URL:</b> &nbsp; <a href="<?php echo ($url == "")?$url2:$url ?>" target="_blank"><?php echo ($url == "")?$url2:$url ?> </a>
<?php
echo "<br>";
echo "<b>ID:</b>" ."&nbsp". $id;
echo "<br>";
?>
<b>Description:</b> &nbsp; <?php echo ($des == "")?"N/A":$des; ?>
<?php
echo "<br>";
?>
<b>Snippet:</b>&nbsp; <?php echo $position?$snippet:"N/A"; ?>
<?php
echo "<br>";
echo "<br>";
?>

	<!--</table>-->
	</li>
<?php
	}
?>
</ol>
<?php
}
?>

</body>
</html>
