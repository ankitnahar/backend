<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrLatesetting extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:latesetting';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Late setting';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $todayDate = date("Y-m-d", strtotime("-1 days"));
            $formattedDate = date('dS M Y, D', strtotime($todayDate));
            $inOutTime = \App\Models\Backend\HrUserInOuttime::with('user_id:id,userfullname,email')->where('date', $todayDate)->where('punch_type', 0)->where('punch_time', '>', '23:45:00')->groupBy('user_id')->orderBy('punch_time', 'dec')->get()->toArray();
            
            $table = '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
            $table .= '<tr><th width="5%">Sr.no</th><th width="65%" align="left">Staff name</th><th width="20%">Punch out time</th></tr>';
            $i = 1;
            foreach($inOutTime as $key => $value){
                $table .= '<tr>';
                $table .= '<td align="center">'.$i++.'</td>';
                $table .= '<td>'.$value["user_id"]["userfullname"].'</td>';
                $table .= '<td align="center">'.date('g:i A', strtotime($value['punch_time'])).'</td>';
                $table .= '</tr>';
            }
            $table .= '</table></div>';
            
            if(!empty($inOutTime)){
                $emailTempate = \App\Models\Backend\EmailTemplate::getTemplate('LATESITTINGNOTIFICATION');
                if($emailTempate->is_active == 1){
                    $find = array('TDATE', 'TABLE-CONTENT');
                    $replace = array($formattedDate, $table);
                    $data['to'] = $emailTempate->to;
                    $data['cc'] = $emailTempate->cc;
                    $data['bcc'] = $emailTempate->bcc;
                    $data['subject'] = $emailTempate->subject;
                    $data['content'] = str_replace($find, $replace, $emailTempate->content);
                    storeMail('', $data);
                }
            }
        } catch (Exception $ex) {
           $cronName = "HR Late Setting";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
