<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @module      Sabotageueberwachung
 *
 * @prefix      SABO
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Sabotageueberwachung
 *
 * @guids       Library
 *              {276F536B-9F2D-C6D3-B4BD-5924DA56950C}
 *
 *              Sabotageueberwachung
 *             	{BE2DC75C-D14A-E49B-001C-BFD428B6A793}
 */

declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Sabotageueberwachung extends IPSModule
{
    // Helper
    use SABO_alarmCall;
    use SABO_alarmLight;
    use SABO_alarmSiren;
    use SABO_backupRestore;
    use SABO_notification;
    use SABO_variables;

    // Constants
    private const SABOTAGEUEBERWACHUNG_LIBRARY_GUID = '{276F536B-9F2D-C6D3-B4BD-5924DA56950C}';
    private const SABOTAGEUEBERWACHUNG_MODULE_GUID = '{BE2DC75C-D14A-E49B-001C-BFD428B6A793}';
    private const HOMEMATIC_DEVICE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
        $this->DeleteProfiles();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        // Never delete this line!
        parent::ApplyChanges();
        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->RegisterVariables();
        $this->RegisterMessages();
        $this->CreateOverview();
        $this->CheckActualStatus();
        $this->CheckMaintenanceMode();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        // Send debug
        // $Data[0] = actual value
        // $Data[1] = value changed
        // $Data[2] = last value
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:
                if ($this->CheckMaintenanceMode()) {
                    return;
                }
                $this->CreateOverview();
                $this->CheckActualStatus();
                $actualValue = boolval($Data[0]);
                if ($actualValue && $Data[1]) {
                    $this->ExecuteAlerting($SenderID);
                }
                break;

            default:
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $moduleInfo = [];
        $library = IPS_GetLibrary(self::SABOTAGEUEBERWACHUNG_LIBRARY_GUID);
        $module = IPS_GetModule(self::SABOTAGEUEBERWACHUNG_MODULE_GUID);
        $moduleInfo['name'] = $module['ModuleName'];
        $moduleInfo['version'] = $library['Version'] . '-' . $library['Build'];
        $moduleInfo['date'] = date('d.m.Y', $library['Date']);
        $moduleInfo['time'] = date('H:i', $library['Date']);
        $moduleInfo['developer'] = $library['Author'];
        $formData['elements'][0]['items'][2]['caption'] = "Instanz ID:\t\t" . $this->InstanceID;
        $formData['elements'][0]['items'][3]['caption'] = "Modul:\t\t\t" . $moduleInfo['name'];
        $formData['elements'][0]['items'][4]['caption'] = "Version:\t\t\t" . $moduleInfo['version'];
        $formData['elements'][0]['items'][5]['caption'] = "Datum:\t\t\t" . $moduleInfo['date'];
        $formData['elements'][0]['items'][6]['caption'] = "Uhrzeit:\t\t\t" . $moduleInfo['time'];
        $formData['elements'][0]['items'][7]['caption'] = "Entwickler:\t\t" . $moduleInfo['developer'];
        $formData['elements'][0]['items'][8]['caption'] = "Präfix:\t\t\tSABO";
        // Registered messages
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $senderID => $messageID) {
            $senderName = IPS_GetName($senderID);
            $parentName = $senderName;
            $parentID = IPS_GetParent($senderID);
            if (is_int($parentID) && $parentID != 0 && @IPS_ObjectExists($parentID)) {
                $parentName = IPS_GetName($parentID);
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formData['actions'][1]['items'][0]['values'][] = [
                'ParentName'                                            => $parentName,
                'SenderID'                                              => $senderID,
                'SenderName'                                            => $senderName,
                'MessageID'                                             => $messageID,
                'MessageDescription'                                    => $messageDescription];
        }
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    #################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Monitoring':
                $this->SetValue('Monitoring', $Value);
                $this->CheckActualStatus();
                break;
        }
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        // Descriptions
        $this->RegisterPropertyString('Location', '');
        // Monitored variables
        $this->RegisterPropertyString('MonitoredVariables', '[]');
        // Visibility
        $this->RegisterPropertyBoolean('UseOverview', false);
        $this->RegisterPropertyInteger('LinkCategory', 0);
        $this->RegisterPropertyBoolean('UseLinks', false);
        // Alarm protocol
        $this->RegisterPropertyInteger('AlarmProtocol', 0);
        // Notification
        $this->RegisterPropertyInteger('NotificationCenter', 0);
        $this->RegisterPropertyInteger('NotificationScript', 0);
        // Alarm siren
        $this->RegisterPropertyInteger('AlarmSiren', 0);
        $this->RegisterPropertyInteger('AlarmSirenScript', 0);
        // Alarm light
        $this->RegisterPropertyInteger('AlarmLight', 0);
        $this->RegisterPropertyInteger('AlarmLightScript', 0);
        // Alarm call
        $this->RegisterPropertyInteger('AlarmCall', 0);
        $this->RegisterPropertyInteger('AlarmCallScript', 0);
    }

    private function CreateProfiles(): void
    {
        // Status
        $profileName = 'SABO.' . $this->InstanceID . '.Status';
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 0);
        }
        IPS_SetVariableProfileAssociation($profileName, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profileName, 1, 'Alarm', 'Warning', 0xFF0000);
        // Homematic
        $profile = 'SABO.Sabotage.Integer';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Sabotage', 'Warning', 0xFF0000);
        // Homematic IP
        $profile = 'SABO.Sabotage.Boolean';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Information', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Sabotage', 'Warning', 0xFF0000);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['Status'];
        foreach ($profiles as $profile) {
            $profileName = 'SABO.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Monitoring
        $this->MaintainVariable('Monitoring', 'Überwachung', 0, '~Switch', 10, true);
        $this->EnableAction('Monitoring');
        // Status
        $profile = 'SABO.' . $this->InstanceID . '.Status';
        $this->MaintainVariable('Status', 'Status', 0, $profile, 20, true);
        // Overview
        $this->MaintainVariable('Overview', 'Aktoren / Sensoren', 3, 'HTMLBox', 30, true);
        $overview = $this->GetIDForIdent('Overview');
        IPS_SetIcon($overview, 'Eyes');
        $useOverview = $this->ReadPropertyBoolean('UseOverview');
        if ($useOverview) {
            $this->CreateOverview();
        }
        IPS_SetHidden($overview, !$useOverview);
    }

    private function RegisterMessages(): void
    {
        // Unregister all variable update messages first
        $registeredMessages = $this->GetMessageList();
        if (!empty($registeredMessages)) {
            foreach ($registeredMessages as $id => $registeredMessage) {
                foreach ($registeredMessage as $messageType) {
                    if ($messageType == VM_UPDATE) {
                        $this->UnregisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }
        // Register variables
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = false;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = true;
            $status = 104;
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, $result);
        return $result;
    }
}
