<?php

/******************************************************************************
 *  
 *  PROJECT: Flynax Classifieds Software
 *  VERSION: 4.7.2
 *  LICENSE: FL973Z7CTGV5 - http://www.flynax.com/license-agreement.html
 *  PRODUCT: General Classifieds
 *  DOMAIN: market.coachmatchme.com
 *  FILE: CATEGORY.PHP
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

namespace Flynax\Utils;

/**
 * @since 4.6.0
 */
class Category
{
    /**
     * Get category by ID or path
     *
     * @param  int    $id   - category ID
     * @param  string $path - category path
     * @return array        - categort data
     */
    public static function getCategory($id = null, $path = null)
    {
        if (!$id && !$path) {
            return false;
        }

        // validate data
        $id = (int) $id;
        Valid::escape($path, true);

        // build condition
        if ($id) {
            $where['ID'] = $id;
        } elseif ($path) {
            $where['Path'] = trim($path, '/');
        }

        // get category
        $category = $GLOBALS['rlDb']->fetch(
            array('ID', 'Parent_ID', 'Parent_IDs', 'Path', 'Key', 'Count', 'Lock', 'Type', 'Level', 'Add'),
            $where,
            defined('REALM') ? "AND `Status` <> 'trash'" : "AND `Status` = 'active'",
            null, 'categories', 'row');

        if (empty($category)) {
            return false;
        }

        // set names
        $category = $GLOBALS['rlLang']->replaceLangKeys($category, 'categories', array('name', 'title', 'des', 'meta_description', 'meta_keywords', 'h1'));

        return $category;
    }

    /**
     * Get categories by type and parent ID
     *
     * @param  boolean - listing type key
     * @param  integer - parent category id to get it's child
     * @param  integer - include subcategories:
     *                      0 - doesn't include
     *                      1 - include flag only
     *                      2 - include full data
     * @param  mixed   - include user categories by user id or false
     * @param  boolean - get data from DB to avoid empty categories caching
     * @return array   - categories data
     */
    public static function getCategories(
        $type = false,
        $parent_id = 0,
        $include_sub_categories = 0,
        $include_user_categories = false,
        $from_db = false
    ) {
        // validate data
        $parent = (int) $parent;
        Valid::escape($type, true);

        if (!$type) {
            $GLOBALS['rlDebug']->logger(__METHOD__ . '() failed, no "type" parameter passed');
            return false;
        }

        // fields to fetch
        $fields = array('ID', 'Path', 'Count', 'Key', 'Type', 'Lock', 'Add', 'Add_sub');

        $GLOBALS['rlHook']->load(
            'getCategoryLevelFields',
            $fields,
            $type,
            $parent_id,
            $include_sub_categories,
            $include_user_categories
        );

        // fetch type info
        $type_info = $GLOBALS['rlListingTypes']->types[$type];

        // disable user category submition
        if (!$type_info['Cat_custom_adding']) {
            unset($fields[array_search('Add', $fields)]);
        }

        // get from cache
        if ($GLOBALS['config']['cache'] && REALM != 'admin' && !$from_db) {
            $categories = array_values(
                $GLOBALS['rlCache']->get(
                    'cache_categories_by_parent',
                    $parent_id,
                    $type_info
                )
            );

            // include sub-categories mode
            if ($include_sub_categories > 0) {
                $fields[] = 'sub_categories';
                $fields[] = 'sub_categories_calc';
            }

            // remove unnecessary data
            foreach ($categories as &$category) {
                foreach ($category as $key => &$value) {
                    if (!in_array($key, $fields)) {
                        unset($category[$key]);
                    }
                }

                if ($include_sub_categories === 1) {
                    $category['sub_categories'] = count($category['sub_categories'])
                    ? true
                    : false;
                }
            }
        }
        // get from db
        else {
            $select = $fields;
            $where = array(
                'Status'    => 'active',
                'Parent_ID' => $parent_id,
                'Type'      => $type,
            );

            if (!$from_db && $type_info['Cat_hide_empty']) {
                $add_where = " AND `Count` > 0";
            }

            $categories = $GLOBALS['rlDb']->fetch(
                $select,
                $where,
                $add_where . "ORDER BY `Position`",
                null,
                'categories'
            );

            // include sub-categories mode
            if ($include_sub_categories > 0) {
                foreach ($categories as &$category) {
                    $sub_where = $where;
                    $sub_where['Parent_ID'] = $category['ID'];
                    $sub_categories = $GLOBALS['rlDb']->fetch(
                        $select,
                        $sub_where,
                        $add_where . "ORDER BY `Position`",
                        null,
                        'categories'
                    );

                    $category['sub_categories_calc'] = count($sub_categories);
                    $category['sub_categories'] = $include_sub_categories === 1
                    ? (bool) $category['sub_categories_calc']
                    : $sub_categories;
                }
            }
        }

        // add names
        $categories = $GLOBALS['rlLang']->replaceLangKeys(
            $categories,
            'categories',
            array('name')
        );

        // add user categories
        if ($include_user_categories !== false) {
            $include_user_categories = (int) $include_user_categories;

            $where = array(
                'Parent_ID'    => $parent_id,
                'Account_ID'   => $include_user_categories ?: '',
                'Session_hash' => $include_user_categories ? '' : md5(session_id()),
            );

            if ($user_categories =
                $GLOBALS['rlDb']->fetch(
                    array('ID', "Name` AS `name`, 1 AS `tmp`, CONCAT('user-category-', `ID`) AS `Path"),
                    $where,
                    "AND `Status` <> 'trash' ORDER BY `ID`",
                    null, 'tmp_categories')) {
                $categories = array_merge($categories, $user_categories);
            }
        }

        // sort by names
        if ($type_info['Cat_order_type'] == 'alphabetic') {
            $GLOBALS['reefless']->rlArraySort($categories, 'name');
        }

        return $categories;
    }

    /**
     * Build submit listing form by requested category ID
     *
     * @since 4.7.1 - $parent_ids parameter added
     *
     * @param  int|array - requested category ID as integer or category data as array 
     *                 (ID and Parent_IDs) indexes are required
     * @param  array     - category related listing type data
     * @param  array     - fields array to append fields data in
     * @return array     - form data
     */
    public static function buildForm($category, &$listing_type, &$fields_list = array())
    {
        global $rlCache, $config, $rlHook, $lang, $rlDb, $languages;

        // ignored field keys
        $ignore_fields = array();
        if (!$config['address_on_map']) {
            array_push($ignore_fields, 'account_address_on_map');
        }

        $fields_list = array();
        $category_id = (int) (isset($category['ID']) ? $category['ID'] : $category);
        $parent_ids  = isset($category['Parent_IDs']) ? $category['Parent_IDs'] : null;

        // get from cache
        if ($config['cache']) {
            $form = $rlCache->get('cache_submit_forms', $category_id, $listing_type, $parent_ids);

            foreach ($form as $group_key => &$group) {
                foreach ($group['Fields'] as $field_key => &$field) {
                    // assign name
                    $field['name'] = $lang['listing_fields+name+' . $field['Key']];

                    // assign fields data to external object variable
                    $fields_list[] = $field;

                    // remove 'address on map' field if related option disabled
                    if ($field['Key'] == 'account_address_on_map' && !$config['address_on_map']) {
                        unset($form[$group_key]['Fields'][$field_key]);
                    }

                    // sort items
                    if ($field['Condition'] != 'years' && is_array($field['Values']) && RL_LANG_CODE != $config['lang'] && $field['Condition']) {
                        $order = $GLOBALS['data_formats'][$field['Condition']]['Order_type']
                        ? $GLOBALS['data_formats'][$field['Condition']]['Order_type']
                        : $rlDb->getOne('Order_type', "`Key` = '{$field['Condition']}'", "data_formats");

                        if ($order == 'alphabetic') {
                            foreach ($field['Values'] as $field_item_key => &$field_item) {
                                $field_item['name'] = $lang['data_formats+name+' . $field_item['Key']];
                            }

                            Util::arraySort($field['Values'], 'name');
                        }
                    }
                }
            }
        }
        // get from db
        else {
            $form = self::getFormRelations($category_id, $listing_type);

            foreach ($form as $key => &$value) {
                if ($value['Fields']) {
                    $sql = "SELECT *, FIND_IN_SET(`ID`, '{$value['Fields']}') AS `Order`, ";
                    $sql .= "CONCAT('listing_fields+name+', `Key`) AS `pName`, CONCAT('listing_fields+description+', `Key`) AS `pDescription`, ";
                    $sql .= "CONCAT('listing_fields+default+', `Key`) AS `pDefault` ";
                    $sql .= "FROM `{db_prefix}listing_fields` ";
                    $sql .= "WHERE FIND_IN_SET(`ID`, '{$value['Fields']}' ) > 0 AND `Status` = 'active' ";
                    $sql .= $ignore_fields
                    ? "AND `Key` NOT IN ('" . implode("','", $ignore_fields) . "') "
                    : '';
                    $sql .= "ORDER BY `Order`";

                    $rlHook->load('buildListingFormSql', $sql, $value);

                    $fields = $rlDb->getAll($sql, array('Key'));

                    if (empty($fields)) {
                        unset($form[$key]);
                    } else {
                        $value['Fields'] = $GLOBALS['rlCommon']->fieldValuesAdaptation($fields, 'listing_fields', $listing_type);

                        // assign name
                        foreach ($value['Fields'] as &$field) {
                            $field['name'] = $lang['listing_fields+name+' . $field['Key']];
                        }
                    }

                    // assign fields data to external object variable
                    if (isset($fields_list)) {
                        $fields_list = array_merge($fields_list, $value['Fields']);
                    }

                    unset($fields);
                } else {
                    $form[$key]['Fields'] = false;
                }
            }
        }

        // Adapt values of fields in form
        foreach ($form as $group_key => &$group) {
            foreach ($group['Fields'] as $field_key => &$field) {
                // Add default values for text fields with Multilingual mode
                if ($field['Type'] == 'text' && $field['Multilingual'] && $field['Default'] && count($languages) > 1) {
                    foreach ($languages as $language) {
                        $field['pMultiDefault'][$language['Code']] = $rlDb->getOne(
                            'Value',
                            "`Key` = '{$field['pDefault']}' AND `Code` = '{$language['Code']}'",
                            'lang_keys'
                        );
                    }
                }
            }
        }

        return $form;
    }

    /**
     * Get Form Relations from db by requested category ID
     *
     * @since 4.7.1 - $no_recursive (second param) removed
     *              - $listing_type added
     *
     * @param  int $category_id    - requested category ID
     * @param  array $listing_type - category related listing type data
     * @return array               - form data
     */
    public static function getFormRelations($category_id = 0, $listing_type = [])
    {
        global $rlDb;

        $category_id = (int) $category_id;

        if (!$category_id || !$listing_type) {
            return [];
        }

        if ($listing_type['Cat_general_only'] && $category_id != $listing_type['Cat_general_cat']) {
            return self::getFormRelations($listing_type['Cat_general_cat'], $listing_type);
        }

        $sql = "SELECT `T1`.`Group_ID`, `T1`.`ID`, `T1`.`Category_ID`, `T2`.`Key`, `T1`.`Fields`, `T2`.`Display`, ";
        $sql .= "CONCAT('listing_groups+name+', `T2`.`Key`) AS `pName`, `T2`.`ID` AS `Group` ";
        $sql .= "FROM `{db_prefix}listing_relations` AS `T1` ";
        $sql .= "LEFT JOIN `{db_prefix}listing_groups` AS `T2` ON `T1`.`Group_ID` = `T2`.`ID` ";
        $sql .= "WHERE `T1`.`Category_ID` = '{$category_id}' AND (`T1`.`Group_ID` = '' OR `T2`.`Status` = 'active') ";
        $sql .= "ORDER BY `T1`.`Position`";

        $form = $rlDb->getAll($sql);

        // prepare form
        if ($form) {
            $count = 1;
            if ($form) {
                foreach ($form as &$item) {
                    $index = $item['Key'] ? $item['Key'] : 'nogroup_' . $count;
                    $tmp_form[$index] = $item;
                    $count++;
                }
                $form = $tmp_form;
                unset($tmp_form);

                return $form;
            }
        }
        // check in parent
        else {
            if ($parent = $rlDb->getOne('Parent_ID', "`ID` = '{$category_id}'", 'categories')) {
                $form = self::getFormRelations($parent, $listing_type);
            }

            if (!$form && $listing_type['Cat_general_cat']) {
                $form = self::getFormRelations($listing_type['Cat_general_cat'], $listing_type);
            }

            return $form;
        }
    }

    /**
     * Add user category
     *
     * @param integer - parent category id
     * @param string  - user category name
     * @param integer - account id to assign new category to
     * @param array   - errors
     */
    public static function addUserCategory($parent_id = 0, $name = '', $account_id = 0, &$errors)
    {
        global $rlDb;

        // validate data
        $parent_id = (int) $parent_id;
        $name = trim($name, ' "\'');
        Valid::escape($name, true);

        if (!$parent_id || !$name) {
            $GLOBALS['rlDebug']->logger(__METHOD__ . '() failed, no "parent_id" or "name" parameter passed');
            return false;
        }

        // TODO brute force protection required

        // check for common category existence
        $sql = "SELECT `T1`.`ID` FROM `{db_prefix}categories` AS `T1` ";
        $sql .= "LEFT JOIN `{db_prefix}lang_keys` AS `T2` ON CONCAT('categories+name+', `T1`.`Key`) = `T2`.`key` ";
        $sql .= "WHERE LCASE(`T2`.`Value`) = '" . strtolower($name) . "' AND `Parent_ID` = '{$parent_id}' LIMIT 1";
        $category_exist = $rlDb->getRow($sql);

        // check for user category existence
        $user_category_exist = $rlDb->getOne('ID', "LCASE(`Name`) = '" . strtolower($name) . "'", 'tmp_categories');

        if ($category_exist || $user_category_exist) {
            $errors[] = str_replace('{category}', $name, $GLOBALS['rlLang']->getPhrase('tmp_category_exists', null, false, true));
        }

        if (!$errors) {
            $GLOBALS['reefless']->loadClass('Actions');
            $GLOBALS['reefless']->loadClass('Mail');

            // save category
            $insert = array(
                'Name'         => $name,
                'Parent_ID'    => $parent_id,
                'Account_ID'   => $account_id,
                'Session_hash' => $account_id ? '' : md5(session_id()),
                'Date'         => 'NOW()',
            );

            $GLOBALS['rlActions']->insertOne($insert, 'tmp_categories');
            $user_category_id = $GLOBALS['rlDb']->insertID();

            // inform category owner
            if ($account_id) {
                $mail_tpl = $GLOBALS['rlMail']->getEmailTemplate('custom_category_added_user');
                $mail_tpl['body'] = str_replace('{category_name}', $name, $mail_tpl['body']);
                $account_email = $rlDb->getOne('Mail', "`ID` = {$account_id}", 'accounts');

                $GLOBALS['rlMail']->send($mail_tpl, $account_email);
            }

            // inform administrator
            $mail_tpl = $GLOBALS['rlMail']->getEmailTemplate('custom_category_added_admin');
            $mail_tpl['body'] = str_replace('{category_name}', $name, $mail_tpl['body']);

            $GLOBALS['rlMail']->send($mail_tpl, $GLOBALS['config']['notifications_email']);

            return $user_category_id;
        }

        return false;
    }

    /**
     * Get user category by id or by url path
     *
     * @param  int    $id     - user category ID
     * @param  string $path   - user category path
     * @param  string $prefix - user category path prefix
     * @return array          - user category data
     */
    public static function getUserCategory($id = null, $path = '', $prefix = '')
    {
        if (!($id || ($path && $prefix))) {
            return false;
        }

        $user_category_id = intval($id ?: str_replace($prefix, '', $path));

        $user_category = $GLOBALS['rlDb']->fetch(
            array('Name', 'Parent_ID'),
            array('ID' => $user_category_id),
            null,
            1,
            'tmp_categories',
            'row');

        $category = self::getCategory($user_category['Parent_ID']);
        $category['name'] = $user_category['Name'];
        $category['Level'] = $category['Level'] + 1;
        $category['Path'] = $prefix . $user_category_id;
        $category['Lock'] = 0;
        $category['user_category_id'] = $user_category_id;

        return $category;
    }
}
