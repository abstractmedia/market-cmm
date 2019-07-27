<?php

/******************************************************************************
 *  
 *  PROJECT: Flynax Classifieds Software
 *  VERSION: 4.7.2
 *  LICENSE: FL973Z7CTGV5 - http://www.flynax.com/license-agreement.html
 *  PRODUCT: General Classifieds
 *  DOMAIN: market.coachmatchme.com
 *  FILE: SUBSCRIPTIONS.INC.PHP
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

/* ext js action */
if ($_GET['q'] == 'ext') {
    /* system config */
    require_once '../../includes/config.inc.php';
    require_once RL_ADMIN_CONTROL . 'ext_header.inc.php';
    require_once RL_LIBS . 'system.lib.php';

    /* date update */
    if ($_GET['action'] == 'update') {
        $reefless->loadClass('Actions');

        $type = $rlValid->xSql($_GET['type']);
        $field = $rlValid->xSql($_GET['field']);
        $value = $rlValid->xSql(nl2br($_GET['value']));
        $id = $rlValid->xSql($_GET['id']);
        $key = $rlValid->xSql($_GET['key']);

        if ($field == 'Default') {
            $uncheckall = "UPDATE `{db_prefix}subscriptions` SET `Default` = '0' WHERE `ID` != '" . $id . "'";
            $rlDb->query($uncheckall);

            $value = ($value == 'true') ? '1' : '0';
        }

        $updateData = array(
            'fields' => array(
                $field => $value,
            ),
            'where'  => array(
                'ID' => $id,
            ),
        );

        $rlHook->load('apExtSubscriptionUpdate'); // >= v4.4

        $rlActions->updateOne($updateData, 'subscriptions');
        exit;
    }

    /* data read */
    $limit = (int) $_GET['limit'];
    $start = (int) $_GET['start'];
    $sort = $rlValid->xSql($_GET['sort']);
    $sortDir = $rlValid->xSql($_GET['dir']);

    $search_fields = array(
        'username'        => '`T2`.`Username`',
        'subscription_id' => '`Subscription_ID`',
        'account_type'    => '`T2`.`Type`',
        'plan_id'         => '`T1`.`Plan_ID`',
        'gateway_id'      => '`T1`.`Gateway_ID`',
        'search_status'   => '`T1`.`Status`',
        'amount_from'     => '',
        'amount_to'       => '',
        'date_from'       => '',
        'date_to'         => '',
    );

    $sql = "SELECT SQL_CALC_FOUND_ROWS DISTINCT `T1`.*, ";
    $sql .= "IF(`T2`.`Last_name` <> '' AND `T2`.`First_name` <> '', CONCAT(`T2`.`First_name`, ' ', `T2`.`Last_name`), `T2`.`Username`) AS `Full_name`, ";
    $sql .= "`T3`.`Key` AS `Gateway` ";
    $sql .= "FROM `{db_prefix}subscriptions` AS `T1` ";
    $sql .= "LEFT JOIN `{db_prefix}accounts` AS `T2` ON `T1`.`Account_ID` = `T2`.`ID` ";
    $sql .= "LEFT JOIN `{db_prefix}payment_gateways` AS `T3` ON `T1`.`Gateway_ID` = `T3`.`ID` ";
    $sql .= "WHERE `T1`.`Status` <> 'trash' ";

    if ($_GET['search']) {
        foreach ($search_fields as $sf_key => $sf_field) {
            $field = $_GET[$sf_key];
            if (!empty($field)) {
                switch ($sf_key) {
                    case 'amount_from':
                        $amount_from = (int) $_GET['amount_from'];
                        $amount_to = (int) $_GET['amount_to'];
                        if ($amount_to) {
                            $sql .= "AND `T1`.`Total` BETWEEN {$amount_from} AND {$amount_to} ";
                        } else {
                            $sql .= "AND `T1`.`Total` >= {$amount_from} ";
                        }
                        break;
                    case 'amount_to':
                        $amount_from = (int) $_GET['amount_from'];
                        $amount_to = (int) $_GET['amount_to'];
                        if (!$amount_from) {
                            $sql .= "AND `T1`.`Total` =< {$amount_to} ";
                        }
                        break;

                    case 'date_from':
                        $date_from = $rlValid->xSql($_GET['date_from']);
                        $date_to = $rlValid->xSql($_GET['date_to']);
                        if ($date_to) {
                            $sql .= "AND UNIX_TIMESTAMP(`T1`.`Date`) BETWEEN UNIX_TIMESTAMP('{$date_from}') AND UNIX_TIMESTAMP('{$date_to}') ";
                        } else {
                            $sql .= "AND UNIX_TIMESTAMP(`T1`.`Date`) >= UNIX_TIMESTAMP('{$date_from}') ";
                        }
                        break;

                    case 'date_to':
                        $date_from = $rlValid->xSql($_GET['date_from']);
                        $date_to = $rlValid->xSql($_GET['date_to']);

                        if (!$date_from) {
                            $sql .= "AND UNIX_TIMESTAMP(`T1`.`Date`) <= UNIX_TIMESTAMP('{$date_to}') ";
                        }
                        break;

                    case 'plan_id':
                        $plan_details = explode('-', $field);
                        $sql .= "AND {$sf_field} = '{$plan_details[0]}' AND `T1`.`Service` = '{$plan_details[1]}' ";
                        break;

                    default:
                        $sql .= "AND {$sf_field} = '{$field}' ";
                        break;
                }
            }
        }
    }

    if ($sort) {
        switch ($sort) {
            default:
                $sortField = "`T1`.`{$sort}`";
                break;
        }
        $sql .= "ORDER BY {$sortField} {$sortDir} ";
    }

    $sql .= "LIMIT {$start}, {$limit}";

    $rlHook->load('apExtSubscriptionsSql'); // >= v4.4

    $data = $rlDb->getAll($sql);
    $count = $rlDb->getRow("SELECT FOUND_ROWS() AS `count`");

    foreach ($data as $key => $value) {
        $data[$key]['Status'] = $GLOBALS['lang'][$data[$key]['Status']];
        $data[$key]['Service'] = $GLOBALS['lang'][$value['Service']];
        $data[$key]['Gateway'] = $GLOBALS['lang']['payment_gateways+name+' . $value['Gateway']];
    }

    $rlHook->load('apExtSubscriptionsData'); // >= v4.4

    $reefless->loadClass('Json');

    $output['total'] = $count['count'];
    $output['data'] = $data;

    echo $rlJson->encode($output);
}
/* ext js action end */
else {
    /* get account types */
    $reefless->loadClass('Subscription');
    $reefless->loadClass('Account');
    $reefless->loadClass('PaymentGateways', 'admin');

    /* get all languages */
    $allLangs = $GLOBALS['languages'];
    $rlSmarty->assign_by_ref('allLangs', $allLangs);

    // get account types
    $account_types = $rlAccount->getAccountTypes('visitor');
    $rlSmarty->assign_by_ref('account_types', $account_types);

    // get payment gateways
    $payment_gateways = $rlPaymentGateways->getGateways();
    $rlSmarty->assign_by_ref('payment_gateways', $payment_gateways);

    // assing statuses
    $statuses = array('active', 'canceled');
    $rlSmarty->assign_by_ref('statuses', $statuses);

    // get plans
    $plans = $rlSubscription->getAllPlans();
    $rlSmarty->assign_by_ref('services', $plans);

    if (isset($_GET['action'])) {
        if ($_GET['action'] == 'view') {
            $item_id = (int) $_GET['item'];
            $bcAStep = $lang['subscription_details'];

            $subscription_info = $rlSubscription->getSubscriptionDetails($item_id);
            $rlSmarty->assign_by_ref('subscription_info', $subscription_info);
        }
    }

    $rlHook->load('apPhpSubscriptionBottom'); // >= v4.4
}
