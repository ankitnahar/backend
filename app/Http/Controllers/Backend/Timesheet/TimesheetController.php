<?php

namespace App\Http\Controllers\Backend\Timesheet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Timesheet;

class TimesheetController extends Controller {
    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Sept 20, 2018
     * Purpose: Get Time sheet detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function index(Request $request) {
//try {
//validate request parameters
        $validator = app('validator')->make($request->all(), [
            'sortOrder' => 'in:asc,desc',
            'pageNumber' => 'numeric|min:1',
            'recordsPerPage' => 'numeric|min:0',
            'search' => 'json'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

// define soring parameters
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        $reviewerList = 0;
        $timesheetList = new Timesheet();
        $selectArray = ['timesheet.id', 'u.userfullname', 'u.user_bio_id', 'e.trading_name as entity_name', 'e.parent_id', 'e.discontinue_stage', 'e.parent_id', "ep.trading_name as parent_name", 'm.id as master_id', 'm.name as master', 't.name as task', 't.id as task_id', 's.subactivity_full_name', 'w.start_date', 'w.end_date', 'f.frequency_name', 'f.id as frequency_id', 'timesheet.*'];
        $totalColum = 'A1:W1';
        if ($request->has('listtype') && $request->get('listtype') == 'reviewer') {
            $reviewerList = 1;
            $selectArray = ['timesheet.id', 'u.userfullname', 'ru.userfullname as reviewername', 'u.user_bio_id', 'e.parent_id', 'e.trading_name as entity_name', 'e.parent_id', "ep.trading_name as parent_name", 'e.discontinue_stage', 'm.id as master_id', 'm.name as master', 't.name as task', 't.id as task_id', 's.subactivity_full_name', 'w.start_date', 'w.end_date', 'f.frequency_name', 'timesheet.*'];
            $timesheetList = $timesheetList->leftjoin('user as ru', 'ru.id', '=', 'timesheet.reviewer_id');
            $totalColum = 'A1:O1';
        } elseif ($request->has('is_checklist_view') && $request->get('is_checklist_view') == 1) {
            $userList = array();
        } else {
            $user = getLoginUserHierarchy();
            $userList = array();
            if ($user->designation_id != config('constant.SUPERADMIN')) {
                $is_right = checkButtonRights(79, 'view_team_timesheet');
                if ($is_right == true) {
                    if ($user->department_id == 14) {
                        $userList = \App\Models\User::select(app('db')->raw('id'), 'userfullname')->where('first_approval_user', $user->user_id)->Orwhere('second_approval_user', $user->user_id)->Orwhere('id', $user->user_id)->get()->toArray();
                    } else {
                        $userId = getUserDownDesignationEmployee();
                        $timesheetList = $timesheetList->whereRaw('timesheet.user_id IN(' . implode(",", $userId) . ")");
                        $userList = \App\Models\User::whereRaw('id IN(' . implode(",", $userId) . ')')->select('userfullname', 'id')->get()->toArray();
                    }
                } else {
                    $timesheetList = $timesheetList->where('timesheet.user_id', app('auth')->guard()->id());
                    $userList = array(array('id' => app('auth')->guard()->id(), 'userfullname' => app('auth')->guard()->user()->userfullname));
                }
            } else {
                $userList = \App\Models\User::select('userfullname', 'id')->get()->toArray();
            }
        }


        $timesheetList = $timesheetList->select($selectArray)
                ->leftjoin("entity as e", "e.id", "timesheet.entity_id")
                ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                ->leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                ->leftJoin('user as u', 'u.id', '=', 'timesheet.user_id')
                ->leftJoin('master_activity as m', 'm.id', '=', 'w.master_activity_id')
                ->leftJoin('task as t', 't.id', '=', 'w.task_id')
                ->leftJoin('subactivity as s', 's.subactivity_code', '=', 'timesheet.subactivity_code')
                ->leftJoin('frequency as f', 'f.id', '=', 'timesheet.frequency_id');

        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('created_on' => 'timesheet', 'subactivity_code' => 'timesheet',
                'entity_id' => 'timesheet',
                'master_activity_id' => 'w',
                'service_id' => 'w',
                'task_id' => 'w',
                'start_date' => 'w',
                'end_date' => 'w', "parent_id" => "e");
            $timesheetList = search($timesheetList, $search, $alias);
        }
        $timesheetList = $timesheetList->orderBy($sortBy, $sortOrder);
// Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $timesheetList = $timesheetList->orderBy($sortBy, $sortOrder)->get();
        } else if ($request->has('counter') && $request->get('counter') == 1) {
            $totalRecords = $timesheetList->count();
            return createResponse(config('httpResponse.SUCCESS'), "Review timesheet count.", ['data' => $totalRecords]);
        } else { // Else return paginated records
// Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

//count number of total records
            $totalRecords = $timesheetList->count();

            $timesheetList = $timesheetList->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $timesheetList = $timesheetList->get();

            $filteredRecords = count($timesheetList);

            $pager = ['sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        if ($request->has('excel') && $request->get('excel') == 1) {
            $data = $timesheetList->toArray();
            $billingStatus = config('constant.WIPInvoiceBillingStatus');
            $entityDiscontinueStage = config('constant.entitydiscontinuestage');
            $subactivityCode = config('constant.subactivityCode');

            $column = array();
            $column[0] = ['Sr.No', 'Bio Metric ID', 'Staff name', 'Parent Trading name', 'Client name', 'Master activity', 'Task', 'Subactivity', 'Period', 'Units', 'Notes', 'Timesheet date', 'Bank cc name', 'Bank cc number', 'Period startdate', 'Period enddate', 'Number', 'Frequency', 'Year', 'No of transaction', 'No of employee', 'Billing status', 'Client stage'];
            if ($reviewerList == 1)
                $column[0] = ['Sr.No', 'Bio Metric ID', 'Staff name', 'Parent Trading name', 'Client name', 'Master activity', 'Task', 'Subactivity', 'Period', 'Units', 'Notes', 'Timesheet date', 'Reviewer', 'Billing status', 'Client stage'];
            ini_set('max_execution_time', '0');
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $value) {
                    $numberOfTransation = $numberOfPayslip = $numberOfEmployee = $numberOfyear = $number = '-';
                    if (in_array($value['subactivity_code'], $subactivityCode['numberOfTransation']))
                        $numberOfTransation = $value['no_of_value'];
                    /* if (in_array($value['subactivity_code'], $subactivityCode['numberOfPayslip']))
                      $numberOfPayslip = $value['no_of_value']; */
                    if (in_array($value['subactivity_code'], $subactivityCode['numberOfPayslip']))
                        $numberOfPayslip = $value['extra_value'];
                    if (in_array($value['subactivity_code'], $subactivityCode['numberOfEmployee']))
                        $numberOfEmployee = $value['no_of_value'];

                    if (in_array($value['subactivity_code'], $subactivityCode['numberOfyear']))
                        $numberOfyear = $value['extra_value'];
                    /* if (in_array($value['subactivity_code'], $subactivityCode['number']))
                      $number = $value['extra_value']; */
                    if (in_array($value['subactivity_code'], $subactivityCode['number']))
                        $number = $value['no_of_value'];

                    $columnData[] = $i;
                    $columnData[] = $value['user_bio_id'];
                    $columnData[] = $value['userfullname'];
                    $columnData[] = $value['parent_name'];
                    $columnData[] = $value['entity_name'];
                    $columnData[] = $value['master'];
                    $columnData[] = $value['task_id'];
                    $columnData[] = $value['subactivity_full_name'];
                    $columnData[] = dateFormat($value['start_date']) . ' - ' . dateFormat($value['end_date']);
                    $columnData[] = $value['units'];
                    $columnData[] = 'Notes:-' . $value['notes'];
                    $columnData[] = dateFormat($value['date']);
                    if ($reviewerList == 0) {
                        $columnData[] = $value['bank_cc_name'] != '' ? $value['bank_cc_name'] : '-';
                        $columnData[] = $value['bank_cc_account_no'] != '' ? $value['bank_cc_account_no'] : '-';
                        $columnData[] = $value['period_startdate'] != '0000-00-00' ? dateFormat($value['period_startdate']) : '-';
                        $columnData[] = $value['period_enddate'] != '0000-00-00' ? dateFormat($value['period_enddate']) : '-';
                        $columnData[] = $number;
                        $columnData[] = $value['frequency_name'] != '' ? $value['frequency_name'] : '-';
                        $columnData[] = $numberOfyear;
                        $columnData[] = $numberOfTransation;
                        $columnData[] = $numberOfEmployee;
                    } else {
                        $columnData[] = $value['reviewername'] != '' ? $value['reviewername'] : '-';
                    }
                    $columnData[] = isset($billingStatus[$value['billing_status']]) ? $billingStatus[$value['billing_status']] : '-';
                    $columnData[] = isset($entityDiscontinueStage[$value['discontinue_stage']]) ? $entityDiscontinueStage[$value['discontinue_stage']] : '-';
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'Attendance summary', 'xlsx', $totalColum);
        }
        return createResponse(config('httpResponse.SUCCESS'), "Timesheet list.", ['data' => $timesheetList, 'userList' => $userList], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Timesheet listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Timesheet", ['error' => 'Server error.']);
//        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Sept 21, 2018
     * Purpose: Store time sheet details
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function store(Request $request) {
//try {
//validate request parameters
        $validator = app('validator')->make($request->all(), [
            'date' => 'date_format:"Y-m-d"|required',
            'worksheet_id' => 'required|numeric',
            //'service_id' => 'numeric',
            'entity_id' => 'required|numeric',
            'subactivity_code' => 'required|numeric',
            'units' => 'required|numeric',
            'user_id' => 'numeric',
            'bank_cc_account_no' => 'numeric',
            'period_startdate' => 'date_format:"Y-m-d"',
            'period_enddate' => 'date_format:"Y-m-d"',
            'is_export' => 'required|in:0,1',
            'name_of_employee' => 'json'], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        $day = date("d");
        $date = $request->get('date');
        $todayDate = date('Y-m-d');
        if ($day > 27) {            
            $startDate = date('Y-m-26');
            $endDate = date('Y-m-25', strtotime("+1 month", strtotime($todayDate)));
        } else {
            $startDate = date('Y-m-26', strtotime("-1 month", strtotime($todayDate)));
            $endDate = date('Y-m-28');
        }
        if ($date >= $startDate && $date <= $endDate) {
            $worksheet_id = $request->get('worksheet_id');
            $service_id = $request->get('service_id');
            if (!$request->has('service_id') || $service_id != '') {
                $worksheetDetail = \App\Models\Backend\Worksheet::select('master_activity_id', 'service_id', 'worksheet_reviewer')->find($worksheet_id);
                $service_id = $worksheetDetail->service_id;
            }

            $user_id = app('auth')->guard()->id();
            if ($request->has('user_id') && $request->get('user_id') != '')
                $user_id = $request->get('user_id');


            $code = $request->get('subactivity_code');
            $hr_detail = \App\Models\Backend\HrDetail::select('id')->where('user_id', $user_id)->where('date', $date);
            if ($hr_detail->count() == 0) {
                $userShift = \App\Models\User::where('id', $user_id)->where("is_active", "1")->first();
                \App\Http\Controllers\Backend\Hr\HRController::addHRDetailForUser($user_id, $date, $userShift->shift_id);
            }
            $hr_detail = $hr_detail->first();
            $subactivityCode = config('constant.subactivityCode');

//            if ($request->has('no_of_transaction') && $request->get('no_of_transaction') != '' && in_array($code, $subactivityCode['numberOfTransation']))
//                $numberOfValue = $request->get('no_of_transaction');
//            else if ($request->has('no_of_payslip') && $request->get('no_of_payslip') != '' && in_array($code, $subactivityCode['numberOfPayslip']))
//                $numberOfValue = $request->get('no_of_payslip');
//            else if ($request->has('no_of_employee') && $request->get('no_of_employee') != '' && in_array($code, $subactivityCode['numberOfEmployee']))
//                $numberOfValue = $request->get('no_of_employee');
//            else
//                $numberOfValue = 0;

            $numberOfValue = 0;
            if ($request->has('no_of_value') && $request->get('no_of_value') != '')
                $numberOfValue = $request->get('no_of_value');

            if ($request->has('number') && $request->get('number') != '' && in_array($code, $subactivityCode['number']))
                $extraValue = $request->get('number');
            else if ($request->has('no_of_year') && $request->get('no_of_year') != '' && in_array($code, $subactivityCode['numberOfyear']))
                $extraValue = $request->get('no_of_year');
            else
                $extraValue = 0;

            $bkFlagForChecklist = 0;
            if (in_array($worksheetDetail->master_activity_id, [5, 23]))
                $bkFlagForChecklist = 1;

            if ($request->has('reviewer_id') && $request->get('reviewer_id') != '')
                $reviewerId = $request->get('reviewer_id');
            else if (isset($worksheetDetail->worksheet_reviewer) && $worksheetDetail->worksheet_reviewer != '')
                $reviewerId = $worksheetDetail->worksheet_reviewer;
            else
                $reviewerId = 0;

//            if ($request->has('chargeable_type') && $request->get('chargeable_type') != '')
//                $payrollOptionId = $request->get('chargeable_type');
//            else if ($request->has('superfund_type') && $request->get('superfund_type') != '')
//                $payrollOptionId = $request->get('superfund_type');
//            else
//                $payrollOptionId = 0;

            $payrollOptionId = 0;
            if ($request->has('payroll_option_id') && $request->get('payroll_option_id') != '')
                $payrollOptionId = $request->get('payroll_option_id');

            $nameOfEmployee = '';
            if ($request->has('name_employee') && $request->get('name_employee') != '') {
                $nameOfEmployee = $request->get('name_employee');
                if ($nameOfEmployee == '[]')
                    $nameOfEmployee = '';
            }

            if ($request->has('is_request_from_checklist') && $request->get('is_request_from_checklist') == 1)
                $is_reviewed = 0;
            else
                $is_reviewed = !in_array($code, [301, 404, 405, 413, 414, 416, 417, 422, 425, 448, 460, 462, 463, 464, 466, 468, 470, 2508, 701, 713, 714, 715, 716]) ? 1 : 0;


            $bankName = $bankAccountNumber = '';
            if ($request->has('bank_info') && $request->get('bank_info') != '') {
                $explodeBank = explode(':', $request->get('bank_info'));
                $bankName = $explodeBank[0];
                $bankAccountNumber = $explodeBank[1];
            }

            $frequency_id = $request->get('frequency_id');
            if ($frequency_id == '')
                $frequency_id = $request->get('worksheet_frequency_id');

            $timesheetData = array();
            $timesheetData['hr_detail_id'] = $hr_detail->id;
            $timesheetData['worksheet_id'] = $worksheet_id;
            $timesheetData['service_id'] = $service_id;
            $timesheetData['entity_id'] = $request->get('entity_id');
            $timesheetData['user_id'] = $user_id;
            $timesheetData['worksheet_frequency_id'] = $request->get('worksheet_frequency_id');
            $timesheetData['frequency_id'] = $frequency_id;
            $timesheetData['subactivity_code'] = $code;
            $timesheetData['date'] = $date;
            $timesheetData['start_time'] = $request->get('start_time');
            $timesheetData['end_time'] = $request->get('end_time');
            $timesheetData['units'] = $request->get('units');
            $timesheetData['notes'] = $request->get('notes');
            $timesheetData['bank_cc_name'] = $bankName;
            $timesheetData['bank_cc_account_no'] = $bankAccountNumber;
            $timesheetData['period_startdate'] = $request->get('period_startdate');
            $timesheetData['period_enddate'] = $request->get('period_enddate');
            $timesheetData['is_reviewed'] = $is_reviewed;
            $timesheetData['extra_value'] = $extraValue;
            $timesheetData['no_of_value'] = $numberOfValue;
            $timesheetData['name_of_employee'] = $nameOfEmployee;
            $timesheetData['payroll_option_id'] = $payrollOptionId;
            $timesheetData['reviewer_id'] = $reviewerId;
            //$timesheetData['subclient_id'] = $request->get('subclient_id');
            $timesheetData['bk_flag_for_checklist'] = $bkFlagForChecklist;
            $timesheetData['created_by'] = app('auth')->guard()->id();
            $timesheetData['created_on'] = date('Y-m-d H;i:s');

// Used in Payroll worksheet only
            if ($request->get('is_export') == 1 && $service_id == 2) {
                $worksheetDetail = explode("::", $request->get('task_id'));
                $timesheetData['related_worksheet_id'] = $worksheet_id;
                $timesheetData['worksheet_id'] = $worksheetDetail[1];
            }

// While timesheet review by reviewer 
            if ($request->has('is_reviewed_timesheet') && $request->get('is_reviewed_timesheet') == 1) {
                $timesheetId = $request->get('timesheet_id');
//app('db')->table('timesheet')->where('id', $timesheetId)->update(['is_reviewed' => 0]);
                app('db')->table('timesheet')->where('id', $timesheetId)->update(['is_reviewed' => 1]);
                $timesheetData['review_subcode'] = $request->get('review_subcode');
            }
            $timesheet = Timesheet::create($timesheetData);
            $curretDate = date('Y-m-d');
            if ($date != $curretDate) {
                \App\Http\Controllers\Backend\Hr\AttendanceController::updateRemarkTimesheetAdd($date, $user_id);
                \App\Models\Backend\PendingTimesheet::where("date", $date)->where("user_id", $user_id)->delete();
            }

            if ($request->has('is_pendingtimesheet_id') && $request->get('is_pendingtimesheet_id') > 0) {
                \App\Models\Backend\PendingTimesheet::where('id', $request->get('is_pendingtimesheet_id'))->update(['is_hide' => 0, "stage_id" => 1]);
                \App\Http\Controllers\Backend\Hr\AttendanceController::autoApprove($request->get('is_pendingtimesheet_id'));
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Timesheet has been added successfully', ['data' => $timesheet]);
        } else {
            return createResponse(config('httpResponse.UNPROCESSED'), 'You can not update previous month data', ['error' => 'You can not update previous month data']);
        }
//        } catch (\Exception $e) {
//            app('log')->error("Timesheet creation failed " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add timesheet', ['error' => 'Could not add timesheet']);
//        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Sept 20, 2018
     * Purpose: Get particular time sheet details
     * @param  int  $id   //timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function show($id) {
        try {
            $timesheetDetail = Timesheet::with('frequencyId:id,frequency_name', 'subactivityCode:id,subactivity_full_name', 'payrollOptionId:id,type_name', 'reviewerId:id,userfullname')->find($id);

            if (empty($timesheetDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Timesheet does not exist', ['error' => 'Tmiesheet does not exist']);


            return createResponse(config('httpResponse.SUCCESS'), 'Timesheet detail successfully load.', ['data' => $timesheetDetail]);
        } catch (\Exception $e) {
            app('log')->error("Timesheet details api failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get timesheet detail.', ['error' => 'Could not get timesheet detail.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Sept 20, 2018 
     * Purpose: update time sheet details
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function update(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'number_selection' => 'numeric',
            'frequency_id' => 'numeric',
            'no_of_transation' => 'numeric',
            'no_of_employee' => 'numeric',
            'no_of_payslip' => 'numeric',
            'period_startdate' => 'date_format:"Y-m-d"',
            'period_enddate' => 'date_format:"Y-m-d"',
            'date' => 'date_format:"Y-m-d"',
            'units' => 'numeric',
            'name_of_employee' => 'json'
                ], []);

// If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $timesheetDetail = Timesheet::find($id);

        if (!$timesheetDetail)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Timesheet does not exist', ['error' => 'Timesheet does not exist']);

        $updateData = array();
        $date = $request->input('date');
        $day = date("d");
        $todayDate = date('Y-m-d');
        if ($day > 27) {
            $startDate = date('Y-m-26');
            $endDate = date('Y-m-25', strtotime("+1 month", strtotime($todayDate)));
        } else {
            $startDate = date('Y-m-26', strtotime("-1 month", strtotime($todayDate)));
            $endDate = date('Y-m-28');
        }
        if ($date >= $startDate && $date <= $endDate) {
// Filter the fields which need to be updated
            $updateData = filterFields(['entity_id', 'user_id', 'subactivity_code', 'date', 'start_time', 'end_time', 'units', 'notes', 'period_startdate', 'period_enddate', 'frequency_id', 'name_of_employee', 'payroll_option_id'], $request);

            $subactivityCode = config('constant.subactivityCode');

            $bankName = $bankAccountNumber = '';
            if ($request->has('bank_info') && $request->get('bank_info') != '') {
                $explodeBank = explode(':', $request->get('bank_info'));
                $updateData['bank_cc_name'] = $explodeBank[0];
                $updateData['bank_cc_account_no'] = $explodeBank[1];
            }


//            if ($request->has('no_of_transaction') && $request->get('no_of_transaction') != '' && in_array($code, $subactivityCode['numberOfTransation']))
//                $numberOfValue = $request->get('no_of_transaction');
//            else if ($request->has('no_of_payslip') && $request->get('no_of_payslip') != '' && in_array($code, $subactivityCode['numberOfPayslip']))
//                $numberOfValue = $request->get('no_of_payslip');
//            else if ($request->has('no_of_employee') && $request->get('no_of_employee') != '' && in_array($code, $subactivityCode['numberOfEmployee']))
//                $numberOfValue = $request->get('no_of_employee');
//            else
//                $numberOfValue = 0;

            $numberOfValue = 0;
            if ($request->has('no_of_value') && $request->get('no_of_value') != '')
                $numberOfValue = $request->get('no_of_value');

//            if ($request->has('number') && $request->get('number') != '' && in_array($code, $subactivityCode['number']))
//                $extraValue = $request->get('number');
//            else if ($request->has('no_of_year') && $request->get('no_of_year') != '' && in_array($code, $subactivityCode['numberOfyear']))
//                $extraValue = $request->get('no_of_year');
//            else
//                $extraValue = 0;

            $extraValue = 0;
            if ($request->has('extra_value') && $request->get('extra_value') != '') {
                $extraValue = $request->get('extra_value');
            }

            $nameOfEmployee = '';
            if ($request->has('name_employee') && $request->get('name_employee') != '') {
                $nameOfEmployee = json_encode($request->get('name_employee'));
                if ($nameOfEmployee == '[]')
                    $nameOfEmployee = '';
            }

            if ($request->has('reviewer_id') && $request->get('reviewer_id') != '')
                $reviewerId = $request->get('reviewer_id');
            else if (isset($worksheetDetail->worksheet_reviewer) && $worksheetDetail->worksheet_reviewer != '')
                $reviewerId = $worksheetDetail->worksheet_reviewer;
            else
                $reviewerId = 0;

            $updateData['reviewer_id'] = $reviewerId;
            $updateData['extra_value'] = $extraValue;
            $updateData['no_of_value'] = $numberOfValue;
            $updateData['name_of_employee'] = $nameOfEmployee;
            $updateData['modified_by'] = app('auth')->guard()->id();
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $timesheetDetail->update($updateData);
            $curretDate = date('Y-m-d');
            if ($date != $curretDate) {
                $user_id = $request->input('user_id');
                \App\Http\Controllers\Backend\Hr\AttendanceController::updateRemarkTimesheetAdd($date, $user_id);
                \App\Models\Backend\PendingTimesheet::where("date", $date)->where("user_id", $user_id)->delete();
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Timesheet has been updated successfully', ['message' => 'Timesheet has been updated successfully']);
        } else {
            return createResponse(config('httpResponse.UNPROCESSED'), 'You can not update previous month data', ['error' => 'You can not update previous month data']);
        }
        /* } catch (\Exception $e) {
          app('log')->error("Timesheet updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update timesheet details.', ['error' => 'Could not update timesheet details.']);
          } */
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Sept 20, 2018 
     * Purpose: update time sheet details
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function destroy(Request $request, $id) {
        try {
            $timesheetDetail = Timesheet::find($id);
// Check weather client exists or not
            if (!isset($timesheetDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Timesheet does not exist', ['error' => 'Timesheet does not exist']);

            $timesheetDetail->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Timesheet has been deleted successfully', ['message' => 'Timesheet has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Timesheet deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete timesheet.', ['error' => 'Could not delete timesheet.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Sept 20, 2018 
     * Purpose: fetch worksheet listing
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function worksheet(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'master_activity_id' => 'required|numeric',
                'task_id' => 'required|numeric',
                'frequency_id' => 'required|numeric'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $entity_id = $request->get('entity_id');
            $master_activity_id = $request->get('master_activity_id');
            $task_id = $request->get('task_id');
            $frequency_id = $request->get('frequency_id');

            $worksheet = \App\Models\Backend\Worksheet::select("id", app('db')->raw("CONCAT(DATE_FORMAT(start_date, '%d/%m/%Y'), ' - ', DATE_FORMAT(end_date, '%d/%m/%Y')) AS period"))
                            ->where('entity_id', $entity_id)
                            ->where('master_activity_id', $master_activity_id)
                            ->where('task_id', $task_id)
                            ->where('frequency_id', $frequency_id)->pluck('period', 'id')->toArray();

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet listout successfully', ['data' => $worksheet]);
        } catch (\Exception $e) {
            app('log')->error("Timesheet deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not list out worksheet.', ['error' => 'Could not list out worksheet.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Sept 20, 2018 
     * Purpose: fetch worksheet listing
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function AssingEntity(Request $request) {
        try {
            $user_id = app('auth')->guard()->id();
            $entityAllocation = checkUserClientAllocation($user_id);
            $entity = array();
            if ($entityAllocation != 1) {
                $entity = \App\Models\Backend\Entity::where('discontinue_stage', '!=', '2')->whereIn('id', $entityAllocation)->pluck('trading_name', 'id')->toArray();
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet client list successfully', ['data' => $entity]);
        } catch (\Exception $e) {
            app('log')->error("Timesheet deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not list out worksheet client.', ['error' => 'Could not list out worksheet client.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Sept 20, 2018 
     * Purpose: fetch worksheet master activity listing based on entity
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function entityMasterActivity(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $entity_id = $request->get('entity_id');
            $entityMasterActivity = \App\Models\Backend\Worksheet::where('entity_id', $entity_id)
                            ->leftjoin('master_activity AS ma', 'ma.id', '=', 'worksheet.master_activity_id')
                            ->groupBy('worksheet.master_activity_id')
                            ->pluck('ma.name', 'worksheet.master_activity_id')->toArray();

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet listout successfully', ['data' => $entityMasterActivity]);
        } catch (\Exception $e) {
            app('log')->error("Timesheet deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not list out worksheet.', ['error' => 'Could not list out worksheet.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Sept 20, 2018 
     * Purpose: fetch worksheet master activity listing based on entity
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function entityBankInfo(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $entity_id = $request->get('entity_id');
            $entityMasterActivity = \App\Models\Backend\EntityBankInfo::select('entity_bank_info.id', 'entity_bank_info.account_no', 'b.bank_name', app('db')->raw("CONCAT(b.bank_name,'-',bt.type_name,'-',entity_bank_info.account_no) AS bankdetail"), app('db')->raw("CONCAT(b.bank_name,':',entity_bank_info.account_no) AS bankname"))
                            ->leftjoin('bank_type AS bt', 'bt.id', '=', 'entity_bank_info.type_id')
                            ->leftjoin('banks AS b', 'b.id', '=', 'entity_bank_info.bank_id')
                            ->where('entity_bank_info.entity_id', $entity_id)
                            ->where('entity_bank_info.is_active', 1)
                            ->get()->toArray();

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet listout successfully', ['data' => $entityMasterActivity]);
        } catch (\Exception $e) {
            app('log')->error("Timesheet deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not list out worksheet.', ['error' => 'Could not list out worksheet.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Sept 22, 2018 
     * Purpose: fetch subactivity code payroll option
     * @param  $id 
     * @return Illuminate\Http\JsonResponse
     */

    public function payrollOption($code) {
        try {
            $payrollOption = \App\Models\Backend\TimesheetPayrollOption::where('subcategory_code', $code)->get()->toArray();

            if (empty($payrollOption))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Worksheet payroll option does not exist', ['error' => 'Worksheet payroll option does not exist']);

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet payroll option load successfully', ['data' => $payrollOption]);
        } catch (\Exception $e) {
            app('log')->error("Timesheet deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not list out worksheet payroll option.', ['error' => 'Could not list out worksheet.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Sept 25, 2018 
     * Purpose: fetch time sheet summary listing
     * @param  $id 
     * @return Illuminate\Http\JsonResponse
     */

    public function timesheetSummary(Request $request) {
        try {
//validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $timesheetSummary = \App\Models\Backend\TimesheetSummary::with('userId:id,userfullname', 'divisionHead:id,userfullname', 'technicalAccountManager:id,userfullname');
            if ($request->has('search')) {
                $search = $request->get('search');
                $timesheetSummary = search($timesheetSummary, $search);
            }

// Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $timesheetSummary = $timesheetSummary->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
// Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

//count number of total records
                $totalRecords = $timesheetSummary->count();

                $timesheetSummary = $timesheetSummary->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $timesheetSummary = $timesheetSummary->get();

                $filteredRecords = count($timesheetSummary);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $timesheetSummary->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Timesheet period', 'Username', 'Technical account manager', 'Division head', 'User writeoff%', 'Total unit', 'Client unit', 'Befree unit', 'Created on'];

                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $value) {
                        $columnData[] = $i;
                        $columnData[] = $value['month_year'];
                        $columnData[] = $value['user_id']['userfullname'];
                        $columnData[] = $value['technical_account_manager']['userfullname'] != '' ? $value['technical_account_manager']['userfullname'] : '-';
                        $columnData[] = $value['division_head']['userfullname'] != '' ? $value['division_head']['userfullname'] : '-';
                        $columnData[] = $value['user_writeoff'] != '' ? $value['user_writeoff'] : '-';
                        $columnData[] = $value['total_unit'] != '' ? $value['total_unit'] : '-';
                        $columnData[] = $value['client_unit'] != '' ? $value['client_unit'] : '-';
                        $columnData[] = $value['nonchargeable_unit'] != '' ? $value['nonchargeable_unit'] : '-';
                        $columnData[] = dateFormat($value['created_on']);
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Timesheet summary', 'xlsx', 'A1:J1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Timesheet summary list.", ['data' => $timesheetSummary], $pager);
        } catch (\Exception $e) {
            app('log')->error("Timesheet summary listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing timesheet summary", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Jan 30, 2019
     * To get worksheet details
     */

    public function getTimesheetDetailInfo(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'master_activity_id' => 'numeric',
                'task_id' => 'numeric',
                'frequency_id' => 'numeric'], []);

            $entityId = $request->get('entity_id');
            $masterActivityId = $request->get('master_activity_id');
            $taskId = $request->get('task_id');
            $frequencyId = $request->get('frequency_id');
            $worksheetMaster = new \App\Models\Backend\WorksheetMaster();

            if ($entityId != '' && $masterActivityId != '' && $taskId != '' && $frequencyId != '') {
                $worksheetMaster = $worksheetMaster->select('w.id as worksheet_id', 'w.start_date', 'w.end_date');
                $worksheetMaster = $worksheetMaster->leftJoin('worksheet as w', 'w.worksheet_master_id', '=', 'worksheet_master.id');
                $worksheetMaster = $worksheetMaster->where('worksheet_master.frequency_id', $frequencyId);
                $worksheetMaster = $worksheetMaster->where('worksheet_master.task_id', $taskId);
                $worksheetMaster = $worksheetMaster->where('worksheet_master.master_activity_id', $masterActivityId);
                $worksheetMaster = $worksheetMaster->where('worksheet_master.entity_id', $entityId);
                $worksheetMaster = $worksheetMaster->where('w.status_id', '!=', 4);
            } else if ($entityId != '' && $masterActivityId != '' && $taskId != '') {
                $worksheetMaster = $worksheetMaster->select('id', 'frequency_id');
                $worksheetMaster = $worksheetMaster->with('frequencyId:id,frequency_name');
                $worksheetMaster = $worksheetMaster->where('task_id', $taskId);
                $worksheetMaster = $worksheetMaster->where('master_activity_id', $masterActivityId);
                $worksheetMaster = $worksheetMaster->where('entity_id', $entityId);
                $worksheetMaster = $worksheetMaster->groupBy('frequency_id');
            } else if ($entityId != '' && $masterActivityId != '') {
                $worksheetMaster = $worksheetMaster->select('id', 'task_id');
                $worksheetMaster = $worksheetMaster->with('taskId:id,name');
                $worksheetMaster = $worksheetMaster->where('master_activity_id', $masterActivityId);
                $worksheetMaster = $worksheetMaster->groupBy('task_id');
            } else {
                $worksheetMaster = $worksheetMaster->select('id', 'master_activity_id');
                $worksheetMaster = $worksheetMaster->with('masterActivityId:id,name');
                $worksheetMaster = $worksheetMaster->where('entity_id', $entityId);
                $worksheetMaster = $worksheetMaster->groupBy('master_activity_id');
            }
            $worksheetMaster = $worksheetMaster->get();
            $entityGrouptypeId = \App\Models\Backend\Billing::select('entity_grouptype_id')->where('entity_id', $entityId)->get();

            return createResponse(config('httpResponse.SUCCESS'), 'Timesheet data', ['data' => $worksheetMaster, 'entityGrouptypeId' => isset($entityGrouptypeId[0]->entity_grouptype_id) ? $entityGrouptypeId[0]->entity_grouptype_id : 0]);
        } catch (Exception $ex) {
            app('log')->error("Timesheet deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch timesheet period', ['error' => 'Could not fetch timesheet period']);
        }
    }

    public function getUserlist(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'access_token' => 'required',
                'user_type' => 'required',
//                'username' => 'required',
//                'bdms_user_id' => 'required'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                echo json_encode(array('error' => $validator->errors()->first()));

            if ($request->get('access_token') != '0vBBoaNmkg5Uq88QGWJmvpakDyz3oskUZJKY0Fr04crA3W1RlNdib4tl7x4vrecUEKZVspzqIlfGJ9u3klLLHRM7NdmTKoJaAHLBdqdJPUHZ4sU0Mb1lmkqhyAyQc2IkovxLRecySsFIiHfLP3gRmglyAUw9dxVFQO5Fu9IeUOCqEPmaHr276Ve8FJSx64PfNzcQ4gnhieyHzQBOkt6OwY2pojjccHuHs9tWNuhCBPBkkZHDYEJEpdgz0xCGlWZ') {
                $response = array('result' => 'failed', 'message' => 'invalid access token');
                echo json_encode($response);
                exit;
            }

            /* To check IP address is accessable or not. If not accessable then should be return result with invalid IP address */
            $isAccess = \App\Models\Backend\IpAddress::whereRaw("from_ip = INET_ATON('" . $_SERVER['REMOTE_ADDR'] . "') OR (from_ip <= INET_ATON('" . $_SERVER['REMOTE_ADDR'] . "') AND to_ip >= INET_ATON('" . $_SERVER['REMOTE_ADDR'] . "'))")->count();
            if ($isAccess == 0) {
                $response = array('result' => 'failed', 'message' => 'invalid IP address');
                echo json_encode($response);
                exit;
            }

            $userDetail = \App\Models\User::where('user_timesheet_fillup_flag', 0);
            if ($request->get('bdms_user_id') != '') {
                $userDetail = $userDetail->where('id', $request->get('bdms_user_id'));
            } else {
                $userDetail = $userDetail->whereRaw("(user_fname LIKE '%" . $request->get('username') . "%' OR user_lname LIKE '%" . $request->get('username') . "%')");
            }

            if ($request->get('user_type') != '') {
                $userDetail = $userDetail->where('is_active', 1);
            } else {
                $userDetail = $userDetail->where('is_active', 0);
            }

            $userDetail = $userDetail->get();
            $response = array();
            if (!empty($userDetail)) {
                $isActive = 'No';
                foreach ($userDetail as $key => $value) {
                    if ($value->is_active == 1)
                        $isActive = 'Yes';

                    $response[] = array('username' => $value->user_fname . " " . $value->user_lname, 'email' => $value->email, 'user_id' => $value->id, 'is_active' => $isActive);
                }
                echo json_encode($response);
                exit;
            }
        } catch (Exception $ex) {
            app('log')->error("Getuser list fetch failed : " . $e->getMessage());
            echo createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch user details', ['error' => 'Could not fetch user details']);
        }
    }

    public function getTimesheetFromPortal(Request $request) {
        //try {
        if ($request->get('access_token') != "" && $request->get('timesheet_data') != "" && $request->get('type') != '') {
            try {
                /* To check access token is valid or not. If not valid then should be return result with invalid access token */
                if ($request->get('access_token') != '0vBBoaNmkg5Uq88QGWJmvpakDyz3oskUZJKY0Fr04crA3W1RlNdib4tl7x4vrecUEKZVspzqIlfGJ9u3klLLHRM7NdmTKoJaAHLBdqdJPUHZ4sU0Mb1lmkqhyAyQc2IkovxLRecySsFIiHfLP3gRmglyAUw9dxVFQO5Fu9IeUOCqEPmaHr276Ve8FJSx64PfNzcQ4gnhieyHzQBOkt6OwY2pojjccHuHs9tWNuhCBPBkkZHDYEJEpdgz0xCGlWZ') {
                    $response = array('result' => 'failed', 'message' => 'invalid access token');
                    echo json_encode($response);
                    exit;
                }

                /* To check IP address is accessable or not. If not accessable then should be return result with invalid IP address */
                /* $isAccess = \App\Models\Backend\IpAddress::whereRaw("from_ip = INET_ATON('" . $_SERVER['REMOTE_ADDR'] . "') OR (from_ip <= INET_ATON('" . $_SERVER['REMOTE_ADDR'] . "') AND to_ip >= INET_ATON('" . $_SERVER['REMOTE_ADDR'] . "'))")->count();
                  if ($isAccess == 0) {
                  $response = array('result' => 'failed', 'message' => 'invalid IP address');
                  echo json_encode($response);
                  exit;
                  } */
                $worksheet_id = 259615;
                $subactivity_code = 2308;
                if ($request->get('type') == 'SR_PORTAL') {
                    $entity_id = 386;
                } else if ($request->get('type') == 'UK_PORTAL') {
                    $entity_id = 4329;
                } else if ($request->get('type') == 'AUDIT_PORTAL') {
                    $entity_id = 386;
                }

                $concatId = $response = array();
                $timesheetData = $request->input('timesheet_data');

                $timesheetremove = $concatId = $response = $userRes = array();
                foreach ($timesheetData as $key => $value) {
                    $hrDetail = app('db')->table('hr_detail')->select('id', 'punch_in')->where('date', $value['date'])->where('user_id', $value['bdms_user_id']);
                    if ($hrDetail->count() == 0) {
                        \App\Http\Controllers\Backend\Hr\HRController::addHrDetail($value['date']);
                    }
                    $hrDetail = $hrDetail->get()->toArray();
                    if ($hrDetail[0]->punch_in == NULL) {
                        continue;
                    }
                    $timesheetInsertData = $updateData = $timesheetPortalData = array();

                    $user_id = $value['bdms_user_id'];
                    $notes = trim(stripcslashes($value['comment']));
                    $units = trim($value['units']);
                    $date = trim($value['date']);
                    $timesheetPortalData['prev_bdms_timesheet_id'] = $value['prev_bdms_timesheet_id'];
                    $timesheetPortalData['hr_detail_id'] = $hrDetail[0]->id;
                    $timesheetPortalData['worksheet_id'] = $worksheet_id;
                    $timesheetPortalData['entity_id'] = $entity_id;
                    $timesheetPortalData['service_id'] = 0;
                    $timesheetPortalData['subactivity_code'] = $subactivity_code;
                    $timesheetPortalData['user_id'] = $user_id;
                    $timesheetPortalData['date'] = $date;
                    $timesheetPortalData['units'] = $units;
                    $timesheetPortalData['notes'] = $notes;
                    $timesheetPortalData['timesheet_data_sr'] = json_encode($timesheetData);
                    $timesheetPortalData['timesheet_save_units_id'] = isset($value['timesheet_save_units_id']) ? $value['timesheet_save_units_id'] : 'Field not come through SR';
                    $timesheetPortalData['created_on'] = date('Y-m-d H:i:s');
                    $timesheetPortalData['created_by'] = 1;

                    $portaData = app('db')->table('timesheet_portal')->insertGetId($timesheetPortalData);
                    /* $lastMonthDate = date('Y-m-d', strtotime("-1 month"));
                      if ($value['date'] < $lastMonthDate) {
                      continue;
                      } */
                    $item_count = isset($value['item_count']) ? $value['item_count'] : 0;
                    if (isset($value['bdms_user_id']) && trim($value['bdms_user_id']) != "" && isset($value['user_id']) && trim($value['user_id']) != "" && isset($value['date']) && trim($value['date']) != "" && isset($value['units'])) {
                        /*  $timesheetSyncData = array();
                          $timesheetSyncData['bdms_user_id'] = $value['bdms_user_id'];
                          $timesheetSyncData['sr_user_id'] = $value['user_id'];
                          $timesheetSyncData['timesheet_date'] = $value['date'];
                          $timesheetSyncData['timesheet_sync'] = 1;
                          $timesheetSyncData['type'] = 1;
                          $timesheetSyncData['request_txt'] = json_encode($value);
                          $timesheetSyncData['created_on'] = date('Y-m-d H:i:s'); */
                        // $hrDetail = app('db')->table('hr_detail')->select('id')->where('date', $value['date'])->where('user_id', $value['bdms_user_id'])->get()->toArray();

                        /* $timesheetDetail = app('db')->table('timesheet')->select('id')
                          ->where('date', $value['date'])
                          ->where('user_id', $value['bdms_user_id']); */

                        // $timesheetInsertData = $updateData = $timesheetPortalData = array();
                        // $worksheet_master_id = 49111;

                        /* $user_id = $value['bdms_user_id'];
                          $notes = trim($value['comment']);
                          $units = trim($value['units']);
                          $date = trim($value['date']);

                          $timesheetPortalData['prev_bdms_timesheet_id'] = $value['prev_bdms_timesheet_id'];
                          $timesheetPortalData['hr_detail_id'] = $hrDetail[0]->id;
                          $timesheetPortalData['worksheet_id'] = $worksheet_id;
                          $timesheetPortalData['entity_id'] = $entity_id;
                          $timesheetPortalData['service_id'] = 0;
                          $timesheetPortalData['subactivity_code'] = $subactivity_code;
                          $timesheetPortalData['user_id'] = $user_id;
                          $timesheetPortalData['date'] = $date;
                          $timesheetPortalData['units'] = $units;
                          $timesheetPortalData['notes'] = $notes;
                          $timesheetPortalData['timesheet_save_units_id'] = isset($value['timesheet_save_units_id']) ? $value['timesheet_save_units_id'] : 'Field not come through SR';
                          $timesheetPortalData['created_on'] = date('Y-m-d H:i:s');
                          $timesheetPortalData['created_by'] = 1;

                          $portaData = app('db')->table('timesheet_portal')->insertGetId($timesheetPortalData); */
                        $timesheet_id = '';
                        $timesheetInsertData['hr_detail_id'] = $hrDetail[0]->id;
                        $timesheetInsertData['worksheet_id'] = $worksheet_id;
                        $timesheetInsertData['entity_id'] = $entity_id;
                        $timesheetInsertData['service_id'] = 0;
                        $timesheetInsertData['subactivity_code'] = $subactivity_code;
                        $timesheetInsertData['user_id'] = $user_id;
                        $timesheetInsertData['date'] = $date;
                        $timesheetInsertData['units'] = $units;
                        $timesheetInsertData['notes'] = $notes;
                        $timesheetInsertData['is_reviewed'] = 1;
                        $timesheetInsertData['created_on'] = date('Y-m-d H:i:s');
                        $timesheetInsertData['created_by'] = 1;
                        if (isset($hrDetail[0]->id) && $hrDetail[0]->id > 0 && ($value['prev_bdms_timesheet_id'] == 0 || $value['prev_bdms_timesheet_id'] == '')) {

                            $timesheet_id = app('db')->table('timesheet')->insertGetId($timesheetInsertData);
                            $concatId[$value['bdms_user_id']][$value['date']][] = $timesheet_id;
                        } else if (isset($hrDetail[0]->id) && $hrDetail[0]->id > 0 && $value['prev_bdms_timesheet_id'] != 0 && $value['prev_bdms_timesheet_id'] != '') {
                            $timesheet_id = $value['prev_bdms_timesheet_id'];
                            $getTimehseteDetail = Timesheet::where("id", $timesheet_id);
                            if ($getTimehseteDetail->count() > 0) {
                                $getTimehseteDetail = $getTimehseteDetail->first();
                                if ($getTimehseteDetail->user_id == $user_id) {
                                    app('db')->table('timesheet')->where("id", $timesheet_id)->update(["units" => $units, "notes" => $notes, "modified_on" => date('Y-m-d H:i:s'), "modified_by" => 1]);
                                } else {
                                    $timesheet_id = app('db')->table('timesheet')->insertGetId($timesheetInsertData);
                                    $concatId[$value['bdms_user_id']][$value['date']][] = $timesheet_id;
                                }
                            } else {

                                $timesheet_id = app('db')->table('timesheet')->insertGetId($timesheetInsertData);
                                $concatId[$value['bdms_user_id']][$value['date']][] = $timesheet_id;
                            }
                        }
                        $curretDate = date('Y-m-d');
                        if ($date != $curretDate) {
                            $day = date('d');
                            if ($day > 27) {
                                $startDate = date('Y-m-26');
                                $endDate = date('Y-m-25', strtotime("+1 month", strtotime($curretDate)));
                            } else {
                                $startDate = date('Y-m-26', strtotime("-1 month", strtotime($curretDate)));
                                $endDate = date('Y-m-28');
                            }
                            if ($date >= $startDate && $date <= $endDate) {
                                \App\Http\Controllers\Backend\Hr\AttendanceController::updateRemarkTimesheetAdd($date, $user_id);
                            }
                        }
                    }
                    \App\Models\Backend\PendingTimesheet::where("date", $date)->where("user_id", $user_id)->delete();
                    //  app('db')->table('timesheet_sync_issue')->insert($timesheetSyncData);
                    $timesheetUnit = isset($value['timesheet_save_units_id']) ? $value['timesheet_save_units_id'] : 0;
                    $response[$user_id . "_" . $date] = array('user_id' => $value['user_id'], 'timesheet_id' => $timesheet_id, 'date' => $date, 'bdms_user_id' => $user_id, 'item_count' => $item_count, 'timesheet_save_units_id' => $timesheetUnit);
                    $userRes[$user_id . "_" . $date] = array('user_id' => $value['user_id'], 'timesheet_id' => $timesheet_id, 'date' => $date, 'bdms_user_id' => $user_id, 'item_count' => $item_count, 'timesheet_save_units_id' => $timesheetUnit);

                    $resData = json_encode($userRes);
                    app('db')->table('timesheet_portal')->where("id", $portaData)->update(['responce_id' => $resData]);
                }
                echo json_encode($userRes);
                exit;
            } catch (\Exception $e) {
                app('log')->error("Timesheet added failed : " . $e->getMessage());
                $data['to'] = 'pankaj.k@befree.com.au';
                $data['subject'] = 'timesheet cron issue';
                $data['from_email'] = 'no-reply@befree.com.au';
                $data['content'] = $e->getMessage();
                //storeMail('', $data);
                //return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Timesheet", ['error' => 'Server error.']);
            }
        } else {
            $response = array('result' => 'failed', 'message' => 'invalid request');
            echo json_encode($response);
            exit;
        }
        /* } catch (\Exception $e) {
          app('log')->error("Timesheet added failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Timesheet", ['error' => 'Server error.']);
          } */
    }

}
