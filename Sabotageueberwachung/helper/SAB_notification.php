<?php

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUndefinedFunctionInspection */

/*
 * @module      Sabotageueberwachung
 *
 * @prefix      SAB
 *
 * @file        SAB_notification.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Sabotageueberwachung
 *
 */

declare(strict_types=1);

trait SAB_notification
{
    /**
     * Sends a notification.
     *
     * @param string $ActionText
     * @param string $MessageText
     * @param string $LogText
     *
     * @param int $MessageType
     * 0    = Notification
     * 1    = Acknowledgement
     * 2    = Alert
     * 3    = Sabotage
     * 4    = Battery
     *
     * @throws Exception
     */
    private function SendNotification(string $ActionText, string $MessageText, string $LogText, int $MessageType): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        if ($notificationCenter == 0 || !@IPS_ObjectExists($notificationCenter)) {
            return;
        }
        $location = $this->ReadPropertyString('Location');
        //Push
        $pushTitle = substr($location, 0, 32);
        $pushText = "\n" . $ActionText . "\n" . $MessageText;
        //E-Mail
        $eMailSubject = $location . ', ' . $ActionText;
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
            $eventMessages = IPS_GetObjectIDByIdent('EventMessages', $alarmProtocol);
            $content = array_merge(array_filter(explode("\n", GetValue($eventMessages))));
            $name = IPS_GetName($eventMessages);
            array_unshift($content, $name . ":\n");
            for ($i = 0; $i < 2; $i++) {
                array_unshift($content, "\n");
            }
            $eventProtocol = implode("\n", $content);
            $LogText .= "\n\n" . $eventProtocol;
        }
        $eMailText = $LogText;
        //SMS
        $smsText = $ActionText . ' - ' . $location . "\n" . $MessageText;
        //Send notification
        @BZ_SendNotification($notificationCenter, $pushTitle, $pushText, $eMailSubject, $eMailText, $smsText, $MessageType);
        /*
        $script = 'BZ_SendNotification(' . $notificationCenter . ', "' . $pushTitle . '", "' . $pushText . '", "' . $eMailSubject . '", "' . $eMailText . '", "' . $smsText . '", ' . $MessageType . ');';
        @IPS_RunScriptText($script);
         */
    }
}