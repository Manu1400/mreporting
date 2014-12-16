<?php
include ("../../../inc/includes.php");

$config = new PluginMreportingConfig();
$res = $config->find();
$profil = new PluginMreportingProfile();

//Save profile
if (isset ($_REQUEST['update'])) {
   foreach( $res as $report) {
      if (class_exists($report['classname'])) {
         $access = $_REQUEST[$report['id']];

         $profil->getFromDBByQuery("where profiles_id = ".$_REQUEST['profile_id'].
                                   " AND reports = ".$report['id']);
         $profil->fields['right'] = $access;
         $profil->update($profil->fields);
      }
   }

} else if (isset ($_REQUEST['add'])) {
   $query = "SELECT `id`, `name`
   FROM `glpi_profiles` where `interface` = 'central'
   ORDER BY `name`";

   foreach ($DB->request($query) as $profile) {
      $access = $_REQUEST[$profile['id']];

      $profil->getFromDBByQuery("where profiles_id = ".$profile['id'].
                                " AND reports = ".$_REQUEST['report_id']);
      $profil->fields['right'] = $access;
      $profil->update($profil->fields);
   }

} else if (isset($_REQUEST['giveReadAccessForAllReport'])){
   foreach( $res as $report) {
      $profil->getFromDBByQuery("where profiles_id = ".$_REQUEST['profile_id'].
                                   " AND reports = ".$report['id']);
      $profil->fields['right'] = 'r';
      $profil->update($profil->fields);
   }

} else if (isset($_REQUEST['giveNoneAccessForAllReport'])){
   foreach( $res as $report) {
      $profil->getFromDBByQuery("where profiles_id = ".$_REQUEST['profile_id'].
                               " AND reports = ".$report['id']);
      $profil->fields['right'] = 'NULL';
      $profil->update($profil->fields);
   }

} else if (isset($_REQUEST['giveNoneAccessForAllProfile'])){
   $query = "SELECT `id`, `name`
   FROM `glpi_profiles` where `interface` = 'central'
   ORDER BY `name`";

   foreach ($DB->request($query) as $profile) {
      $profil->getFromDBByQuery("where profiles_id = ".$profile['id'].
                                " AND reports = ".$_REQUEST['report_id']);
      $profil->fields['right'] = 'NULL';
      $profil->update($profil->fields);
   }

} else if (isset($_REQUEST['giveReadAccessForAllProfile'])){
   $query = "SELECT `id`, `name`
   FROM `glpi_profiles` where `interface` = 'central'
   ORDER BY `name`";

   foreach ($DB->request($query) as $profile) {
      $profil->getFromDBByQuery("where profiles_id = ".$profile['id'].
                                " AND reports = ".$_REQUEST['report_id']);
      $profil->fields['right'] = 'r';
      $profil->update($profil->fields);
   }

}
Html::back();

