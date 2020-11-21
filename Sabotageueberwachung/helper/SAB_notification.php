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
     * @param string $Title
     * @param string $Text
     * @param int $MessageType
     * 0    = Notification
     * 1    = Acknowledgement
     * 2    = Alert
     * 3    = Sabotage
     * 4    = Battery
     */
    private function SendNotification(string $Title, string $Text, int $MessageType): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $notificationCenter = $this->ReadPropertyInteger('NotificationCenter');
        if ($notificationCenter != 0 && @IPS_ObjectExists($notificationCenter)) {
            @BZ_SendNotification($notificationCenter, $Title, $Text, $MessageType);
        }
    }
}