<?php

// Declare
declare(strict_types=1);

trait SABO_alarmCall
{
    /**
     * Executes an alarm call.
     *
     * @param string $SensorName
     */
    public function ExecuteAlarmCall(string $SensorName): void
    {
        // Alarm call
        $alarmCall = $this->ReadPropertyInteger('AlarmCall');
        if ($alarmCall != 0 && @IPS_ObjectExists($alarmCall)) {
            // Execute alarm call
            @AANR_ExecuteAlarmCall($alarmCall, $SensorName);
        }

        // Alarm call script
        $alarmCallScript = $this->ReadPropertyInteger('AlarmCallScript');
        if ($alarmCallScript != 0 && @IPS_ObjectExists($alarmCallScript)) {
            // Execute script
            IPS_RunScript($alarmCallScript);
        }
    }
}