<?php

/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait SABO_alarmLight
{
    /**
     * Toggles the alarm light.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function ToggleAlarmLight(bool $State): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        // Alarm light
        $alarmLight = $this->ReadPropertyInteger('AlarmLight');
        if ($alarmLight != 0 && @IPS_ObjectExists($alarmLight)) {
            // Toggle alarm light
            @ABEL_ToggleAlarmLight($alarmLight, $State);
        }

        // Alarm light script
        $alarmLightScript = $this->ReadPropertyInteger('AlarmLightScript');
        if ($alarmLightScript != 0 && IPS_ObjectExists($alarmLightScript)) {
            // Execute Script
            IPS_RunScriptEx($alarmLightScript, ['Status' => $State]);
        }
    }
}