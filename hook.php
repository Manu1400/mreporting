<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Mreporting plugin for GLPI
 Copyright (C) 2003-2011 by the mreporting Development Team.

 https://forge.indepnet.net/projects/mreporting
 -------------------------------------------------------------------------

 LICENSE

 This file is part of mreporting.

 mreporting is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 mreporting is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with mreporting. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

function plugin_mreporting_install() {
   global $DB;

   $version   = plugin_version_mreporting();
   $migration = new Migration($version['version']);

   include_once(GLPI_ROOT."/plugins/mreporting/inc/profile.class.php");

   require_once "inc/dashboard.class.php";
   PluginMreportingDashboard::install($migration);

   require_once "inc/config.class.php";
   PluginMreportingConfig::install($migration);

   $queries = array();

   $queries[] = "CREATE TABLE IF NOT EXISTS `glpi_plugin_mreporting_profiles` (
      `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
      `profiles_id` VARCHAR(45) NOT NULL,
      `reports` CHAR(1),
      PRIMARY KEY (`id`),
      UNIQUE `profiles_id_reports` (`profiles_id`, `reports`)
      )
      ENGINE = MyISAM;";

   $queries[] = "CREATE TABLE  IF NOT EXISTS `glpi_plugin_mreporting_preferences` (
   `id` int(11) NOT NULL auto_increment,
   `users_id` int(11) NOT NULL default 0,
   `template` varchar(255) collate utf8_unicode_ci default NULL,
   PRIMARY KEY (`id`),
   KEY `users_id` (`users_id`)
   ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

   // add display preferences
   $query_display_pref = "SELECT id
      FROM glpi_displaypreferences
      WHERE itemtype = 'PluginMreportingConfig'";

   $res_display_pref = $DB->query($query_display_pref);

   if ($DB->numrows($res_display_pref) == 0) {
      $queries[] = "INSERT INTO `glpi_displaypreferences`
         VALUES (NULL,'PluginMreportingConfig','2','2','0');";
      $queries[] = "INSERT INTO `glpi_displaypreferences`
         VALUES (NULL,'PluginMreportingConfig','3','3','0');";
      $queries[] = "INSERT INTO `glpi_displaypreferences`
         VALUES (NULL,'PluginMreportingConfig','4','4','0');";
      $queries[] = "INSERT INTO `glpi_displaypreferences`
         VALUES (NULL,'PluginMreportingConfig','5','5','0');";
      $queries[] = "INSERT INTO `glpi_displaypreferences`
         VALUES (NULL,'PluginMreportingConfig','6','6','0');";
      $queries[] = "INSERT INTO `glpi_displaypreferences`
         VALUES (NULL,'PluginMreportingConfig','8','8','0');";
   }

   foreach ($queries as $query) {
      $DB->query($query);
   }

   // == Update to 2.3 ==
   if (!fieldExists('glpi_plugin_mreporting_profiles', 'right')
       && fieldExists('glpi_plugin_mreporting_profiles', 'reports')) {
      //save all profile with right READ
      $right = PluginMreportingProfile::getRight();

      //truncate profile table
      $query = "TRUNCATE TABLE `glpi_plugin_mreporting_profiles`";
      $DB->query($query);

       //migration of field
      $migration->addField('glpi_plugin_mreporting_profiles', 'right', 'char');
      $migration->changeField('glpi_plugin_mreporting_profiles', 'reports', 
                             'reports','integer');
      $migration->changeField('glpi_plugin_mreporting_profiles', 'profiles_id', 
                             'profiles_id','integer');
      $migration->dropField('glpi_plugin_mreporting_profiles', 'config');

      $migration->migrationOneTable('glpi_plugin_mreporting_profiles');
   }

   // == UPDATE to 0.84+1.0 == 
   $query = "UPDATE `glpi_plugin_mreporting_profiles` pr SET pr.right = ".READ." WHERE pr.right = 'r'";
   $DB->query($query);
   if (!isIndex('glpi_plugin_mreporting_profiles', 'profiles_id_reports')) {
      $query = "ALTER IGNORE TABLE glpi_plugin_mreporting_profiles 
                ADD UNIQUE INDEX `profiles_id_reports` (`profiles_id`, `reports`)";
      $DB->query($query);
   }

   // == UPDATE TO 0.90+1.2

   require_once "inc/notificationtarget.class.php";
   PluginMreportingNotificationTarget::install($migration);

   require_once "inc/criterias.class.php";
   PluginMreportingCriterias::install($migration);

   //== Create directories
   $rep_files_mreporting = GLPI_PLUGIN_DOC_DIR."/mreporting";
   if (!is_dir($rep_files_mreporting))
      mkdir($rep_files_mreporting);
   $notifications_folder = GLPI_PLUGIN_DOC_DIR."/mreporting/notifications";
   if (!is_dir($notifications_folder))
      mkdir($notifications_folder);

   // == Install notifications
   require_once "inc/notification.class.php";
   PluginMreportingNotification::install($migration);
   CronTask::Register('PluginMreportingNotification', 'SendNotifications', MONTH_TIMESTAMP);

   $migration->addField("glpi_plugin_mreporting_preferences", "selectors", "text");
   $migration->migrationOneTable('glpi_plugin_mreporting_preferences');

   // == Init available reports
   require_once "inc/baseclass.class.php";
   require_once "inc/common.class.php";
   $config = new PluginMreportingConfig();
   $config->createFirstConfig();

   PluginMreportingProfile::addRightToProfile($_SESSION['glpiactiveprofile']['id']);

   return true;
}


function plugin_mreporting_uninstall() {
   global $DB;

   $version   = plugin_version_mreporting();
   $migration = new Migration($version['version']);

   $tables = array("glpi_plugin_mreporting_profiles",
                   "glpi_plugin_mreporting_preferences",
   );

   foreach ($tables as $table) {
      $migration->dropTable($table);
   }

   Toolbox::deleteDir(GLPI_PLUGIN_DOC_DIR."/mreporting/notifications");
   Toolbox::deleteDir(GLPI_PLUGIN_DOC_DIR."/mreporting");

   require_once "inc/dashboard.class.php";
   PluginMreportingDashboard::uninstall($migration);

   require_once "inc/config.class.php";
   PluginMreportingConfig::uninstall($migration);

   require_once "inc/notification.class.php";
   PluginMreportingNotification::uninstall($migration);

   // 0.90+1.2
   require_once "inc/notificationtarget.class.php";
   PluginMreportingNotificationTarget::uninstall($migration);

   require_once "inc/criterias.class.php";
   PluginMreportingCriterias::uninstall($migration);

   // == Minor uninstall ==

   // Delete global view and personal view
   $query = "DELETE FROM glpi_displaypreferences WHERE itemtype LIKE 'PluginMreporting%'";
   $DB->query($query);

   // Clean log
   $query = "DELETE FROM glpi_logs WHERE itemtype LIKE 'PluginMreporting%' OR itemtype_link LIKE 'PluginMreporting%'";
   $DB->query($query);

   return true;
}

// Define dropdown relations
function plugin_mreporting_getDatabaseRelations() {

   $plugin = new Plugin();
   if ($plugin->isActivated("mreporting")) {
      return array("glpi_profiles" => array ("glpi_plugin_mreporting_profiles" => "profiles_id"));
   } else {
      return array();
   }
}

function plugin_mreporting_giveItem($type,$ID,$data,$num) {
   global $LANG;

   $searchopt=&Search::getOptions($type);
   $table=$searchopt[$ID]["table"];
   $field=$searchopt[$ID]["field"];

   $output_type=Search::HTML_OUTPUT;
   if (isset($_GET['display_type']))
      $output_type=$_GET['display_type'];

   switch ($type) {

      case 'PluginMreportingNotification':
      case 'PluginMreportingConfig':

         switch ($table.'.'.$field) {
            case "glpi_plugin_mreporting_configs.show_label":
               $out = ' ';
               if (!empty($data['raw']["ITEM_$num"])) {
                  $out=PluginMreportingConfig::getLabelTypeName($data['raw']["ITEM_$num"]);
               }
               return $out;
               break;
            case "glpi_plugin_mreporting_configs.name":
               $out = ' ';
               if (!empty($data['raw']["ITEM_$num"])) {
                  $title_func = '';
                  $short_classname = '';
                  $f_name = '';

                  $inc_dir = GLPI_ROOT."/plugins/mreporting/inc";
                  //parse inc dir to search report classes
                  $classes = PluginMreportingCommon::parseAllClasses($inc_dir);

                  foreach($classes as $classname) {
                     if (!class_exists($classname)) {
                        continue;
                     }
                     $functions = get_class_methods($classname);

                     foreach($functions as $funct_name) {
                        $ex_func = preg_split('/(?<=\\w)(?=[A-Z])/', $funct_name);
                        if ($ex_func[0] != 'report') continue;

                        $gtype = strtolower($ex_func[1]);

                        if ($data['raw']["ITEM_$num"] == $funct_name) {
                           if (!empty($classname) && !empty($funct_name)) {
                              $short_classname = str_replace('PluginMreporting', '', $classname);
                              if (isset($LANG['plugin_mreporting'][$short_classname][$funct_name]['title'])) {
                                 $title_func = $LANG['plugin_mreporting'][$short_classname][$funct_name]['title'];
                              }
                           }
                        }
                     }
                  }
                  $out="<a href='config.form.php?id=".$data["id"]."'>".
                        $data['raw']["ITEM_$num"]."</a> (".$title_func.")";
               }
               return $out;
               break;
         }
         return "";
         break;

   }
   return "";
}

function plugin_mreporting_MassiveActionsFieldsDisplay($options=array()) {

   $table = $options['options']['table'];
   $field = $options['options']['field'];
   $linkfield = $options['options']['linkfield'];

   //Fixed a GLPI bug exist in Notification class

   //if ($options['itemtype'] == 'PluginMreportingNotification') {
      if ($table.".".$field == 'glpi_notificationtemplates.name') {
         Dropdown::show('NotificationTemplate', array(
            'condition' => "itemtype = '".$options['itemtype']."'")
         );
         return true;
      }
   //}

   if ($table == getTableForItemType($options['itemtype'])) {

      // Table fields
      switch ($table.".".$field) {

         case "glpi_plugin_mreporting_configs.show_label":
            PluginMreportingConfig::dropdownLabel('show_label');
            return true;
            break;

         case "glpi_plugin_mreporting_configs.graphtype":
            Dropdown::showFromArray("graphtype",
               array('GLPI'=>'GLPI', 'PNG'=>'PNG', 'SVG'=>'SVG'));
            return true;
            break;
      }

   }
   // Need to return false on non display item
   return false;
}


function plugin_mreporting_searchOptionsValues($options = array()) {

   $table = $options['searchoption']['table'];
   $field = $options['searchoption']['field'];

   switch ($table.".".$field) {
      case "glpi_plugin_mreporting_configs.graphtype":
         Dropdown::showFromArray("graphtype",
            array('GLPI'=>'GLPI', 'PNG'=>'PNG', 'SVG'=>'SVG'));
         return true;
   }
   return false;
}
