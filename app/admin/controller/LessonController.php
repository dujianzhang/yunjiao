<?php

/* 课时 */
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class LessonController extends AdminBaseController
{

    /* 内容形式 */
    protected function getTypes($k='',$status=true){
        if($status){
            $type=[
                '1'=>'图文自学',
                '2'=>'视频自学',
                '3'=>'音频自学',
                '8'=>'普通直播',
                '7'=>'白板互动',
            ];
        }else{
            $type=[
                '1'=>'图文自学',
                '2'=>'视频自学',
                '3'=>'音频自学',
            ];
        }

        if($k===''){
            return $type;
        }
        return isset($type[$k])? $type[$k] : '' ;
    }
    
    public function index()
    {
        $data = $this->request->param();
        $map=[];
        
        $courseid=isset($data['courseid']) ? $data['courseid']: '0';
        $map[]=['courseid','=',$courseid];
        
        $courseinfo=Db::name("course")
                ->where(['id'=>$courseid])
                ->find();
        if($courseinfo){
            $courseinfo['thumb']=get_upload_path($courseinfo['thumb']);
            $type_a='免费';
            if($courseinfo['paytype']==1){
                $type_a='￥'.$courseinfo['payval'];
            }
            if($courseinfo['paytype']==2){
                $type_a='密码';
            }
            $courseinfo['type_a']=$type_a;
        }


        $this->assign('courseinfo', $courseinfo);

        $types=$this->getTypes();

        $list = Db::name("course_lesson")
            ->where($map)
            ->order("list_order asc")
            ->paginate(20);

        $list->each(function($v,$k)use($types){
            $v['type_t']=$types[$v['type']] ?? '';
            $v['trial']=$v['istrial'] ==1 ? '是':'否';
            $times='--';
            if($v['type']>3){
                $times=date('Y-m-d H:i',$v['starttime']);
            }
            $v['times']=$times;

            return $v;
        });

        $list->appends($data);
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        

        $this->assign('courseid', $courseid);
        // 渲染模板输出
        return $this->fetch('index');
    }

    public function add()
    {
        $data = $this->request->param();
        
        $courseid=isset($data['courseid']) ? $data['courseid']: '0';
        $uid=isset($data['uid']) ? $data['uid']: '0';
        
        $this->assign('courseid', $courseid);
        $this->assign('uid', $uid);
        
        $courseinfo=Db::name("course")
                ->where(['id'=>$courseid])
                ->find();

        $this->assign('courseinfo', $courseinfo);

        $this->assign('types', $this->getTypes('',$courseinfo['uid'] ? true : false));
        $txsign='';
        $configpri=getConfigPri();
        $trans_switch=$configpri['trans_switch'];
        if($trans_switch==1){
            $txsign=getTxVodSign();
        }

        $this->assign('trans_switch', $trans_switch);
        $this->assign('txsign', $txsign);

        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();
            
            $courseid=$data['courseid'];

            $name=$data['name'];
            if($name == ''){
                $this->error('请填写名称');
            }
            
            $map[]=['name','=',$name];
            $map[]=['courseid','=',$courseid];
            $isexist = DB::name('course_lesson')->where($map)->find();
            if($isexist){
                $this->error('同名课时已存在');
            }
            $configpri=getConfigPri();
            /* 内容类 */
            $type=$data['type'];
            if($type == 1){
                /* 图文自学 */

            }

            if($type==2){
                /* 视频自学 */
                $type_video=$data['type_video'];
                if($type_video == ''){
                    $this->error('请上传视频');
                }
                $data['url']=$type_video;
            }

            if($type==3){
                /* 音频自学 */
                $type_audio=$data['type_audio'];
                if($type_audio == ''){
                    $this->error('请上传音频');
                }
                $data['url']=$type_audio;
            }

            if($type>=4){
                $starttime=$data['starttime'];
                if($starttime == ''){
                    $this->error('请填写上课时间');
                }
                $data['starttime']=strtotime($starttime);

                $endtime=$data['endtime'];
                if($endtime == ''){
                    $this->error('请填写下课时间');
                }

                $data['endtime']=strtotime($endtime);

                if($data['starttime']>=$data['endtime']){
                    $this->error('下课时间不能早于上课时间');
                }
            }else{
                unset($data['starttime']);
                unset($data['endtime']);
            }

            if($type==7){
                /* 白板 */

                $white_type=$configpri['whiteboard_type'];
                $data['whitetype']=$white_type;
                if($white_type==1){
                    $res = createNetlessRoom();
                    if (!$res) {
                        $this->error('创建白板失败');
                    }

                    if ($res['code'] != 200) {
                        $this->error($res['msg']['reason']);
                    }

                    $data['uuid'] = $res['msg']['room']['uuid'];
                    $data['roomtoken'] = $res['msg']['roomToken'];
                }
            }

            if($type==8){
                /* 普通直播 */

            }

            if($type<4){
                $content=isset($data['content'])?$data['content']:'';
                if($content == ''){
                    $this->error('请编辑内容');
                }
            }else{
                $data['des']='';
                $data['content']='';
            }
            
            /* 去除无用字段 */
            unset($data['type_video']);
            unset($data['type_audio']);

            $data['rtc_type']=$configpri['rtc_type'];
            $data['tx_trans']=1;

            $tx_fileid=$data['tx_fileid'] ?? '';
            if($tx_fileid!=''){
                $data['tx_trans']=0;
            }
            

            $id = DB::name('course_lesson')->insertGetId($data);
            if(!$id){
                $this->error("添加失败！");
            }

            $this->upLessons($courseid);

            if( $tx_fileid !='' ){
                tx_sendTrans($tx_fileid);
            }

            /* 课时提醒 */
            $courseinfo=DB::name('course')->where(['id'=>$courseid])->find();
            if($courseinfo){
                $nowtime=time();
                if($courseinfo['shelvestime']<$nowtime){
                    $title='你订阅的'.$courseinfo['name'].'更新课时啦！';
                    $type=1;
                    $touid=DB::name('course_users')->field('uid')->where(['courseid'=>$courseid,'status'=>1])->select()->toArray();
                    if($touid){
                        $touids=array_column($touid,'uid');
                        $touids=array_filter($touids);
                        if($touids){
                            sendMessage($type,$touids,$title);
                        }
                        
                    }
                    
                }
            }
            
            $this->success("添加成功！");
        }
    }

    public function edit()
    {
        $id   = $this->request->param('id', 0, 'intval');
        
        $data=Db::name('course_lesson')
            ->where("id={$id}")
            ->find();
        if(!$data){
            $this->error("信息错误");
        }
        
        $this->assign('data', $data);
        
        $courseinfo=Db::name("course")
                ->where(['id'=>$data['courseid']])
                ->find();
        $this->assign('courseinfo', $courseinfo);
        
        $this->assign('types', $this->getTypes());

        $txsign=getTxVodSign();
        $this->assign('txsign', $txsign);
        
        return $this->fetch();
    }

    public function editPost()
    {
        if ($this->request->isPost()) {
            $data      = $this->request->param();
            
            $id=$data['id'];
            $courseid=$data['courseid'];
            
            $name=$data['name'];
            if($name == ''){
                $this->error('请填写名称');
            }
            
            $map[]=['name','=',$name];
            $map[]=['courseid','=',$courseid];
            $map[]=['id','<>',$id];
            $isexist = DB::name('course_lesson')->where($map)->find();
            if($isexist){
                $this->error('同名课时已存在');
            }

            /* 内容类 */
            $type=$data['type'];
            if($type == 1){
                /* 图文 */

            }

            if($type==2){
                /* 视频 */
                $type_video=$data['type_video'];
                if($type_video == ''){
                    $this->error('请上传视频');
                }
                $data['url']=$type_video;
            }

            if($type==3){
                /* 音频 */
                $type_audio=$data['type_audio'];
                if($type_audio == ''){
                    $this->error('请上传音频');
                }
                $data['url']=$type_audio;


            }

            if($type>=4){
                $starttime=$data['starttime'];
                if($starttime == ''){
                    $this->error('请填写上课时间');
                }
                $data['starttime']=strtotime($starttime);

                $endtime=$data['endtime'];
                if($endtime == ''){
                    $this->error('请填写下课时间');
                }

                $data['endtime']=strtotime($endtime);

                if($data['starttime']>=$data['endtime']){
                    $this->error('下课时间不能早于上课时间');
                }
            }else{
                unset($data['starttime']);
                unset($data['endtime']);
            }

            if($type==7){
                /* 授课直播 */
            }

            if($type==8){
                /* 普通直播 */

            }

            if($type<4){
                $content=isset($data['content'])?$data['content']:'';
                if($content == ''){
                    $this->error('请编辑内容');
                }
            }else{
                $data['des']='';
                $data['content']='';
            }

            /* 去除无用字段 */
            unset($data['type_video']);
            unset($data['type_audio']);
            unset($data['cdntype']);

            $istrans=0;

            $tx_fileid=$data['tx_fileid'] ?? '';
            if($tx_fileid!=''){
                $oldinfo=Db::name('course_lesson')->field('tx_fileid')->where('id',$data['id'])->find();
                if($oldinfo['tx_fileid']!=$tx_fileid){
                    $data['tx_trans']=0;
                    $istrans=1;
                }
            }

            $rs = DB::name('course_lesson')->update($data);

            if($rs === false){
                $this->error("保存失败！");
            }

            if($istrans==1){
                tx_sendTrans($tx_fileid);
            }
            
            $this->success("保存成功！");
        }
    }
    
    public function listOrder()
    {
        $model = DB::name('course_lesson');
        parent::listOrders($model);
        $this->success("排序更新成功！");
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');

        $info=DB::name('course_lesson')->where('id',$id)->find();

        $rs = DB::name('course_lesson')->where('id',$id)->delete();
        if(!$rs){
            $this->error("删除失败！");
        }

        $this->upLessons($info['courseid']);
        $this->success("删除成功！");
    }
    
    protected function upLessons($courseid){
        $nums=DB::name('course_lesson')->where([['courseid','=',$courseid],['tx_trans','=',1]])->count();
        DB::name('course')->where('id',$courseid)->setField('lessons',$nums);
    }
}