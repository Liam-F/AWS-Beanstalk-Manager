<?php
require_once "aws_funcs.php"; //al computation/retrieval here
require_once 'Spyc.php';

$yamlDetails = getYAML();
$configuration = $yamlDetails["config"];
$keys = $yamlDetails["keys"];

session_start();
if(empty($_POST["asgName"]) || empty($_POST["region"]) || empty($_POST["action"]) || empty($_POST["key"]))
	die("Error: One or more parameters are not set");

$asgName = $_POST["asgName"];
$eId = $_POST["eid"];
$eName = $_POST["ename"];
$reg = $_POST["region"];
$act = $_POST["action"];
$ak = findAccessKey($keys, $_POST["key"]);
$sk = findSecretKey($keys, $_POST["key"]);

use Aws\AutoScaling\AutoScalingClient;
$c = AutoScalingClient::factory(array("region" => $reg, "key" => $ak, "secret" => $sk));

if($act=="pause" && !empty($_POST["confirm"])){
	setASGMaxMin($asgName, 0, 0, $c);
	unlink(PATH.$eId.".bstlk"); //force refreshes after 5 seconds so the menu doesnt get screwed
	echo "<span class='glyphicon glyphicon-ok'></span> $eName successfully paused! <br/><br/> Please give it a few minutes for instances to take effect";
	echo "<script>$('#pausebtn').attr('disabled', true); $('#pausebtn').html('<span class=\'glyphicon glyphicon-play\'></span>'); </script>";
	echo "<script>$('#".$eName."').prepend('<span class=\"glyphicon glyphicon-pause\"></span> ');</script>"; //add the temp pause button
	die;
} else if($act=="play"){
	if(isset($_POST["max"]) && isset($_POST["min"])){
		$max = $_POST["max"];
		$min = $_POST["min"];

		setASGMaxMin($asgName, $max, $min, $c);
		unlink(PATH.$eId.".bstlk"); //force refreshes after 5 seconds so the menu doesnt get screwed
		echo "<span class='glyphicon glyphicon-ok'></span> AutoScaling Group: $asgName successfully resumed! <br/> Max: $max - Min: $min <br/><br/> Please give it a few minutes for the respective instances to instantiate.";
		echo "<script>$('#playbtn').attr('disabled', true); $('#playbtn').html('<span class=\'glyphicon glyphicon-pause\'></span>'); </script>";
		echo "<script>$('#".$eName."').find('span.glyphicon-pause').remove();</script>";
	}
	die;
}
?>
<div id="main">
<b>NOTICE:</b> <br/>
Are you sure you want to pause this beanstalk environment? <br/><br/>
<div class="text-center">
	<button type="button" onclick="resubmit();" class="btn btn-lg btn-success">Yes</button>
	<button type="button" onclick="exit();" class="btn btn-lg btn-danger">Nah</button>
</div>
</div>
<script>
	function resubmit(){
		var loadGif = "<img src=\'images/loading.gif\' width=\'100\' height=\'100\'> Working...";
		$("#pauseModalBody").html(loadGif).load("pauseplay_bstlk.php?c=<?php echo $_GET['c']; ?>", {"confirm": true, "action": "<?php echo $act; ?>", "ename": "<?php echo $eName; ?>", "eid": "<?php echo $eId; ?>", "asgName": "<?php echo $asgName; ?>", "region": "<?php echo $reg; ?>", "key": "<?php echo $_POST['key']; ?>"});
	}
	function exit(){
		$("#pauseModal").modal('hide');
	}
</script>
