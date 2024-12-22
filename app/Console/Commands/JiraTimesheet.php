<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class JiraTimesheet extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Jiratimesheet:insert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert jira timesheet';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
             $mailPending = \App\Models\Backend\EmailContent::where("status", "2");
             $content ='';
            if ($mailPending->count() > 0) {
                $mailData['to'] = 'pankaj.k@befree.com.au';
                $content .= '<table><tbody><tr><td>Sr.No.</td><td>Email Subject</td></tr>';
                $mailData['subject'] = "Mail is in pending stage";
                $i = 1;
                foreach ($mailPending->get() as $e) {
                    $content .= '<tr><td>' . $i . '</td><td>' . $e->subject . '</td></tr>';
                    $i++;
                }
                $content .= '</table>';
                $mailData['content'] = html_entity_decode($content);
                cronStoreMail($mailData);
                //\App\Models\Backend\EmailContent::where("status", "2")->update(["status" => 0]);
            }
            $date = date('Y-m-d');
            $workLogs = getWorklogs($date);
            if (!isset($workLogs->issues) || count($workLogs->issues) == 0 || $workLogs->total == 0)
                exit;

            $workLogsIssues = $workLogs->issues;
            $userData = $userEmails = array();
            foreach ($workLogsIssues as $keyIssue => $issue) {

                if (!isset($issue->fields->worklog->worklogs) || count($issue->fields->worklog->worklogs) == 0)
                    continue;

                //Single Issue worklog
                //if worklog more than 20 then get worklog from issues
                if ($issue->fields->worklog->total > 20) {
                    $worklogsObj = getIssueWorklogs($issue->key)->worklogs;
                } else {
                    $worklogsObj = $issue->fields->worklog->worklogs;
                }
                foreach ($worklogsObj as $keyWorklogs => $worklogs) {

                    if (strpos($worklogs->started, $date) === FALSE)
                        continue;

                    /* if (isset($worklogs->author->emailAddress)) {
                      $userEmails[] = "'" . $worklogs->author->emailAddress;

                      $userData[$worklogs->author->emailAddress][] = array("key" => $issue->key,
                      'timeSpentSeconds' => $worklogs->timeSpentSeconds,
                      'comment' => isset($worklogs->comment) ? $worklogs->comment : "");
                      } else { */
                    //$customEmail = $worklogs->author->key . '@befree.com.au';
                    $customEmail = $worklogs->author->accountId;
                    $userEmails[] = "'" . $customEmail . "'";
                    $userData[$customEmail][] = array("key" => $issue->key,
                        'timeSpentSeconds' => $worklogs->timeSpentSeconds,
                        'comment' => isset($worklogs->comment) ? $worklogs->comment : "");
                    //}
                }
            }

            $userEmails = array_unique($userEmails);
            //$userList = app("db")->select("SELECT id, email FROM `user` WHERE email IN (" . implode(",", $userEmails) . ")");
            $userList = app("db")->select("SELECT id, email,jira_account_id FROM `user` WHERE jira_account_id IN (" . implode(",", $userEmails) . ")");

            $userBDMSData = array();
            foreach ($userList AS $key => $value) {
                $userBDMSData[$value->jira_account_id] = $value->id;
            }
            $worksheet_master_id = 39307;
            $worksheet_id = 188429;
            $subactivity_code = 2318;
            $entity_id = 386;
            foreach ($userData as $key => $val) {
                //find user entry only else continue
                if (!array_key_exists($key, $userBDMSData))
                    continue;

                $user_id = $userBDMSData[$key];
                $hr_detail = app("db")->select("SELECT id FROM `hr_detail` WHERE user_id = " . $user_id . " AND date = '" . $date . "'");

                $notes = array();
                $timeSpentSeconds = 0;

                foreach ($val as $insKey => $insVal) {
                    if ($user_id != 700) {
                        $notes[] = $insVal['key'] . " | " . $insVal['comment'];
                    } else {
                        $notes[] = $insVal['comment'];
                    }
                    $timeSpentSeconds += $insVal['timeSpentSeconds'];
                }

                $unit = $timeSpentSeconds / 360;
                $timesheetNotes = implode("<br/>", $notes);
                $notes = implode("\n", $notes);


                $pendingTimesheet = \App\Models\Backend\PendingTimesheet::where('user_id', $user_id)->where('date', $date);
                if ($pendingTimesheet->count() > 0) {
                    $pendingTimesheet->delete();
                }

                $alreadyTimesheet = \App\Models\Backend\Timesheet::where('worksheet_id', $worksheet_id)->where('subactivity_code', $subactivity_code)->where('user_id', $user_id)->where('date', $date);

                $alreadyFillTimesheet = $alreadyTimesheet->count();
                if ($alreadyFillTimesheet > 0) {
                    $row = $alreadyTimesheet->get();

                    $alreadyExitsTodaysTimesheetDataFirstData = $row[0]['id'];
                    $worksheetId = $row[0]['worksheet_id'];
                    app("db")->table('timesheet')->where('id', $alreadyExitsTodaysTimesheetDataFirstData)->update(['units' => ceil($unit), 'modified_on' => date('Y-m-d H:i:s'), 'notes' => addslashes($notes), 'name_of_employee' => $timesheetNotes, 'modified_by' => app('auth')->guard()->id()]);
                } else {
                    $hr_detail = app("db")->select("SELECT id FROM `hr_detail` WHERE user_id = " . $user_id . " AND date = '" . $date . "'");
                    $hr_detail_id = $hr_detail[0]->id;
                    $insertQuery['user_id'] = $user_id;
                    $insertQuery['hr_detail_id'] = $hr_detail_id;
                    $insertQuery['entity_id'] = $entity_id;
                    $insertQuery['worksheet_id'] = $worksheet_id;
                    $insertQuery['subactivity_code'] = $subactivity_code;
                    $insertQuery['date'] = $date;
                    $insertQuery['units'] = ceil($unit);
                    $insertQuery['notes'] = addslashes($notes);
                    $insertQuery['name_of_employee'] = $timesheetNotes;
                    $insertQuery['is_reviewed'] = 1;
                    $insertQuery['created_on'] = date('Y-m-d H:i:s');
                    \App\Models\Backend\Timesheet::insert($insertQuery);
                }
            }
        } catch (Exception $ex) {
            $cronName = "Jira Timesheet";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
