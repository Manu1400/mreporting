<?php

class PluginMreportingCriterias extends CommonDBTM {

	static function getTypeName($nb=0) {
      return _n("Criterion", "Criteria", $nb);
   }

   static function getFormURL($full = true) {
      global $CFG_GLPI;

      //TODO : quick and dirty 'target'
      return $CFG_GLPI['root_doc'] . "/plugins/mreporting/front/dashboard.form.php";
   }

	static function install(Migration $migration) {
		global $DB;

      $table = self::getTable();

		$query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id` INT(11) NOT NULL AUTO_INCREMENT,
                  `notification_id` INT(11) NOT NULL DEFAULT '0',
                  `selectors` TEXT NOT NULL,
                  PRIMARY KEY (`id`)
               )
               COLLATE='latin1_swedish_ci'
               ENGINE=InnoDB";
		$DB->query($query);
	}

	static function uninstall(Migration $migration) {
		$migration->dropTable(self::getTable());
	}

   static function saveSelectors($graphname, $notification_id) {

      $remove_fields = array('short_classname', 'f_name', 'notification_id');

      $values = array();

      foreach ($_REQUEST as $key => $value) {
         if (!preg_match("/^_/", $key) && !in_array($key, $remove_fields) ) {
            $values[$key] = $value;
         }

         // Simplication of $_REQUEST
         if (empty($value)) {
            unset($_REQUEST[$key]);
         }
      }

      $selectors = $values;

      $input = array('notification_id' => $notification_id,
                     'selectors'       => addslashes(json_encode($selectors)));

      $criteria = new self();
      if ($criteria->getFromDBByQuery(" WHERE notification_id = $notification_id")) {
         $input['id'] = $criteria->getID();
         $criteria->update($input);
      } else {
         $criteria->add($input);
      }

      //TODO : Add that to locale plugin
      Session::addMessageAfterRedirect(__('Saved', 'mreporting'), true);

      //$_SESSION['mreporting_values'] = $values;
   }

   /**
    *
    * Get a preference for an notification_id
    * @param unknown_type preference field to get
    * @param unknown_type user ID
    * @return preference value or 0
    */
   static function checkPreferenceValue($field, $notification_id = 0) {
      $data = getAllDatasFromTable(self::getTable(), "`notification_id`='$notification_id'");
      if (empty($data)) {
         return 0;
      }

      $first = array_pop($data);

      return $first[$field];
   }

   static function getSelectorValuesByNotification_id($notification_id) {

      $myvalues  = isset($_SESSION['mreporting_values']) ? $_SESSION['mreporting_values'] : array();

      $selectors = self::checkPreferenceValue('selectors', $notification_id);
      if ($selectors) {
         $values = json_decode(stripslashes($selectors), true);

         foreach ($values as $key => $value) {
            $myvalues[$key] = $value;
         }
         /*
         if (isset($values[$_REQUEST['f_name']])) {
            foreach ($values[$_REQUEST['f_name']] as $key => $value) {
               $myvalues[$key] = $value;
            }
         }
         */
      }

      return $myvalues;
   }

   //Adapted from getReportSelectors()
   static function getReportSelectors() {
      ob_start();

      PluginMreportingCommon::addToSelector();

      $graphname = $_REQUEST['f_name'];

      if (!isset($_SESSION['mreporting_selector'][$graphname])
         || empty($_SESSION['mreporting_selector'][$graphname])) {
         return '';
      }

      $classname = 'PluginMreporting'.$_REQUEST['short_classname'];
      if (!class_exists($classname)) {
         return '';
      }

      $i = 1;
      foreach ($_SESSION['mreporting_selector'][$graphname] as $selector) {
         if ($i % 4 == 0) {
            echo '</tr><tr class="tab_bg_1">';
         }
         $selector = 'selector'.ucfirst($selector);
         if (method_exists('PluginMreportingCommon', $selector)) {
            $classselector = 'PluginMreportingCommon';
         } elseif (method_exists($classname, $selector)) {
            $classselector = $classname;
         } else {
            continue;
         }

         $i++;
         echo '<td>';
         $classselector::$selector();
         echo '</td>';
      }

      while ($i % 4 != 0) {
         $i++;
         echo '<td>&nbsp;</td>';
      }

      return ob_get_clean();
   }

   /**
     * Load $_SESSION['mreporting_selector'][$graphname] without dateinterval
     **/
   static function loadSessionMreportingSelector($graphname, $classname) {

      $config = PluginMreportingConfig::initConfigParams($graphname, $classname);

      $obj = new $classname($config);
      $obj->$graphname($config);

      //Security for external classes
      if (!isset($_SESSION['mreporting_selector'][$graphname])) {
         $_SESSION['mreporting_selector'][$graphname] = array();
      }

      //Note : can remove only begin date or end date selector because selector is 'dateinterval'

      //Remove begin date selector and end date selector
      if (in_array('dateinterval', $_SESSION['mreporting_selector'][$graphname])) {
         $key = array_search('dateinterval', $_SESSION['mreporting_selector'][$graphname]);
         unset ($_SESSION['mreporting_selector'][$graphname][$key]);

         if (empty($_SESSION['mreporting_selector'][$graphname])) {
            echo __("Setting the start date and the end date is in the configuration of the report.", 'mreporting');
            echo "<br><br>";
         }
      }

   }

   //Adapted from getConfig() in dashboard class
   static function showFormCriteriasFilters($notification_id) {
      self::getReportInfosAssociatedTo($notification_id);
      
      //Saved actual mreporting_values session
      $saved_session = isset($_SESSION['mreporting_values']) ? $_SESSION['mreporting_values'] : array();

      // Rewrite mreporting_values session (temporary)
      $_SESSION['mreporting_values'] = self::getSelectorValuesByNotification_id($notification_id);

      $reportSelectors = PluginMreportingCommon::getReportSelectors(true);

      // == Display filters ==

      //Note : No need to make a save of mreporting_selector session
      self::loadSessionMreportingSelector($_REQUEST['f_name'], 'PluginMreporting'.$_REQUEST['short_classname']);

      $reportSelectors = self::getReportSelectors();

      //Restore mreporting_values session
      $_SESSION['mreporting_values'] = $saved_session;

      if ($reportSelectors == "") {
         echo __("No configuration for this report", 'mreporting');
         echo "<br><br>";

         return;
      }

      echo "<form method='POST' action='".self::getFormURL()."' name='form'>";

      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'>";
      echo $reportSelectors;
      echo "</table>";

      echo "<input type='hidden' name='short_classname' value='".$_REQUEST['short_classname']."'>";
      echo "<input type='hidden' name='f_name' value='".$_REQUEST['f_name']."''>";
      //echo "<input type='hidden' name='gtype' value='".$_REQUEST['gtype']."'>";
      echo "<input type='hidden' name='notification_id' value='".$notification_id."'>";

      $criteria = new self();
      if ($criteria->getFromDBByQuery(" WHERE notification_id = $notification_id")) {
         $value = _sx('button', 'Post');
      } else {
         $value = _sx('button', 'Add');
      }
      echo "<input type='submit' class='submit' name='_saveCriterias' value='". $value ."'>";

      Html::closeForm();
   }

   static function getReportInfosAssociatedTo($notification_id) {
      $notification = new PluginMreportingNotification();
      if ($notification->getFromDB($notification_id)) {

         $config = new PluginMreportingConfig();
         if ($config->getFromDB($notification->fields['report'])) {

            $_REQUEST['f_name']           = $config->getName(); //'reportGlineBacklogs';
            $_REQUEST['short_classname']  = str_replace('PluginMreporting', '', $config->fields["classname"]); //'Helpdeskplus';
            //$_REQUEST['gtype']            = 'gline';

            //Note : useless
            return $config->fields;
         }
      }
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
   	global $CFG_GLPI;

      if ($item->getType() == 'PluginMreportingNotification') {
         echo "<div class='graph_navigation'>";
			self::showFormCriteriasFilters($item->getID());
         echo "</div>";
      }

      return true;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType() == 'PluginMreportingNotification' && Notification::canView()) { //"Security"

         if ($_SESSION['glpishow_count_on_tabs']) {
         	return self::createTabEntry(self::getTypeName(Session::getPluralNumber()));

         	//Note : Possible to have best code ?
            //return self::createTabEntry(self::getTypeName(Session::getPluralNumber()),
            //                            countElementsInTable($this->getTable())); //, "notifications_id = '".$item->getID()."'"
         }
         return self::getTypeName(Session::getPluralNumber());
      }
      return '';
   }

}