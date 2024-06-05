<?php
namespace app\appapi\controller;
use think\Db;

use app\models\UsersModel;
use cmf\controller\HomeBaseController;
use app\models\ChatModel;

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

        $file_type = $param['file_type'] ?? 0;

        $file_suffix = $param['file_suffix'] ?? 0;

        $file_cover = $param['file_cover'] ?? 0;
        $status = Db::name('class')->alias('A')
                ->field('B.is_group_open')
                ->join('class_grade_system B','A.pid = B.id')
                ->where(['A.id'=>$class_id])
                ->find()['is_group_open'] ?? 0;

        if(!$status){
            $this->off('已关闭班级群聊');
        }
        if(!in_array($user_type,[1,2])) $this->off('信息有误');

        if($content_type == 1){
            if(!$content_text)$this->off('内容不能为空');

            if(!$this->bear($content_text))$this->off('含有违禁词汇');
            $result['content_text'] = $content_text;

            $insert['content_text'] = $content_text;
        }else{
            $this->existUser(2,$class_id,$teacher_id,$uid);

            $exist = Db::name('class_teacher')->where(['class_id'=>$class_id,'id'=>$teacher_id])->find();
            if(!$exist) $this->off('您不是老师，无法上传文件');
//            if(!$file_name || !$file_url || !$file_size)$this->off('信息有误');

            if($library_class_id){
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
            }


            $result['file_name'] = $file_name;
            $result['file_url'] = get_upload_path($file_url);
            $result['file_size'] = $file_size;
            $result['file_type'] = $file_type;
            $result['file_suffix'] = $file_suffix;
            $result['file_cover'] = $file_cover ? get_upload_path(get_upload_path($file_cover)) : '';

            $insert['file_name'] = $file_name;
            $insert['file_url'] = $file_url;
            $insert['file_size'] = $file_size;
            $insert['file_type'] = $file_type;
            $insert['file_suffix'] = $file_suffix;
            $insert['file_cover'] = $file_cover;
        }
        $result['content_type'] = $content_type;
        $insert['user_type'] = $user_type;
        $insert['class_id'] = $class_id;
        $insert['content_type'] = $content_type;
        if($user_type == 1){
            $this->existUser(1,$class_id,$patriarch_id,$uid);
            $result['name'] = $this->getName($user_type,$patriarch_id);
            $insert['patriarch_id'] = $param['patriarch_id'];
        }else{
            $this->existUser(2,$class_id,$teacher_id,$uid);
            $result['name'] = $this->getName($user_type,$teacher_id);
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
        $res['data'] = $result;
        echo json_encode($res,true);
        exit;
    }

    //问卷通知
    public function groupChatSurvey(){
        $res = ['code'=>0,'message'=>'','data'=>[]];
        
        $param = $this->request->param();
        $insert = [
            'addtime'=>time(),
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


        $survey_id = $param['survey_id'] ?? 0;
        if(!$survey_id)$this->off('信息有误');
        $info = Db::name('class_survey')->where(['id'=>$survey_id])->find();
        if(!$info)$this->off('信息有误');
        $teacher = Db::name('class_teacher')->where(['class_id'=>$class_id,'uid'=>$info['uid']])->find();
        if(!$teacher) $this->off('信息有误');

        $name = $this->getName(2,$teacher['id']);
        $title = $info['title'];
        $addtime = date('Y-m-d H:i:s',$info['addtime']);
        $status = $info['end_time'] < time() ? 0 : 1;




        $insert['survey_id'] = $survey_id;
        $insert['teacher_id'] = $teacher['id'];
        $insert['content_type'] = 5;

        $insert['user_type'] = $user_type;
        $insert['class_id'] = $class_id;
        Db::name('class_chat')->insertGetId($insert);

        $where = [['class_id','=',$class_id]];
        if($user_type == 1){
            $where[] = ['id','<>',$patriarch_id];
        }else{
            $where[] = ['id','<>',$teacher_id];
        }

        Db::name('class_teacher')->where($where)->setInc('group_chat_count', 1);
        Db::name('class_patriarch')->where($where)->setInc('group_chat_count', 1);


        $teacher = array_column(Db::name('class_teacher')->where(['class_id'=>$class_id])->select()->toArray(),null,'id');
        $patriarch = array_column(Db::name('class_patriarch')->where(['class_id'=>$class_id])->select()->toArray(),null,'id');
        $res['teacher'] = $teacher;
        $res['patriarch'] =  $patriarch;
        $res['data'] = [
            'name' => $name,
            'title' => $title,
            'addtime' => $addtime,
            'status' => $status,
            'content_type' => 5,
            'survey_id' => $survey_id
        ];

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
        $file_type = $param['file_type'] ?? 0;

        $file_suffix = $param['file_suffix'] ?? 0;

        $file_cover = $param['file_cover'] ?? 0;


        if(!in_array($user_type,[1,2])) $this->off('信息有误');
        $content_text = $param['content_text'] ?? 0;
        $file_name = $param['file_name'] ?? 0;
        $file_url = $param['file_url'] ?? 0;
        $file_size = $param['file_size'] ?? 0;
        $uid = $param['uid'] ?? 0;

        if($content_type == 1){
            if(!$content_text)$this->off('内容不能为空');
            if(!$this->bear($content_text))$this->off('含有违禁词汇');

            $result['content_text'] = $content_text;
            $insert['content_text'] = $content_text;
        }
        elseif($content_type == 2){
            $result['file_name'] = $file_name;
            $result['file_size'] = $file_size;
            $result['file_url'] = get_upload_path($file_url);
            $result['file_type'] = $file_type;
            $result['file_suffix'] = $file_suffix;
            $result['file_cover'] = $file_cover ? get_upload_path(get_upload_path($file_cover)) : '';

            $insert['file_size'] = $file_size;
            $insert['file_name'] = $file_name;
            $insert['file_url'] = $file_url;
            $insert['file_type'] = $file_type;
            $insert['file_suffix'] = $file_suffix;
            $insert['file_cover'] = $file_cover;
        }
        else{
            $survey_id = $param['survey_id'] ?? 0;
            if(!$survey_id)$this->off('信息有误');
            $info = Db::name('class_survey')->where(['id'=>$survey_id])->find();
            if(!$info)$this->off('信息有误');
            $teacher = Db::name('class_teacher')->where(['class_id'=>$class_id,'uid'=>$info['uid']])->find();
            if(!$teacher) $this->off('信息有误');

            $title = $info['title'];
            $addtime = date('Y-m-d H:i:s',$info['addtime']);
            $status = $info['end_time'] < time() ? 0 : 1;



            $result['title'] = $title;
            $result['addtime'] = $addtime;
            $result['status'] = $status;


            $insert['survey_id'] = $survey_id;

        }
        $result['content_type'] = $content_type;
        $insert['user_type'] = $user_type;
        $insert['class_id'] = $class_id;
        $insert['content_type'] = $content_type;

        if($user_type == 1){
            $this->existUser(1,$class_id,$patriarch_id,$uid);
            $insert['patriarch_id'] = $patriarch_id;
            $result['name'] = $this->getName(1,$patriarch_id);
        }else{
            $this->existUser(2,$class_id,$teacher_id,$uid);
            $insert['teacher_id'] = $teacher_id;
            $result['name'] = $this->getName(2,$teacher_id);
        }
        if($tuser_type == 1){
            $patriarch = Db::name('class_patriarch')->where(['id'=>$tid])->find();
            $result['t_name'] = $this->getName(1,$patriarch['id']);
            $res['patriarch_t'] = $patriarch;
        }else{
            $teacher = Db::name('class_teacher')->where(['id'=>$tid])->find();
            $result['t_name'] = $this->getName(2,$teacher['id']);
            $res['teacher_t'] = $teacher;

        }
        $insert['tid'] = $param['tid'];
        $insert['tuser_type'] = $param['tuser_type'];
        $insert['is_read'] = 0;
        Db::name('class_chat')->insert($insert);

        $where = [
            'user_type' => $user_type,
            'type' =>2,
            'class_id'=>$class_id,
            'patriarch_id'=>$patriarch_id,
            'teacher_id'=>$teacher_id,
            'tid' => $tid,
            'tuser_type' => $tuser_type
        ];
        $result['count'] = Db::name('class_chat')->where($where)->where(['is_read'=>0])->count();
        $result['type'] = 2;
        $res['data'] = $result;
        echo json_encode($res,true);
        exit;
    }


    //问卷通知
    public function privateChatSurvey(){
        $res = ['code'=>0,'message'=>'','data'=>[]];

        $param = $this->request->param();
        $insert = [
            'addtime'=>time(),
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


        $survey_id = $param['survey_id'] ?? 0;
        if(!$survey_id)$this->off('信息有误');
        $info = Db::name('class_survey')->where(['id'=>$survey_id])->find();
        if(!$info)$this->off('信息有误');
        $teacher = Db::name('class_teacher')->where(['class_id'=>$class_id,'uid'=>$info['uid']])->find();
        if(!$teacher) $this->off('1');

        $name = $this->getName(2,$teacher['id']);
        $title = $info['title'];
        $addtime = date('Y-m-d H:i:s',$info['addtime']);
        $status = $info['end_time'] < time() ? 0 : 1;




        $insert['survey_id'] = $survey_id;
        $insert['teacher_id'] = $teacher['id'];
        $insert['content_type'] = 5;

        $insert['user_type'] = $user_type;
        $insert['class_id'] = $class_id;
        Db::name('class_chat')->insertGetId($insert);

        $where = [['class_id','=',$class_id]];
        if($user_type == 1){
            $where[] = ['id','<>',$patriarch_id];
        }else{
            $where[] = ['id','<>',$teacher_id];
        }

        Db::name('class_teacher')->where($where)->setInc('group_chat_count', 1);
        Db::name('class_patriarch')->where($where)->setInc('group_chat_count', 1);


        $teacher = array_column(Db::name('class_teacher')->where(['class_id'=>$class_id])->select()->toArray(),null,'id');
        $patriarch = array_column(Db::name('class_patriarch')->where(['class_id'=>$class_id])->select()->toArray(),null,'id');
        $res['teacher'] = $teacher;
        $res['patriarch'] =  $patriarch;
        $res['data'] = [
            'name' => $name,
            'title' => $title,
            'addtime' => $addtime,
            'status' => $status,
            'content_type' => 5,
            'survey_id' => $survey_id
        ];

        echo json_encode($res,true);
        exit;
    }



    //连接校验
    public function check(){
        $res = ['code'=>0,'message'=>'','data'=>[]];
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
        $uid = $param['uid'] ?? 0;
        if($user_type == 1){
            $this->existUser(1,$class_id,$patriarch_id,$uid);
            Db::name('class_patriarch')->where(['class_id'=>$class_id,'id'=>$patriarch_id])->update(['group_chat_count'=>0]);
        }else{
            $this->existUser(2,$class_id,$teacher_id,$uid);
            Db::name('class_teacher')->where(['class_id'=>$class_id,'id'=>$teacher_id])->update(['group_chat_count'=>0]);
        }
        unset($param['token']);
        $res['data'] = ['type'=>5,'info'=>$param];
        echo json_encode($res,true);
        exit;
    }

    //清楚私聊未读数
    public function privateChatClear(){

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
        $uid = $param['uid'] ?? 0;

        $tid = $param['tid'] ?? 0;

        $tuser_type = $param['tuser_type'] ?? 0;

        if(!$class_id || !$user_type || !$uid || !$tid || !$tuser_type){
            $this->off('信息有误');
        }
        $where = [
            'user_type' => $tuser_type,
            'type' =>2,
            'class_id'=>$class_id,
            'tuser_type' => $user_type,
            'is_read' =>  0
        ];

        if($tuser_type == 1){
            $where['patriarch_id'] = $tid;
        }else{
            $where['teacher_id'] = $tid;
        }
        if($user_type == 1){
            $where['tid'] = $patriarch_id;
        }else{
            $where['tid'] = $teacher_id;
        }
        Db::name('class_chat')->where($where)->update(['is_read'=>1]);
        unset($param['token']);
        $res['data'] = ['type' => 6 , 'info'=>$param];
        echo json_encode($res);
        exit;
    }

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
                $name = $student['name'] . $relation[$info['relation_id']]['name'] ?? $info['relation_id'];
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


    public function get_inform_info($v,$type){
        if($type == 1){
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
        }else{
            $inform_info = Db::name('class_inform')->whereOr(function ($query)use($v){
                $query->where(['teacher_show'=>1,'push_school_id'=>$v['school_id']])
                    ->where(function ($query)use ($v){
                        $query->where([['keys','like',"%{$v['key']}%"]])->whereOr('keys','[]');
                    });
            })->whereOr(function ($query)use ($v){
                $query->where(['teacher_show'=>1])
                    ->where(function ($query)use ($v){
                        $query->where([['school_id','like',"%{$v['school_id']}%"]])->whereOr('school_id','[]');
                    });
            })->order('id desc')->find();
        }
        $inform_name = '';
        if($inform_info['type']==1){
            $teacher_name = Db::name('class_teacher')->where(['uid'=>$v['uid'],'class_id'=>$v['class_id']])->find()['name'] ?? '';
            $inform_name = $teacher_name ? $teacher_name.'老师' : '';
        }elseif ($inform_info['type']== 2){
            $inform_name = '学校';
        }elseif($inform_info['type']== 3){
            $inform_name = '总后台';
        }
        $time = $inform_info['addtime'] ? date('Y-m-d H:i:s',$inform_info['addtime']) : '';
        return compact('time','inform_name');
    }

    public function get_survey_info($v,$type){

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
            })->order('id desc')->find();
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
            })->order('id desc')->find();
        }
        $survey_name = '';
        if($survey_info['types']==1){
            $teacher_name = Db::name('class_teacher')->where(['uid'=>$v['uid'],'class_id'=>$v['class_id']])->find()['name'] ?? '';
            $survey_name = $teacher_name ? $teacher_name.'老师' : '';
        }elseif ($survey_info['types']== 2){
            $survey_name = '学校';
        }elseif($survey_info['types']== 3){
            $survey_name = '总后台';
        }
        $time = $survey_info['addtime'] ? date('Y-m-d H:i:s',$survey_info['addtime']) : '';
        return compact('time','survey_name');
    }



    public function count(){
        $res = ['code'=>0,'message'=>'','data'=>[]];


        function getGroupChat($v){
            $chat = Db::name('class_chat')->where(['class_id'=>$v['class_id'],'type'=>1])->order('id desc')->find();
            $content_text = '';
            if($chat){
                if($chat['content_type'] == 1){
                    $content_text = $chat['content_text'];
                }elseif($chat['content_type'] == 2){
                    $content_text = $chat['file_name'];
                }
                elseif($chat['content_type'] == 5){
                    $content_text = '调查问卷';
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

        function getChat($uid,$class_id,$type,$patriarch_id,$teacher_id){
            $where = [
                'class_id' => $class_id,
                'user_type'=>$type
            ];
            if($type == 1){
                $where['patriarch_id'] = $patriarch_id;
            }else{
                $where['teacher_id'] = $teacher_id;
            }


            $info = Db::name('class_chat_log')->where($where)
                ->order('id desc')->select();
            $scoket = new ScoketController;
            foreach ($info as $k=>$v){

                if($v['tuser_type'] == 1){
                    $name = $scoket->getName(1,$v['tid']);
                }else{
                    $name = $scoket->getName(2,$v['tid']);
                }


                $where = [
                    'class_id' => $class_id,
                    'type' => 2
                ];


                $where_oneself = [
                    'user_type' => $type,
                    'tuser_type' => $v['tuser_type'],
                    'tid' => $v['tid']
                ];
                if($type == 1){
                    $where_oneself['patriarch_id'] = $patriarch_id;

                }elseif ($type == 2){
                    $where_oneself['teacher_id'] = $teacher_id;
                }

                $where_others = [
                    'user_type' => $v['tuser_type'],
                    'tuser_type' => $v['user_type'],
                    'tid' => $v['patriarch_id'] ?? $v['teacher_id']
                ];
                if($v['tuser_type'] == 1){
                    $where_others ['patriarch_id'] = $v['tid'];
                }else{
                    $where_others ['teacher_id'] = $v['tid'];
                }

                $chat = Db::name('class_chat')->where($where)
                    ->whereOr(function ($query)use ($where_oneself){
                        $query->where($where_oneself);
                    })
                    ->whereOr(function ($query)use($where_others){
                        $query->where($where_others);
                    })
                    ->order('id desc')->find();

                if($chat['content_type'] == 1){
                    $message = $chat['content_text'];
                }elseif($chat['content_type'] == 2){
                    $message = $chat['file_name'];
                }else{
                    $message = '问卷通知';
                }

                $where = [
                    'user_type' => $v['tuser_type'],
                    'type' =>2,
                    'class_id'=>$v['class_id'],
                    'patriarch_id'=>$v['tuser_type'] == 1 ? $v['tid'] : 0,
                    'teacher_id'=>$v['tuser_type'] == 2 ? $v['tid'] : 0,
                    'tid' => $v['teacher_id'] ?? $v['patriarch_id'],
                    'tuser_type' => $v['user_type'],
                    'is_read' =>  0
                ];

                $value = [
                    'name' => $name,
                    'user_type' => $v['tuser_type'],
                    'patriarch_id' => $v['tuser_type'] == 1 ? $v['tid'] : 0,
                    'teacher_id' => $v['tuser_type'] == 2 ? $v['tid'] : 0,
                    'message' => $chat ? $message :  "",
                    'time' => $chat ? date('Y-m-d H:i:s',$chat['addtime']) : '',
                    'count' => Db::name('class_chat')->where($where)->count()
                ];
                $info[$k] = $value;
            }
            return $info;
        }

        $param = $this->request->param();


        $where = [];
        $where['uid'] = $param['uid'];
        $patriarch = Db::name('class_patriarch')->where($where)->select();
        $teacher = Db::name('class_teacher')->where($where)->select();

        $result = [];
        foreach ($patriarch as $k=>$v){

            $inform_info = $this->get_inform_info($v,1);
            $survey_info = $this->get_survey_info($v,1);
            $getGroupChat= getGroupChat($v);
            $Chat = getChat($param['uid'],$v['class_id'],1,$v['id'],0);
            $count=0;
            foreach ($Chat  as $key=>$item){
                $count += $item['count'];
            }

            $result[] = [
                'user_type' => 1,
                'patriarch_id' => $v['id'],
                'class_id' => $v['class_id'],
                'group_chat_count' => $v['group_chat_count'],
                'group_content' => $getGroupChat['content_text'],
                'group_time' => $getGroupChat['time'] ? date('Y-m-d H:i:s',$getGroupChat['time']) : 0,
                'group_push_name' => $getGroupChat['push_name'],
                'inform_count' => $v['inform_count'],
                'inform_name' =>$inform_info['inform_name'],
                'inform_time' =>$inform_info['time'],
                'survey_count' => $v['survey_count'],
                'survey_name' => $survey_info['survey_name'],
                'survey_time' => $survey_info['time'],
                'count' => $v['group_chat_count'] +  $v['inform_count'] + $v['survey_count'] + $count,
                'chat' => $Chat
            ];
        }

        foreach ($teacher as $k=>$v){
            $inform_info = $this->get_inform_info($v,2);
            $survey_info = $this->get_survey_info($v,2);
            $getGroupChat = getGroupChat($v);


            $Chat = getChat($param['uid'],$v['class_id'],2,0,$v['id']);
            $count=0;
            foreach ($Chat  as $key=>$item){
                $count += $item['count'];
            }

            $result[] = [
                'user_type' => 2,
                'teacher_id' => $v['id'],
                'class_id' => $v['class_id'],
                'group_chat_count' => $v['group_chat_count'],
                'group_content' => $getGroupChat['content_text'],
                'group_time' => $getGroupChat['time'] ? date('Y-m-d H:i:s',$getGroupChat['time']) : 0,
                'group_push_name' => $getGroupChat['push_name'],
                'inform_count' => $v['inform_count'],
                'inform_name' =>$inform_info['inform_name'],
                'inform_time' =>$inform_info['time'],
                'survey_count' => $v['survey_count'],
                'survey_name' => $survey_info['survey_name'],
                'survey_time' => $survey_info['time'],
                'count' => $v['group_chat_count'] +  $v['inform_count'] + $v['survey_count'] + $count,
                'chat' => $Chat
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


    public function clearNotification(){
        $res = ['code'=>0,'message'=>'','data'=>[]];
        $param = $this->request->param();
        $inform_id = $param['survey_id'] ?? 0;
        $inform = '';
        if($inform_id){
            $inform = Db::name('class_inform')->where(['id'=>$inform_id])->find();
        }
        $type = $param['type'];
        $data = [];
        if($type == 2){
            $uid = json_decode($param['uid'],true);
            $info = Db::name('class_teacher')->where(['uid'=>$uid])->select();
            foreach ($info as $k=>$v){
                if(!$inform){
                    $inform = $this->get_inform_info($v,2);
                }
                $data[$v['uid']][] = [
                    'teacher_id' => $v['id'],
                    'class_id' => $v['class_id'],
                    'user_type' => '2',
                    'inform_count' => $v['inform_count'],
                    'inform_name' => $inform_id ? $inform['title'] : $inform['inform_name'],
                    'inform_time' =>  $inform_id ? date('Y-m-d H:i:s',$inform['addtime']) :  $inform['time']
                ];
            }
        }else{
            $patriarch_id = json_decode($param['patriarch_id'],true);
            $info = Db::name('class_patriarch')->where(['id'=>$patriarch_id])->select();
            foreach ($info as $k=>$v){
                if(!$inform){
                    $inform = $this->get_inform_info($v,1);
                }
                $data[$v['uid']][] = [
                    'patriarch_id' => $v['id'],
                    'class_id' => $v['class_id'],
                    'user_type' => '1',
                    'inform_name' => $inform_id ? $inform['title'] : $inform['inform_name'],
                    'inform_count' => $v['inform_count'],
                    'inform_time' => $inform_id ? date('Y-m-d H:i:s',$inform['addtime']) :  $inform['time']
                ];
            }
        }
        $res['data'] = ['type'=>7,'info'=>$data];

        echo json_encode($res);
        exit;
    }


    public function clearSurvey(){
        $res = ['code'=>0,'message'=>'','data'=>[]];
        $param = $this->request->param();
        $type = $param['type'];
        $survey_id = $param['survey_id'] ?? 0;
        $survey = '';
        if($survey_id){
            $survey = Db::name('class_survey')->where(['id'=>$survey_id])->find();
        }
        $data = [];
        if($type == 2){
            $uid = json_decode($param['uid'],true);
            $info = Db::name('class_teacher')->where(['uid'=>$uid])->select();
            foreach ($info as $k=>$v){
                if(!$survey){
                    $survey = $this->get_survey_info($v,2);
                }
                $data[$v['uid']][] = [
                    'teacher_id' => $v['id'],
                    'class_id' => $v['class_id'],
                    'user_type' => '2',
                    'survey_count' => $v['survey_count'],
                    'survey_name' => $survey_id ? $survey['title'] : $survey['survey_name'],
                    'survey_time' => $survey_id ? date('Y-m-d H:i:s',$survey['addtime']) :  $survey['time']
                ];
            }
        }else{
            $patriarch_id = json_decode($param['patriarch_id'],true);
            $info = Db::name('class_patriarch')->where(['id'=>$patriarch_id])->select();
            foreach ($info as $k=>$v){
                if(!$survey){
                    $survey = $this->get_survey_info($v,1);
                }
                $data[$v['uid']][] = [
                    'patriarch_id' => $v['id'],
                    'class_id' => $v['class_id'],
                    'user_type' => '1',
                    'survey_count' => $v['survey_count'],
                    'survey_name' => $survey_id ? $survey['title'] : $survey['survey_name'],
                    'survey_time' => $survey_id ? date('Y-m-d H:i:s',$survey['addtime']) :  $survey['time']
                ];
            }
        }
        $res['data'] = ['type'=>8,'info'=>$data];
        echo json_encode($res);
        exit;
    }


    public function bear($content_text){
        $key = 'getclasschatbear';
        $bear = getcaches($key);
        if(!$bear){
            $bear = DB::name('class_chat_bear')
                ->field('id,name')
                ->order("list_order asc")
                ->select()->toArray();
            setcaches($key, $bear);
        }
        if($bear){
            $bear = array_column($bear,'name');
            foreach ($bear as $k=>$v){
                if(strstr($content_text,$v)){
                    return 0;
                }
            }
        }
        return 1;
    }


    public function existUser($type,$class_id,$type_id=0,$uid=0){

        if($type == 1){
            $key = 'patriarch_class'.$class_id;
            $patriarchAll = getcaches($key);
            if(!$patriarchAll){
                $patriarchAll = Db::name('class_patriarch')->where(['class_id'=>$class_id])->select()->toArray();
                $patriarchAll = array_column($patriarchAll,'uid','id');
                setcaches($key,$patriarchAll);
            }
            if($patriarchAll[$type_id] != $uid) $this->off('您不是本班家长');
        }else{
            $key = 'teacher_class'.$class_id;
            $teacherAll = getcaches($key);
            if(!$teacherAll){
                $teacherAll = Db::name('class_teacher')->where(['class_id'=>$class_id])->select()->toArray();
                $teacherAll = array_column($teacherAll,'uid','id');
                setcaches($key,$teacherAll);
            }
            if($teacherAll[$type_id] != $uid) $this->off('您不是本班老师');
        }

    }


    public function test(){
        $uid = 5;
        $where['uid'] = $uid;
        $patriarch = Db::name('class_patriarch')->where($where)->find();
        $teacher = Db::name('class_teacher')->where($where)->find();

        function get_inform_info($v,$type){
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
                })->order('id desc')->find();
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
                })->order('id desc')->fetchSql()->find();
            };
            return $survey_info;
        }


        return var_dump(get_inform_info($teacher,2));
    }
}
