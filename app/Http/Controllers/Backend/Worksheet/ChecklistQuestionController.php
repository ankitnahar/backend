<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
// Use Models
use App\Models\Worksheet\MasterTaskActivity,App\Models\Worksheet\ChecklistQuestion,App\Models\Worksheet\ChecklistGroup;
// Use External Classes 
use DB, Carbon\Carbon, Excel, Session, Yajra\Datatables\Datatables;

class ChecklistQuestionController extends Controller
{

    public function __construct() {
        $this->middleware('auth:admin');
    }

    /**
     * Display index page.
     *
     * @return \BladeView|bool|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index() {
        // Listing for filter
        $masterActivity = MasterTaskActivity::where("parent_id","=","0")->pluck("name","id")->prepend('Please Select Master Activity','');
        $masterTaskActivity = MasterTaskActivity::where("parent_id","!=","0")->pluck("name","id")->prepend('Please Select Task','');
        $checklistGroup = ChecklistGroup::pluck("name","id")->prepend('Please Select Group','');
        $masterChecklist = MasterTaskActivity::where("parent_id","!=","0")->where("is_checklist","=","1")->pluck("checklist_name","id")->prepend('Please Select Checklist','');
        return view('admin.worksheet.checklistquestion.index',compact('masterActivity','masterTaskActivity','checklistGroup','masterChecklist'));
    }

    /**
     * Process dataTable ajax response.
     *
     * @param \Yajra\Datatables\Datatables $datatables
     * @return \Illuminate\Http\JsonResponse
     */
    public function data(Request $request) {
        // For Set Row Number
        $start = $request->get('start');
        $masterChecklist = DB::table('checklist_question as cq')
                ->leftJoin('checklist_group as cg', 'cq.checklist_group_id', '=', 'cg.id')
                ->leftJoin('master_task_activity as mta', 'cq.master_task_id', '=', 'mta.id')
                ->leftJoin('master_task_activity as ma', 'mta.parent_id', '=', 'ma.id')
                ->leftJoin('user', 'user.id', '=', 'cq.modified_by')
                ->select(['cq.id','mta.checklist_name','ma.name as master_activity','mta.name as task_name','cg.name as checklist_group',
                        'cq.question_name','cq.help_text','user.userfullname','cq.modified_by','cq.modified_on','cq.is_active']);

        return Datatables::of($masterChecklist)
                        // passing variable by reference row number for group by condition
                        ->addColumn('rownum', function($query) use (&$start) {
                            return ++$start;
                        },1)
                        ->addColumn('action', function ($masterChecklist) {
                            return '<a href="checklistquestion/update/' . $masterChecklist->id . '" class="btn btn-icon fuse-ripple-ready"><i class="icon icon-pencil text-grey-600 s-4"></i></a>';
                        })
                        ->editColumn('is_active', function ($masterChecklist) {
                            return ($masterChecklist->is_active == 1) ? '<span class="label label-success">Active</span>': '<span class="label label-default">Inactive</span>';
                        })
                        ->editColumn('modified_on', function ($masterChecklist) {
                            $return = '<ul class="list list-unstyled no-margin">';
                            $return .= '<li class="no-margin"><i class="icon-user text-size-base text-info-600 position-left"></i>
                                        '.$masterChecklist->userfullname.'
                                        </li>';
                             $return .= '<li class="no-margin"><i class="icon-calendar2 text-size-base text-grey-600 position-left"></i>
                                        '.Carbon::parse($masterChecklist->modified_on)->format('d-m-Y H:i:s').'
                                        </li>';
                             $return .= '</ul>';
                            return $return;
                        })
                        ->filter(function ($query) use ($request) {
                            $this->filterData($query,$request);
                        })->rawColumns([0, 8])->make(true);
    }
    /**
     * Process dataTable ajax filter.
     *
     * @param \Yajra\Datatables\Datatables $datatables
     * @return \Illuminate\Http\JsonResponse
     */
    public static function filterData($query,$request){
        // Filter Data as per requirement
        if ($request->has('question') && trim($request->get('question')) != '') {
            $query->where('cq.question_name','like', "%{$request->get('question')}%");
        }
        if ($request->has('master_checklist') && trim($request->get('master_checklist')) != '') {
            $query->where('mta.id', '=', $request->get('master_checklist'));
        }
        if ($request->has('master_activity') && trim($request->get('master_activity')) != '') {
            $query->where('mta.parent_id', '=', $request->get('master_activity'));
        }
        if ($request->has('task_id') && trim($request->get('task_id')) != '') {
            $query->where('mta.id', '=', $request->get('task_id'));
        }
        if ($request->has('checklist_group') && trim($request->get('checklist_group')) != '') {
            $query->where('cq.checklist_group_id', '=', $request->get('checklist_group'));
        }
        if ($request->has('is_active') && trim($request->get('is_active')) != '') {
            $query->where('cq.is_active', '=', $request->get('is_active'));
        }
    }
    /* Created By - Alok Shukla
     * Created On - 06/03/2018
     * Create Function for download file
     */

    public function download(REQUEST $request, $type ='xlsx') {
        // For Set Row Number
        $start = $request->get('start');
        $masterChecklist = DB::table('checklist_question as cq')
                ->leftJoin('checklist_group as cg', 'cq.checklist_group_id', '=', 'cg.id')
                ->leftJoin('master_task_activity as mta', 'cq.master_task_id', '=', 'mta.id')
                ->leftJoin('master_task_activity as ma', 'mta.parent_id', '=', 'ma.id')
                ->leftJoin('user', 'user.id', '=', 'cq.modified_by')
                ->select(['mta.checklist_name','ma.name as master_activity','mta.name as task_name','cg.name as checklist_group',
                        'cq.question_name','cq.help_text','cq.is_active','user.userfullname','cq.modified_on']);

        $data =  Datatables::of($masterChecklist)
                        // passing variable by reference row number for group by condition
                        ->addColumn('rownum', function($query) use (&$start) {
                            return ++$start;
                        },0)
                        ->editColumn('is_active', function ($masterChecklist) {
                            return ($masterChecklist->is_active == 1) ? "Active" : "Inactive";
                        },8)
                        ->editColumn('modified_on', function ($masterChecklist) {
                            return Carbon::parse($masterChecklist->modified_on)->format('d-m-Y H:i:s');
                        })
                        ->filter(function ($query) use ($request) {
                            $this->filterData($query,$request);
                        });
        // Convert to Array for pass it to excel
        $convertToArray = $data->toArray();
        // Initialize Excel Array to pass with data and column
        $exportToExcelColumn = array();
        // Column Name
        $exportToExcelColumn[] = ['Sr.No','Checklist Name','Master Activity','Task','Checklist Group','Question','Help Text','Status','Modified By','Modified On'];
        // Convert each object to array
        if(!empty($convertToArray["data"])) {
            foreach ($convertToArray["data"] as $data) {
                $exportToExcelColumn[] = $data;
            }
        }
        // Generate Exce File
        return Excel::create('Checklist Question', function($excel) use ($exportToExcelColumn) {
                    // Function for sheet1 used
                    $excel->sheet('Sheet1', function($sheet) use ($exportToExcelColumn) {
                        // Function for cell format change
                        $sheet->cell('A1:J1', function($cell) {
                            $cell->setFontColor('#ffffff');
                            $cell->setBackground('#0c436c');
                        });
                        // Function for insert/write data into excel file
                        $sheet->fromArray($exportToExcelColumn,null, 'A1', false, false);
                    });
                })->export($type);
    }
    /* Created By - Alok Shukla
     * Created On - 06/03/2018
     * Create Function of Master Checklist
     */
    public function create(REQUEST $request) {
       // Fetch Master Checklist Data id given in request
        $checklistQuestion = new ChecklistQuestion;
        // Fetch Master Activity & Task Data For Selection
        $checklistGroup = ChecklistGroup::pluck("name","id")->prepend('Please Select Group','');
        $masterChecklist = MasterTaskActivity::where("parent_id","!=","0")->where("is_checklist","=","1")->pluck("checklist_name","id")->prepend('Please Select Checklist','');        

        // Check Post Method
        if ($request->isMethod('POST')) {
            // Server Side Validation
            $this->customValidationRules($request);
            
            // Save Data in Object
            $checklistQuestion->master_task_id = $request->input('master_task_id');
            $checklistQuestion->checklist_group_id = $request->input('checklist_group_id');
            $checklistQuestion->question_name = $request->input('question_name');
            $checklistQuestion->help_text = $request->input('help_text');
            $checklistQuestion->is_active = $request->input('is_active');
            $checklistQuestion->created_by = Session::get('admin_user_id');
            $checklistQuestion->created_on = date('Y-m-d H:i:s');
            $checklistQuestion->modified_by = Session::get('admin_user_id');
            $checklistQuestion->modified_on = date('Y-m-d H:i:s');
            $checklistQuestion->save();
            // Redirect after added
            return redirect('admin/worksheet/checklistquestion')->with('flash_success', 'Checklist Question added succesfully!');
        }
        //Redirect on page load
        $request->request->add(['action' => 'Save']);
        return view('admin.worksheet.checklistquestion.form', compact('checklistGroup',$checklistGroup,'masterChecklist',$masterChecklist,'checklistQuestion',$checklistQuestion));
    }

    /* Created By - Alok Shukla
     * Created On - 06/03/2018
     * Update Function of Master Checklist
     */
    public function update(REQUEST $request, $id) {
        // Fetch Master Checklist Data id given in request
        $checklistQuestion = ChecklistQuestion::find($id);
        // Fetch Master Activity & Task Data For Selection
        $checklistGroup = ChecklistGroup::pluck("name","id")->prepend('Please Select Group','');
        $masterChecklist = MasterTaskActivity::where("parent_id","!=","0")->where("is_checklist","=","1")->pluck("checklist_name","id")->prepend('Please Select Checklist','');        

        // Check Post Method
        if ($request->isMethod('POST')) {
            // Server Side Validation
            $this->customValidationRules($request);
            
            // Save Data in Object
            $checklistQuestion->master_task_id = $request->input('master_task_id');
            $checklistQuestion->checklist_group_id = $request->input('checklist_group_id');
            $checklistQuestion->question_name = $request->input('question_name');
            $checklistQuestion->help_text = $request->input('help_text');
            $checklistQuestion->is_active = $request->input('is_active');
            $checklistQuestion->modified_by = Session::get('admin_user_id');
            $checklistQuestion->modified_on = date('Y-m-d H:i:s');
            $checklistQuestion->save();
            // Redirect after added
            return redirect('admin/worksheet/checklistquestion')->with('flash_success', 'Checklist Question updated succesfully!');
        }
        //Redirect on page load
        return view('admin.worksheet.checklistquestion.form', compact('checklistGroup',$checklistGroup,'masterChecklist',$masterChecklist,'checklistQuestion',$checklistQuestion));
    }
    
   /* Created By - Alok Shukla
    * Created On - 06/03/2018
    * Used - This Function is used for validation of custom rules
    */ 
    public static function customValidationRules($request,$id=''){
        $request->validate([
                'master_task_id' => 'required',
                'checklist_group_id' => 'required',
                'question_name' => 'required',
                'is_active' => 'required|in:1,2',
            ],
            [
                'master_task_id.required' => 'The master checklist can not be blank.',
                'checklist_group_id.required' => 'The checklist group can not be blank.',
                'question_name' => 'The question can not be blank.',
                'is_active.required' => 'The question status can not be blank.'
            ]);
    }
    /* Created By - Alok Shukla
    * Created On - 06/03/2018
    * Used - This Function is used for get master activity name from task
    */ 
    public function getMasterActivityTaskName($id)
    {
        // Return task data
        $masterChecklist = DB::table('master_task_activity as ma')
                ->leftjoin('master_task_activity as mta', 'ma.parent_id', '=', 'mta.id')
                ->where("ma.id","=",$id)
                ->select("mta.name as masteractivity","ma.name as task")->get()->toArray();
        return $masterChecklist;
    }
}
