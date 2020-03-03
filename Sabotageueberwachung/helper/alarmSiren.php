<?php

// Declare
declare(strict_types=1);

trait SABO_alarmSiren
{
    /**
     * Toggles the alarm siren.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function ToggleAlarmSiren(bool $State): void
    {
        // Alarm siren
        $alarmSiren = $this->ReadPropertyInteger('AlarmSiren');
        if ($alarmSiren != 0 && @IPS_ObjectExists($alarmSiren)) {
            // Toggle alarm siren
            @ASIR_ToggleAlarmSiren($alarmSiren, $State);
        }

        // Alarm siren script
        $alarmSirenScript = $this->ReadPropertyInteger('AlarmSirenScript');
        if ($alarmSirenScript != 0 && @IPS_ObjectExists($alarmSirenScript)) {
            IPS_RunScriptEx($alarmSirenScript, ['Status' => $State]);
        }
    }
}