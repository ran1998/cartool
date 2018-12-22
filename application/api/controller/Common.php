<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Area;
use app\common\model\Version;
use fast\Random;
use think\Config;

/**
 * 公共接口
 */
class Common extends Api
{

    protected $noNeedLogin = ['init'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 加载初始化
     *
     * @param string $version 版本号
     * @param string $lng 经度
     * @param string $lat 纬度
     */
    public function init()
    {
        if ($version = $this->request->request('version')) {
            $lng = $this->request->request('lng');
            $lat = $this->request->request('lat');
            $content = [
                'citydata'    => Area::getCityFromLngLat($lng, $lat),
                'versiondata' => Version::check($version),
                'uploaddata'  => Config::get('upload'),
                'coverdata'   => Config::get("cover"),
            ];
            $this->success('', $content);
        } else {
            $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 上传文件
     * @ApiMethod (POST)
     * @param File $file 文件流
     */
    public function upload()
    {
        $data = $this->request->post();
        $file_list = array('img_shenfen', 'img_baoxian', 'img_chepai', 'img_chejia', 'img_fadongji', 'img_cheshen1', 'img_cheshen2');
        foreach ($file_list as $filefield) {
            $file = $this->request->file($filefield);
            if (empty($file)) {
                $this->error(__('No file upload or server upload limit exceeded'));
            }

            //判断是否已经存在附件
            $sha1 = $file->hash();

            $upload = Config::get('upload');

            preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
            $type = strtolower($matches[2]);
            $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
            $size = (int)$upload['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);
            $fileInfo = $file->getInfo();
            $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
            $suffix = $suffix ? $suffix : 'file';

            $mimetypeArr = explode(',', strtolower($upload['mimetype']));
            $typeArr = explode('/', $fileInfo['type']);

            //验证文件后缀
            if ($upload['mimetype'] !== '*' &&
                (
                    !in_array($suffix, $mimetypeArr)
                    || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
                )
            ) {
                $this->error(__('Uploaded file format is limited'));
            }
            $replaceArr = [
                '{year}'     => date("Y"),
                '{mon}'      => date("m"),
                '{day}'      => date("d"),
                '{hour}'     => date("H"),
                '{min}'      => date("i"),
                '{sec}'      => date("s"),
                '{random}'   => Random::alnum(16),
                '{random32}' => Random::alnum(32),
                '{filename}' => $suffix ? substr($fileInfo['name'], 0, strripos($fileInfo['name'], '.')) : $fileInfo['name'],
                '{suffix}'   => $suffix,
                '{.suffix}'  => $suffix ? '.' . $suffix : '',
                '{filemd5}'  => md5_file($fileInfo['tmp_name']),
                '{category}' => $filefield
            ];
            $savekey = $upload['savekey'];
            $savekey = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);

            $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
            $fileName = substr($savekey, strripos($savekey, '/') + 1);
            //
            $splInfo = $file->validate(['size' => $size])->move(ROOT_PATH . '/public' . $uploadDir, $fileName);
            if ($splInfo) {
                $params[$filefield] =  $uploadDir . $splInfo->getSaveName();
                $url[$filefield] = $uploadDir . $splInfo->getSaveName();
            } else {
                // 上传失败获取错误信息
                $this->error($file->getError());
            }
        }
        $insertLastId = model('customer')->saveCustomer($data);
        if (!empty($params) && $insertLastId) {
            $params['sn'] = $data['sn'];
            $customer = model("image");
            $customer->data(array_filter($params));
            $customer->save();
            \think\Hook::listen("upload_after", $customer);
            $this->success(__('Upload successful'), [
                'url' => $url
            ]);
        } else {
            $this->error('添加失败');
        }
    }

}
