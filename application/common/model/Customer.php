<?php

namespace app\common\model;

use think\Model;

/**
 * 邮箱验证码
 */
class Customer Extends Model
{

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    // 追加属性
    protected $append = [
    ];

    public function saveCustomer($data)
    {
    	try {
	    	$this->save($data);
    	} catch (\Exception $e) {
    		return false;
    	}
    	return true;
    }

}
