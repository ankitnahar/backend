<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;
use ZipArchive;
class HrWelcomeKit extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:welcomekit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hr Welcomkit';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
       // try {
            $firstDayofMonth = date("d");
            $today = date("Y-08-29");
           // if ($firstDayofMonth == "29")
            {
                $startDate = date('Y-08-26', strtotime("-1 month", strtotime($today)));
                $endDate = date('Y-09-25', strtotime($today));
                $userDetail = \App\Models\Backend\WelcomeKitDetail::leftjoin("user as u", "u.id", "welcomekit_detail.user_id")
                        ->where("u.user_joining_date", ">=", $startDate)
                        ->where("u.user_joining_date", "<=", $endDate)
                        ->where("u.is_active", "1")
                        ->where("welcomekit_detail.i_card_status", "0");
               // echo $userDetail->count();exit;
                if ($userDetail->count() > 0) {
                    $zip = new \ZipArchive();
                    $storagePath = storageEfs('/uploads/welcomekit');
                    if (!is_dir($storagePath)) {
                        mkdir($storagePath, 0777, true);
                    }

                    $zipfile = storageEfs() . '/uploads/welcomekit/welcomekitReport' . date('d-m-Y').'.zip';

                    if ($zip->open($zipfile, \ZipArchive::CREATE) === TRUE) {
                        // Add File in ZipArchive
                        foreach ($userDetail->get() as $u) {
                            if ($u->user_image != null) {
                                $document_path = public_path().$u->user_image;
                                $commanFolder = '/uploads/welcomekit/' . date('Y-m');
                                $uploadPath = storageEfs() . $commanFolder;
                                $fileExtention = explode(".",$u->user_image);
                                if (!is_dir($uploadPath))
                                    mkdir($uploadPath, 0777, true);
                                $fileName = 'user-'.$u->user_bio_id.'.'.$fileExtention[1];
                                $path = $uploadPath . '/' .$fileName;
                                $f = copy($document_path , $path);
                                chmod($path, 0777);
                                //$fi = file_put_contents($path, $document_path);
                                $zip->addFile($path, $fileName);
                                
                            }
                        }

                        // Close ZipArchive
                        $zip->close();
                    }
                    \App\Models\Backend\WelcomeKitDocument::create([
                        'document_title' => 'welcomekitReport' . date('29-08-Y'),
                        'document_name' =>'welcomekitReport' . date('29-08-Y'),
                        'document_path' => $zipfile,
                        'is_latest' => 1,
                        'created_by' => '1',
                        'created_on' => date('Y-m-d')
                    ]);
                /*$headers = array('Content-Type' => 'application/octet-stream',
                        'Content-disposition: attachment; filename = ' . $zipfile);
*/
                    $column = array();
                    $column[] = ['Sr.No', 'User Bio Id', 'User name', 'Join Date', 'User Image', 'Department', 'Location', 'Emergency Contact No', 'Emergency Contact Name', 'Blood Group', 'Shirt size', 'Welcome kit Status', 'I-Card Status', 'Created on', 'Created By'];


                    $columnData = array();
                    $i = 1;
                    foreach ($userDetail->get() as $data) {
                        $status = config('constant.welcomekitStatus');
                        $columnData[] = $i;
                        $columnData[] = $data['user_bio_id'];
                        $columnData[] = $data['userfullname'];
                        $columnData[] = $data['user_joining_date'];
                        $columnData[] = 'https://befreecrm.com.au:4100' . $data['user_image'];
                        $columnData[] = $data['department_name'];
                        $columnData[] = $data['location_name'];
                        $columnData[] = $data['Emergency_No_1'];
                        $columnData[] = $data['Name_Emergency_Contact_1'];
                        $columnData[] = $data['Blood_Group'];
                        $columnData[] = $data['shirt_size'];
                        $columnData[] = $status[$data['welcome_kit_status']];
                        $columnData[] = $status[$data['i_card_status']];
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['userfullname'];
                        $columnData[] = $data['modified_on'];
                        $columnData[] = $data['modified_by']['userfullname'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }


                    $zipAttachment = $zipfile;

                    //showArray($data);exit;
                    app('excel')->create('WelcomeKitReport ' . date('d-m-Y'), function($excel) use ($column) {
                                $excel->sheet('WelcomeKitReport', function($sheet) use ($column) {
                                            $sheet->cell('A1:O1', function($cell) {
                                                        $cell->setFontColor('#ffffff');
                                                        $cell->setBackground('#0c436c');
                                                    });

                                            $sheet->getAllowedStyles();
                                            $sheet->fromArray($column, null, 'A1', false, false);
                                        });
                            })->store('xlsx', storageEfs('/templocation/hr/'));
                }
                $hrAttachment = storageEfs('/templocation/hr/WelcomeKitReport ' . date('29-08-Y') . '.xlsx');
                // $request->merge(['exceldownload' => '0']);
                $attachment = array();
                $attachment[] = $hrAttachment;
                //$attachment[] = $zipAttachment;


                $emailData['to'] = 'pankaj.k@befree.com.au';
                $emailData['cc'] = '';
                $emailData['subject'] = 'Welcome Kit Monthly Report' . date('29-08-Y');
                $emailData['content'] = "Hi Team <br/> Please Find Attachment <br/><br/> Thanks befree";
                $emailData['attachment'] = $attachment;

                $sendMail = cronStoreMail($emailData);
                if (!$sendMail) {
                    app('log')->channel('monthlyreport')->error("Ticket monthly report send failed ");
                }
            }
       /* } catch (Exception $ex) {
            $data['to'] = 'bdmsdeveloper@befree.com.au';
            $data['subject'] = 'Hr WelcomeKit cron not run dated: ' . date('d-m-Y H:i:s');
            $data['content'] = '<h3 style="font-family:sans-serif;">Hello Team,</h3><p style="font-family:sans-serif;">Update remark previous day cron does not execute due to below mentioned exception.</p><p style="font-family:sans-serif;">' . $ex->getMessage() . '</p>';
            storeMail('', $data);
        }*/
    }


}
