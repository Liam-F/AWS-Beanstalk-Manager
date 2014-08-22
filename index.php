<?php
require_once 'Spyc.php';
require_once 'aws_funcs.php';

  //check yaml/config file
  if(empty($_GET["c"]) || !file_exists(CONFIG_PREFIX.$_GET["c"].".yaml")){
    echo "No Config specified, select a potential one below: <br/><br/>";
    echo "<ul>";
    $fs = scandir(CONFIG_PREFIX);
    foreach($fs as $f){
      if($f !== "keys.yaml" && strpos($f, ".yaml") !== FALSE){
        $floc = explode(".", $f);
        echo "<li><a href='index.php?c=".$floc[0]."'> ".$floc[0]." </a></li>";
      }
    }
    echo "</ul>";
    die;
  }

$yamlDetails = getYAML();
$configuration = $yamlDetails["config"];
$keys = $yamlDetails["keys"];

  $auth_realm = 'Beanstalk Manager';
  require_once 'productname.php';

  use Aws\ElasticBeanstalk\ElasticBeanstalkClient;
  use Aws\AutoScaling\AutoScalingClient;
  use Aws\Route53\Route53Client;

  if(!empty($_GET["a"])){
    if(file_exists(PATH.$_GET["a"].".bstlk")){
      unlink(PATH.$_GET["a"].".bstlk");
      $EPRefresh ="<div class='alert alert-success'><b>Refresh Successful</b> Please revisit page to load new information</div>";
    } else {
      $EPRefresh = "<div class='alert alert-danger'><b>Refresh Unsuccessful</b> No save cache was found</div>";
      }
  }
?>

<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="images/favicon.ico">

    <title><?php print $productLongName; ?></title>

    <!-- Bootstrap core CSS -->
    <link href="bootstrap/css/bootstrap.css" rel="stylesheet">
    <style>
      table {
        table-layout: fixed;
        word-wrap: break-word;
      }

      .table {
        margin-bottom: 0px;
      }
      
      .top-buffer { margin-top:50px; }

      .input-sm {
        height:20px;
        line-height:0;
      }
    </style>
    <!-- Custom styles for this template -->
    <link href="navbar.css" rel="stylesheet">
  </head>

  <body>

    <div class="container">

    <?php include 'menu.php'; ?>


      <div class="col-md-3">
          <ul class="list-group">
          <?php
            //List the main groups
            $isExistDNSField = false;
            foreach($configuration["menu_apps"] as $menuApps){

              //Set the access/secret key
              $aws_app_access_key = findAccessKey($keys, $menuApps["application"]["keys"]);
              $aws_app_secret_key = findSecretKey($keys, $menuApps["application"]["keys"]);

              $menuAppName = $menuApps["application"]["name"];
              $bstlkApps = $menuApps["application"]["beanstalk_apps"];
              $dns_switching_excl = $configuration["dns_exclusions"];

              //if DNS Record exists, let there be a dns switcher!
              if(!empty($menuApps["application"]["DNS"])){
                $isExistDNSField = true;
                $dns_switch_button = '<button id="dns_switch_button" type="button" onclick=\'fetchDNSSwitcher('.json_encode($dns_switching_excl).', '.json_encode($bstlkApps).', '.
                  json_encode($menuApps["application"]["DNS"]).', "'.$menuApps["application"]["keys"].'");\' class="btn btn-default"><span class="glyphicon glyphicon-random"></span></button> ';
                
                //pull the Live ELB the DNS is pointing to
                $c = Route53Client::factory(array("key" => $aws_app_access_key, "secret" => $aws_app_secret_key));
                $elb = getElbFromDNS($menuApps["application"]["DNS"][0]["name"], $menuApps["application"]["DNS"][0]["zid"],$c);
                $elb = pullDNS($elb);
              }
              else 
                $dns_switch_button = '';

              echo '<a href="#'.htmlSafeName($menuAppName).'-collapse" class="menuCollapse list-group-item">
              '.$dns_switch_button.$menuAppName.'</a>';
              echo '<ul id="'.htmlSafeName($menuAppName).'-collapse" class="collapse">'; //create collapse menu
              $isFoundLiveELB = false; //Set to true when we have found a live ELB else we will do some alert boxes
              //Find the application's beanstalks
              foreach($bstlkApps as $app){ //support many application support under one collapse
                //find the asscoiated set of keys
                $c = ElasticBeanstalkClient::factory(array("region" => $app["region"], "key" => $aws_app_access_key, "secret" => $aws_app_secret_key));
                $c2 = AutoScalingClient::factory(array("region" => $app["region"], "key" => $aws_app_access_key, "secret" => $aws_app_secret_key));
                $menuAppBstlks = listBeanstalks($app["name"], $app["region"], $c);
                foreach($menuAppBstlks as $bstlk){

                  //if the live elb force refresh is present $_GET["b"], delete the cache and then force the thing to reget it
                  //Scan for a cache
                  if(file_exists(PATH.$bstlk["id"].".bstlk") && empty($_GET["b"])){
                    $dataSerial = file_get_contents(PATH.$bstlk["id"].".bstlk");
                    $dataSerial = explode("|||", $dataSerial);
                    $asg = unserialize($dataSerial[3]);
                    $resVar2 = unserialize($dataSerial[0]);
                  } else { //or get our own
                      if(!empty($_GET["b"])){ //since we are on force refresh, lets save off a version.
                        @unlink(PATH.$bstlk["id"].".bstlk");
                        //file_get_contents("./fetch_bstlk_detail.php?appname=".$app["name"]."&bstlkname=".$bstlk."&region=".$app["region"]."&ak=".$aws_app_access_key."&sk=".$aws_app_secret_key);
                      }
                    $resVar2 = getBeanstalkInformation($bstlk["name"], $c);
                      if(pullBstlkStatus($resVar2) == "Terminated" || pullBstlkStatus($resVar2) == "Terminating")
                      continue;
                    $resVar = getBeanstalkInstances($bstlk["name"], $c); //needed for ASG 
                    $asg = getASGInformation(pullASG($resVar), $c2);
                  }

                  if(pullBstlkStatus($resVar2) == "Terminated" || pullBstlkStatus($resVar2) == "Terminating")
                      continue;

                  if(isPaused($asg))
                    $status = "<span class='glyphicon glyphicon-pause'></span> ";
                  else 
                    $status = "";

                  if(!empty($menuApps["application"]["DNS"])){
                    if(strcasecmp(pullELB($resVar2).".", $elb) == 0){
                      $isFoundLiveELB = true;
                      $liveELBColor = "<span class='glyphicon glyphicon-ok'></span> ";
                    } else {
                    $liveELBColor = "";
                    }
                  } else {
                    $liveELBColor = "";
                  }

                  echo '<a href="#" id='.$bstlk["name"].' onclick=\'fetchBstlkDetail("'.$bstlk["id"].'","'.$bstlk["name"].'", "'.$app["name"].'",
                   "'.$app["region"].'", "'.$menuApps["application"]["keys"].'", '.
                   json_encode($configuration["cloner_mandatory_tags"]).');\' class="list-group-item">'.$liveELBColor.$status.$bstlk["name"].'</a>';
                }
              }
              echo '</ul>';
            }
          ?>
          </ul>
      </div>

      <div class="col-md-9">
        <div id="mainDetail">
        <?php
            //check for a force refresh from a particular env
            if(!empty($_GET["a"])){
              echo $EPRefresh;
            }

            //Check if there WAS no live ELB
            if(!$isFoundLiveELB && $isExistDNSField){
              echo "<div class='alert alert-danger'><b>No Live Environment was found</b> to force refresh all cache, click <a href='index.php?b=true'>here</a>.</div>";
            }
          ?>
          <div class="well">
            <h2><span class="glyphicon glyphicon-user text-success"></span>  Welcome to <?php echo $productLongName; ?>!</h2>
            <p>To the left are your listed applications and its respective environments.  Specific categorization settings and display can be modified in the configuration files.</p>
          </div><br/>
        </div>
      </div>

      </div> 

    <!-- /container -->


    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="https://code.jquery.com/jquery-1.10.2.min.js"></script>
    <script src="bootstrap/js/bootstrap.min.js"></script>
    <script>

        var shouldCollapse = true;

        $("a.menuCollapse").click( function() {
          if(window.shouldCollapse){
            var id = $(this).attr("href");
            $(id).collapse("toggle");
          } else {
            var id = $(this).attr("href");
            $(id).collapse("show"); //always show when dns switcher was pressed
          }
        });

        // Collapsed state saving mechanism
        //save off
        $(".collapse").on('hidden.bs.collapse', function(){ //collapsed = true, not collapsed = false
          setCookie($(this).attr("id"), "true", 5);
        });
        $(".collapse").on('shown.bs.collapse', function(){ //collapsed = true, not collapsed = false
          setCookie($(this).attr("id"), "false", 5);
        });
        //read off, we arbitrarily chose hidden
        $(".collapse").each(function(){
          if(getCookie($(this).attr("id")) == "false"){
            $(this).collapse("show");
          }
        });
        //

        function fetchBstlkDetail(bstlkId, bstlkName, appName, region, key, mt){
          var loadGif = "<img src='images/loading.gif' width='80' height='80'> Loading details for "+bstlkName;
          $("#mainDetail").html(loadGif).load("fetch_bstlk_detail.php?c=<?php echo $_GET["c"]; ?>", {"bstlkId": bstlkId, "bstlkname": bstlkName, "appname": appName, "region": region, "key": key, "mt": mt});
        }

        function fetchDNSSwitcher(excl, appName, dns, key){
          window.shouldCollapse = false;
          var loadGif = "<img src='images/loading.gif' width='80' height='80'> Loading DNS Switcher ...";
          $("#mainDetail").html(loadGif).load("dns_switcher.php?c=<?php echo $_GET["c"]; ?>", {"exclusions": excl, "appname": appName, "dns_payload": dns, "key": key});
          //wait seconds then renable the collapse
          sleep(400, function() { window.shouldCollapse = true;});
        }
        // by w3school 
        function setCookie(cname, cvalue, exdays) {
          var d = new Date();
          d.setTime(d.getTime() + (exdays*24*60*60*1000));
          var expires = "expires="+d.toGMTString();
          document.cookie = cname + "=" + cvalue + "; " + expires;
        }
        // by w3school 
        function getCookie(cname) {
          var name = cname + "=";
          var ca = document.cookie.split(';');
          for(var i=0; i<ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(name) == 0) return c.substring(name.length,c.length);
        }

    return "";
}


        function sleep(millis, callback) {
          setTimeout(function()
            { callback(); }
          , millis);
        }       
    </script>
  </body>
</html>

