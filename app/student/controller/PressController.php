<?php


namespace app\student\controller;

use app\student\model\CourseModel;
use cmf\controller\StudentBaseController;
use app\student\model\NewsModel;
use think\Db;

/**
 * 年级新闻资讯
 * Class PressController
 * @package app\student\controller
 */
class PressController extends StudentBaseController
{
    public function index()
    {
        $NewsModel = new NewsModel();
        $newsList = $NewsModel
            ->where([
                ['gradeid', '=', session('gradeid') ?? 0],
            ])
            ->order('id DESC')
            ->paginate();

        foreach ($newsList as $key => $value) {
            $newsList[$key]['thumb'] = get_upload_path($value['thumb']);
            $newsList[$key]['add_time'] = date('Y-m-d H:i:s', $value['addtime']);
        }

        //直播课
        $whereSort3 = [
            ['status', '=', 1],
            ['shelvestime', '<=', time()],
            ['sort', 'IN', [2, 3, 4]],
            ['tx_trans', '=', 1],
            ['isvip', '=', 0],//非会员专享
        ];

        if (session('student')) {
            $whereSort3[] = ['gradeid', '=', session('student.gradeid')];
        }
        $CourseModel = new CourseModel();

        //好课推荐
        $goodLesson = $CourseModel
            ->getCourseList($whereSort3, 8, 0, 0, 'sort ASC')
            ->with('seckill,users,pinkInfo')
            ->select()
            ->each(function ($item, $key) use ($CourseModel) {
                $item['avatar_thumb'] = $item->users['avatar_thumb'];
                $item['user_nickname'] = $item->users['user_nickname'];

                $CourseModel->setCourseStatus($item);
                $nums=Db::name('course_users')->where([['courseid','=',$item['id']]])->count();
                $item['nums'] = $nums;
            })
            ->toArray();


        $this->assign([
            'newsList' => $newsList,
            'goodlesson' => $goodLesson,
            'navid' => 10
        ]);

        return $this->fetch();
    }

    public function read()
    {
        $id = input('id/d', 0);
        if ($id <= 0) {
            return $this->error('参数错误');
        }

        $NewsModel = new NewsModel();
        $info = $NewsModel->where([
            ['id', '=', $id]
        ])->find();
        $info['thumb'] = get_upload_path($info['thumb']);
        $info['add_time'] = date('Y-m-d H:i:s', $info['addtime']);
        $year1 = explode(' ',$info['add_time']);
        $year = explode('-',$year1[0])[0];

        $month = explode('-',$year1[0])[1].'/'.explode('-',$year1[0])[2];

        $time1 = explode(' ',$info['add_time'])[1];
        $time = explode(':',$time1)[0].':'.explode(':',$time1)[1];

        $info['year'] = $year;
        $info['month'] = $month;
        $info['time'] = $time;

        $newsList = $NewsModel
            ->where([
                ['gradeid', '=', session('student.gradeid') ?? 0],
            ])
            ->order('id DESC')
            ->paginate();

        foreach ($newsList as $key => $value) {
            $newsList[$key]['thumb'] = get_upload_path($value['thumb']);
            $newsList[$key]['add_time'] = date('Y-m-d H:i:s', $value['addtime']);
        }

        $this->assign([
            'info' => $info,
            'newsList' => $newsList,
        ]);

        return $this->fetch();
    }
}
