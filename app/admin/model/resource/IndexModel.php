<?php

namespace app\admin\model\resource;

use think\Model;

/**
 * 附件Model
 */
class IndexModel extends Model
{
    protected $pk = 'id';
    protected $name = 'resource';

    public function addResource($value)
    {
        $strFileExtension = strtolower(cmf_get_file_extension($value['url']));
        if (!isset($value['file_size'])) {
            $size = remote_filesize($value['url']);
        } else {
            $size = $value['file_size'];
        }
        if (!isset($value['file_md5'])) {
            $file_md5 = md5_file($value['url']);
        } else {
            $file_md5 = $value['file_md5'];
        }

        if (!isset($value['file_sha1'])) {
            $file_sha1 = sha1_file($value['url']);
        } else {
            $file_sha1 = $value['file_sha1'];
        }

        $ResourceCate = app()->make(CategoryModel::class);
        $cateInfo = $ResourceCate->where('id', $value['cate_id'])->find();
        if (!$cateInfo) {
            return;
        }
        $adminId = cmf_get_current_admin_id();
        if (!$adminId) {
            $adminId = cmf_get_current_user_id();
        }

        $installData = [
            'user_id' => $adminId ?? 0,
            'file_size' => $size,
            'create_time' => time(),
            'file_md5' => $file_md5,
            'file_sha1' => $file_sha1,
            'filename' => $value['name'],
            'file_path' => $value['filepath'],
            'suffix' => $strFileExtension,
            'url' => $value['url'],
            'storage_type' => $value['storage_type'],
            'file_key' => $file_md5 . $file_sha1,
            'cate_id' => $value['cate_id'],
            'type' => $cateInfo['type'],

        ];
        $res = $this->save($installData);
    }


    /**
     * 获取分类西有多少资源
     */
    public function getCountByCateId($cate_id)
    {
        $CategoryModel = app()->make(CategoryModel::class);

        $where = [];
        $cateInfo = $CategoryModel->find($cate_id);
        if (!$cateInfo) {
            return 0;
        }
        if ($cateInfo['pid'] <= 0) {
            $where[] = ['type', '=', $cateInfo['type']];
        } else {
            $where[] = ['cate_id', '=', $cateInfo['id']];
        }
        $count = $this
            ->where($where)
            ->count();

        return $count;
    }
}