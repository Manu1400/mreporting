<?php

class PluginMreportingMenu extends CommonGLPI {

   static function getAdditionalMenuOptions() {

      $options = array();

      $options['notification'] = array(
           'title' => 'Notification ' . PluginMreportingNotification::getTypeName(Session::getPluralNumber()), //Note : quick locale hack
           'page'  => PluginMreportingNotification::getSearchURL(false),
           'links' => array(
               'add' => PluginMreportingNotification::getFormURL(false), //No check now
               'search' => PluginMreportingNotification::getSearchURL(false),
           ));
      
      return $options;
   }

}
