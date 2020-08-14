<?php

/** @noinspection PhpUnused */
/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait SABO_variables
{
    /**
     * Executes the alerting.
     *
     * @param int $SenderID
     */
    public function ExecuteAlerting(int $SenderID): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $timeStamp = date('d.m.Y, H:i:s');
        $alarmObjectName = $this->ReadPropertyString('Location');
        $alarmProtocol = $this->ReadPropertyInteger('AlarmProtocol');
        $name = '';
        $monitoringActive = $this->GetValue('Monitoring');
        if ($monitoringActive) {
            $monitoredVariables = json_decode($this->ReadPropertyString('MonitoredVariables'), true);
            if (!empty($monitoredVariables)) {
                $key = array_search($SenderID, array_column($monitoredVariables, 'ID'));
                if (is_int($key)) {
                    $name = $monitoredVariables[$key]['Name'];
                }
            }
            // Log
            if (isset($name)) {
                $text = $name . ' , es wurde eine Sabotage erkannt. Bitte prüfen! (ID ' . $SenderID . ')';
            } else {
                $text = 'Es wurde eine Sabotage erkannt. Bitte prüfen! (ID ' . $SenderID . ')';
            }
            $logText = $timeStamp . ', ' . $text;
            if ($alarmProtocol != 0 && @IPS_ObjectExists($alarmProtocol)) {
                @APRO_UpdateMessages($alarmProtocol, $logText, 2);
            }
            // Notification
            $title = $alarmObjectName . ', Sabotage!';
            $this->SendNotification($title, $logText, 3);
            // Alarm siren
            $this->ToggleAlarmSiren(true);
            // Alarm light
            $this->ToggleAlarmLight(true);
            // Alarm call
            $this->ExecuteAlarmCall($name);
        }
    }

    /**
     * Determines the variables automatically.
     */
    public function DetermineVariables(): void
    {
        $listedVariables = [];
        $instanceIDs = @IPS_GetInstanceListByModuleID(self::HOMEMATIC_DEVICE_GUID);
        if (!empty($instanceIDs)) {
            $variables = [];
            foreach ($instanceIDs as $instanceID) {
                $childrenIDs = @IPS_GetChildrenIDs($instanceID);
                foreach ($childrenIDs as $childrenID) {
                    $match = false;
                    $object = @IPS_GetObject($childrenID);
                    if ($object['ObjectIdent'] == 'SABOTAGE' || $object['ObjectIdent'] == 'ERROR_SABOTAGE') {
                        $match = true;
                    }
                    if ($match) {
                        // Check for variable
                        if ($object['ObjectType'] == 2) {
                            $name = strstr(@IPS_GetName($instanceID), ':', true);
                            if ($name == false) {
                                $name = @IPS_GetName($instanceID);
                            }
                            $deviceAddress = @IPS_GetProperty(IPS_GetParent($childrenID), 'Address');
                            array_push($variables, ['Use' => true, 'ID' => $childrenID, 'Name' => $name, 'Address' => $deviceAddress]);
                        }
                    }
                }
            }
            // Get already listed variables
            $listedVariables = json_decode($this->ReadPropertyString('MonitoredVariables'), true);
            // Delete non existing variables anymore
            if (!empty($listedVariables)) {
                $deleteVariables = array_diff(array_column($listedVariables, 'ID'), array_column($variables, 'ID'));
                if (!empty($deleteVariables)) {
                    foreach ($deleteVariables as $key => $deleteVariable) {
                        unset($listedVariables[$key]);
                    }
                }
            }
            // Add new variables
            if (!empty($listedVariables)) {
                $addVariables = array_diff(array_column($variables, 'ID'), array_column($listedVariables, 'ID'));
                if (!empty($addVariables)) {
                    foreach ($addVariables as $addVariable) {
                        $name = strstr(@IPS_GetName(@IPS_GetParent($addVariable)), ':', true);
                        $deviceAddress = @IPS_GetProperty(@IPS_GetParent($addVariable), 'Address');
                        array_push($listedVariables, ['Use' => true, 'ID' => $addVariable, 'Name' => $name, 'Address' => $deviceAddress]);
                    }
                }
            } else {
                $listedVariables = $variables;
            }
        }
        // Sort variables by name
        usort($listedVariables, function ($a, $b)
        {
            return $a['Name'] <=> $b['Name'];
        });
        // Rebase array
        $listedVariables = array_values($listedVariables);
        // Update variable list
        $json = json_encode($listedVariables);
        @IPS_SetProperty($this->InstanceID, 'MonitoredVariables', $json);
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Variablen wurden automatisch ermittelt!';
    }

    /**
     * Assigns the profile to the variable.
     *
     * @param bool $Override
     * false    = our profile will only be assigned, if the variables has no existing profile.
     * true     = our profile will be assigned to the variables.
     */
    public function AssignVariableProfile(bool $Override): void
    {
        // Assign profile only for listed variables
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $variableType = @IPS_GetVariable($variable->ID)['VariableType'];
                $profileName = null;
                // Boolean variable
                if ($variableType == 0) {
                    $profileName = 'SABO.Sabotage.Boolean';
                }
                // Integer variable
                if ($variableType == 1) {
                    $profileName = 'SABO.Sabotage.Integer';
                }
                // Always assign profile
                if ($Override) {
                    if (!is_null($profileName)) {
                        @IPS_SetVariableCustomProfile($variable->ID, $profileName);
                    }
                } // Only assign profile, if variable has no profile
                else {
                    // Check if variable has a profile
                    $assignedProfile = @IPS_GetVariable($variable->ID)['VariableProfile'];
                    if (empty($assignedProfile)) {
                        @IPS_SetVariableCustomProfile($variable->ID, $profileName);
                    }
                }
            }
        }
        echo 'Variablenprofile wurden zugewiesen!';
    }

    /**
     * Creates links of monitored variables.
     */
    public function CreateVariableLinks(): void
    {
        $useLinks = $this->ReadPropertyBoolean('UseLinks');
        if ($useLinks) {
            $categoryID = $this->ReadPropertyInteger('LinkCategory');
            // Define icon first
            $icon = 'Warning';
            // Get all monitored variables
            $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
            $targetIDs = [];
            $i = 0;
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    $targetIDs[$i] = ['name' => $variable->Name, 'targetID' => $variable->ID];
                    $i++;
                }
            }
            // Sort array alphabetically by device name
            sort($targetIDs);
            // Get all existing links (links have not an ident field, so we use the object info field)
            $existingTargetIDs = [];
            $links = @IPS_GetLinkList();
            if (!empty($links)) {
                $i = 0;
                foreach ($links as $link) {
                    $linkInfo = @IPS_GetObject($link)['ObjectInfo'];
                    if ($linkInfo == 'SABO.' . $this->InstanceID) {
                        // Get target id
                        $existingTargetID = @IPS_GetLink($link)['TargetID'];
                        $existingTargetIDs[$i] = ['linkID' => $link, 'targetID' => $existingTargetID];
                        $i++;
                    }
                }
            }
            // Delete dead links
            $deadLinks = array_diff(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($deadLinks)) {
                foreach ($deadLinks as $targetID) {
                    $position = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$position]['linkID'];
                    if (@IPS_LinkExists($linkID)) {
                        @IPS_DeleteLink($linkID);
                    }
                }
            }
            // Create new links
            $newLinks = array_diff(array_column($targetIDs, 'targetID'), array_column($existingTargetIDs, 'targetID'));
            if (!empty($newLinks)) {
                foreach ($newLinks as $targetID) {
                    $linkID = @IPS_CreateLink();
                    @IPS_SetParent($linkID, $categoryID);
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    @IPS_SetPosition($linkID, $position + 1);
                    $name = $targetIDs[$position]['name'];
                    @IPS_SetName($linkID, $name);
                    @IPS_SetLinkTargetID($linkID, $targetID);
                    @IPS_SetInfo($linkID, 'SABO.' . $this->InstanceID);
                    @IPS_SetIcon($linkID, $icon);
                }
            }
            // Edit existing links
            $existingLinks = array_intersect(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
            if (!empty($existingLinks)) {
                foreach ($existingLinks as $targetID) {
                    $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                    $targetID = $targetIDs[$position]['targetID'];
                    $index = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                    $linkID = $existingTargetIDs[$index]['linkID'];
                    @IPS_SetPosition($linkID, $position + 3);
                    $name = $targetIDs[$position]['name'];
                    @IPS_SetName($linkID, $name);
                    @IPS_SetInfo($linkID, 'SABO.' . $this->InstanceID);
                    @IPS_SetIcon($linkID, $icon);
                }
            }
            echo 'Variablenverknüpfungen erfolgreich erstellt!';
        }
    }

    #################### Private

    /**
     * Creates an overview of all monitored variables.
     */
    private function CreateOverview(): void
    {
        $string = '';
        $useOverview = $this->ReadPropertyBoolean('UseOverview');
        if ($useOverview) {
            $string = "<table style='width: 100%; border-collapse: collapse;'>";
            $string .= '<tr><td><b>ID</b></td><td><b>Name</b></td><td><b>Adresse</b></td><td><b>Sabotagestatus</b></td></tr>';
            $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    if ($variable->Use) {
                        $id = $variable->ID;
                        if (@IPS_ObjectExists($id)) {
                            $name = $variable->Name;
                            $deviceAddress = @IPS_GetProperty(@IPS_GetParent($id), 'Address');
                            if (!$deviceAddress) {
                                $deviceAddress = '-';
                            }
                            $actualStateText = 'OK';
                            // Set color to red
                            $actualValue = boolval(GetValue($id));
                            if ($actualValue) {
                                $id = '<span style="color:#FF0000"><b>' . $id . '</b></span>';
                                $name = '<span style="color:#FF0000"><b>' . $variable->Name . '</b></span>';
                                $deviceAddress = '<span style="color:#FF0000"><b>' . $deviceAddress . '</b></span>';
                                $actualStateText = '<span style="color:#FF0000"><b>Sabotage!</b></span>';
                            }
                            $string .= '<tr><td>' . $id . '</td><td>' . $name . '</td><td>' . $deviceAddress . '</td><td>' . $actualStateText . '</td></tr>';
                        }
                    }
                }
                $string .= '</table>';
            }
        }
        $this->SetValue('Overview', $string);
    }

    /**
     * Checks the actual status.
     */
    private function CheckActualStatus(): void
    {
        $actualStatus = false;
        $monitoredVariables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($monitoredVariables)) {
            foreach ($monitoredVariables as $variable) {
                if ($variable->Use) {
                    $id = $variable->ID;
                    $actualValue = boolval(GetValue($id));
                    if ($actualValue) {
                        $actualStatus = true;
                    }
                }
            }
        }
        $this->SetValue('Status', $actualStatus);
    }
}