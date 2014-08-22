<?php
require_once 'Spyc.php';
require_once "aws_funcs.php"; //al computation/retrieval here
session_start();

$yamlDetails = getYAML();
$configuration = $yamlDetails["config"];
$keys = $yamlDetails["keys"];

if(empty($_POST["bstlkname"]) || empty($_POST["appname"]) || empty($_POST["region"]) || empty($_POST["key"]))
	die("Error: One or more parameters are not set");

//rest is formatting
$eName = $_POST["bstlkname"];
$eId = $_POST["bstlkId"];
$aName = $_POST["appname"];
$reg = $_POST["region"];
$ak = findAccessKey($keys, $_POST["key"]);
$sk = findSecretKey($keys, $_POST["key"]);
$mts = $_POST["mt"];


use Aws\ElasticBeanstalk\ElasticBeanstalkClient;
use Aws\Ec2\Ec2Client;
use Aws\AutoScaling\AutoScalingClient;
use Aws\CloudFormation\CloudFormationClient;
use Aws\Iam\IamClient;
//EnvironmentVariables ommitted to exclude double counting


	$c = ElasticBeanstalkClient::factory(array("region" => $reg, "key" => $ak, "secret" => $sk));
	$c2 = EC2Client::factory(array("region" => $reg, "key" => $ak, "secret" => $sk));
	$c3 = AutoScalingClient::factory(array("region" => $reg, "key" => $ak, "secret" => $sk));
		//Check if saved data exists
		if(file_exists(PATH.$eId.".bstlk")){
			$dataSerial = file_get_contents(PATH.$eId.".bstlk");
			$dataSerial = explode("|||", $dataSerial);
			$resVar = unserialize($dataSerial[0]);
			$resVar2 = unserialize($dataSerial[1]);
			$resVar4 = unserialize($dataSerial[2]);
			$eventVar = unserialize($dataSerial[4]);
			$asg = unserialize($dataSerial[3]);
				//check for changes
				if(pullEventDate(getLatestEvent($eName, $c)) !== pullEventDate($eventVar)){
					echo "<div class='small'>New Changes detected, beanstalk information has been updated</div>";
					$resVar = getBeanstalkInformation($eName, $c);
						$status = formatStatus($resVar, $eId); //placed here incase of terminated/terminating event
					$resVar2 = getBeanstalkInstances($eName, $c);
					$resVar4 = getConfigurationSettings($aName, $eName, $c);
					$eventVar = getLatestEvent($eName, $c);
					$asg = getASGInformation(pullASG($resVar2), $c3);
				}
		} else {
			$resVar = getBeanstalkInformation($eName, $c);
				$status = formatStatus($resVar, $eId); //placed here incase of terminated/terminating event
			$resVar2 = getBeanstalkInstances($eName, $c);
			$resVar4 = getConfigurationSettings($aName, $eName, $c);
			$eventVar = getLatestEvent($eName, $c);
			$asg = getASGInformation(pullASG($resVar2), $c3);
			// The main configuration settings are divided into two parts:  Environmentvariables and any with namespace parameter. Excluding AWS Keys...
		}


	//Pause/Start Functionality//
	//is it paused?
	if(isPaused($asg))
		$pauseButtonOutput = '<button type="button" id="playbtn" data-toggle="modal"  data-target="#playModal" title="This Environment is currently paused, press to resume beanstalk operations" class="btn btn-lg btn-default"><span class="glyphicon glyphicon-play"></span></button>';
	else
		$pauseButtonOutput = '<button type="button" id="pausebtn" data-toggle="modal" data-target="#pauseModal" title="This Environment is currently operating, press to pause the beanstalk" class="btn btn-lg btn-default"><span class="glyphicon glyphicon-pause"></span></button>';
	//

	//Name+Region+VersionLabel+Status header
	$status = formatStatus($resVar, $eId);
	//extra health info check
	if(pullBstlkHealth($resVar)=="Red")
		$status = "<span id='health-status' title='Health: ".pullBstlkHealth($resVar)." <br/> Status: ".pullBstlkStatus($resVar)."' class='glyphicon glyphicon-info-sign text-warning'></span>";
	//forece refresh
	echo "<small><a id='rf' href='#'>Force Refresh</a></small>";
	echo "<script>$('#rf').attr('href', '?'+window.location.href.split('?')[1].split('#')[0]+'&a=".$eName."');</script>";
	// main header box
	echo "<div class='col-md-12 alert alert-info'>
			<div class='col-md-11'>";
			echo '<b class="lead">'.$status.' '.$eName.' ('.$reg.')</b><br/>';
			echo '<b>Version:</b> '.pullVersionLabel($resVar).'<br/>';
			if(!empty(pullConfigValueByName("Notification Endpoint", $resVar4)))
				echo '<b>Notification Endpoint:</b> '.pullConfigValueByName("Notification Endpoint", $resVar4);
			echo "</div>";
			echo '<div class="col-md-1">
					'.$pauseButtonOutput.'
					<button type="button" data-toggle="modal"  data-target="#cloneModal" title="Clone this environment with the option of modifying specific parameters" class="btn btn-lg btn-default"><span class="glyphicon glyphicon-file"></span></button>
				  </div>';
	echo '</div>';
	////////////////////////////////////////////////////////////////////
	$maintabnavs = '
	<ul class="nav nav-tabs" role="tablist">
  		<li class="active"><a href="#instances" role="tab" data-toggle="tab">Instances <span class="badge">'.
			count(pullBstlkInstances($resVar2)).'</span></a></li>
		<li><a href="#server" role="tab" data-toggle="tab">Server Parameters</a></li>
  		<li><a href="#container" role="tab" data-toggle="tab">Container</a></li>
  		<li><a href="#elb" role="tab" data-toggle="tab">ELB</a></li>
  		<li><a href="#asg" role="tab" data-toggle="tab">ASG</a></li>
  	</ul>
  	<div class="tab-content">';
  	echo $maintabnavs;
	// Instances PANEL //
	$instancepanel = '
		<div class="tab-pane fade in active" id="instances">
  		<table class="table">
  			<tr>
    			<th style="width: auto">Instance ID</th>
    			<th style="width: auto">State</th>
    			<th style="width: auto">IP</th>
    			<th style="width: auto">Uptime</th>
    		</tr>';
    echo $instancepanel;
	foreach(pullBstlkInstances($resVar2) as $instance){
		$resVar3 = getInstanceInformation($instance, $c2);
		echo "<tr>";
		echo "<td>".$instance."</td>";
		echo "<td>".pullInstanceState($resVar3)."</td>";
		echo "<td>".pullIPAdress($resVar3)."</td>";
		echo "<td>".pullUptime($resVar3)."</td>";
		echo "</tr>";
	} 

  	$instancepanelend = '</table>
	</div>
	';
	echo $instancepanelend;

	////////////////////////////////////////////////////////////////////

	// SERVER PANEL //
	$serverpanel = '
	<div class="tab-pane fade" id="server">
  		<table class="table small">
  			<tr>
    			<th style="width: 30%">Parameter Name</th>
    			<th style="width: 70%">Value</th>
    		</tr>
    		<tr><td>EC2 Key Name</td><td>'.pullConfigValueByName("EC2KeyName", $resVar4).'</td></tr>
    		<tr><td>Iam Instance Profile</td><td>'.pullConfigValueByName("IamInstanceProfile", $resVar4).'</td></tr>
    		<tr><td>Image Id</td><td>'.pullConfigValueByName("ImageId", $resVar4).'</td></tr>
    		<tr><td>Instance Type</td><td>'.pullConfigValueByName("InstanceType", $resVar4).'</td></tr>
    		<tr><td>Monitoring Interval</td><td>'.pullConfigValueByName("MonitoringInterval", $resVar4).'</td></tr>
    		<tr><td>Security Groups</td><td>'.pullConfigValueByName("SecurityGroups", $resVar4).'</td></tr>
  		</table>
	</div>
	';
	echo $serverpanel;

	////////////////////////////////////////////////////////////////////

	// CONTAINER PANEL //
	$containerpanel = '
	<div class="tab-pane fade" id="container">
  		<table class="table small">
  			<tr>
    			<th style="width: 30%">Parameter Name</th>
    			<th style="width: 70%">Value</th>
    		</tr>
    		<tr><td>Container Name</td><td>'.pullContainer($resVar).'</td></tr>';
    echo $containerpanel;
    $copts = pullEnvironmentVariables($resVar4, true);
    foreach($copts as $opt){
    	if(!empty($opt["Value"])  && $opt["Name"] !== "AppSource")
    	echo '
    	<tr>
    		<td>'.$opt["Name"].'</td>
    		<td>'.$opt["Value"].'</td>
    	</tr>
    	';
    }
    $containerpanelend = '
  		</table>
	</div>
	';
	echo $containerpanelend;

	////////////////////////////////////////////////////////////////////

	// ELB PANEL //
	$elbpanel = '
	<div class="tab-pane fade" id="elb">
  		<table class="table small">
  			<tr>
    			<th style="width: 30%">Parameter Name</th>
    			<th style="width: 70%">Value</th>
    		</tr>
    		<tr><td>Elastic LoadBalancer</td><td>'.pullELB($resVar).'</td></tr>
    		<tr><td>Application Healthcheck URL</td><td>'.pullConfigValueByName("Application Healthcheck URL", $resVar4).'</td></tr>
    		<tr><td>Load Balancer HTTP Port</td><td>'.pullConfigValueByName("LoadBalancerHTTPPort", $resVar4).'</td></tr>
    		<tr><td>Load Balancer HTTPS Port</td><td>'.pullConfigValueByName("LoadBalancerHTTPSPort", $resVar4).'</td></tr>
    		<tr><td>Load Balancer Port Protocol</td><td>'.pullConfigValueByName("LoadBalancerPortProtocol", $resVar4).'</td></tr>
    		<tr><td>Load Balancer SSL Port Protocol</td><td>'.pullConfigValueByName("LoadBalancerSSLPortProtocol", $resVar4).'</td></tr>
    		<tr><td>SSL Certificate Id</td><td>'.pullConfigValueByName("SSLCertificateId", $resVar4).'</td></tr>
    		<tr><td>Stickiness Policy</td><td>'.pullConfigValueByName("Stickiness Policy", $resVar4).'</td></tr>
  		</table>
	</div>
	';
	echo $elbpanel;

	////////////////////////////////////////////////////////////////////

	// ASG PANEL //
	$asgpanel = '
	<div class="tab-pane fade" id="asg">
  		<table class="table small">
  			<tr>
    			<th style="width: 30%">Parameter Name</th>
    			<th style="width: 70%">Value</th>
    		</tr>
    		<tr><td>AutoScaling Group</td><td>'.pullASG($resVar2).'</td></tr>
    		<tr><td>Availability Zones</td><td>'.pullConfigValueByName("Availability Zones", $resVar4).'</td></tr>
    		<tr><td>Cooldown</td><td>'.pullConfigValueByName("Cooldown", $resVar4).'</td></tr>
    		<tr><td>Max Instances Size</td><td>'.pullConfigValueByName("MaxSize", $resVar4).'</td></tr>
    		<tr><td>Min Instances Size</td><td>'.pullConfigValueByName("MinSize", $resVar4).'</td></tr>
  		</table>
	</div>
	';
	echo $asgpanel;

	echo "</div>";

	////////////////////////////////////////////////////////////////////

	////PAUSE/PLAY MODAL///
	echo '<!--Pause Modal -->
	<div class="modal fade" id="pauseModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  		<div class="modal-dialog">
  			<div class="modal-content">
  				<div class="modal-body" id="pauseModalBody">
  					Pausing a beanstalk
  				</div>
  			</div>
  		</div>
	</div>';

	echo '<!--Play Modal -->
	<div class="modal fade" id="playModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  		<div class="modal-dialog">
  			<div class="modal-content">
  				<div class="modal-body">
  					Input below the number of max and min instances you wish to restore to this beanstalk (environment).
  					<br/><br/>
  					<form role="form-inline">
  						<div class="form-group">
    						<label for="minLabel">Min Instances:</label>
    						<input type="number" class="form-control" id="minInput" placeholder="#" value="'.pullConfigValueByName("MinSize", $resVar4).'">
  						</div>
  						<div class="form-group">
    						<label for="maxLabel">Max Instances:</label>
    						<input type="number" class="form-control" id="maxInput" placeholder="#" value="'.pullConfigValueByName("MaxSize", $resVar4).'">
  						</div>
  					</form>
  					<div class="text-right">
  						<button type="submit" onclick="playBeanstalk(\'play\', \''.$eName.'\', \''.$eId.'\', \''.pullASG($resVar2).'\', \''.$reg.'\', \''.$_POST["key"].'\')" class="btn btn-default">Play</button>
  					</div>
  					<br/>
  					<div id="playResult" class="text-center">
  					</div>
  				</div>
  			</div>
  		</div>
	</div>';
	///////////////////////////////////////////////////////////////////////

	////Clone Modal///
	echo '<!--clone Modal -->
	<div class="modal fade" id="cloneModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  		<div class="modal-dialog modal-lg">
  			<div class="modal-content">
  			      <div class="modal-header">
        		  	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
       			  	<h4 class="modal-title">Cloning a Beanstalk - '.$eName.'</h4>
      			  </div>
  				<div class="modal-body row">
  				<div class="col-md-12">
  				<!--------- GENERAL BEANSTALK ---------------->
  				<p class="text-warning small" id="warning-pane-modal">
  				</p>
  				<p>
  					<b>Beanstalk Name: </b><br/>
  					<input type="text" class="form-control input-sm" id="beanstalk_name"><br/>
  					<b>Application Version:</b><br/>
  					<input type="text" class="form-control input-sm" id="VersionLabel" value="'.pullVersionLabel($resVar).'"><br/>
  					<b>Environment Tier:</b><br/>
  					<select class="form-control input-sm" id="Tier">
  					';
  					foreach( $AVAILABLE_BEANSTALK_CLONE_TIERS as $t)
  						echo "<option value='$t'>$t</option>";
  	echo '
  					</select><br/>
  				</p>
  				<!-- TABS NAV -->
  				<ul class="nav nav-tabs" role="tablist">
  					<li class="active"><a href="#server-modal" role="tab" data-toggle="tab">Server Params</a></li>
  					<li><a href="#container-modal" role="tab" data-toggle="tab">Container</a></li>
  					<li><a href="#elb-modal" role="tab" data-toggle="tab">ELB</a></li>
  					<li><a href="#asg-modal" role="tab" data-toggle="tab">ASG</a></li>
  					<li><a href="#ru-modal" role="tab" data-toggle="tab">Rolling Update</a></li>
  					<li><a href="#tags-modal" role="tab" data-toggle="tab">Tags</a></li>
  				</ul>
  				<!--------------------------------------------->
  				<div class="tab-content">
  				<!-------- SERVER PANEL ---------------------->
  				<div class="tab-pane fade in active" id="server-modal">
  						<table class="table small table-condensed">
  							<tr>
    							<th style="width: 30%">Parameter Name</th>
    							<th style="width: 70%">Value</th>
    						</tr>
    						<tr><td>Application Name</td><td id="Application_Name">'.$aName.'</td></tr>
    						<tr><td>Notification Endpoint</td><td id="'.urlencode("Notification Endpoint").'">'.pullConfigValueByName("Notification Endpoint", $resVar4).'</td></tr>
    						<tr><td>EC2 Key Name</td><td id="EC2KeyName">'.pullConfigValueByName("EC2KeyName", $resVar4).'</td></tr>
    						<tr><td>Iam Instance Profile</td><td id="IamInstanceProfile">'.pullConfigValueByName("IamInstanceProfile", $resVar4).'</td></tr>
    						<tr><td>Instance Type <a target="_blank" href="http://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instance-types.html"><span class="glyphicon glyphicon-th-list"></span></a></td><td id="InstanceType">'.pullConfigValueByName("InstanceType", $resVar4).'</td></tr>
    						<tr><td>Monitoring Interval</td><td id="MonitoringInterval">'.pullConfigValueByName("MonitoringInterval", $resVar4).'</td></tr>
    						<tr><td>Security Groups</td><td id="SecurityGroups">'.pullConfigValueByName("SecurityGroups", $resVar4).'</td></tr>
    						<tr><td>Environment Type</td><td><select class="form-control input-sm" id="EnvironmentType">';
  							foreach($AVAILABLE_ENVIRONMENT_TYPES as $t){
  								if(pullConfigValueByName("EnvironmentType", $resVar4) == $t)
									$isSelected = " selected='selected'";
								else
									$isSelected = "";	
  								echo "<option value='$t'$isSelected>$t</option>";
  							}
  	echo '
  						</table>
				</div>

				<!------------------------------------------------>
				<!------- CONTAINER PANEL ------------------------>
				<div class="tab-pane fade" id="container-modal">
  						<table class="table small table-condensed">
  							<tr>
    							<th style="width: 30%">Parameter Name</th>
    							<th style="width: 70%">Value</th>
    						</tr>
    						<tr><td>Container Name</td><td id="SolutionStackName">'.pullContainer($resVar).'</td></tr>
    						<tr><td>JVM Options CLI</td><td id="JVMOptions">'.pullJVMOptsCLI($resVar4).'</td></tr>
    						<tr><td>XX:MaxPermSize</td><td id="XX:MaxPermSize">'.pullConfigValueByName("XX:MaxPermSize", $resVar4).'</td></tr>
    						<tr><td>Xmx</td><td id="Xmx">'.pullConfigValueByName("Xmx", $resVar4).'</td></tr>
    						<tr><td>Xms</td><td id="Xms">'.pullConfigValueByName("Xms", $resVar4).'</td></tr>';
    						$copts = pullEnvironmentVariables($resVar4, false);
    						$hasKeysToTokenize = FALSE;
    						foreach($copts as $opt){
    							//exclude appsource due to recreation error
    							if(!empty($opt["Value"]) && $opt["Name"] !== "AppSource"){
    								if($opt["Name"] == "AWS_SECRET_KEY" || $opt["Name"] == "AWS_ACCESS_KEY_ID"){
    									$hasKeysToTokenize = TRUE;
    									if($opt["Name"] == "AWS_SECRET_KEY")
    										$SKToBeTokenized = $opt["Value"];
    									else
    										$AKToBeTokenized = $opt["Value"];
    									$opt["Value"] = obscure($opt["Value"]); //security
    								}

    								echo '
    								<tr>
    									<td>'.$opt["Name"].'</td>
    									<td id="'.$opt["Name"].'">'.$opt["Value"].'</td>
    								</tr>
    								';
    							}
    						}

    						//ELB SSL Certificate Check
                $formattedCertDetail = '';
    						$certName = pullConfigValueByName("SSLCertificateId", $resVar4);
    						if(!is_null($certName)){
    							$iamcert = IamClient::factory(array("region" => $reg, "key" => $ak, "secret" => $sk));
    							$certName = explode("/", $certName);
    							$certName = $certName[1]; //last part of the slash
    							$cert = getCertDetail($certName, $iamcert);
    							if($cert){
    								$formattedCertDetail = "<span id='certDetail' title='CN: ".$cert["subject"]["CN"]."<br/>Certificate Valid until: ".date("M j, Y", $cert["validTo_time_t"])."' class='glyphicon glyphicon-info-sign'></span>";
    							} else
    								$formattedCertDetail = "";
    					}
    echo '
  						</table>
				</div>
			  <!--------------------------------------------------->
			  <!------------- ELB PANEL -------------------------->
			  <div class="tab-pane fade" id="elb-modal">
  						<table class="table small table-condensed">
  							<tr>
    							<th style="width: 30%">Parameter Name</th>
    							<th style="width: 70%">Value</th>
    						</tr>
    						<tr><th>Load Balancer</th><th></th></tr>
    						<tr><td>Listener Port</td><td id="LoadBalancerHTTPPort">'.pullConfigValueByName("LoadBalancerHTTPPort", $resVar4).'</td></tr>
    						<tr><td>Listener Protocol</td><td id="LoadBalancerPortProtocol">'.pullConfigValueByName("LoadBalancerPortProtocol", $resVar4).'</td></tr>
    						<tr><td>Secure Listener Port</td><td id="LoadBalancerHTTPSPort">'.pullConfigValueByName("LoadBalancerHTTPSPort", $resVar4).'</td></tr>
    						<tr><td>Secure Listener Protocol</td><td id="LoadBalancerSSLPortProtocol">'.pullConfigValueByName("LoadBalancerSSLPortProtocol", $resVar4).'</td></tr>
    						<tr><td>SSL Certificate Id '.$formattedCertDetail.'</td><td id="SSLCertificateId">'.pullConfigValueByName("SSLCertificateId", $resVar4).'</td></tr>
    						<tr><th>EC2 Instance Health Check</th><th></th></tr>
    						<tr><td>Application Healthcheck URL</td><td id="'.urlencode("Application Healthcheck URL").'">'.pullConfigValueByName("Application Healthcheck URL", $resVar4).'</td></tr>
    						<tr><td>Interval (seconds)</td><td id="Interval">'.pullConfigValueByName("Interval", $resVar4).'</td></tr>
    						<tr><td>Timeout (seconds)</td><td id="Timeout">'.pullConfigValueByName("Timeout", $resVar4).'</td></tr>
    						<tr><td>Healthy Threshold</td><td id="HealthyThreshold">'.pullConfigValueByName("HealthyThreshold", $resVar4).'</td></tr>
    						<tr><td>Unhealthy Threshold</td><td id="UnhealthyThreshold">'.pullConfigValueByName("UnhealthyThreshold", $resVar4).'</td></tr>
    						<tr><td>Target</td><td id="Target">'.pullConfigValueByName("Target", $resVar4).'</td></tr>
    						<tr><th>Sessions</th><th></th></tr>
    						<tr><td>Stickiness Policy</td><td id="'.urlencode("Stickiness Policy").'">'.pullConfigValueByName("Stickiness Policy", $resVar4).'</td></tr>
    						'.(pullConfigValueByName("Stickiness Policy", $resVar4) == "true" ? 
    							'<tr><td>Stickiness Cookie Expiration</td><td id="'.urlencode("Stickiness Cookie Expiration").'">'.pullConfigValueByName("Stickiness Cookie Expiration", $resVar4).'</td></tr>' : '').'
  						</table>
				</div>
			 <!----------------------------------------------------->
			 <!------------ASG PANEL-------------------------------->
			 <div class="tab-pane fade" id="asg-modal">
  					<table class="table small table-condensed">
  						<tr>
    						<th style="width: 30%">Parameter Name</th>
    						<th style="width: 70%">Value</th>
    					</tr>
    					<tr><th>Auto Scaling</th><th></th></tr>
    					<tr><td>Min Instances Size</td><td id="MinSize">'.pullConfigValueByName("MinSize", $resVar4).'</td></tr>
    					<tr><td>Max Instances Size</td><td id="MaxSize">'.pullConfigValueByName("MaxSize", $resVar4).'</td></tr>
    					<tr><td>Availability Zones</td><td id="'.urlencode("Availability Zones").'">'.pullConfigValueByName("Availability Zones", $resVar4).'</td></tr>
    					'.(empty(pullConfigValueByName("Custom Availability Zones", $resVar4)) ? '' : 
    						'<tr><td>Custom Availability Zones</td><td id="'.urlencode("Custom Availability Zones").'">'.pullConfigValueByName("Custom Availability Zones", $resVar4).'</td></tr>' )
    					.'
    					<tr><td>Scaling Cooldown (seconds)</td><td id="Cooldown">'.pullConfigValueByName("Cooldown", $resVar4).'</td></tr>
    					<tr><th>Scaling Trigger</th><th></th></tr>
    					<tr><td>Measure Name</td><td><select class="form-control input-sm" id="MeasureName">';
  						foreach($AVAILABLE_MEASURENAME_LIST as $t){
  							if(pullConfigValueByName("MeasureName", $resVar4) == $t)
								$isSelected = " selected='selected'";
							else
								$isSelected = "";	
  							echo "<option value='$t'$isSelected>$t</option>";
  						}
  	echo '
  						</select></td></tr>
  	    				<tr><td>Statistic</td><td><select class="form-control input-sm" id="Statistic">';
  						foreach($AVAILABLE_STATISTIC_LIST as $t){
  							if(pullConfigValueByName("Statistic", $resVar4) == $t)
								$isSelected = " selected='selected'";
							else
								$isSelected = "";	
  							echo "<option value='$t'$isSelected>$t</option>";
  						}
  	echo '
  						</select></td></tr>
  	  					<tr><td>Unit</td><td><select class="form-control input-sm" id="Unit">';
  						foreach($AVAILABLE_UNIT_LIST as $t){
  							if(pullConfigValueByName("Unit", $resVar4) == $t)
								$isSelected = " selected='selected'";
							else
								$isSelected = "";	
  							echo "<option value='$t'$isSelected>$t</option>";
  						}
  	echo '				</select></td></tr>
    					<tr><td>Measurement Period (minutes)</td><td id="Period">'.pullConfigValueByName("Period", $resVar4).'</td></tr>
    					<tr><td>Breach Duration (minutes)</td><td id="BreachDuration">'.pullConfigValueByName("BreachDuration", $resVar4).'</td></tr>
    					<tr><td>Lower Threshold</td><td id="LowerThreshold">'.pullConfigValueByName("LowerThreshold", $resVar4).'</td></tr>
    					<tr><td>Upper Threshold</td><td id="UpperThreshold">'.pullConfigValueByName("UpperThreshold", $resVar4).'</td></tr>
    					<tr><td>Lower Breach Scale Increment</td><td id="LowerBreachScaleIncrement">'.pullConfigValueByName("LowerBreachScaleIncrement", $resVar4).'</td></tr>
    					<tr><td>Upper Breach Scale Increment</td><td id="UpperBreachScaleIncrement">'.pullConfigValueByName("UpperBreachScaleIncrement", $resVar4).'</td></tr>
    					<tr><td>Evaluation Periods</td><td id="EvaluationPeriods">'.pullConfigValueByName("EvaluationPeriods", $resVar4).'</td></tr>
  					</table>
			</div>
			<!------------------------------------------------------->
			<!------------- ROLLING UPDATES PANEL -------------------------->
			  <div class="tab-pane fade" id="ru-modal">
  						<table class="table small table-condensed">
  							<tr>
    							<th style="width: 30%">Parameter Name</th>
    							<th style="width: 70%">Value</th>
    						</tr>
    						<tr><td>Min Instances In Service</td><td id="MinInstancesInService">'.pullConfigValueByName("MinInstancesInService", $resVar4).'</td></tr>
    						<tr><td>Rolling Update Enabled</td><td id="RollingUpdateEnabled">'.pullConfigValueByName("RollingUpdateEnabled", $resVar4).'</td></tr>
    						<tr><td>Max Batch Size</td><td id="MaxBatchSize">'.pullConfigValueByName("MaxBatchSize", $resVar4).'</td></tr>
    						<tr><td>Pause Time</td><td id="PauseTime">'.pullConfigValueByName("PauseTime", $resVar4).'</td></tr>
  						</table>
				</div>
			 <!----------------------------------------------------->
			<!----------- TAGS PANEL --------------------->
			<div class="tab-pane fade" id="tags-modal">
  					<table class="table small table-condensed">
  						<tr>
    						<th style="width: 30%">Tag Name</th>
    						<th style="width: 70%">Value</th>
    					</tr>';
			//Getting the environment tags through cloudFormation
			$cfClient = CloudFormationClient::factory(array("region" => $reg, "key" => $ak, "secret" => $sk));
			$tags = getEnvironmentTags(pullEnvironmentId($resVar), $cfClient);
    					foreach($mts as $mt){
    						$tagVal = (is_null(pullEnvTagByName($mt, $tags)) ? "<tr><td>$mt</td><td id='$mt'></td></tr>" : 
    																		"<tr><td>$mt</td><td id='$mt'>".pullEnvTagByName($mt, $tags)."</td></tr>");
    						echo $tagVal;
    					}

    		echo '
  					</table>
			</div>
			</div>
			</div>
			<div class="col-md-10 top-buffer lead" id="cloner-status">
			Status
			</div>
			<div class="col-md-2 text-right top-buffer">
				<button type="button" id="clonebtn" onclick=\'cloneBeanstalk('.json_encode(removeKeys($resVar4)).');\' class="btn btn-lg btn-success">Clone</button>
			</div>
  			</div>
  		</div>
	</div>';

	//////////////////////////////////////////////////////////////////////

	///ADDITIONAL JS ////
	//Cloner functionalities
	//Matching Containers with available retrieved from AWS
	preg_match("/running (\w*)/", pullContainer($resVar), $match); //find the current solutionstackname type
	$availSolutionStacks = pullFilteredSolutionStacks(getAvailSolutionStacks($c), $match[1]); //filter
	if(!in_array(pullContainer($resVar), $availSolutionStacks)) //use jquery to add a warning of No matching container if not found
		echo "<script>$('#warning-pane-modal').append('<span class=\'glyphicon glyphicon-info-sign\'></span> The current beanstalk runs a different Solution Stack (Container) than the available ones to be cloned<br/>');</script>";
	$htmlAvailSolutionStacks = "";
	foreach($availSolutionStacks as $ss){
		if(pullContainer($resVar) == $ss)
			$isSelected = " selected='selected'";
		else
			$isSelected = "";
			$htmlAvailSolutionStacks .= "<option value='".$ss."'".$isSelected.">".$ss."</option>";
	}

	//save off a token as a pointer to the AK/SK stored in session (security)
	if($hasKeysToTokenize){
		$keyToken = rand(10000,99999);
		$_SESSION["AK_".$keyToken] = $AKToBeTokenized;
		$_SESSION["SK_".$keyToken] = $SKToBeTokenized;
	}
	/////////////////////////////////////////

	echo '<script>
		//Fast way to disable the pause button if the curr env is live.
		if($("a#'.$eName.'").find("span").attr("class") == "glyphicon glyphicon-ok"){
			$("button[data-target|=\'#pauseModal\']").attr("disabled", true);
			$("button[data-target|=\'#playModal\']").attr("disabled", true);
		} else {
			$("button.btn-lg").tooltip({placement: "right"});
		}

		$("#health-status").tooltip({placement: "left", html: true});
		$("#certDetail").tooltip({placement: "right", html: true});

		function pauseBeanstalk(action, ename, eid, asgName, region, key){
			var loadGif = "<img src=\'images/loading.gif\' width=\'100\' height=\'100\'> Working...";
			$("#pauseModalBody").html(loadGif).load("pauseplay_bstlk.php?c='.$_GET["c"].'", {"action": action, "ename": ename, "eid": eid, "asgName": asgName, "region": region, "key": key});
		}

		function playBeanstalk(action, ename, eid, asgName, region, key){
			var max = $("#maxInput").val();
			var min = $("#minInput").val();
			if($.isNumeric(max) && $.isNumeric(min)){
				var loadGif = "<img src=\'images/loading.gif\' width=\'100\' height=\'100\'> Firing up the environment...";
				$("#playResult").html(loadGif).load("pauseplay_bstlk.php?c='.$_GET["c"].'", {"action": action, "ename": ename, "eid": eid, "asgName": asgName, "region": region, "max": max, "min": min, "key": key});
			} else {
				$("#playResult").append("<span class=\'text-danger\'>Either Max or Min instances aren\'t numbers!</span>");
			}
		}

		$("#pauseModal").on("shown.bs.modal", function () { pauseBeanstalk(\'pause\', \''.$eName.'\', \''.$eId.'\', \''.pullASG($resVar2).'\', \''.$reg.'\' , \''.$_POST["key"].'\'); });
		  
		  ///////////////////////////////////////////////////////CLONE BEANSTALK FUNCS
		  var mandatory_tags = '.json_encode($mts).'; //mandatory tags to check against
		  $("td").each(function(i) {
		  	// If Container label.
		  	if($(this).attr("id") == "SolutionStackName"){
		  		$(this).html("<select class=\"form-control input-sm\" id=\"SolutionStackName\">'.$htmlAvailSolutionStacks.'</select>");
		  		return;
		  	}

		  	//If MonitoringInterval or Application_Name (disable it)
		  	if($(this).attr("id") == "MonitoringInterval" || $(this).attr("id") == "Application_Name"){
		  		//transform to editbox
		  		$(this).html("<input type=\"text\" id=\""+$(this).attr("id")+"\" class=\"input-sm form-control\" value=\""+$(this).text()+"\" disabled>");
		  		return;
		  	}

		  	// If SecurityGroups Label.
		  	if($(this).attr("id") == "SecurityGroups"){
		  		var sgArr = $(this).text().split(",");
		  		var sgNewArr = [];
		  		for(var i=0; i < sgArr.length; i++){
		  			if(sgArr[i].indexOf("awseb") > -1)
		  				continue;
		  			else
		  				sgNewArr.push(sgArr[i]);
		  		}
		  		$(this).html("<input type=\"text\" id=\""+$(this).attr("id")+"\" class=\"input-sm form-control\" value=\""+sgNewArr.join(",")+"\">");
		  		return;
		  	}

		  	// If mandatory tag
		  	if($.inArray($(this).attr("id"), mandatory_tags) > -1){
		  		if(!$(this).text())
		  			$("#warning-pane-modal").append("<div id=\'"+$(this).attr("id")+"\'><span class=\'glyphicon glyphicon-ban-circle\'></span> Tag: "+$(this).attr("id")+" is not set! (mandatory)</div>");
		  	}

		  	// Convert to input box
		  	if($(this).attr("id")){
		  		//transform to editbox
		  		$(this).html("<input type=\"text\" id=\""+$(this).attr("id")+"\" class=\"input-sm form-control\" value=\""+$(this).text()+"\">");
		  	}

		  });

			//beanstalkname validation
			$("#beanstalk_name").keypress(function(){
				$(this).val($(this).val().replace(" ", ""));
			});
			
			function cloneBeanstalk(comparisonVar){
				var payload = {"mt": mandatory_tags, "key": "'.$_POST['key'].'", region: "'.$reg.'", keySessionToken: "'.($hasKeysToTokenize ? $keyToken : null).'"};
				$("#clonebtn").attr("disabled","disabled");
				//all editable inputs
				$("input").each(function(i){
					if($(this).attr("id"))
						payload[$(this).attr("id")] = $(this).val();
				});

				//all SELECTS
				$("select").each(function(i){
					if($(this).attr("id"))
						payload[$(this).attr("id")] = $(this).val();
				});

				//send a copy of resVar4 to compare configuration settings (we dont know the namespace)
				payload["configCompare"] = comparisonVar;
				$("#cloner-status").html("Cloning...").load("clonebstlk.php?c='.$_GET['c'].'", payload, function(){ 
					$("#clonebtn").removeAttr("disabled");
				});
			}
		
			
		  </script>';

	/////////////////////////////////////////////////////////////////

	//SAVE A LOCAL COPY
	file_put_contents(PATH.$eId.".bstlk", serialize($resVar)."|||".serialize($resVar2)."|||".serialize($resVar4)."|||".serialize($asg)."|||".serialize($eventVar), LOCK_EX);
?>
