<?php


namespace app\student\model;

use think\Model;

class CoursePackageModel extends Model
{
    protected $pk = 'id';
    protected $name = 'course_package';


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
    public function getList($where = [], $limit = 0, $page = 0, $page_num = 0, $order = '', $field = '')
    {
        if (!$field) {
            $field = 'id,gradeid,name,des,thumb,nums,courseids,price';
        }

        $query = $this
            ->field($field);

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

    public function getNumsAttr($value)
    {

        return '<span class="default">' . $value . '课程</span>';
    }


    public function getThumbAttr($value)
    {
        return get_upload_path($value);
    }

    public function setTapStatus($item)
    {
        $teacher_tap = '';
        $user_nickname = '';
        if (count($item['teacher']) == 1) {
            $teacher_tap = '<img src="' . $item['teacher'][0]['users']['avatar_thumb'] . '">';
            $user_nickname = $item['teacher'][0]['users']['user_nickname'] ?? '';
        } else if (count($item['teacher']) > 1) {
            foreach ($item['teacher'] ?? [] as $key => $value) {
                if ($value['users']['avatar_thumb'] ?? '') {
                    $teacher_tap .= '<img src="' . $value['users']['avatar_thumb'] . '">';
                }
            }
            $user_nickname = '';
        }

        //VIP状态
        $vipTag = '';
        if (isset($item->vip_course) && !$item->vip_course->isEmpty()) {
            $vipTag = '<span class="vip">VIP</span>';
        }

        $money = '<span class="money">¥ ' . $item['price'] . '</span>';

        //秒杀状态
        if (isset($item->seckill) && !$item->seckill->isEmpty()  && $item['status'] = 1) {
            $money = '<span class="seckill">秒杀价</span>' . $money;
        }

        //拼团状态
        if (isset($item->pink_info) && !$item->pink_info->isEmpty()  && $item['status'] = 1) {
            $money = '<span class="seckill pink">拼团价</span>' . $money;
        }

        $res = [
            'teacher_avatar_tap' => $teacher_tap,
            'user_nickname_tap' => $user_nickname,
            'vip_tag' => $vipTag,
            'marketing_tag' => $money,
        ];
        return $res;
    }

    public function getIsmaterial($packId){
        $packInfo = $this->field('courseids')->where('id',$packId)->find();
        $courseArr = handelSetToArr($packInfo['courseids']);
        $CourseModel = new CourseModel();

        $isset = $CourseModel->where('id','IN',$courseArr)->find();
        $ismaterialTag = '';
        if ($isset) {
            $ismaterialTag = '<img src="/static/student/images/index/book.png" class="ismaterial_img"><span class="ismaterial_text">含教材</span>';
        }
        return $ismaterialTag;
    }

    //VIP课程
    public function vipCourse()
    {
        return $this->hasMany('VipCourseModel', 'cid', 'id')
            ->field('id,cid')
            ->where([['type', '=', 1]]);
    }

    //进行中的秒杀课程
    public function seckill()
    {
        return $this->hasMany('SeckillModel', 'cid', 'id')
            ->field('id,cid')
            ->where([
                ['type', '=', 1],
                ['status', '=', 1],
                ['starttime', '<=', time()],
                ['endtime', '>=', time()]
            ]);
    }

    //进行中的拼团
    public function pinkInfo()
    {
        return $this
            ->hasMany('PinkModel', 'cid', 'id')
            ->field('id,cid')
            ->where([
                ['type', '=', 1],
                ['status', '=', 1],
                ['starttime', '<=', time()],
                ['endtime', '>=', time()]
            ]);
    }
}
