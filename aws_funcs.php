<?php
 DEFINE("PATH", "beanbasket/");
 //EDIT THIS CODE TO ADD MORE CLONING OPTIONS IN BEANSTALK ENVIRONMENTS
 // In format: TYPE NAME VERSION  ex: Standard WebServer 2.0
 $AVAILABLE_BEANSTALK_CLONE_TIERS = array("Standard WebServer 1.0", "SQS/HTTP Worker 1.0", "SQS/HTTP Worker 1.1");

 $AVAILABLE_ENVIRONMENT_TYPES = array("SingleInstance", "LoadBalanced");
 $AVAILABLE_STATISTIC_LIST = array("Average", "Sum", "Maximum", "Minimum");
 $AVAILABLE_UNIT_LIST = array("Seconds", "Percent", "Bytes", "Bits", "Count", "Bytes/Second", "Bits/Second", "Count/Second", "None");
 $AVAILABLE_MEASURENAME_LIST = array("CPUUtilization", "NetworkIn", "NetworkOut", "DiskWriteOps", "DiskReadBytes", "DiskReadOps", "DiskWriteBytes", "Latency", "RequestCount", "HealthyHostcount", "UnhealthyHostCount");
 $CONFIGURATION_EXCLUSION_LIST = array("AWS_SECRET_KEY", "AWS_ACCESS_KEY_ID", "EnvironmentVariables");
 $CLONER_EXCLUSION_LIST = array("EnvironmentVariables", "JVMOptions", "EnvironmentType", "EnvironmentVariables");

 DEFINE("CONFIG_PREFIX", "./"); //include the last backslash!

require '../vendor/autoload.php';
use Aws\ElasticBeanstalk\ElasticBeanstalkClient;
use Aws\Ec2\Ec2Client;
use Aws\ElasticLoadBalancing\ElasticLoadBalancingClient;

function getYAML(){
	$configuration = spyc_load_file(CONFIG_PREFIX.$_GET["c"].".yaml");
  if(empty($configuration["keys"]) || !file_exists($configuration["keys"]["keyfile"]))
      die("No AWS Keys file available/stored!");
  return array("keys" => spyc_load_file($configuration["keys"]["keyfile"]), "config" => $configuration);
}
function listBeanstalks($app_name, $region, $c){
	$res = $c->describeEnvironments(array("ApplicationName" => $app_name));
	foreach($res["Environments"] as $bstlk){
		$ret[] = array("name" => $bstlk["EnvironmentName"], "id" => $bstlk["EnvironmentId"]);
	}

	return $ret;
}

function getCertDetail($name, $c){
	return openssl_x509_parse($c->getServerCertificate(array("ServerCertificateName" => $name))["ServerCertificate"]["CertificateBody"]);
}

function obscure($key){
	//only leave the first 4 digits
	return substr($key, 0, 4).str_repeat("*", strlen($key)-4);
}

function htmlSafeName($name){
	return str_replace(" ", "-", $name);
}

function isPaused($resVar){
	if(empty($resVar))
		return false;
	return (pullASGMaxSize($resVar) == 0 && pullASGMinSize($resVar) == 0);
}

function findAccessKey($keys, $needle){
	foreach($keys["keys"] as $key){
		if($key["environment"] == $needle)
			return $key["accesskey"];
	}
	return null;
}

function findSecretKey($keys, $needle){
	foreach($keys["keys"] as $key){
		if($key["environment"] == $needle)
			return $key["secretkey"];
	}
	return null;
}

function formatStatus($resVar, $eId){
	switch(pullBstlkStatus($resVar)){
		case "Ready":
			return "<span id='health-status' title='Health: ".pullBstlkHealth($resVar)." <br/> Status: Ready' class='glyphicon glyphicon-ok-circle text-success'></span>";
		case "Updating":
			return "<span id='health-status' title='Health: ".pullBstlkHealth($resVar)." <br/> Status: Updating' class='glyphicon glyphicon-refresh text-warning'></span>";
		case "Launching":
			return "<span id='health-status' title='Health: ".pullBstlkHealth($resVar)." <br/> Status: Launching'class='glyphicon glyphicon-upload text-warning'></span>";
		case "Terminating":
			unlink(PATH.$eId.".bstlk");
			die("<span class='text-danger lead'>This Beanstalk environment will be terminated. Therefore, no information will be passed.  Please refresh the page for updates to take effect.</span>");
		case "Terminated":
			unlink(PATH.$eId.".bstlk");
			die("<span class='text-danger lead'>This Beanstalk environment will be terminated. Therefore, no information will be passed.  Please refresh the page for updates to take effect.</span>");	
		}
}

/**** BEANSTALK APP DETAILS ****/
//DETAIL FUNCTIONS
function pullContainer($resVar){ //parent: getBeanStalkInformation();
	return $resVar[0]["SolutionStackName"];
}
function pullVersionLabel($resVar){ //parent: getBeanStalkInformation();
	return @$resVar[0]["VersionLabel"];
}
function pullELB($resVar){ //parent: getBeanStalkInformation();
	return @$resVar[0]["EndpointURL"];
}
function pullUpdateDate($resVar){ //parent: getBeanStalkInformation();
	return $resVar[0]["DateUpdated"];
}
function pullBstlkStatus($resVar){ //parent: getBeanStalkInformation();
	return $resVar[0]["Status"];
}
function pullBstlkHealth($resVar){ //parent: getBeanStalkInformation();
	return $resVar[0]["Health"];
}
function pullTier($resVar){ //parent: getBeanStalkInformation();
	return $resVar[0]["Tier"];
}
function pullEnvironmentId($resVar){ //parent: getBeanStalkInformation();
	return $resVar[0]["EnvironmentId"];
}
function pullBstlkInstances($resVar){ //parent: getBeanStalkInstances();
	if(empty($resVar["Instances"]))
		return array();

	foreach($resVar["Instances"] as $var){
		$ret[] = $var["Id"];
	}
	return $ret;
}
function pullASG($resVar){ //parent: getBeanStalkInstances();
	return @$resVar["AutoScalingGroups"][0]["Name"];
}


function pullUptime($resVar){ //parent: getInstanceInformation();
	$launchTime = new DateTime($resVar["LaunchTime"]);
	$currentTime = new DateTime("now");
	return $launchTime->diff($currentTime)->format('%R%a d');
}
function pullIPAdress($resVar){ //parent: getInstanceInformation();
	if(empty($resVar["PublicIpAddress"]))
		return "N/A";
	return $resVar["PublicIpAddress"];
}
function pullInstanceState($resVar){ //parent: getInstanceInformation();
	return $resVar["State"]["Name"];
}


function pullEnvironmentVariables($resVar, $useDefault){ //parent getConfigurationSettings();
	global $CONFIGURATION_EXCLUSION_LIST;
	global $CLONER_EXCLUSION_LIST;
	// The main configuration settings are divided into two parts:  Environmentvariables and any with namespace parameter. Excluding AWS Keys...

	$excl = ($useDefault == TRUE ? $CONFIGURATION_EXCLUSION_LIST : $CLONER_EXCLUSION_LIST);

	foreach($resVar[0]["OptionSettings"] as $OS){
		if(strpos($OS["Namespace"], "environment") !== false && !in_array($OS["OptionName"], $excl))
			$ret[] = array("Name" => $OS["OptionName"], "Value" => $OS["Value"]);
	}

	//Pull parameters
	foreach($resVar[0]["OptionSettings"] as $OS){
		if(strpos($OS["Namespace"], "parameter") !== false && !in_array($OS["OptionName"], $excl))
			$ret[] = array("Name" => $OS["OptionName"], "Value" => $OS["Value"]);
	}

	return $ret;
}
function pullConfigValueByName($name, $resVar){  //parent getConfigurationSettings();
	foreach($resVar[0]["OptionSettings"] as $OS){
		if($OS["OptionName"] == $name)
			return @$OS["Value"];
	}
	return null;
}
function pullConfigNamespaceByName($name, $resVar){  //parent getConfigurationSettings();
	foreach($resVar[0]["OptionSettings"] as $OS){
		if($OS["OptionName"] == $name)
			return @$OS["Namespace"];
	}
}
function pullJVMOptsCLI($resVar){  //parent getConfigurationSettings();
	foreach($resVar[0]["OptionSettings"] as $OS){
		if($OS["OptionName"] == "JVM Options" && strpos($OS["Namespace"], "jvmoptions") !== FALSE )
			return @$OS["Value"];
	}
}
function removeKeys($resVar){
	$new = array();
	foreach($resVar[0]["OptionSettings"] as $OS){
		if($OS["OptionName"] == "AWS_ACCESS_KEY_ID" || $OS["OptionName"] == "AWS_SECRET_KEY" || $OS["OptionName"] == "EnvironmentVariables")
			$OS["Value"] = "";
		$new[0]["OptionSettings"][] = $OS;
	}
	return $new;
}

function pullASGMaxSize($resVar){ //parent getASGMaxMin();
	return $resVar[0]["MaxSize"];
}
function pullASGMinSize($resVar){ //parent getASGMaxMin();
	return $resVar[0]["MinSize"];
}

function pullEventDate($resVar){ //parent getLatestEvent();
	if(empty($resVar[0]["EventDate"]))
		return date('Y-m-d H:i:s');
	return $resVar[0]["EventDate"];
}

function pullDNS($resVar){ //parent getElbFromDNS();
	if(empty($resVar["AliasTarget"]["DNSName"]))
		return "N/A";
	return $resVar["AliasTarget"]["DNSName"];
}

function pullFilteredSolutionStacks($resVar, $filter){ //parent getAvailSolutionStacks
	$ret = array();
	foreach($resVar as $ss){
		if(strpos($ss, $filter) !== FALSE)
			$ret[] = $ss;
	}

	return $ret;
}

function pullEnvTagByName($name, $resVar){ //parent: getEnvironmentTags();
	foreach($resVar as $tag){
		if($tag["Key"] == $name)
			return $tag["Value"];
	}
	return null;
}

//REQUEST FUNCTIONS
function getBeanstalkInformation($aName, $c){
		return $c->describeEnvironments(array("EnvironmentNames" => array($aName)))["Environments"];
}
function getBeanstalkInstances($aName, $c){
	return $c->describeEnvironmentResources(array("EnvironmentName" => $aName))["EnvironmentResources"];
}
function getInstanceInformation($iName, $c){
	return $c->describeInstances(array("Filters" => array(array("Name" => "instance-id", "Values" => array($iName)))))["Reservations"][0]["Instances"][0];
}
function getConfigurationSettings($aName, $eName, $c){
	return $c->describeConfigurationSettings(array("ApplicationName" => $aName, "EnvironmentName" => $eName))["ConfigurationSettings"];
}
function getASGInformation($asgName, $c){
	if(empty($asgName))
		return;
	return $c->describeAutoScalingGroups(array("AutoScalingGroupNames" => array($asgName)))["AutoScalingGroups"];
}
function setASGMaxMin($asgName, $max, $min, $c){
	return $c->updateAutoScalingGroup(array("AutoScalingGroupName" => $asgName, "MinSize" => $min, "MaxSize" => $max));
}
function getLatestEvent($eName, $c){
		return $c->describeEvents(array("EnvironmentName" => $eName, "MaxRecords" => 10))["Events"];
}
function getAvailSolutionStacks($c){
	return $c->listAvailableSolutionStacks()["SolutionStacks"];
}
function getElbFromDNS($dns, $zid, $c){
	return $c->listResourceRecordSets(array("HostedZoneId" => $zid, "StartRecordName" => $dns, "MaxItems" => 1))["ResourceRecordSets"][0];
}
function setDNS($dns, $zid, $newelb, $c, $c2){
	$elbs = $c2->describeLoadBalancers(array());
	foreach($elbs["LoadBalancerDescriptions"] as $elb){
		if($newelb == $elb["DNSName"]){
			$newzid = $elb["CanonicalHostedZoneNameID"];
		}
	}

	$request = array("HostedZoneId" => $zid, "ChangeBatch" => array(
		'Comment' => 'changed on ' . date('l F dS, Y @ G:i T'),
		'Changes' => array(array(
			"Action" => "UPSERT",
			"ResourceRecordSet" => array(
				"Name" => $dns,
				"Type" => "A",
				"AliasTarget" => array(
					"HostedZoneId" => $newzid,
					"DNSName" => $newelb,
					"EvaluateTargetHealth" => false))))));

	return $c->changeResourceRecordSets($request);

}
function getEnvironmentTags($eid, $c){
	$stackId = "awseb-".$eid."-stack";
	return $c->describeStacks(array("StackName" => $stackId))["Stacks"][0]["Tags"];
}
/******************************/
?>