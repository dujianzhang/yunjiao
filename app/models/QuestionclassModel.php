<?php

namespace app\models;

use think\Model;

class QuestionclassModel extends Model
{
    protected $pk = 'id';
    protected $name = 'question_class';

    public static $redis_key='getquestionclass';

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
}