<?php

namespace Addons\Health\Controller;
use Home\Controller\AddonsController;

class HealthController extends AddonsController{
	
	Public function _initialize() {
	
		$controller = strtolower (_ACTION);
		$res['title'] = '健康数据列表';
		$res['url'] = addons_url ('Health://Health/lists');
		$res['class'] = $controller == 'lists' ? 'current' : '';
		$nav[] = $res;

                $res['title'] = '手动录入';
                $res['url'] = addons_url ('Health://Health/add');
                $res['class'] = $controller == 'add' ? 'current' : '';
                $nav[] = $res;

                $res['title'] = 'Execl自动导入';
                $res['url'] = addons_url ('Health://Health/autoadd');
                $res['class'] = $controller == 'autoadd' ? 'current' : '';
                $nav[] = $res;

		$res['title'] = '相关设置';
		$res['url'] = addons_url ('Health://Health/config');
		$res['class'] = $controller == 'config' ? 'current' : '';
		$nav[] = $res;
	
		$this->assign('nav', $nav);
	}

        // 通用插件的列表方法
        public function lists() {

                $this->assign ( 'add_button', false );

                $model = $this->getModel('headata');
                parent::common_lists ( $model );
        }

        // 通用插件的添加方法
        public function add() {
	        $normal_tips = '管理员可在此处手动添加学生每天相关的健康数据。<br>学生ID 指的是学生的考勤卡片的ID编号,您可以在“用户列表”中的考勤编号里找到。</br>健康数据的填写格式： 名称：数值（单位）</br>一行对应一种健康数据种类，可添加多行，单位可不带，例如：<br>跑步：1345M</br>睡眠：7.8h';
                $this->assign('normal_tips', $normal_tips);
		
		if(IS_POST){
			$data['userid'] = I('userid');
			$data['c_name'] = I('c_name');
			$data['hdata'] = I('hdata')."\r";
			$data['cTime'] = date('Y-m-d H:m:s');
			M('headata')->add($data);
			$this->success('营养数据添加成功！');

		}else{
                	$model = $this->getModel('headata');
                	parent::common_add ( $model );
		}
        }

        // 通用插件的编辑方法
        public function edit() {

                $model = $this->getModel('headata');
                parent::common_edit ( $model );
        }

        // 通用插件的删除模型
        public function del() {
                parent::common_del ( $this->getModel('headata') );
        }
	
	// 配置中心
        public function config() {
                $normal_tips = '此处可设置相关项，温馨提示：<br>1.在选择从Execl自动导入数据前需要在下面的“Execl中的健康种类”选择Execl文件中包含的健康数据种类数。</br>2.“健康数据名称和单位”处填写Execl中导入健康数据的名称，单位可选，格式：名称+单位,例如：<br>跑步+M</br>睡眠+h';
                $this->assign('normal_tips', $normal_tips);

                parent::config();

        }


        //Execl自动录入-文件上传
	public function autoadd() {
		
		$config = getAddonConfig('Health');

		$normal_tips = '管理员可在此处上传Execl文件自动导入数据。温馨提示：<br>1.上传的文件只允许为Execl，即以.xls结尾的文件</br>2.Execl文件中营养数据的种类数目和相关名称、单位，需先在“相关设置中”设置好，以防解析出错！<br>3.为防止上传相同文件名导致数据丢失，建议使用不同英文文件名，例如以时间命名：health-2015-08-12.xls</br>4.上传Execl文件的格式：学生ID号 | 学生姓名 | 健康数据名1 | 健康数据名2 | ...';
                $this->assign('normal_tips', $normal_tips);
		$url = addons_url ('Health://Health/autoadd');
                $this->assign('posturl',$url);
	        
		if(IS_POST){

                        $uploaddir = '/tmp';
                        $uploadfile = $uploaddir . basename($_FILES['userfile']['name']);
	                $fileinfo = pathinfo($uploadfile);
			if($fileinfo['extension']=='xls'){
				$uploaddir = $config['UploadURL'];
 	                        $uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

				if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {

					$this->reader($uploadfile);
				}else{
					$this->error("File move error!");
				}
			}else{
				$this->error("文件类型错误！", U('autoadd'));
			}
		}else{	
			$this->display();
		}
	}
	
	// 解析Execl文件
	public function reader($file){

		$config = getAddonConfig('Health');

		header("Content-Type:text/html;charset=utf-8");	
		require_once 'Spreadsheet_Excel_Reader.class.php';
		$data = new \bar\Spreadsheet_Excel_Reader();
                //$data->setOutputEncoding('UTF-8');
                $data->read($file);
		
		$errorid = array();
		$ctime = date('Y-m-d H:m:s');
		$fnums = $data->sheets[0]['numCols'] - 2; //Fdata numbers
		if($fnums == $config['heanums']) {
			for($i = 2; $i <= $data->sheets[0]['numRows']; $i++){
		
				$strs = $config['heanames'];
				$userid = $data->sheets[0]['cells'][$i][1]; //userid
				$c_name = M ('wx_userlist')->where("userid='$userid'")->getField('c_name');
				if(!empty($c_name)){
					for($j = 3; $j <= $data->sheets[0]['numCols']; $j ++){
						$fone = "=".$data->sheets[0]['cells'][$i][$j];
						$strs = $this->str_replace_once("+",$fone,$strs);
					}
					$fdatas['userid'] = $userid;
					$fdatas['c_name'] = $c_name;
					$fdatas['hdata'] = $strs;
					$fdatas['cTime'] = $ctime;
					M('headata')->add($fdatas);
				}else{
					array_push($errorid,$userid);
				}
			}
			if(!empty($errorid)){
				$errid = implode(',',$errorid);
				$this->error_id($errid);
			}else{
				$this->success('Execl数据导入成功！',U('lists'));
			}
		}else{
			$this->error('Execl解析出错，请确认营养种类数正确！', U('autoadd'));

		}
	}

	//微信前端显示
	public function show(){

                $config = getAddonConfig('Health');
		$getn = (int)$config['shown'];


		$isWeixinBrowser = isWeixinBrowser ();
		if($isWeixinBrowser){
			$oid = get_openid();
			$user = M ('wx_userlist')->where("openid='$oid'")->getField('userid');

			if(!empty($user)){
				$re = M ('headata')->where("userid='$user'")->order('id desc')->limit($getn)->select();
				if(!empty($re)){
					for($i = 0; $i < count($re); $i ++){
						$t = 0;
						$k = array();
						for($j = 0; $j < strlen($re[$i]['hdata']); $j ++){
							if($re[$i]['hdata']{$j}=="\r"){
								$n = substr($re[$i]['hdata'], $t, $j-$t);
								$t = $j +1;
								array_push($k, $n);
							}
						}
						$re[$i]['hdata'] = $k;
					}
					$this->assign('infos',$config['hinfos']);
					$this->assign('lists',$re);
					$this->display();

				}else{
					$this->error('目前尚未录入您宝宝的相关数据！');
				}
			}else{
				$this->success('请先进行用户绑定！', U('/addon/UserList/UserList/useradd'));
			}
		}else{
			$this->error('请使用微信访问！');
		}
	}
	

	//字符串替换（一次）
	public function str_replace_once($needle, $replace, $haystack) {
                $pos = strpos($haystack, $needle);
                if ($pos === false) {
                        return $haystack;
                }
                return substr_replace($haystack, $replace, $pos, strlen($needle));
        }
	
	//Execl导入错误显示
	public function error_id($errid) {
		$this->assign('errid',$errid);
		$this->display('error_id');
	}
}
