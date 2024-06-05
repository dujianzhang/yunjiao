<?php


namespace app\student\model;

use think\Model;

/**
 * 课程
 * Class CourseModel
 * @package app\student\model
 */
class CourseModel extends Model
{
    protected $pk = 'id';
    protected $name = 'course';


    // 模型初始化
    protected static function init()
    {
        //TODO:初始化内容
    }

    /**
     * @param array $where
     * @param int $limit
     * @param int $page
     * @param int $page_num
     * @param string $order
     * @param string $field
     * @return array|\PDOStatement|string|\think\Collection|\think\model\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getCourseList($where = [], $limit = 0, $page = 0, $page_num = 0, $order = '', $field = '')
    {
        if (!$field) {
            $field = 'id,uid,name,gradeid,sort,name,thumb,des,lessons,starttime,endtime,paytype,payval,type,lessons,ismaterial,islive,views';
        }
        $query = $this
            ->field($field);

        $where[] = ['tx_trans', '=', 1];

        if ($where) {
            $query = $query->where($where);
        }

        if ($limit) {
            $query = $query->limit($limit);
        }
        if ($order) {
            $query = $query->order($order);
        }
        if ($page && $page_num) {
            $query = $query->page($page, $page_num);
        }

        return $query;
    }

    /**
     * 获取课程表中的名字
     */
    public function getTypeSort($sort, $type)
    {
        $sortType = [
//            内容
            0 => [
                1 => '',//图文
                2 => '',//视频
                3 => '',//语音
            ],
            //课程
            1 => [
                1 => '',//语音ppt
                2 => '',//
                3 => '',//语音
            ],
            //语音直播
            2 => [
                1 => 'PPT讲解',//语音ppt
                2 => '视频讲解',//语音视频
                3 => '音频讲解',//语音音频
            ],
            //视频直播
            3 => [
                5 => '普通直播',
            ],
            //白板互动
            4 => [
                4 => '白板互动'
            ]
        ];

        return $sortType[$sort][$type] ?? '';
    }


    public function getLessonsAttr($value)
    {
        if ($value == 0) {
            return '<span class="default">尚未添加内容</span>';
        }
        return '<span class="default">' . $value . '课时</span>';
    }

    public function getThumbAttr($value)
    {
        return get_upload_path($value);
    }

    /**
     * 设置 课程的状态
     * @param $item
     */
    public function setCourseStatus($item)
    {
        $startDateTime = date('Y-m-d H:i:s', (int)$item['starttime']);
        $startArr = explode(' ', $startDateTime);

        if (time() < $item['starttime']) {//直播未开始

            $liveTag = '<span class="default">' . $startDateTime . '</span>';
            if (date("Y-m-d") == $startArr[0]) {
                $liveTag = '<span class="default">今天 ' . $startArr[1] . '</span>';
            }

            if ($item['islive'] == 1) {
                $liveTag = '<span class="in_live">正在直播</span>';
            } elseif ($item['islive'] == 2) {
                $liveTag = '<span class="default">已结束</span>';
            }


        } else if ((time() > $item['starttime']) && (time() < $item['endtime'])) {//直播中
            $liveTag = '<span class="in_live">正在直播</span>';

            if ($item['islive'] == 0) {
                $liveTag = '<span class="default">未开始</span>';
            } elseif ($item['islive'] == 2) {
                $liveTag = '<span class="default">已结束</span>';
            }

        } else {//已结束
            $liveTag = '<span class="default">已结束</span>';

            if ($item['islive'] == 0) {
                $liveTag = '<span class="default">未开始</span>';
            } elseif ($item['islive'] == 1) {
                $liveTag = '<span class="in_live">正在直播</span>';
            }
        }


        //VIP状态
        $vipTag = '';
        if (isset($item->vip_course) && !$item->vip_course->isEmpty()) {
            $vipTag = '<span class="vip">VIP</span>';
        }

        //获取形式
        $payTag = '';
        if ($item['paytype'] == 0) {
            $payTag = '<span class="free">免费</span>';
        } else if ($item['paytype'] == 1) {

            if (isset($item->seckill) && !$item->seckill->isEmpty()) {//秒杀
                $payTag = '<span class="money">¥ ' . $item->seckill['money'] . '</span>';
            } else {
                $payTag = '<span class="money">¥ ' . $item['payval'] . '</span>';
            }

        } else if ($item['paytype'] == 2) {
            $payTag = '<span class="password">密码</span>';
        }

        //图文类型
        $typeTap = '';
        if ($item['sort'] == 0) {//图文
            if ($item['type'] == 1) {
                $typeTap = '<div class="content"><img src="/static/student/images/common/tu.png" alt=""><span>图文</span></div>';
            } else if ($item['type'] == 2) {
                $typeTap = '<div class="content"><img src="/static/student/images/common/shi.png" alt=""><span>视频</span></div>';
            } else if ($item['type'] == 3) {
                $typeTap = '<div class="content"><img src="/static/student/images/common/yin.png" alt=""><span>音频</span></div>';
            }
        }

        $marketingTag = '';
        //秒杀状态
        $seckillTag = '';
        if (isset($item->seckill) && !$item->seckill->isEmpty()) {
            $seckillTag = '<span class="seckill">秒杀价</span>';
            $marketingTag = '<span class="seckill">秒杀价</span>' . $payTag;
        }

        //拼团状态
        $pinkTag = '';
        if (isset($item->pink_info) && !$item->pink_info->isEmpty()) {
            $pinkTag = '<span class="seckill pink">拼团价</span>';
            $marketingTag = '<span class="seckill pink">拼团价</span>' . $payTag;
        }
        if (!$marketingTag) {
            $marketingTag = $payTag;
        }

        $ismaterialTag = '';
        if (isset($item['ismaterial']) && $item['ismaterial'] == 1) {
            $ismaterialTag = '<img src="/static/student/images/index/book.png" class="ismaterial_img"><span class="ismaterial_text">含教材</span>';
        }

        $item['live_tag'] = $liveTag;
        $item['vip_tag'] = $vipTag;
        $item['seckill_tag'] = $seckillTag;
        $item['pink_tag'] = $pinkTag;
        $item['pay_tag'] = $payTag;
        $item['type_tag'] = $typeTap;
        $item['marketing_tag'] = $marketingTag;
        $item['ismaterialTag'] = $ismaterialTag;

        return $item;
    }

    public function autotag($item)
    {
        if ($item['sort'] == 0) {//内容
            return $item['type_tag'];
        } else if ($item['sort'] == 1) {//课程
            return $item['lessons'];
        } else if (in_array($item['sort'], [2, 3, 4])) {//直播
            return $item['live_tag'];
        }

    }

    /**
     * 获取课程老师信息
     * @param array $cidArr
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getTeacherInfo(array $cidArr)
    {
        $result = $this
            ->with(['users' => function ($query) {
                $query->where([
                    ['type', '=', 1],
                    ['user_status', '=', 1],
                ]);
            }])
            ->field('id,uid')
            ->where([['id', 'IN', $cidArr]])
            ->select()
            ->toArray();
        return $result;
    }


    public function users()
    {
        return $this->belongsTo('UsersModel', 'uid', 'id')->field('id,avatar_thumb,user_nickname');
    }

    //VIP课程
    public function vipCourse()
    {
        return $this->hasOne('VipCourseModel', 'cid', 'id')->field('id,cid')->where([['type', '=', 0]]);
    }

    //未开始秒杀课程
    public function seckill()
    {
        return $this->hasOne('SeckillModel', 'cid', 'id')->field('id,cid,starttime,endtime,money')
            ->where([
                ['type', '=', 0],
                ['status', '=', 1],
                ['starttime', '<=', time()],
                ['endtime', '>=', time()]
            ]);
    }

    //未开始的拼团
    public function pinkInfo()
    {
        return $this->hasMany('PinkModel', 'cid', 'id')
            ->field('id,cid')
            ->where([
                ['type', '=', 0],
                ['status', '=', 1],
                ['starttime', '<=', time()],
                ['endtime', '>=', time()]
            ]);
    }

    /**
     * 获取科目分类下的所有课程
     */
    public function getSelectByCourseClass($courseClass)
    {
        $list = [];


        foreach ($courseClass as $key => $value) {

            $whereSort1 = [
                ['sort', '=', 1],
                ['status', '=', 1],
                ['shelvestime', '<=', time()],
                ['tx_trans', '=', 1],
                ['isvip', '=', 0],//非会员专享
            ];

            $whereSort1[] = ['classid', '=', $value['id']];
            $courseList = $this
                ->where($whereSort1)
                ->field('id,name,thumb,paytype,payval,classid')
                ->select()
                ->each(function ($item, $key) {
                    $payTag = '';
                    if ($item['paytype'] == 0) {
                        $payTag = '免费';
                    } else if ($item['paytype'] == 1) {
                        $payTag = '¥ ' . $item['payval'];
                    } else if ($item['paytype'] == 2) {
                        $payTag = '密码';
                    }
                    $item['price'] = $payTag;
                    return $item;
                })
                ->toArray();
            $list[$value['id']] = $courseList;
        }
        return $list;
    }

//    获取老师下的课程数量/和学习人数
    public function getCountByteacher($touid)
    {
        $course_number = 0;
        $student_number = 0;
        $where = [
            ['status', '>=', 1],
            ['shelvestime', '<', time()],
            ['uid', '=', $touid]
        ];
        $course_number = $this
            ->where($where)
            ->where('tx_trans', 1)
            ->count();

        $student_number = $this
            ->where($where)
            ->where('tx_trans', 1)
            ->sum('views');
        return compact('course_number', 'student_number');
    }

}
