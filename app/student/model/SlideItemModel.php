<?php


namespace app\student\model;

use think\Model;

class SlideItemModel extends Model
{
    protected $pk = 'id';
    protected $name = 'slide_item';

    // 模型初始化
    protected static function init()
    {
        //TODO:初始化内容
    }

    /**
     * 获取幻灯片
     * @param $slide_id
     * @param array $where
     */
    public function getSlideItemList($slide_id, $where = [])
    {
        $where[] = ['status', '=', 1];
        $where[] = ['slide_id', '=', $slide_id];
        $result = $this->field('id,image,url')
            ->where($where)
            ->order('list_order ASC')
            ->select()
            ->toArray();
        return $result;
    }

    public function getImageAttr($value)
    {
        return get_upload_path($value);
    }
}