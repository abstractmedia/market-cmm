<?php

/******************************************************************************
 *  
 *  PROJECT: Flynax Classifieds Software
 *  VERSION: 4.7.2
 *  LICENSE: FL973Z7CTGV5 - http://www.flynax.com/license-agreement.html
 *  PRODUCT: General Classifieds
 *  DOMAIN: market.coachmatchme.com
 *  FILE: ADMIN.PHP
 *  
 *  The software is a commercial product delivered under single, non-exclusive,
 *  non-transferable license for one domain or IP address. Therefore distribution,
 *  sale or transfer of the file in whole or in part without permission of Flynax
 *  respective owners is considered to be illegal and breach of Flynax License End
 *  User Agreement.
 *  
 *  You are not allowed to remove this information from the file without permission
 *  of Flynax respective owners.
 *  
 *  Flynax Classifieds Software 2019 | All copyrights reserved.
 *  
 *  http://www.flynax.com/
 ******************************************************************************/

/* load configs */
include_once( dirname(__FILE__) . "/../../includes/config.inc.php");

/* system controller */
require_once( RL_ADMIN_CONTROL . 'admin.control.inc.php' );

/* load system configurations */
$config = $rlConfig -> allConfig();
$rlSmarty -> assign_by_ref('config', $config);
$GLOBALS['config'] = $config;

$reefless -> loadClass('Account');

$add_photo_path = $rlDb -> getOne('Path', "`Key` = 'add_photo'", 'pages');
$listing_id = $_SESSION['admin_transfer']['listing_id'];
$upload_controller = 'admin.php';

if ( !$listing_id )
    exit;

if ( !$rlAdmin -> isLogin() )
    exit;

$reefless -> loadClass('Json');

include_once(RL_LIBS .'upload'. RL_DS . 'upload.php');
