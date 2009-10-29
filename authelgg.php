<?php if (!defined('PmWiki')) exit();
/**
 * PmWiki AuthElgg
 *
 * @author      Rolando Espinoza La fuente (contacto@rolandoespinoza.info)
 * @copyright   Rolando Espinoza La fuente (contacto@rolandoespinoza.info)
 * @license     http://www.gnu.org/licenses/gpl.html GPL
 * @version     $Id$
 */

$EnableAuthUser = 1;
$EnableAuthElgg = 1;
$HasElggAccount = false;

// default db settings
SDVA($AuthElggConfig, array(
    'dbuser' => 'root',
    'dbpass' => '',
    'dbname' => 'elgg',
    'dbhost' => 'localhost',
    'prefix' => 'elgg',
    'secret' => 'elgg-pmwiki-secret',
    ));

if (!empty($_POST['authid']) && !empty($_POST['authpw'])) {
    AuthElggId($pagename, stripmagic($_POST['authid']), stripmagic($_POST['authpw']));
} else {
    SessionAuth($pagename);
}

/**
 * Perform elgg login check and retrive user's elgg groups
 * 
 * @param  mixed  $pagename 
 * @param  string $id      Username
 * @param  string $pw      Password
 * @return void
 */
function AuthElggId($pagename, $id, $pw) {
    global $AuthId, $AuthElggConfig, $HasElggAccount;

    if ($guid = ElggCheckUserPass($id, $pw)) {
        $AuthId = $id;
        // defaults access
        $authlist = array(
            "id:$id" => 1,
            "id:-$id" => 1,
            );
        $elgg_groups = ElggFetchGroups($id);
        if ($elgg_groups) {
            foreach ($elgg_groups as $group) {
                $authlist["@$group"] = 1;
            }
        }
        SessionAuth($pagename, array(
            'authid' => $id,
            'authlist' => $authlist,
            'elggguid' => $guid,
            'pmelgg' => $AuthElggConfig['secret'],
            ));
        $HasElggAccount = true;
    } else {
        $GLOBALS['InvalidLogin'] = 1;
    }
}

/**
 * Retrieves Elgg's groups of given user
 * 
 * @param  string   $username 
 * @return array
 */
function ElggFetchGroups($username) {
    global $AuthElggConfig;
    ElggSetupDb();
    $prefix = $AuthElggConfig['prefix'];
    $query = "SELECT g.guid FROM {$prefix}users_entity u "
        ."JOIN {$prefix}entity_relationships r ON r.guid_one=u.guid "
        ."JOIN {$prefix}groups_entity g ON g.guid=r.guid_two "
        ."WHERE u.username = '%s' AND r.relationship='member'";
    $query = sprintf($query, mysql_real_escape_string($username));
    $result = mysql_query($query);

    $groups = array();
    if ($result) {
        while (list($group_guid) = mysql_fetch_row($result)) {
            //TODO: use pmwiki_alias metadata
            $groups[] = "elgg$group_guid";
        }
    }
    return $groups;
}

/**
 * Checks elgg's user password directly in database
 * 
 * @param  array  $AuthElggConfig 
 * @return boolean 
 */
function ElggCheckUserPass($id, $pw) {
    global $AuthElggConfig;
    if ($id == 'admin') {
        // don't validate wiki admin through elgg
        return false;
    }
    ElggSetupDb();
    $prefix = $AuthElggConfig['prefix'];
    $query = sprintf("SELECT guid FROM {$prefix}users_entity "
        ."WHERE username='%s' "
        ."AND password=MD5(CONCAT('%s', salt)) "
        ."AND banned='no' LIMIT 1",
        mysql_real_escape_string($id),
        mysql_real_escape_string($pw)
    );
    $result = mysql_query($query);
    if (!$result) die('Could not successfully run query from DB: ' . mysql_error());
    //return (mysql_num_rows($result) > 0);
    $row = mysql_fetch_row($result);
    return $row[0];
}

/**
 * Setup elgg's database connection
 * 
 * @return resource mysql link resource
 */
function ElggSetupDb() {
    global $AuthElggConfig, $ElggDbLink;
    if (empty($ElggDbLink)) {
        $config =& $AuthElggConfig;
        $ElggDbLink = mysql_connect($config['dbhost'], $config['dbuser'], $config['dbpass']);
        if (!$ElggDbLink) die('Could not connect: ' . mysql_error());
        @mysql_select_db($config['dbname']) or die("Unable to select database: " . mysql_error());
    }
    return $ElggDbLink;
}
