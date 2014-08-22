<?php
session_start();
require_once 'Spyc.php';
require_once "aws_funcs.php"; //all computation/retrieval here
$yamlDetails = getYAML();
$configuration = $yamlDetails["config"];
$keys = $yamlDetails["keys"];

use Aws\ElasticBeanstalk\ElasticBeanstalkClient;

$payload = array();
foreach($_POST as $key => $value){
	$payload[$key] = $value;
}
$comparisonArr = $payload["configCompare"];
unset($payload["configCompare"]);

//First get the basic requirements
$ak = findAccessKey($keys, $payload["key"]);
$sk = findSecretKey($keys, $payload["key"]);
unset($payload["key"]);
$region = $payload["region"];
unset($payload["region"]);
//Second we get all custom fitting component for createEnvironment and then work with config values
//get SessionToken if avail
$keyToken = $payload["keySessionToken"];
unset($payload["keySessionToken"]);
//Get AppName
$appName = $payload["Application_Name"];
unset($payload["Application_Name"]);
//Get beanstalk name
$envName = $payload["beanstalk_name"];
unset($payload["beanstalk_name"]);
	if(empty($envName))
		die("<span class='text-danger'>Cloned beanstalk needs to have a name!</span>");
	else if(strpos($envName, " ") !== FALSE)
		die("<span class='text-danger'>Beanstalk name cannot contain spaces!</span>");
//Get Tier
$tier = explode(" ", $payload["Tier"]);
$tier = array("Name" => $tier[1], "Type" => $tier[0], "Version" => $tier[2]);
unset($payload["Tier"]);
//Get Tags
$Tags = array();
foreach($payload["mt"] as $mtag){
	//Check if a mandatory tag is empty
	if(empty($payload[$mtag]))
		die("<span class='text-danger'>One or more Mandatory tags are not set!</span>");
	$Tags[] = array("Key" => $mtag, "Value"=>$payload[$mtag]);
	unset($payload[$mtag]);
}
unset($payload["mt"]);
//Get Version Label
$vLabel = $payload["VersionLabel"];
unset($payload["VersionLabel"]);
//Get Container
$container = $payload["SolutionStackName"];
unset($payload["SolutionStackName"]);

//Lastly, we prep the configuration settings
$configSettings = array();
foreach($payload as $key=>$val){
	if(stripos($key, "AWS") === FALSE) //excludes then AWS_SECRET_KEY AND AWS_ACCESS_KEY
		$key = urldecode($key);
	else if($key == "AWS_ACCESS_KEY_ID") //replace the keys with the appropriate session set ones
		$val = $_SESSION["AK_".$keyToken];
	else if($key == "AWS_SECRET_KEY")
		$val = $_SESSION["SK_".$keyToken];
    $namespace = pullConfigNamespaceByName($key, $comparisonArr);
   	if(is_null($namespace))
		continue;
	$configSettings[] = array("OptionName" => $key,
												"Value" => $val,
												"Namespace" => $namespace);
}

//Create client
$c = ElasticBeanstalkClient::factory(array("region" => $region, "key" => $ak, "secret" => $sk));
$req = array("ApplicationName" => $appName,
			 "EnvironmentName" => $envName,
			 "Tier" => $tier,
			 "Tags" => $Tags,
			 "VersionLabel" => $vLabel,
			 "SolutionStackName" => $container,
			 "OptionSettings" => $configSettings);
$res = $c->createEnvironment($req);

if($res->hasKey("EnvironmentId")){
	//Success
	echo "<span class='text-success'>Cloning Success! Environment ".$res->get("EnvironmentName")." (".$res->get("EnvironmentId").") on ".$res->get("DateCreated")." 
	<br/> Status: ".$res->get("Status")." , Health: ".$res->get("Health")." <br/> Please give it up to 30 seconds for the bmgr to correctly update info on the environment</span>";
} else {
	echo "<span class='text-danger'>Cloning failed - Log: <br/><small>".var_dump($res)."</small></span>";
}
?>