<?php

/******************************************************************************
 *  
 *  PROJECT: Flynax Classifieds Software
 *  VERSION: 4.7.2
 *  LICENSE: FL973Z7CTGV5 - http://www.flynax.com/license-agreement.html
 *  PRODUCT: General Classifieds
 *  DOMAIN: market.coachmatchme.com
 *  FILE: REQUEST.AJAX.PHP
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

use Flynax\Utils\Category;
use Flynax\Utils\File;
use Flynax\Utils\ListingMedia;
use Flynax\Utils\Profile;
use Flynax\Utils\Valid;

header('Access-Control-Allow-Origin: *');

define('AJAX_FILE', true);

require_once 'includes/config.inc.php';
require_once RL_INC . 'control.inc.php';

$rlHook->load('init');

// set language
$request_lang = @$_REQUEST['lang'] ?: $config['lang'];
$rlValid->sql($request_lang);

$languages = $rlLang->getLanguagesList();
$rlLang->defineLanguage($request_lang);
$rlLang->modifyLanguagesList($languages);

// load system libs
require_once RL_LIBS . 'system.lib.php';

// set timezone
$reefless->setTimeZone();
$reefless->setLocalization();

// load main types classes
$reefless->loadClass('ListingTypes', null, false, true);
$reefless->loadClass('AccountTypes', null, false, true);

// get page paths
$reefless->loadClass('Navigator');
$pages = $rlNavigator->getAllPages();

// load classes
$reefless->loadClass('Account');
$reefless->loadClass('MembershipPlan');

// define seo base
$seo_base = RL_URL_HOME;
if ($config['lang'] != RL_LANG_CODE && $config['mod_rewrite']) {
    $seo_base .= RL_LANG_CODE . '/';
}
if (!$config['mod_rewrite']) {
    $seo_base .= 'index.php';
}

$rlHook->load('seoBase');
define('SEO_BASE', $seo_base);

$rlSmarty->registerFunctions();

// validate data
$request_mode = $rlValid->xSql($_REQUEST['mode']);
$request_item = $rlValid->xSql($_REQUEST['item']);

// out variable will be printed as response
$out = array();

/**
 * @since 4.6.0
 */
$rlHook->load('requestAjaxBeforeSwitchCase', $request_mode, $request_item, $request_lang);

// do task by requested mode
switch ($request_mode) {
    case 'listing':
        $lang = $rlLang->getLangBySide('frontEnd', $request_lang);

        $request_type = $rlValid->xSql($_REQUEST['type']);
        $request_field = $rlValid->xSql($_REQUEST['field']);

        $reefless->loadClass('Common');
        $reefless->loadClass('Listings');
        $reefless->loadClass('Search');

        $data['keyword_search'] = $request_item;
        $fields['keyword_search'] = array(
            'Type' => 'text',
        );

        $rlSearch->fields = $fields;
        $listings = $rlSearch->search($data, false, false, 20);

        foreach ($listings as $listing) {
            $out[] = array(
                'listing_title' => $listing['listing_title'],
                'Category_name' => $lang['categories+name+' . $listing['Cat_key']],
                'Category_path' => $reefless->getCategoryUrl($listing['Category_ID']),
                'Listing_path'  => $reefless->url('listing', $listing),
            );
        }
        unset($listings);

        break;

    case 'photo':
        $pattern = '/_sold_[a-z]{2}/';
        if ((bool) preg_match($pattern, $request_item)) {
            $request_item = preg_replace($pattern, '', $request_item);
        }

        $out = RL_FILES_URL . $rlDb->getOne('Photo', "`Thumbnail` = '{$request_item}'", 'listing_photos');
        break;

    case 'getListingsByCoordinates':
        require_once RL_ROOT . 'templates' . RL_DS . $config['template'] . RL_DS . 'settings.tpl.php';

        $lang = $rlLang->getLangBySide('frontEnd', $request_lang);

        $type = $rlValid->xSql($_REQUEST['type']);
        $start = (int) $_REQUEST['start'];
        $coordinates = array(
            'centerLat'    => (double) $_REQUEST['centerLat'],
            'centerLng'    => (double) $_REQUEST['centerLng'],
            'northEastLat' => (double) $_REQUEST['northEastLat'],
            'northEastLng' => (double) $_REQUEST['northEastLng'],
            'southWestLat' => (double) $_REQUEST['southWestLat'],
            'southWestLng' => (double) $_REQUEST['southWestLng'],
        );
        $form = $_REQUEST['form'];
        $home_page = (bool) $_REQUEST['home_page'] ? true : false;

        $group_search = $_REQUEST['group'] ? true : false;
        $group_lat = $rlValid->xSql($_REQUEST['lat']);
        $group_lng = $rlValid->xSql($_REQUEST['lng']);

        $reefless->loadClass('Listings');
        $out = $rlListings->getListingsByLatLng($type, $start, $coordinates, $form, $home_page, $group_search, $group_lat, $group_lng);

        break;

    case 'getCategoriesByType':
        $lang = $rlLang->getLangBySide('frontEnd', $request_lang);
        $type_key = $rlValid->xSql($_REQUEST['type']);
        $category_id = (int) $_REQUEST['id'];

        $reefless->loadClass('Categories');
        $out = array_values($rlCategories->getCatTree($category_id, $type_key));
        break;

    case 'category':
        $sql = "SELECT `T1`.`ID`, `T1`.`Path`, `T1`.`Count`, `T1`.`Type`, `T2`.`Value` AS `name`, `T3`.`Cat_postfix` ";
        $sql .= "FROM `{db_prefix}categories` AS `T1` ";
        $sql .= "LEFT JOIN `{db_prefix}lang_keys` AS `T2` ON CONCAT('categories+name+', `T1`.`Key`) = `T2`.`Key` AND ";
        $sql .= "`T2`.`Code` = '{$request_lang}' AND `T2`.`Key` LIKE 'categories+name+%' ";
        $sql .= "LEFT JOIN `{db_prefix}listing_types` AS `T3` ON `T1`.`Type` = `T3`.`Key` ";
        $sql .= "WHERE `T1`.`Status` = 'active' AND `T3`.`Status` = 'active' AND ";
        if ($request_item == 'rest') {
            $sql .= "`T2`.`Value` RLIKE '^[0-9]' ";
        } else {
            $sql .= "`T2`.`Value` LIKE BINARY '{$request_item}%' ";
        }
        $sql .= "GROUP BY `T1`.`ID` ";
        $sql .= "ORDER BY `T1`.`Count` DESC, `Value` ASC ";
        $sql .= "LIMIT 50";

        $out = $rlDb->getAll($sql);

        foreach ($out as &$category) {
            $category['Cat_type_page'] = $pages[$rlListingTypes->types[$category['Type']]['Page_key']];
        }

        break;

    case 'changeListingStatus':
        $account_info = $_SESSION['account'];
        $reefless->loadClass('Actions');
        $reefless->loadClass('Listings');
        $total = $rlListings->isListingOver($request_item);
        if ($total && $_REQUEST['value'] == 'active') {
            $out = array('status' => 'failure', 'message_text' => str_replace('{count}', $total, $lang['not_available_free_cells']));
        } else {
            $result = $rlListings->changeListingStatus($request_item, $_REQUEST['value']);
            $out = $result ? array('status' => 'ok', 'message_text' => $lang['status_changed_ok']) : array('status' => 'failure', 'message_text' => $lang['status_changed_fail']);
        }

        break;

    case 'changeListingFeaturedStatus':
        $account_info = $_SESSION['account'];
        $reefless->loadClass('Listings');
        $reefless->loadClass('Actions');
        $membership_plan = $rlDb->fetch('*', array('ID' => $account_info['Plan_ID']), null, 1, 'membership_plans', 'row');
        if ($total = $rlListings->isListingOverByType($request_item, $_REQUEST['value'])) {
            $out = array('status' => 'failure', 'message_text' => str_replace('{count}', $total, $lang['not_available_free_cells_' . $_REQUEST['value']]));
        } else {
            $result = $rlListings->changeFeaturedStatus($request_item, $_REQUEST['value']);
            $out = $result ? array('status' => 'ok', 'message_text' => $lang['type_changed_ok']) : array('status' => 'failure', 'message_text' => $lang['type_changed_fail']);
        }

        break;

    case 'contactOwner':
        $lang = $rlLang->getLangBySide('frontEnd', $request_lang);

        $name = $rlValid->xSql($_REQUEST['name']);
        $email = $rlValid->xSql($_REQUEST['email']);
        $phone = $rlValid->xSql($_REQUEST['phone']);
        $message = $rlValid->xSql($_REQUEST['message']);
        $security_code = $rlValid->xSql($_REQUEST['security_code']);
        $listing_id = (int) $_REQUEST['listing_id'];
        $account_id = (int) $_REQUEST['account_id'];
        $box_index = (int) $_REQUEST['box_index'];

        $account_info = $_SESSION['account'];

        $reefless->loadClass('Message');
        $out = $rlMessage->contactOwner($name, $email, $phone, $message, $security_code, $listing_id, $box_index, $account_id);

        break;

    case 'getCategoryLevel':
        $lang = $rlLang->getLangBySide('frontEnd', $request_lang);

        $categories = Category::getCategories($_REQUEST['type'], $_REQUEST['parent_id'], 1, $_REQUEST['account_id'], $_REQUEST['from_db']);
        $out = array(
            'status'  => 'OK',
            'results' => &$categories,
            'count'   => count($categories),
        );

        break;

    case 'addUserCategory':
        $errors = [];

        if ($user_category_id = Category::addUserCategory($_REQUEST['parent_id'], $_REQUEST['name'], $_REQUEST['account_id'], $errors)) {
            $out = array(
                'status'  => 'OK',
                'results' => $user_category_id,
            );
        } else {
            $out = array(
                'status'  => 'ERROR',
                'message' => $errors,
            );
        }

        break;

    case 'pictureUpload':
        $out = (new Flynax\Classes\ListingPictureUpload)->init();
        break;

    case 'mediaDelete':
        $account_info = $_SESSION['account']; // TODO
        $out['status'] = ListingMedia::delete($_REQUEST['listing_id'], $_REQUEST['media_id'], $account_info)
        ? 'OK'
        : 'ERROR';

        break;

    case 'mediaChangeDescription':
        $account_info = $_SESSION['account']; // TODO
        $out['status'] = ListingMedia::manageDescription($_REQUEST['listing_id'], $_REQUEST['media_id'], $_REQUEST['description'], $account_info)
        ? 'OK'
        : 'ERROR';

        break;

    case 'mediaSetOrder':
        $account_info = $_SESSION['account']; // TODO
        $out['status'] = ListingMedia::reorder($_REQUEST['listing_id'], $_REQUEST['data'], $account_info)
        ? 'OK'
        : 'ERROR';

        break;

    case 'mediaAddYouTube':
        $account_info = $_SESSION['account']; // TODO

        // Define the instance class by referrer controller
        $class_name = $_REQUEST['controller'] == 'edit_listing'
        ? 'Flynax\Classes\EditListing'
        : 'Flynax\Classes\AddListing';

        // Get/create instance
        $instance = $class_name::getInstance();
        $plan_info = $instance->plans[$instance->planID];

        if ($results = ListingMedia::addYouTube(
            $_REQUEST['listing_id'],
            $_REQUEST['link'],
            $account_info,
            $plan_info,
            $_REQUEST['position']
        )) {
            $out = array(
                'status'  => 'OK',
                'results' => $results,
            );
        } else {
            $out['status'] = 'ERROR';
        }

        break;

    case 'pictureCrop':
        $account_info = $_SESSION['account']; // TODO
        if ($results = ListingMedia::cropPicture($_REQUEST['listing_id'], $_REQUEST['media_id'], $_REQUEST['data'], $account_info)) {
            $out = array(
                'status'  => 'OK',
                'results' => $results,
            );
        } else {
            $out['status'] = 'ERROR';
        }

        break;

    case 'manageListing':
        require_once RL_ROOT . 'templates' . RL_DS . $config['template'] . RL_DS . 'settings.tpl.php';

        $lang = $rlLang->getLangBySide('frontEnd', $request_lang);

        $account_info = $_SESSION['account']; // TODO

        // Define the instance class by referrer controller
        $class_name = $_REQUEST['controller'] == 'edit_listing'
        ? 'Flynax\Classes\EditListing'
        : 'Flynax\Classes\AddListing';

        // Get/create instance
        $instance = $class_name::getInstance();
        $results = $instance->ajaxAction($_REQUEST['action'], $_REQUEST['data'], $account_info);

        if ($results !== false) {
            // Save instance
            $class_name::saveInstance($instance);

            $out = array(
                'status'  => 'OK',
                'results' => $results,
            );
        } else {
            $out['status'] = 'ERROR';
        }

        break;

    case 'loadPaymentForm':
        $lang = $rlLang->getLangBySide('frontEnd', $request_lang);
        $rlSmarty->assign_by_ref('lang', $lang);

        $reefless->loadClass('Payment');
        $gateway = $rlValid->xSql($_REQUEST['gateway']);
        $form = $_REQUEST['form'] ? $_REQUEST['form'] : 'form.tpl';
        $out = array(
            'status' => 'OK',
            'html'   => $rlPayment->loadPaymentForm($gateway, $form),
        );
        break;

    case 'ajaxFavorite':
        $id = (int) $_REQUEST['id'];
        $delete_action = (bool) $_REQUEST['delete'];
        $account_info = $_SESSION['account'];

        $rlListings->ajaxFavorite($id, $delete_action);
        break;

    case 'deleteTmpFile':
        if ($results = File::removeTmpFile($_REQUEST['field'], $_REQUEST['parent'])) {
            $out = array(
                'status'  => 'OK',
                'results' => $results,
            );
        } else {
            $out['status'] = 'ERROR';
        }
        break;

    case 'deleteFile':
        $data = $_REQUEST;

        if ($result = File::removeFile($data['field'], $data['value'], $data['type'], (int) $account_info['ID'])) {
            $out = array('status' => 'OK', 'results' => $result);
        } else {
            $out['status'] = 'ERROR';
        }
        break;

    case 'profilePictureUpload':
        $out = (new Flynax\Classes\ProfileThumbnailUpload)->init();
        break;

    case 'profileThumbnailCrop':
        $account_info = $_SESSION['account']; // TODO
        if ($results = Profile::cropThumbnail($_REQUEST['data'], $account_info)) {
            $out = array(
                'status'  => 'OK',
                'results' => $results,
            );
        } else {
            $out['status'] = 'ERROR';
        }
        break;

    case 'profileThumbnailDelete':
        $account_info = $_SESSION['account']; // TODO
        if ($results = Profile::deleteThumbnail($account_info['ID'])) {
            $out = array(
                'status'  => 'OK',
                'results' => $results,
            );
        } else {
            $out['status'] = 'ERROR';
        }
        break;

    case 'removeAccount':
        $reefless->loadClass('Admin', 'admin');

        $lang     = $rlLang->getLangBySide('frontEnd', $request_lang);
        $id       = intval($_SESSION['account']['ID'] ?: $_REQUEST['id']);
        $password = Valid::escape($_REQUEST['pass']);
        $hash     = Valid::escape($_REQUEST['hash']);
        $result   = false;
        $message  = '';

        if ($password && $_SESSION['account'] && $id) {
            $db_pass = $rlDb->fetch(array('Password'), array('ID' => $id), null, null, 'accounts', 'row');

            if (FLSecurity::verifyPassword($password, $db_pass['Password'])
                && $rlAdmin->deleteAccountDetails($id, null, true)
            ) {
                $result = true;
            } else {
                $message = $lang['notice_pass_bad'];
            }
        } elseif ($id && $hash) {
            if ($rlDb->getOne('Loc_address', "`ID` = {$id}", 'accounts') == md5(base64_decode($hash)) 
                && $rlAdmin->deleteAccountDetails($id, null, true)
            ) {
                $result = true;
            }
        }

        if ($result === true) {
            $reefless->loadClass('Notice');
            $rlNotice->saveNotice($lang['remote_delete_account_removed']);

            $out = array('status' => 'OK', 'redirect' => $reefless->getPageUrl('home'));
        } else {
            $out = array('status' => 'ERROR', 'message' => $message);
        }
        break;
}

// ajax request hook
$rlHook->load('ajaxRequest', $out, $request_mode, $request_item, $request_lang);

if (!empty($out)) {
    $reefless->loadClass('Json');
    echo $rlJson->encode($out);
} else {
    echo null;
}
