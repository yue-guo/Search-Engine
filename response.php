
<?php
   $url = "http://localhost:8983/solr/myexample/suggest?wt=json&q=".$_GET['query'];
   $answer = file_get_contents($url);
   echo $answer;
?>
