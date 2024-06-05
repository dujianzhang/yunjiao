<?php
namespace app\appapi\controller;
use think\Db;

use app\models\UsersModel;
use cmf\controller\HomeBaseController;
use app\models\ChatModel;
use cmf\lib\Storage;

/**
 */
class ScoketController extends HomeBaseController{

    protected $siteUrl;

    public function initialize()
    {
        parent::initialize();
        $siteInfo = cmf_get_site_info();
        $this->siteUrl = $siteInfo['site_url'];

    }


    //群聊
    public function groupChat(){
        $res = ['code'=>0,'message'=>'','data'=>[]];
        $param = $this->request->param();
        $nowtime = time();
        $insert = [
            'addtime'=>$nowtime,
            'type'   => 1
        ];
        //班级id
        $class_id = $param['class_id'] ?? 0;
        // 1家长 2老师
        $user_type = $param['user_type'] ?? 0;
        //家长id
        $patriarch_id = $param['patriarch_id'] ?? 0;
        //老师id
        $teacher_id = $param['teacher_id'] ?? 0;
        //用户token
        $token = $param['token'];
        //用户uid
        $uid = $param['uid'];
        //信息类型
        $content_type = $param['content_type'];
        //文本内容·
        $content_text = $param['content_text'] ?? 0;
        //文件名称
        $file_name = $param['file_name'] ?? 0;
        //文件地址
        $file_url = $param['file_url'] ?? 0;
        //文件大小
        $file_size = $param['file_size'] ?? 0;
        //文库id
        $library_class_id  = $param['library_class_id'] ?? 0;

        $status = Db::name('class')->alias('A')
                ->join('class_grade_system B','A.pid = B.id')
                ->where(['A.id'=>$class_id])
                ->find()['is_group_open'] ?? 0;

        if(!$status)$this->off('已关闭班级群聊');
        if(!in_array($user_type,[1,2])) $this->off('信息有误');

        if($content_type == 1){
            if(!$content_text)$this->off('内容不能为空');

            $result['content_text'] = $content_text;

            $insert['content_text'] = $content_text;
        }else{
            $key = 'teacher_class'.$class_id;
            if(getcaches($key)[$teacher_id] != $uid) $this->off('您不是本班老师');

            $exist = Db::name('class_teacher')->where(['class_id'=>$class_id,'id'=>$teacher_id])->find();
            if(!$exist) $this->off('您不是老师，无法上传文件');
            if(!$file_name || !$file_url || !$file_size || !$library_class_id)$this->off('信息有误');

            $host = $this->siteUrl.'/api/?s=School.Library.Add';
            $field = [
                'uid' => $uid,
                'token' => $token,
                'class_id' => $class_id,
                'library_class_id' => $param['library_class_id'],
                'type' => 1,
                'file_url' => $file_url,
                'file_name' => $file_name
            ];
            curl_post($host,$field);

            $result['file_name'] = $file_name;
            $result['file_url'] = $file_url;
            $result['file_size'] = $file_size;

            $insert['file_name'] = $file_name;
            $insert['file_url'] = $file_url;
            $insert['file_size'] = $file_size;
        }
        $result['content_type'] = $content_type;
        $insert['user_type'] = $user_type;
        $insert['class_id'] = $class_id;
        $insert['content_type'] = $content_type;
        if($user_type == 1){
            $key = 'patriarch_class'.$class_id;
            if(getcaches($key)[$patriarch_id] != $uid) $this->off('您不是本班家长');
            $result['name'] = $this->getName($user_type,$patriarch_id);
            $insert['patriarch_id'] = $param['patriarch_id'];
        }else{
            $key = 'teacher_class'.$class_id;
            if(getcaches($key)[$teacher_id] != $uid) $this->off('您不是本班老师');
            $result['name'] = $this->getName($user_type,$patriarch_id);
            $insert['teacher_id'] = $param['teacher_id'];
        }
        $id = Db::name('class_chat')->insertGetId($insert);
        $where = [['class_id','=',$class_id]];
        if($user_type == 1){
            $where[] = ['id','<>',$patriarch_id];
        }else{
            $where[] = ['id','<>',$teacher_id];
        }
        Db::name('class_teacher')->where($where)->setInc('group_chat_count', 1);
        Db::name('class_patriarch')->where($where)->setInc('group_chat_count', 1);
        $result['id'] = $id;

        $teacher = array_column(Db::name('class_teacher')->where(['class_id'=>$class_id])->select()->toArray(),null,'id');
        $patriarch = array_column(Db::name('class_patriarch')->where(['class_id'=>$class_id])->select()->toArray(),null,'id');

        $res['teacher'] = $teacher;
        $res['patriarch'] =  $patriarch;
        $result['type'] = 1;
        $res['data'] = $result;
        echo json_encode($res,true);
        exit;
    }

    //私聊
    public function privateChat(){
        $result= [];
        $nowtime = time();
        $insert = [
            'addtime'=>$nowtime,
            'type' => 2
        ];
        $res = ['code'=>0,'message'=>'','data'=>[]];
        $param = $this->request->param();

        $content_type = $param['content_type'] ?? 0;
        // 1家长 2老师
        $user_type = $param['user_type'] ?? 0;
        //家长id
        $patriarch_id = $param['patriarch_id'] ?? 0;
        //老师id
        $teacher_id = $param['teacher_id'] ?? 0;
        //班级id
        $class_id = $param['class_id'] ?? 0;
        //对方id
        $tid = $param['tid'] ?? 0;
        //对方身份 1家长 2老师
        $tuser_type = $param['tuser_type'] ?? 0;
        if(!in_array($user_type,[1,2])) $this->off('信息有误');
        $content_text = $param['content_text'] ?? 0;
        $file_name = $param['file_name'] ?? 0;
        $file_url = $param['file_url'] ?? 0;
        $file_size = $param['file_size'] ?? 0;
        $library_class_id  = $param['library_class_id '] ?? 0;
        $uid = $param['uid'] ?? 0;
        if($content_type == 1){
            if(!$content_text)$this->off('内容不能为空');
            $result['content_text'] = $content_text;
            $insert['content_text'] = $content_text;
        }else{
            if(!$file_name || $file_url || !$file_size || !$library_class_id)$this->off('信息有误');
            $result['file_name'] = $file_name;
            $result['file_url'] = $file_url;
            $insert['file_name'] = $file_name;
            $insert['file_url'] = $file_url;
        }
        $result['content_type'] = $content_type;
        $insert['user_type'] = $user_type;
        $insert['class_id'] = $class_id;
        $insert['content_type'] = $content_type;

        if($user_type == 1){
            $key = 'patriarch_class'.$class_id;
            if(getcaches($key)[$patriarch_id] != $uid) $this->off('您不是本班家长');
            $insert['patriarch_id'] = $patriarch_id;
        }else{
            $key = 'teacher_class'.$class_id;
            if(getcaches($key)[$teacher_id] != $uid) $this->off('您不是本班老师');
            $insert['teacher_id'] = $patriarch_id;
        }
        if($tuser_type == 1){
            $patriarch = Db::name('class_patriarch')->where(['id'=>$tid])->find();
            $res['patriarch_t'] = $patriarch;
        }else{
            $teacher = Db::name('class_teacher')->where(['id'=>$tid])->find();
            $res['teacher_t'] = $teacher;
        }
        $insert['tid'] = $param['tid'];
        $insert['tuser_type'] = $param['tuser_type'];
        Db::name('class_chat')->insert($insert);

        $key = $user_type.'2'.$class_id.$patriarch_id.$teacher_id.$tid.$tuser_type;
        $exist = getcaches($key);
        if(!$exist){
            $where = [
                'user_type' => $user_type,
                'type' =>2,
                'class_id'=>$class_id,
                'patriarch_id'=>$patriarch_id,
                'teacher_id'=>$teacher_id,
                'tid' => $tid,
                'tuser_type' => $tuser_type
            ];
            if(!Db::name('class_chat_log')->where($where)->find()){
                Db::name('class_chat_log')->insert($where);
            }
            setcaches($key,1);
        }

        $result['type'] = 2;
        $res['data'] = $result;
        echo json_encode($res,true);
        exit;
    }

    //连接校验
    public function check(){
        $res = ['code'=>0,'message'=>'','data'=>[]];
        echo json_encode($res);
        $token = $this->request->param('token',0);
        $uid = $this->request->param('uid',0);
        if (!$uid || !$token) $this->off('您的登陆状态失效，请重新登陆！');
        $key = "token_" . $uid;
        $userinfo = getcaches($key);
        if (!$userinfo) {
            $userinfo = \PhalApi\DI()->notorm->users_token ->select('token,expire_time')
                ->where('user_id = ? ', $uid)->fetchOne();
            if ($userinfo) setcaches($key, $userinfo);
        }
        if (!$userinfo || $userinfo['token'] != $token || $userinfo['expire_time'] < time())$this->off('您的登陆状态失效，请重新登陆！');
        echo json_encode($res);
        exit;
    }

    //清楚群聊未读数
    public function clearGroupChatCount(){
        $param = $this->request->param();
        //班级id
        $class_id = $param['class_id'] ?? 0;
        // 1家长 2老师
        $user_type = $param['user_type'] ?? 0;
        //家长id
        $patriarch_id = $param['patriarch_id'] ?? 0;
        //老师id
        $teacher_id = $param['teacher_id'] ?? 0;
        $uid = $param['uid'] ?? 0;
        if($user_type == 1){
            $key = 'patriarch_class'.$class_id;
            if(getcaches($key)[$patriarch_id] != $uid) $this->off('您不是本班家长');
            Db::name('class_patriarch')->where(['class_id'=>$class_id,'id'=>$patriarch_id])->update(['group_chat_count'=>0]);
        }else{
            $key = 'teacher_class'.$class_id;
            if(getcaches($key)[$teacher_id] != $uid) $this->off('您不是本班老师');
            Db::name('class_teacher')->where(['class_id'=>$class_id,'id'=>$teacher_id])->update(['group_chat_count'=>0]);
        }
    }

//    //群聊数量统计
//    public function GroupChatCount(){
//        $res = ['code'=>0,'message'=>'','data'=>[]];
//        $param = $this->request->param();
//        //班级id
//        $class_id = $param['class_id'] ?? 0;
//        // 1家长 2老师
//        $user_type = $param['user_type'] ?? 0;
//        //家长id
//        $patriarch_id = $param['patriarch_id'] ?? 0;
//        //老师id
//        $teacher_id = $param['teacher_id'] ?? 0;
//        if($user_type == 1){
//            $count = Db::name('class_patriarch')->where(['class_id'=>$class_id,'id'=>$patriarch_id])->find()['group_chat_count'] ?? 0;
//        }else{
//            $count = Db::name('class_teacher')->where(['class_id'=>$class_id,'id'=>$teacher_id])->find()['group_chat_count'] ?? 0;
//        }
//        $res['data'] = ['count'=>$count,'type'=>2];
//        echo json_encode($res);
//        exit;
//    }

    //撤回
    public function GroupChatRevocation(){
        $now_time = time();
        $res = ['code'=>0,'message'=>'','data'=>[]];
        $param = $this->request->param();
        //班级id
        $class_id = $param['class_id'] ?? 0;
        // 1家长 2老师
        $user_type = $param['user_type'] ?? 0;
        //家长id
        $patriarch_id = $param['patriarch_id'] ?? 0;
        //老师id
        $teacher_id = $param['teacher_id'] ?? 0;

        $id = $param['id'];
        $chat = Db::name('class_chat')->where('id',$id)->find();
        $addtime = $chat['addtime'];
        if($user_type == 1){
            if(($now_time - $addtime)  >  120){
                $res['code'] = 1000;
                $res['message'] = '已超出可撤回时间';
                echo json_encode($res);
                exit;
            }
            if(($chat['user_type'] != $user_type) || ($chat['patriarch_id'] != $patriarch_id)){
                $res['code'] = 1000;
                $res['message'] = '该消息不是您发出的';
                echo json_encode($res);
                exit;
            }
        }else{
            $teacher = Db::name('class_teacher')->where(['class_id'=>$class_id,'id'=>$teacher_id])->find();
            if(!$teacher['is_director']){
                if(($now_time - $addtime)  >  120){
                    $res['code'] = 1000;
                    $res['message'] = '已超出可撤回时间';
                    echo json_encode($res);
                    exit;
                }
                if(($chat['user_type'] != $user_type) || ($chat['teacher_id'] != $teacher_id)){
                    $res['code'] = 1000;
                    $res['message'] = '该消息不是您发出的';
                    echo json_encode($res);
                    exit;
                }
            }
        }
        Db::name('class_chat')->where('id',$id)->delete();
        if($user_type == 1){
            $relation = getcaches('getrelation');
            if(!$relation){
                $relation = Db::name('relation')->order('list_order asc')->select()->toArray();
                setcaches('getrelation',$relation);
            }
            $relation = array_column($relation,null,'id');
            $patriarch = Db::name('class_patriarch')->where(['class_id'=>$class_id,'id'=>$patriarch_id])->find();
            $student_id = $patriarch['student_id'];
            $student = Db::name('student')->where(['id'=>$student_id])->find();
            $name = $student['name'] . $relation[$patriarch['relation_id']]['name'];
        }else{
            $name = $teacher['name'].'老师';
        }

        $teacher = array_column(Db::name('class_teacher')->where(['class_id'=>$class_id])->select()->toArray(),null,'uid');
        $patriarch = array_column(Db::name('class_patriarch')->where(['class_id'=>$class_id])->select()->toArray(),null,'uid');

        $res['teacher'] = $teacher;
        $res['patriarch'] =  $patriarch;

        $info =  ['name'=>$name,'id'=>$id,'type'=>4];
        $res['data'] = $info;
        echo json_encode($res);
        exit;
    }


    public function getName($type,$id){
        if($type == 1){
            $key = "patriarch" . $id;
            $name = getcaches($key);
            if(!$name){
                $relation = getcaches('getrelation');
                if(!$relation){
                    $relation = Db::name('relation')->order('list_order asc')->select()->toArray();
                    setcaches('getrelation',$relation);
                }
                $relation = array_column($relation,null,'id');
                $info = Db::name('class_patriarch')->where(['id'=>$id])->find();
                $student_id = $info['student_id'];
                $student = Db::name('student')->where(['id'=>$student_id])->find();
                $name = $student['name'] . $relation[$info['relation_id']]['name'];
                setcaches($key,$name,86400 * 5);
            }
        }else{
            $key = "teacher" . $id;
            $name = getcaches($key);
            if(!$name){
                $info = Db::name('class_teacher')->where(['id'=>$id])->find();
                $name = $info['name'].'老师';
                setcaches($key,$name,86400 * 5);
            }
        }
        return $name;
    }

    public function off($message){
        $res = ['code' => 1000, 'message' => $message, 'data' => []];
        echo json_encode($res);
        exit;
    }


    public function count(){

        function get_inform_info($v){
            $inform_info = Db::name('class_inform')
                ->where(['class_id'=>$v['class_id']])
                ->whereOr(function ($query) use ($v){
                    $query->where(['patriarch_show'=>1,'push_school_id'=>$v['school_id']])
                        ->where(function ($query)use ($v){
                            $query->where([['keys','like',"%{$v['key']}%"]])->whereOr([["keys",'=','[]']]);
                        });
                })->whereOr(function ($query)use ($v){
                    $query->where(['patriarch_show'=>1])
                        ->where(function ($query)use ($v){
                            $query->where([['school_id','like',"%{$v['school_id']}%"]])->whereOr([["school_id",'=','[]']]);
                        });
                })->order('id desc')->find();
            $inform_name = '';
            if($inform_info['type']==1){
                $teacher_name = Db::name('class_teacher')->where(['uid'=>$v['uid'],'class_id'=>$v['class_id']])->find()['name'] ?? '';
                $inform_name = $teacher_name ? $teacher_name.'老师' : '';
            }elseif ($inform_info['type']== 2){
                $inform_name = '学校';
            }else{
                $inform_name = '总后台';
            }
            $time = date('Y-m-d H:i:s',$inform_info['addtime']);
            return compact('time','inform_name');
        }
        function get_survey_info($v,$type){

            $db= Db::name('class_survey');

            if ($type == 1){
                $survey_info = $db->where(function ($query)use ($v){
                    $query->where(['class_id'=>$v['class_id']])
                        ->where(function ($query)use ($v){
                            $query->where([['patriarch_id','like',"%{$v['id']}%"]])->whereOr('type',1);
                        });
                })->whereOr(function ($query)use($v){
                    $query->where(['patriarch_show'=>1,'push_school_id'=>$v['school_id']])
                        ->where(function ($query)use ($v){
                            $query->where([['keys','like',"%{$v['key']}%"]])->whereOr('keys','[]');
                        });
                })->whereOr(function ($query)use ($v){
                    $query->where(['patriarch_show'=>1])
                        ->where(function ($query)use ($v){
                            $query->where([['school_id','like',"%{$v['school_id']}%"]])->whereOr('school_id','[]');
                        });
                })->find();
            }else{
                $survey_info = $db->whereOr(function ($query)use($v){
                    $query->where(['teacher_show'=>1,'push_school_id'=>$v['school_id']])
                        ->where(function ($query)use ($v){
                            $query->where([['keys','like',"%{$v['key']}%"]])->whereOr('keys','[]');
                        });
                })->whereOr(function ($query)use ($v){
                    $query->where(['teacher_show'=>1])
                        ->where(function ($query)use ($v){
                            $query->where([['school_id','like',"%{$v['school_id']}%"]])->whereOr('school_id','[]');
                        });
                })->find();
            }
            $survey_name = '';
            if($survey_info['types']==1){
                $teacher_name = Db::name('class_teacher')->where(['uid'=>$v['uid'],'class_id'=>$v['class_id']])->find()['name'] ?? '';
                $survey_name = $teacher_name ? $teacher_name.'老师' : '';
            }elseif ($survey_info['types']== 2){
                $survey_name = '学校';
            }else{
                $survey_name = '总后台';
            }
            $time = date('Y-m-d H:i:s',$survey_info['addtime']);
            return compact('time','survey_name');
        }
        function getChat($v){
            $chat = Db::name('class_chat')->where(['class_id'=>$v['class_id']])->order('id desc')->find();
            if($chat){
                if($chat['content_type'] == 1){
                    $content_text = $chat['content_text'];
                }else{
                    $content_text = $chat['file_name'];
                }
                $time = $chat['addtime'];
                $user_type = $chat['user_type'];
                if($user_type == 1){
                    $push_name = (new ScoketController)->getName($user_type,$chat['patriarch_id']);
                }else{
                    $push_name = (new ScoketController)->getName($user_type,$chat['teacher_id']);
                }
            }else{
                $content_text = '';
                $time = '';
                $push_name = '';
            }
            return compact('content_text','time','push_name');
        }
        $res = ['code'=>0,'message'=>'','data'=>[]];
        echo json_encode($res);
        exit;
        $param = $this->request->param();

        $server_type = $param['server_type'];


        $where = [];

        if($server_type == 'server'){
            $where['uid'] = $param['uid'];
            $patriarch = Db::name('class_patriarch')->where($where)->select();
            $teacher = Db::name('class_teacher')->where($where)->select();
        }elseif ($server_type == 'teacher'){
            $where['uid'] = $param['uid'];
            $patriarch = [];
            $teacher = Db::name('class_teacher')->where($where)->select();
        }elseif ($server_type == 'clear'){
            $user_type = $param['user_type'];
            $where['class_id'] = $param['class_id'];
            if($user_type == 1){
                $where['id'] = $param['patriarch_id'];
                $patriarch = Db::name('class_patriarch')->where($where)->select();
            }else{
                $where['id'] = $param['teacher_id'];
                $teacher = Db::name('class_teacher')->where($where)->select();
            }
        }else{
            $this->off('信息有误');
        }

        $result = [];
        foreach ($patriarch as $k=>$v){
            $inform_info = get_inform_info($v);
            $survey_info = get_survey_info($v,1);
            $chat = getChat($v);
            $result[] = [
                'user_type' => 1,
                'patriarch_id' => $v['id'],
                'class_id' => $v['class_id'],
                'group_chat_count' => $v['group_chat_count'],
                'group_content' => $chat['content_text'],
                'group_time' => $chat['time'],
                'group_push_name' => $chat['push_name'],
                'inform_count' => $v['inform_count'],
                'inform_name' =>$inform_info['inform_name'],
                'inform_time' =>$inform_info['time'],
                'survey_count' => $v['survey_count'],
                'survey_name' => $survey_info['survey_name'],
                'survey_time' => $survey_info['time'],
                'count' => $v['group_chat_count'] +  $v['inform_count'] + $v['survey_count']
            ];
        }
        foreach ($teacher as $k=>$v){
            $inform_info = get_inform_info($v);
            $survey_info = get_survey_info($v,2);
            $chat = getChat($v);
            $result[] = [
                'user_type' => 2,
                'teacher_id' => $v['id'],
                'class_id' => $v['class_id'],
                'group_chat_count' => $v['group_chat_count'],
                'group_content' => $chat['content_text'],
                'group_time' => $chat['time'],
                'group_push_name' => $chat['push_name'],
                'inform_count' => $v['inform_count'],
                'inform_name' =>$inform_info['inform_name'],
                'inform_time' =>$inform_info['time'],
                'survey_count' => $v['survey_count'],
                'survey_name' => $survey_info['survey_name'],
                'survey_time' => $survey_info['time'],
                'count' => $v['group_chat_count'] +  $v['inform_count'] + $v['survey_count']
            ];
        }
        $data = [
            'type' => 3 ,
            'info' => $result
        ];
        $res['data'] = $data;
        echo json_encode($res);
        exit;
    }


}
