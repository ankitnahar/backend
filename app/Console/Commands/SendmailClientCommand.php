<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class SendmailClientCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clientemail:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send mail using cron file';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $blankemail = \App\Models\Backend\EmailClientContent::where("content", "=", "''")->where("content", "=", "null");
            // echo $blankemail->toSql();            
            $blankemail->delete();

            $startTime = date("Y-m-d h:i:s");
            $startTimeThree = date('Y-m-d h:i:s', strtotime('-3 minutes', strtotime($startTime)));
            $halfHourAgo = date('Y-m-d h:i:s', strtotime('-90 minutes', strtotime($startTime)));
           /* $sqlUpdateTwo = \App\Models\Backend\EmailClientContent::where("status", "2")
                            ->where("created_on", ">=", "$halfHourAgo")
                            ->where("created_on", "<=", "$startTimeThree")->update(["status" => "0"]);*/


            $emailList = \App\Models\Backend\EmailClientContent::where('status', 0)->where("content", "!=", "''")->limit(100)->get();
            if (!empty($emailList) && count($emailList) > 0) {
                foreach ($emailList as $key => $value) {
                    \App\Models\Backend\EmailClientContent::where("id", $value->id)->update(["status" => 2]);
                    $data = array();
                    if ($value->to_email != '' && $value->subject != '') {
                        $data['from'] = [$value->from_email => $value->from_name];
                        $data['to'] = array_unique(explode(',', $value->to_email));
                        $data['cc'] = $value->cc_email != '' ? array_unique(explode(',', $value->cc_email)) : array();
                        $data['bcc'] = $value->bcc_email != '' ? array_unique(explode(',', $value->bcc_email)) : array();
                        /*if($value->from_email=='payroll@befree.com.au' || $value->from_email=='payroll@maxtax.com.au'){
                            if(!empty($data['bcc'])){
                                array_push($data['bcc'],$value->from_email);
                            }else{
                                $data['bcc'] = array($value->from_email);
                            }
                            array_values(array_unique($data['bcc']));
                        }*/
                        
                        $data['subject'] = $value->subject;
                        $data['content'] = $value->content;
                        $data['attachment'] = $value->attachment != '' ? \GuzzleHttp\json_decode($value->attachment) : array();
                    }
                    if(!empty($data['cc'])){
                        array_values(array_unique($data['cc']));
                    }

                    $emailSend = \Illuminate\Support\Facades\Mail::send([], [], function($message) use ($data) {
                                $message->from($data['from']);
                                $message->replyTo($data['from']);
                                $message->to($data['to']);

                                if (!empty($data['cc']))
                                    $message->cc($data['cc']);

                                if (!empty($data['bcc']))
                                    $message->bcc($data['bcc']);

                                if (!empty($data['attachment'])) {
                                    foreach ($data['attachment'] as $value) {
                                        if(isset($value->path))
                                            $message->attach(storageEfs() . $value->path);
                                        else
                                            $message->attach(storageEfs() . $value);
                                    }
                                }
                                $message->subject($data['subject']);
                                $message->setBody($data['content'], 'text/html');
                            });
                    /* if ($emailSend->failures()) {

                      
                      // return response showing failed emails
                      } else { */

                   $directory = date("Y");
                    $webmail_id = generateRandomString(20);
                    if (!is_dir(storage_path() . '/uploads/email_json/' . $directory)) {
                        mkdir(storage_path() . '/uploads/email_json/' . $directory);
                    }
                    $EmailContent = array("to" => $data['to'], "from" => $data['from'], "cc" => $data['cc'], "bcc" => $data['bcc'],
                        "subject" => $data['subject'], "content" => $data['content'], "webmail_id" => $webmail_id, "attachment" => $data['attachment']);
                    //json_encode($EmailContent);
                    $fp = fopen(storage_path() . '/uploads/email_json/' . $directory . "/" . $value->id . "_" . $webmail_id . '.json', 'w');
                    fwrite($fp, json_encode($EmailContent));
                    fclose($fp);
                    $emailSend = \App\Models\Backend\EmailClientContent::find($value->id);
                    $emailSend->delete();
                    //}
                }
                if (count(\Illuminate\Support\Facades\Mail::failures()) > 0) {
                    foreach (\Illuminate\Support\Facades\Mail::failures() as $email_address) {
                        \App\Models\Backend\BouncedEmail::create([
                      'sent_by' => 1,
                      'sent_on' => date('Y-m-d H:i:s'),
                      'email_subject' => $data['subject'],
                      'content' => $data['content'],
                      'email_to' => $data['to'],
                      'email_cc' => $data['cc'],
                      'status' => 0,
                      'attachment' => $data['attachment'],
                      'failure_reason' => $email_address
                      ]);
                        //echo " - $email_address <br />";
                    }
                }
            }
        } catch (Exception $ex) {
            app('log')->error("Email content table related error : " . $ex->getMessage());
        }
//        try {
//            $message = 'This is testing data';
//            app('db')->insert("INSERT INTO crontest (message, created_on) VALUES ('".$message."', '".date('Y-m-d H:i:s')."')");
//        } catch (Exception $ex) {
//            
//        }
    }
    
    

}
