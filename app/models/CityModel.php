<?php

namespace app\models;

use think\Model;

class CityModel extends Model
{
  protected $pk = 'id';
  protected $name = 'city';
  public static $redis_key = 'citylist';
  public static $redis_key_2 = 'citylist2';

  protected function setAreaCodeAttr($value)
  {
    return substr(str_pad($value, 8, 0, STR_PAD_RIGHT), 0, 8);
  }

  public static function resetcache()
  {
    $key = self::$redis_key;
    $key2 = self::$redis_key_2;

    //$list=self::where(['status'=>1])->order("pid asc, list_order asc")->select();
    $list = self::order("pid asc, list_order asc,id asc")->select();
    if ($list) {
      $list2 = tree($list);
      setcaches($key, $list);
      setcaches($key2, $list2);
    } else {
      delcache($key);
      delcache($key2);
    }

    return $list;
  }
  /* 列表 */
  public static function getList()
  {
    $key = self::$redis_key;

    if (isset($GLOBALS[$key])) {
      return $GLOBALS[$key];
    }
    $list = getcaches($key);
    if (!$list) {
      $list = self::resetcache();
    }

    $GLOBALS[$key] = $list;
    return $list;

  }

  public static function getStatus($k = '')
  {
    $status = [
      '0' => '未开通',
      '1' => '已开通',
    ];

    if ($k === '') {
      return $status;
    }
    return $status[$k] ?? '';
  }

  /* 某信息 */
  public static function getInfo($id)
  {

    $info = [];

    if ($id < 1) {
      return $info;
    }
    $list = self::getList();

    foreach ($list as $k => $v) {
      if ($v['id'] != $id) {
        continue;
      }

      $info = $v;
      break;

    }

    return $info;
  }

  /* 一级分类 */
  public static function getLevelOne()
  {
    return self::where(['pid' => 0])->order("list_order asc,id asc")->column('*', 'id');
  }

  public static function getLevelList()
  {

    $list2 = [];
    $list = self::getList();

    $list = handelList($list);

    foreach ($list as $k => $v) {
      foreach ($v['list'] as $k2 => $v2) {

        $v2['name2'] = $v['name'] . '-' . $v2['name'];

        $list2[] = $v2;
      }
    }

    return $list2;
  }

  public static function getNoOpen()
  {
    $list = self::getLevelList();

    foreach ($list as $k => $v) {
      if ($v['status'] == 0) {
        continue;
      }
      //unset($list[$k]);
    }

    return array_values($list);
  }

  public static function getKeyID()
  {

    $list2 = [];

    $list = self::getLevelList();

    foreach ($list as $k => $v) {
      $list2[$v['id']] = $v;
    }

    return $list2;
  }

  public static function setStatus($cityid, $status)
  {
    if ($cityid < 1) {
      return 0;
    }

    self::where(['id' => $cityid])->update(['status' => $status]);
    self::resetcache();

    return 1;
  }

  public static function getTwoCityName($id)
  {

    $name = '';

    if ($id < 1) {
      return $name;
    }
    $list = self::getLevelList();

    foreach ($list as $k => $v) {
      if ($v['id'] == $id) {
        $name = $v['name2'];
        break;
      }
    }

    return $name;

  }

}