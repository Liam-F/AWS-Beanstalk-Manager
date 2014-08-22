<?php
require_once 'Spyc.php';
require_once "aws_funcs.php"; //all computation/retrieval here
$yamlDetails = getYAML();
$configuration = $yamlDetails["config"];
$keys = $yamlDetails["keys"];

use Aws\Route53\Route53Client;
use Aws\ElasticBeanstalk\ElasticBeanstalkClient;
use Aws\AutoScaling\AutoScalingClient;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;
if(empty($_POST["key"]))
	die("No access/secret keys provided");

$ak = findAccessKey($keys, $_POST["key"]);
$sk = findSecretKey($keys, $_POST["key"]);

session_start();

$c = Route53Client::factory(array("key" => $ak, "secret" => $sk));

//Section for handling DNS SWITCHING//
if(!empty($_POST["switch"])){
	$c4 = ElasticLoadBalancingClient::factory(array("region" => $_POST["region"], "key" => $ak, "secret" => $sk));
	$dns_pl = $_POST["dns_pl"];
	$newelb = $_POST["new_elb"];
	foreach($dns_pl as $record){
		$res = setDNS($record["name"], $record["zid"], $newelb, $c, $c4);
		if(!empty($res->get("ChangeInfo")["Id"])){
			echo "<span class='glyphicon glyphicon-ok-sign'></span> DNS (".$record["name"].") Successfully Changed!<br/>";
			sleep(2);
			echo "<script>
				var param = window.location.href.split('?')[1].split('#')[0];
				window.location.replace('index.php?'+param);</script>";
		} else {
			echo "<span class='glyphicon glyphicon-remove-sign'></span> DNS (".$record["name"].") Unsuccessfully Changed! Check System logs!<br/>";
			sleep(2);
			echo "<script>
				var param = window.location.href.split('?')[1].split('#')[0];
				window.location.replace('index.php?'+param);</script>";
		}
	}

	die;
} else {
/////


if(empty($_POST["appname"]) || empty($_POST["dns_payload"]))
	die;

//rest is formatting
$aName = $_POST["appname"];
$dns_pl = $_POST["dns_payload"];
$dns_excl = (empty($_POST["exclusions"]) ? array() : $_POST["exclusions"]);  //optional param

//Look for current info, since all need to point to one, we will check the first one
$resVar = getElbFromDNS($dns_pl[0]["name"], $dns_pl[0]["zid"], $c);
$currDNS = pullDNS($resVar);

}
?>
<h2>DNS Switcher</h2>
<br/>
<div class="col-md-3">
	<b>Current Live Beanstalk:</b><br/><br/>
	<b>New Live Beanstalk:</b>
</div>
<div class="col-md-8 col-md-offset-1">
	<span id="currEnv"></span><br/><br/>
	<select id="switchDNSSelect" type="email" class="form-control">
	<?php
	foreach($aName as $appName){
	$reg = $appName["region"];
	$c2 = ElasticBeanstalkClient::factory(array("region" => $reg, "key" => $ak, "secret" => $sk)); 
	$c3 = AutoScalingClient::factory(array("region" => $reg, "key" => $ak, "secret" => $sk));
	$c4 = ElasticLoadBalancingClient::factory(array("region" => $reg, "key" => $ak, "secret" => $sk));


	//list of to switch to
	$bstlks = listBeanstalks($appName["name"], $reg, $c2);
	for($i=0;$i<count($bstlks);$i++){
		//First check for a cache
		if(file_exists(PATH.$bstlks[$i]["id"].".bstlk")){
       	 	$dataSerial = file_get_contents(PATH.$bstlks[$i]["id"].".bstlk");
     		$dataSerial = explode("|||", $dataSerial);
        	$asg = unserialize($dataSerial[3]);
        	$resVar2 = unserialize($dataSerial[0]);
		} else {
			$resVar2 = getBeanstalkInformation($bstlks[$i]["name"], $c2);
            if(pullBstlkStatus($resVar2) == "Terminated" || pullBstlkStatus($resVar2) == "Terminating")
                continue;
           	$resVar = getBeanstalkInstances($bstlks[$i]["name"], $c2);
        	$asg = getASGInformation(pullASG($resVar), $c3);
		}

		//detect the current DNS
		if(strcasecmp(pullELB($resVar2).".", $currDNS) == 0){
			$currEnv = $bstlks[$i]["name"];
			continue; //if its the live one then move on to the next opt
		}

		$param = "data-region='".$reg."' id='".pullELB($resVar2)."' ";
		$excludesParam = "";

    	//check if paused
    	if(isPaused($asg)){
    		$excludesParam .="|paused|";
   	 	}

    	//check for additional exclusions
    	foreach($dns_excl as $exclusion){
    		if(stripos($bstlks[$i]["name"], $exclusion) !== FALSE){
    			$excludesParam .= "|".$exclusion."|";
    			break;
    		}
    	}

    	$param .= "data-excl='".$excludesParam."'";
    	echo "<option $param >".$bstlks[$i]["name"]."</option>";
	}
}
	?>
	</select>
</div>
<br/><br/>
<div class="top-buffer text-info col-md-10">
	<div id="switchstatus"></div>
</div>
<div class="top-buffer text-right col-md-2">
	<button type="button" onclick='submitNewDNS(<?php echo json_encode($dns_pl); ?>, "<?php echo $_POST['key']; ?>");' id="DNSSubmit" class="btn btn-primary">Submit</button>
</div>

<script>
$("#currEnv").text("<?php echo $currEnv; ?>");  //Because the currenv is fetched later, we do a bit of jquery wizardry to set it

function submitNewDNS(dns_payload, key){
	var loadGif = "<img src='images/loading.gif' width='30' height='30'> Switching DNS ...";
	var newelb = $("#switchDNSSelect").find(":selected").attr('id');
	var reg = $("#switchDNSSelect option:selected").data("region");
    $("#switchstatus").html(loadGif).load("dns_switcher.php?c=<?php echo $_GET['c']; ?>", {"switch": true, "dns_pl": dns_payload, "new_elb": newelb, "region": reg, "key": key});
}

//first check (incase it's the first one selected)
if($("#switchDNSSelect option:selected").data("excl")){
		$("#switchstatus").html("<span class='glyphicon glyphicon-remove-sign'></span> WARNING: You cannot switch DNS to a paused (see left) OR excluded beanstalk [EXCLUSIONS: <?php echo implode(',', $dns_excl); ?>]!!");
		$("#DNSSubmit").attr("disabled", true);
}

$("#switchDNSSelect").change(function(){
	if($("#switchDNSSelect option:selected").data("excl")){
		$("#switchstatus").html("<span class='glyphicon glyphicon-remove-sign'></span> WARNING: You cannot switch DNS to a paused (see left) OR excluded beanstalk! [EXCLUSIONS: <?php echo implode(',', $dns_excl); ?>]");
		$("#DNSSubmit").attr("disabled", true);
	} else {
		$("#switchstatus").html("");
		$("#DNSSubmit").attr("disabled", false);
	}
});
</script>