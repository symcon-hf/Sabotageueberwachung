<?php

/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

trait SABO_notification
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
            @BENA_SendNotification($notificationCenter, $Title, $Text, $MessageType);
        }
        // Execute script
        $id = $this->ReadPropertyInteger('NotificationScript');
        if ($id != 0 && IPS_ObjectExists($id)) {
            IPS_RunScriptEx($id, ['Title' => $Title, 'Text' => $Text]);
        }
    }
}