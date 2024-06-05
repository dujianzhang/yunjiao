<?php


namespace app\student\model;

use think\Model;


class NewsModel extends Model
{
    protected $pk = 'id';
    protected $name = 'news';


    // 模型初始化
    protected static function init()
    {
        //TODO:初始化内容
    }


    /**
     * 首页新闻咨询列表
     * @param $gradeid
     * @param int $number
     */
    public function getHomeList($gradeid, $number = 8)
    {
        $list = $this
            ->field('id,title,thumb,des,content,addtime')
            ->where([
                ['gradeid', '=', $gradeid],
            ])
            ->limit($number)
            ->select()->toArray();

        foreach ($list as $key => &$value) {
            $value['thumb'] = get_upload_path($value['thumb']);
            $value['add_time'] = date('Y-m-d H:i:s', $value['addtime']);
        }
        return $list;
    }
}
