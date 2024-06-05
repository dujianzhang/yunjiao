<?php

namespace app\models;

use think\Model;

class GradeModel extends Model
{
    protected $pk = 'id';
    protected $name = 'course_grade';

    public static $redis_key='getcoursegrade';

    public static function resetcache(){
        $key=self::$redis_key;

        $list=self::field('*')->order("list_order asc")->select();
        if($list){
            setcaches($key,$list);
        }else{
            delcache($key);
        }
        return $list;
    }
    /* 列表 */
    public static function getList(){
        $key=self::$redis_key;
        if(isset($GLOBALS[$key])){
            return $GLOBALS[$key];
        }
        $list=getcaches($key);
        if(!$list){
            $list=self::resetcache();
        }
        $GLOBALS[$key]=$list;
        return $list;

    }

    /* 某信息 */
    public static function getInfo($id){

        $info=[];

        if($id<1){
            return $info;
        }
        $list=self::getList();

        foreach($list as $k=>$v){
            if($v['id']==$id){
                unset($v['list_order']);
                $info=$v;
                break;
            }
        }

        return $info;
    }

    public static function getListTwo(){

        $rs=[];
        $list=self::getList();

        foreach ($list as $k=>$v){
            $rs[$v['id']]=$v;
        }
        $list2=[];
        foreach($rs as $k=>$v){
            if($v['pid']!=0){
                $name=$rs[$v['pid']]['name'].' - '.$v['name'];
                $v['name']=$name;

                $list2[$k]=$v;
            }
        }
        return $list2;
    }

    public static function getIdName(){
        $geade_name=[];
        $geade=self::getListTwo();
        foreach ($geade as $k=>$v){
            $geade_name[$v['id']]=$v['name'];
        }
        return $geade_name;
    }
}
