<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class which manages notification events
**/
class PluginMreportingNotificationEvent extends NotificationEvent {

   /**
    * Raise a notification event event
    *
    * @param $event           the event raised for the itemtype
    * @param $item            the object which raised the event
    * @param $options array   of options used
    * @param $label           used for debugEvent() (default '')
   **/
   static function raiseEvent($event, $item, $options=array(), $label='') {
      global $CFG_GLPI;

      //If notifications are enabled in GLPI's configuration
      if ($CFG_GLPI["use_mailing"]) {
         $email_notprocessed = array();

         $options['entities_id'] = 0;
         $notificationtarget = PluginMreportingNotificationTarget::getInstance($item, $event, $options);
         if (!$notificationtarget) {
            return false;
         }

         //Get template's information
         $template = new NotificationTemplate();

         $entity = $notificationtarget->getEntity();

         //Foreach mreporting notification
         foreach (PluginMreportingNotification::getNotificationsByEventAndType($event, $item->getType(), $entity)
                  as $data) {
            $targets = getAllDatasFromTable(getTableForItemType('PluginMreportingNotificationTarget'), 'notifications_id = '.$data['id']);

            $notificationtarget->clearAddressesList();

            //Process more infos (for example for tickets)
            $notificationtarget->addAdditionnalInfosForTarget();

            $template->getFromDB($data['notificationtemplates_id']);
            $template->resetComputedTemplates();

            //Set notification's signature (the one which corresponds to the entity)
            $template->setSignature(Notification::getMailingSignature($entity));

            $can_notify_me = Session::isCron() ? true : $_SESSION['glpinotification_to_myself'];

            //Foreach mreporting notification targets
            foreach ($targets as $target) {

               $email_processed = array();

               //Get all users affected by this notification
               $notificationtarget->getAddressesByTarget($target,$options);

               foreach ($notificationtarget->getTargets() as $user_email => $users_infos) {
                  if ($label
                      || $notificationtarget->validateSendTo($event, $users_infos, $can_notify_me)) {
                     //If the user have not yet been notified
                     if (!isset($email_processed[$users_infos['language']][$users_infos['email']])) {
                        //If ther user's language is the same as the template's one
                        if (isset($email_notprocessed[$users_infos['language']]
                                                     [$users_infos['email']])) {
                           unset($email_notprocessed[$users_infos['language']]
                                                    [$users_infos['email']]);
                        }

                        //put all fields mreporting notication
                        $options['notification_id'] = $data; //*All* fields

                        if ($tid = $template->getTemplateByLanguage($notificationtarget, $users_infos,
                                                                    $event, $options)) {
                           //Send notification to the user
                           if ($label == '') {
                              PluginMreportingNotification::send(array_merge($template->getDataToSend($notificationtarget, $tid,
                                                                          $users_infos, $options), $notificationtarget->additionalData));
                           } else {
                              $notificationtarget->getFromDB($target['id']);
                              echo "<tr class='tab_bg_2'>";
                              echo "<td>".$label."</td>";
                              echo "<td>".$notificationtarget->getNameID()."</td>";
                              echo "<td>".sprintf(__('%1$s (%2$s)'), $template->getName(),
                                                  $users_infos['language'])."</td>";
                              echo "<td>".$users_infos['email']."</td>";
                              echo "</tr>";
                           }

                           $email_processed[$users_infos['language']][$users_infos['email']]
                                                                     = $users_infos;
                        } else {
                           $email_notprocessed[$users_infos['language']][$users_infos['email']]
                                                                        = $users_infos;
                        }
                     }
                  }
               }
            }
         }
      }
      unset($email_notprocessed);
      $template = null;
      return true;
   }

}

