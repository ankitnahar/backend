<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;
class WorksheetReminder extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Worksheet:reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send worksheet reminder to staff';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $today = date('Y-m-d');
            $worksheetReminder = \App\Models\Backend\Worksheet::with('entityId:id,trading_name', 'masterActivityId:id,name', 'taskId:id,name', 'frequencyId:id,frequency_name', 'statusId:id,status_name')->where('status_id', '!=', 4)->whereNotIn('service_id', [1, 2, 6])->whereOr('service_id', 0)->groupBy('worksheet.id');

            $reminderWorksheet = $worksheetReminder->where('reminder_date', $today)->get()->toArray();
            $gettingOverdueWorksheet = $worksheetReminder->where('due_date', $today)->get()->toArray();
            $dueWorksheet = $worksheetReminder->where('due_date', '<', $today)->get()->toArray();

            $arrangeData = $userList = array();
            if (!empty($reminderWorksheet)) {
                foreach ($reminderWorksheet as $keyReminder => $valueReminder) {
                    $data = array();
                    $data['task'] = $valueReminder['task_id']['name'];
                    $data['master_activity'] = $valueReminder['master_activity_id']['name'];
                    $data['start_date'] = dateFormat($valueReminder['start_date']);
                    $data['end_date'] = dateFormat($valueReminder['end_date']);
                    $data['due_date'] = dateFormat($valueReminder['due_date']);
                    $data['frequency'] = $valueReminder['frequency_id']['frequency_name'];
                    $data['status'] = $valueReminder['status_id']['status_name'];
                    if ($valueReminder['team_json'] != '') {
                        $teamJson = \GuzzleHttp\json_decode($valueReminder['team_json'], true);
                        $userId = $valueReminder['worksheet_additional_assignee'] != 0 ? $valueReminder['worksheet_additional_assignee'] : $teamJson[10];
                        $userIdArray[] = $userId;
                        $arrangeData[$userId]['reminder'][$valueReminder['entity_id']['trading_name']][] = $data;
                    }
                    
                }
            }

            if (!empty($gettingOverdueWorksheet)) {
                foreach ($gettingOverdueWorksheet as $keyOverdue => $valueOverdue) {
                    $data = array();
                    $data['task'] = $valueOverdue['task_id']['name'];
                    $data['master_activity'] = $valueOverdue['master_activity_id']['name'];
                    $data['start_date'] = dateFormat($valueOverdue['start_date']);
                    $data['end_date'] = dateFormat($valueOverdue['end_date']);
                    $data['due_date'] = dateFormat($valueOverdue['due_date']);
                    $data['frequency'] = $valueOverdue['frequency_id']['frequency_name'];
                    $data['status'] = $valueOverdue['status_id']['status_name'];
                    if ($valueOverdue['team_json'] != '') {
                        $teamJson = \GuzzleHttp\json_decode($valueOverdue['team_json'], true);
                        $userId = $valueOverdue['worksheet_additional_assignee'] != 0 ? $valueOverdue['worksheet_additional_assignee'] : $teamJson[10];
                        $userIdArray[] = $userId;
                        $arrangeData[$userId]['overdue'][$valueOverdue['entity_id']['trading_name']][] = $data;
                    }
                    
                }
            }

            if (!empty($dueWorksheet)) {
                foreach ($dueWorksheet as $keyDue => $valueDue) {
                    $data = array();
                    $data['task'] = $valueDue['task_id']['name'];
                    $data['master_activity'] = $valueDue['master_activity_id']['name'];
                    $data['start_date'] = dateFormat($valueDue['start_date']);
                    $data['end_date'] = dateFormat($valueDue['end_date']);
                    $data['due_date'] = dateFormat($valueDue['due_date']);
                    $data['frequency'] = $valueDue['frequency_id']['frequency_name'];
                    $data['status'] = $valueDue['status_id']['status_name'];
                    if($valueDue['team_json'] != '') {
                        $teamJson = \GuzzleHttp\json_decode($valueDue['team_json'], true);
                        $userId = $valueReminder['worksheet_additional_assignee'] != 0 ? $valueReminder['worksheet_additional_assignee'] : $teamJson[10];
                        $userIdArray[] = $userId;
                        $arrangeData[$userId]['due'][$valueDue['entity_id']['trading_name']][] = $data;
                    }
                   
                }
            }

            if (!empty($userIdArray)) {
                $userData = \App\Models\User::select('id', 'userfullname', 'email')->whereIn('id', $userIdArray)->get()->toArray();
                $userList = array();
                foreach ($userData as $key => $value) {
                    $userList[$value['id']] = $value;
                }
            }

            if (!empty($arrangeData)) {
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('WORKSHEETREMINDER');
                if ($emailTemplate->is_active == 1) {
                    foreach ($arrangeData as $keyUser => $valueUser) {
                        $table = '';
                        $table .= '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                        foreach ($valueUser as $keyType => $valueType) {
                            $table .= '<tr><td>' . ucfirst($keyType) . ' worksheet</td></tr>';
                            $table .= '<tr><td>';
                            $table .= '<table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                            $table .= '<tr>
                                 <th width="20%">Master activity</th>
                                 <th width="20%">Task</th>
                                 <th width="15%">Frequency</th>
                                 <th width="15%">Period</th>
                                 <th width="15%">Due date</th>
                                 <th width="20%">Status</th>
                             </tr>';
                            foreach ($valueType as $keyEntity => $valueEntity) {
                                $table .= '<tr><td colspan="6">' . $keyEntity . '</td></tr>';
                                foreach ($valueEntity as $keyRecord => $valueRecord) {
                                    $table .= '<tr>
                                 <td>' . $valueRecord['master_activity'] . '</td>
                                 <td>' . $valueRecord['task'] . '</td>
                                 <td>' . $valueRecord['frequency'] . '</td>
                                 <td>' . $valueRecord['start_date'] . ' - ' . $valueRecord['end_date'] . '</td>
                                 <td>' . $valueRecord['due_date'] . '</td>
                                 <td>' . $valueRecord['status'] . '</td>
                             </tr>';
                                }
                                $table .= '</table>';
                            }
                            $table .= '</td></tr>';
                        }
                        $table .= '</table></div>';

                        $data['to'] = $userList[$keyUser]['email'];
                        $data['cc'] = $emailTemplate->cc;
                        $data['bcc'] = $emailTemplate->bcc;
                        $data['subject'] = str_replace('TODAY', date('d-m-Y'), $emailTemplate->subject);
                        $data['content'] = str_replace(array('USERNAME', 'TABLE-ACTION'), array($userList[$keyUser]['userfullname'], $table), $emailTemplate->content);
                        storeMail('', $data);
                    }
                }
            }
        } catch (Exception $ex) {
            $cronName = "Worksheet Reminder";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
