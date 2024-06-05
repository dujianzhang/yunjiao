<?php

namespace app\admin\controller\resource;

use cmf\controller\AdminBaseController;
use think\Db;
use tree\Tree;
use app\admin\model\resource\IndexModel as resourceModel;
use app\admin\model\resource\CategoryModel;

/**
 * 资源列表
 */
class IndexController extends AdminBaseController
{
    public function index()
    {
        $arrData = $this->request->param();
        $cate_id = $this->request->param('cate_id', 0);

        $type = [
            0 => 'image',
            1 => 'audio',
            2 => 'video',
        ];
        $CategoryModel = app()->make(CategoryModel::class);

        if ($cate_id <= 0) {
            $categoryInfo = $CategoryModel->order("list_order asc")->find();
            $filetype = $type[$categoryInfo['type'] ?? 0];
        } else {
            $categoryInfo = $CategoryModel->find($cate_id);
            $filetype = $type[$categoryInfo['type'] ?? 0];
        }
        $resourceWhere = [];
        $resourceWhere[] = ['type', '=', $categoryInfo['type']];
        if (isset($arrData['keywords']) && ($arrData['keywords'] != '')) {
            $resourceWhere[] = ['filename', 'LIKE', "%{$arrData['keywords']}%"];
        }
        if ($categoryInfo['pid'] > 0) {
            $resourceWhere[] = ['cate_id', '=', $categoryInfo['id']];
        } else {
            $resourceWhere[] = ['type', '=', $categoryInfo['type']];
        }

        $list = $this->getresourceList($resourceWhere, 15);

        $this->assign('list', $list);
        $this->assign('page', $list->render());
        $this->assign('filetype', 'image');
        $this->assign('catelist', $this->getCateList([], $categoryInfo['id'] ?? 0));
        $this->assign('filetype', $filetype);
        $this->assign('cate_id', $categoryInfo['id']);
        return $this->fetch();
    }

    /**
     * 资源分类
     * @param array $where
     * @param int $sid
     * @return mixed
     */
    public function getCateList($where = [], $sid = 0)
    {

        $result = Db::name("resource_category")
            ->where($where)
            ->order("list_order asc")
            ->select()
            ->toArray();
        $tree = new Tree();
        $tree->icon = ['&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─', '&nbsp;&nbsp;&nbsp;└─ '];
        $tree->nbsp = '';

        /** @var resourceModel $resourceModel */
        $resourceModel = app()->make(resourceModel::class);


        foreach ($result as $key => $value) {
            $selected = '';
            if ($sid == $value['id']) {
                $selected = 'selected';
            }
            $count = $resourceModel->getCountByCateId($value['id']);
            $result[$key]['parent_id_node'] = ($value['pid']) ? ' child-of-node-' . $value['pid'] . " {$selected}" : " {$selected}";
            $result[$key]['parent_id'] = $value['pid'];
            $result[$key]['count'] = $count;
            $result[$key]['style'] = empty($value['pid']) ? '' : 'display:none;';
            $url = "javascript:admin.openIframeLayer('" . url('resource.category/edit', ["id" => $value['id']]) . "','编辑',{btn: ['保存','关闭'],area:['640px','50%'],end:function(){}});";
            $result[$key]['str_manage'] = '<a class="layui-bo layui-bo-small layui-bo-checked" href="' . $url . '">' . lang('EDIT') . '</a>  
                                           <a class="layui-bo layui-bo-small layui-bo-close js-ajax-delete" href="' . url("resource.category/del", ["id" => $value['id']]) . '">' . lang('DELETE') . '</a> ';
        }
        $tree->init($result);
        $str = "<tr id='node-\$id' class='\$parent_id_node' style='\$style'>
                    <td><span class='click' data-id='\$id'>\$spacer\$name (\$count)</span></td>
                </tr>";
        $list = $tree->getTree(0, $str, $sid);


        return $list;
    }

    /**
     * 记录上传的文件
     */
    public function recordFile()
    {
        $arrData = $this->request->param('data');
        if ($arrData && is_array($arrData)) {

            $storage = cmf_get_option('storage');
            $storageType = $storage['type'];
            if (!in_array($storageType, ['Cos', 'Oss'])) {
                return;
            }
            /** @var resourceModel $resourceModel */
            $resourceModel = app()->make(resourceModel::class);
            foreach ($arrData as $key => $value) {
                $value['storage_type'] = $storageType;
                $resourceModel->addResource($value);
            }


        }
    }

    //文件选择框
    public function webuploader()
    {
        $arrData = $this->request->param();
        $cate_id = $this->request->param('cate_id/d', 0);
        $filetype = $arrData['filetype'] ?? 'image';
        $type = [
            'image' => 0,
            'audio' => 1,
            'video' => 2,
        ];

        /** @var CategoryModel $CategoryModel */
        $CategoryModel = app()->make(CategoryModel::class);
        if ($cate_id <= 0) {
            $categoryInfo = $CategoryModel->where('type', $type[$filetype] ?? 0)->order("list_order asc")->find();
        } else {
            $categoryInfo = $CategoryModel->find($cate_id);
        }

        $cateWhere = [];
        $cateWhere[] = ['type', '=', $categoryInfo['type'] ?? 0];
        $cateList = $this->getCateList($cateWhere, $categoryInfo['id']);

        $resourceWhere = [];
        $resourceWhere[] = ['type', '=', $categoryInfo['type'] ?? 0];
        if (isset($arrData['keywords']) && ($arrData['keywords'] != '')) {
            $resourceWhere[] = ['filename', 'LIKE', "%{$arrData['keywords']}%"];
        }

        if ($categoryInfo['pid'] > 0) {
            $resourceWhere[] = ['cate_id', '=', $categoryInfo['id']];
        } else {
            $resourceWhere[] = ['type', '=', $categoryInfo['type']];
        }

        $list = $this->getresourceList($resourceWhere, 6);
        $this->assign([
            'cateList' => $cateList,
            'list' => $list,
            'filetype' => strtolower($filetype),
            'page' => $list->render()
        ]);
        return $this->fetch();
    }

    /**
     * 获取资源列表
     * @param $where
     */
    public function getresourceList($where, $page = 5)
    {
        $result = Db::name("resource")
            ->where($where)
            ->order('id DESC')
            ->paginate($page, false, [
                'query' => request()->param()
            ]);

        $result->each(function ($value, $k) {
            $value['create_time'] = date('Y-m-d H:i:s');
            if ($value['file_size'] > 1024) {
                $value['file_size'] = round($value['file_size'] / 1024 / 1024, 2) . 'MB';
            } else {
                $value['file_size'] = round($value['file_size'] / 1024, 2) . 'KB';
            }
            return $value;
        });

        return $result;
    }

    /**
     * 删除资源
     */
    public function del()
    {
        $id = $this->request->param('id/d', 0);
        if ($id <= 0) {
            return $this->error("删除失败！");
        }
        /** @var CategoryModel $CategoryModel */
        $resourceModel = app()->make(resourceModel::class);
        $del = $resourceModel->where('id', $id)->delete();
        return $this->success("删除成功！");

    }

}