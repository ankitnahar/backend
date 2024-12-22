<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use Session;
use DB;

class Tabs extends Model {

    // Table name which we used from database
    protected $table = 'tabs';
    protected $fillable = ['tab_name', 'is_active'];
    protected $hidden = [];
    public $timestamps = false;

    // Designation tab detail
    public static function tabData($id) {
        return Tabs::leftJoin('designation_tab_right as dt', function($join) use ($id) {
                            $join->on('dt.tab_id', '=', 'tabs.id');
                            $join->on('dt.designation_id', '=', DB::raw($id));
                        })
                        ->select(['tabs.id', 'tabs.tab_name', 'tabs.view as is_view', 'tabs.add_edit as is_add_edit', 'tabs.delete as is_delete', 'tabs.export as is_export', 'tabs.download as is_download', 'dt.view', 'dt.add_edit', 'dt.delete', 'dt.export', 'dt.download'])
                        ->where("tabs.parent_id", "!=", 0)
                        ->where("tabs.is_active", "1");
    }

    //user wise tab detail
    public static function usertabData($id) {
        return Tabs::leftJoin('user_tab_right as dt', function($join) use ($id) {
                            $join->on('dt.tab_id', '=', 'tabs.id');
                            $join->on('dt.user_id', '=', DB::raw($id));
                        })
                        ->select(['tabs.id', 'tabs.tab_name', 'tabs.view as is_view', 'tabs.add_edit as is_add_edit', 'tabs.delete as is_delete', 'tabs.export as is_export', 'tabs.download as is_download', 'dt.view', 'dt.add_edit', 'dt.delete', 'dt.export', 'dt.download'])
                        ->where("tabs.parent_id", "!=", 0)
                         ->where("tabs.is_active","1")        
                        ->orderby("tabs.tab_name", "asc");
    }

    //get tab name

    public static function getname($id) {
        $tab = Tabs::select("tab_name")->where("id", "=", $id)->first();
        return $tab->tab_name;
    }

    //check tabs user wise
    public static function tabTree($designation_id, $user_id) {
        $tabDetail = array();
        if ($designation_id != '7') {
            $tabArray = DB::select("CALL get_user_tab($user_id)");            
            $tabButton = Button::userbuttonData($user_id)->get();
            $buttonArray = array();
            if (!empty($tabButton)) {
                foreach ($tabButton as $button) {
                    if ($button['view'] == 1)
                        $buttonArray[$button->tab_id][] = array("button_name" => $button['button_name']);
                }
            }
            foreach ($tabArray as $tab) {

                $tab->otherRights = isset($buttonArray[$tab->id]) ? $buttonArray[$tab->id] : '';
                $tabDetail[] = $tab;
            }
        } else {
            $tabArray = DB::select("CALL get_tab_hierarchy()");
            $tabButton = Button::get();
            foreach ($tabButton as $button) {
                $buttonArray[$button->tab_id][] = array("button_name" => $button['button_name']);
            }
            
            foreach ($tabArray as $tab) {
                $tab->otherRights = isset($buttonArray[$tab->id]) ? $buttonArray[$tab->id] : '';
                $tabDetail[] = $tab;
            }
        }
        return $tabDetail;
    }

}
