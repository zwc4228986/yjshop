<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace app\admin\controller;
use Think\Db;

/**
 * 数据库备份还原控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class Database extends Yj{
    /**
     * 数据库备份/还原列表
     * @param  String $type import-还原，export-备份
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function lists($type = 'export'){
        switch ($type) {
            /* 数据还原 */
            case 'import':
                //列出备份文件列表
                $path = C('DATA_BACKUP_PATH');
                if(!is_dir($path)){
                    mkdir($path, 0755, true);
                }
                $path = realpath($path);
                $flag = \FilesystemIterator::KEY_AS_FILENAME;
                $glob = new \FilesystemIterator($path,  $flag);
                $list = array();
                foreach ($glob as $name => $file) {
                    if(preg_match('/^\d{8,8}-\d{6,6}-\d+\.sql(?:\.gz)?$/', $name)){
                        $name = sscanf($name, '%4s%2s%2s-%2s%2s%2s-%d');
                        $date = "{$name[0]}-{$name[1]}-{$name[2]}";
                        $time = "{$name[3]}:{$name[4]}:{$name[5]}";
                        $part = $name[6];
                        if(isset($list["{$date} {$time}"])){
                            $info = $list["{$date} {$time}"];
                            $info['part'] = max($info['part'], $part);
                            $info['size'] = $info['size'] + $file->getSize();
                        } else {
                            $info['part'] = $part;
                            $info['size'] = $file->getSize();
                        }
                        $extension        = strtoupper(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                        $info['compress'] = ($extension === 'SQL') ? '-' : $extension;
                        $info['time']     = strtotime("{$date} {$time}");

                        $list["{$date} {$time}"] = $info;
                    }
                }
                $title = '数据还原';
                break;

            /* 数据备份 */
            case 'export':
            	
                $list  = Db::query('SHOW TABLE STATUS');
                $list  = array_map('array_change_key_case', $list);
                break;

            default:
                $this->error('参数错误！');
        }
        //渲染模板
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 优化表
     * @param  String $tables 表名
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function optimize($tables = null){
        if($tables) {
            $Db   = Db::getInstance();
            if(is_array($tables)){
                $tables = implode('`,`', $tables);
                $list = $Db->query("OPTIMIZE TABLE `{$tables}`");
                if($list){
                    $this->success("数据表优化完成！");
                } else {
                    $this->error("数据表优化出错请重试！");
                }
            } else {
                $list = $Db->query("OPTIMIZE TABLE `{$tables}`");
                if($list){
                    $this->success("数据表'{$tables}'优化完成！");
                } else {
                    $this->error("数据表'{$tables}'优化出错请重试！");
                }
            }
        } else {
            $this->error("请指定要优化的表！");
        }
    }

    /**
     * 修复表
     * @param  String $tables 表名
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function repair($tables = null){
        if($tables) {
            $Db   = Db::getInstance();
            if(is_array($tables)){
                $tables = implode('`,`', $tables);
                $list = $Db->query("REPAIR TABLE `{$tables}`");

                if($list){
                    $this->success("数据表修复完成！");
                } else {
                    $this->error("数据表修复出错请重试！");
                }
            } else {
                $list = $Db->query("REPAIR TABLE `{$tables}`");
                if($list){
                    $this->success("数据表'{$tables}'修复完成！");
                } else {
                    $this->error("数据表'{$tables}'修复出错请重试！");
                }
            }
        } else {
            $this->error("请指定要修复的表！");
        }
    }

    /**
     * 删除备份文件
     * @param  Integer $time 备份时间
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function del($time = 0){
        if($time){
            $name  = date('Ymd-His', $time) . '-*.sql*';
            $path  = realpath(C('DATA_BACKUP_PATH')) . DIRECTORY_SEPARATOR . $name;
            array_map("unlink", glob($path));
            if(count(glob($path))){
                $this->error('备份文件删除失败，请检查权限！');
            } else {
                $this->success('备份文件删除成功！');
            }
        } else {
            $this->error('参数错误！');
        }
    }

    /**
     * 备份数据库
     * @param  String  $tables 表名
     * @param  Integer $id     表ID
     * @param  Integer $start  起始行数
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function export($tables = null, $id = null, $start = null){
    	if(request()->isPost()&&!empty($tables)&&is_array($tables)){
    		
    			$path = config('data_backup_path');
    			
    			if (!is_dir($path)) {
					mkdir($path, 0755, true);
				}
				//读取配置文件
				$config = [
					'path'=>$path.DS,
					'part' => config('data_backup_part_size'),//大小
					'compress' => config('data_backup_compress'), //是否压缩
					'level' => config('data_backup_compress_level') //压缩级别
				];
				$lock = "{$config['path']}backup.lock";
				if(is_file($lock)){
					return $this->error('检测到有一个备份任务正在执行');
				}else{
					$a = file_put_contents($lock,time());
				}
				if (!is_writeable($config['path'])) {
					return $this->error('备份目录不存在或不可写，请检查后重试！');
				}
				session('backup_config', $config);
				//生成备份文件信息
				$file = array('name' => date('Ymd-His', time()), 'part' => config('data_backup_part_size'));
				session('backup_file', $file);
				session('backup_tables', $tables);
    			$Database = new \com\Database($file, $config);
    			if(false!==$Database->create()){
    				$tab = array('id'=>0,'start'=>0);
    				return $this->success('初始化成功！','',array('tables' => $tables, 'tab' => $tab));
    			}else{
    				return $this->error('初始化失败，备份文件创建失败！');
    			}
    	}elseif(request()->isGet()&&is_numeric($id)&&is_numeric($start)){
    			$tables = session('backup_tables');
    			$Database = new \com\Database(session('backup_file'), session('backup_config'));
    			$start =  $Database->backup($tables[$id], $start);
    			if(false === $start){
    					return $this->error('备份出错！');
    			}elseif(0===$start){
    					if(isset($tables[++$id])){
    							$tab = array('id' => $id, 'start' => 0);
								return $this->success('备份完成！', '', array('tab' => $tab));
    					}else{
    							//备份完成
    							unlink(session('backup_config.path') . 'backup.lock');
								session('backup_tables', null);
								session('backup_file', null);
								session('backup_config', null);
								return $this->success('备份完成');
    					}
    				
    			}else{
    					$tab  = array('id' => $id, 'start' => $start[0]);
						$rate = floor(100 * ($start[0] / $start[1]));
						return $this->success("正在备份...({$rate}%)", '', array('tab' => $tab));
				}
    	}else{
    				return $this->error('参数错误！');
    	}
    	
    }
    /**
     * 还原数据库
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function import($time = 0, $part = null, $start = null){
        if(is_numeric($time) && is_null($part) && is_null($start)){ //初始化
            //获取备份文件信息
            $name  = date('Ymd-His', $time) . '-*.sql*';
            $path  = realpath(C('DATA_BACKUP_PATH')) . DIRECTORY_SEPARATOR . $name;
            $files = glob($path);
            $list  = array();
            foreach($files as $name){
                $basename = basename($name);
                $match    = sscanf($basename, '%4s%2s%2s-%2s%2s%2s-%d');
                $gz       = preg_match('/^\d{8,8}-\d{6,6}-\d+\.sql.gz$/', $basename);
                $list[$match[6]] = array($match[6], $name, $gz);
            }
            ksort($list);

            //检测文件正确性
            $last = end($list);
            if(count($list) === $last[0]){
                session('backup_list', $list); //缓存备份列表
                $this->success('初始化完成！', '', array('part' => 1, 'start' => 0));
            } else {
                $this->error('备份文件可能已经损坏，请检查！');
            }
        } elseif(is_numeric($part) && is_numeric($start)) {
            $list  = session('backup_list');
            $db = new Database($list[$part], array(
                'path'     => realpath(C('DATA_BACKUP_PATH')) . DIRECTORY_SEPARATOR,
                'compress' => $list[$part][2]));

            $start = $db->import($start);

            if(false === $start){
                $this->error('还原数据出错！');
            } elseif(0 === $start) { //下一卷
                if(isset($list[++$part])){
                    $data = array('part' => $part, 'start' => 0);
                    $this->success("正在还原...#{$part}", '', $data);
                } else {
                    session('backup_list', null);
                    $this->success('还原完成！');
                }
            } else {
                $data = array('part' => $part, 'start' => $start[0]);
                if($start[1]){
                    $rate = floor(100 * ($start[0] / $start[1]));
                    $this->success("正在还原...#{$part} ({$rate}%)", '', $data);
                } else {
                    $data['gz'] = 1;
                    $this->success("正在还原...#{$part}", '', $data);
                }
            }

        } else {
            $this->error('参数错误！');
        }
    }

}
