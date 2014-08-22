AWS Beanstalk Manager
=======

The AWS Beanstalk Manager `(BMGR)` is an all in one service tool for the Amazon Web Service Elastic Beanstalk.  `BMGR` was motivated through various active routines development teams take in modifying and maintaining the elastic beanstalks.  This tool serves two purposes:  Maintenance/Monitoring work through seamless all in one information access and Utility work through certain automated features that the GUI cannot provide at this time.  

The Utility work consists of three main components:

* DNS Switching between Beanstalks
* Beanstalk Pause/Play functionality
* Cloning Beanstalks

These functionalities will be explained in more details in their respective sections.  

Requirements
======

* PHP >= 5.4  
* AWS SDK - This repo does not include the SDK, it is preferred that you have the latest version that supports beanstalk environment tagging.  Place the `vendor` folder outside of the beanstalk manager folder.  

Installation
======

1. Place all files and folders to the directory of your choice
2. Create a `keys.yaml` file to store your keys (See section on .YAML Configuration for specifics)
3. Create a configuration .yaml file to store resource lookup information (See section .YAML Configuration as well)
4. *Optional* Edit any constants inside `aws_funcs.php` if needed.  Currently, the available ones on AWS is listed which can change in the future.  See section on __aws_funcs Headers__ for more info  

.YAML Settings
======
.YAML stands for [Yet Another Markup Language](http://en.wikipedia.org/wiki/YAML).  The reason I use .YAML is due to its easy and readable formatting.  
Two files will need to be created: `Keys` and `Configuration` file.  There can only be one Keys file that supports multiple keys.  There can be multiple configuration files to switch between for the beanstalk manager.  

Keys
------
The Keys file will follow the specific .YAML Format:  
> __keys__: an array of three associative variables as listed below  
> &nbsp;&nbsp;&nbsp;__environment__: Key namespace or identifier for reference  
> &nbsp;&nbsp;&nbsp;__accesskey__: AWS Developer Access Key  
> &nbsp;&nbsp;&nbsp;__secretkey__: AWS Developer Secret Key  

Example:  
> __keys__:  
> &nbsp;&nbsp;&nbsp;- __environment__: "johnathan"  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__accesskey__: "AWS-ACCESS-KEY-FOR-A-COWORKER-THAT-HAS-NO-WRITE-ACCESS-HAHAHAHA"  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__secretkey__: "AWS-SECRET-KEY-FOR-A-COWORKER-THAT-HAS-NO-WRITE-ACCESS-HAHAHAHA"  
>  
> &nbsp;&nbsp;&nbsp;- __environment__: "carly"  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__accesskey__: "AWS-ACCESS-KEY-FOR-ANOTHER-DEVELOPER-ACCOUNT-WHY-DIDNT-WE-JUST-DO-ONE-ACCOUNT?"  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__secretkey__: "AWS-SECRET-KEY-FOR-ANOTHER-DEVELOPER-ACCOUNT-WHY-DIDNT-WE-JUST-DO-ONE-ACCOUNT?"  

Configuration
-----
The Configuration file will follow the specific .YAML Format:  
> __keys__: Resource pointing to the Key yaml file  
> &nbsp;&nbsp;&nbsp;&nbsp;__keyfile__: File path to the key  
> &nbsp;&nbsp;&nbsp;&nbsp;__default__: Which set of key to use as default or when no `keys` are specified in an application.  
> __menu\_apps__: Top tier menu for applications, in this section, you specify an array of `application` that the beanstalk will fall under    
> &nbsp;&nbsp;__application__: Menu item for application  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__name__: Menu name  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__DNS__: Array of DNS items for dns switching functionality _(optional)_  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__name__: DNS record that is available on the Route53 Amazon Service  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__zid__: Zone ID for the DNS as stated on Route53  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__beanstalk\_apps__: an array of beanstalk resources information for bmgr to search for  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__name__: Beanstalk environment name  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__region__: Beanstalk environment region  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__keys__: the set of key to use in `keys` file.  This allows for multiple account support  
> __dns\_exclusions__: List of keywords to search for when DNS switching so they cannot be switched to.  This is useful for those new interns that are click happy...(_optional_)    
>__cloner\_mandatory\_tags__: A list of tags that a cloned beanstalk must have (_optional_)  

Example:  
> __keys__:  
> &nbsp;&nbsp;&nbsp;&nbsp;__keyfile__: keys.yaml  
> &nbsp;&nbsp;&nbsp;&nbsp;__default__: jonathan  
> __menu\_apps__:  
> &nbsp;&nbsp;- __application__:  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__name__: Skiers Product Design  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__DNS__:  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__beanstalk\_apps__:  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-  __name__: Tom Wallisch Pro series  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__region__: us-east-1  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__keys__: carly  
> &nbsp;&nbsp;- __application__:  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__name__: Mountain Safety Machine  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__DNS__:  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- __name__: mountainsafety.com  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__zid__: SK1TH3E45TC045T  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- __name__: bigbcmountains.com  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__zid__: SK1TH3W35TC045T  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__beanstalk\_apps__:  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-  __name__: whistler  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__region__: us-west-1  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-  __name__: vermont  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__region__: us-east-1  
> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;__keys__: developers  
> __dns\_exclusions__:  
> &nbsp;&nbsp;- COMPETITION  
> __cloner\_mandatory\_tags__:  
> &nbsp;&nbsp;- "ski:name"  
> &nbsp;&nbsp;- "ski:owner"  

aws_funcs Headers
======
The `aws_funcs.php` file stores all important functions critical to the bmgr.  There are a few hardcoded constants that the bmgr accesses to provide users a set of fixed options whether that is cloning, monitoring beanstalks, etc.  

Since there is no easy way to retrieve these constants from Amazon, it's useful to mention this here in case Amazon adds new options in the future.  

* `AVAILABLE_BEANSTALK_CLONE_TIERS` - a list of available beanstalk tiers for cloning.  These are a fixed set when it comes to creating a beanstalk and comprised of three parts compacted into one space delimited string.  The three parts are: `TYPE` `NAME` `VERSION`  
* `AVAILABLE_ENVIRONMENT_TYPES` - Specify the environment types available when cloning.
* `AVAILABLE_STATISTIC_LIST` - A list of available statistics to be picked for CloudWatch metrics when cloning.
* `AVAILABLE_UNIT_LIST` - A list of available statistics units to be picked for CW metrics when cloning.
* `AVAILABLE_MEASURENAME_LIST` - A list of available statistics measurements to be picked for CW Metrics when cloning.
* `CONFIGURATION_EXCLUSION_LIST` - Configuration Option settings to be excluded when viewing the beanstalk through main tabs.  (In general, `EnvironmentVariables` should be left out as it is a concatenated version of many other `Container` parameters that are already shown)
* `CLONER_EXCLUSION_LIST` - A list of editable/displayable attributes to exclude in the cloner (In general, `EnvironmentVariables` should be left out as it is a concatenated version of many other `Container` parameters that are already shown).

DNS Switching
======
The Beanstalk manager allows you to switch DNS between certain environments easily.  The DNS Switching is enabled when the `DNS` field is filled in the yaml file.  It can be accessed with the shuffle icon button beside each custom application.  The DNS Switcher accesses the Amazon route53 host and changes the respective zone id to the new beanstalk.  This feature is important for individuals and businesses looking for fast ways to deploy new code on their website while still retaining an older revision incase of errors.  In each refresh of the index page, BMGR would create a checkmark (on the left tree nav) beside the current live environment that the DNS specified is pointing to.  

Beanstalk Pause/Play Functionality
======
The beanstalk pause play functionality can be accessed from the beanstalk detail page.  Currently, this feature is not supported by Amazon console and requires the SDK or CLI to perform this feature.  The pause button effectively reduces the number of instances allowed in the autoscaling group to zero and thus the environment is paused.  This feature is useful in reducing cost for terminating unused EC2 computations while still retaining settings and configurations of a beanstalk.  The play button should retain the original Minimum and maximum instances that the AutoScaling Group had when used to resume the beanstalk.  

Beanstalk Cloning
======
The beanstalk Clone feature can clone a beanstalk almost identically.  This feature is ideal for individuals and businesses looking to deploy their code efficiently on more than one environment whether to further modify the code, analyze components or create a stable version for later use.  The cloner has an option to tag the beanstalk environment, a feature that was rolled out in the april AWS update.  You can specify mandatory tags in the .yaml config file under `cloner_mandatory_tags`.  

There are a few important aspects of the cloner to note for:  

* AppSource, or the hosted code, will not contain the old deployed code when cloned.  This is to ensure that there are no interference with the storage and access of the parent beanstalk.  The new deployed code will be a sample app.  
* Security Groups are auto generated if one is not specified when creating a beanstalk.  Since Security groups have to be unique, the auto generated security groups parameter will not be passed to the cloner.  The developer can either fill in his/her own group or leave it empty for AWS to auto-generate.
* Beanstalk name isn't passed along to the cloner.  The developer will need to specify
* The list of available beanstalk platforms is automatically retrieved from AWS.  On the chance that the parent beanstalk (the one to be cloned) runs a platform no longer on the AWS list, a message will appear to warn of this and the developer will have to specify a platform from the available list dropdown.


Creator: Kevin Pei  
Copyright 2014  
[MIT License](https://tldrlegal.com/license/mit-license#summary)